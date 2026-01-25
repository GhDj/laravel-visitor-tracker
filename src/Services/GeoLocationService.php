<?php

declare(strict_types=1);

namespace Ghdj\VisitorTracker\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Geolocation service using Laravel's HTTP client.
 *
 * No external packages required - uses free IP geolocation APIs.
 */
class GeoLocationService
{
    /**
     * Supported geolocation providers.
     */
    protected const PROVIDERS = [
        'ip-api' => 'http://ip-api.com/json/%s?fields=status,message,country,countryCode,region,regionName,city,lat,lon',
        'ipinfo' => 'https://ipinfo.io/%s/json',
        'ipapi' => 'https://ipapi.co/%s/json/',
    ];

    /**
     * Get geolocation data for an IP address.
     *
     * @return array{country: string|null, country_code: string|null, city: string|null, region: string|null, latitude: float|null, longitude: float|null}
     */
    public function lookup(string $ip): array
    {
        if (! $this->isEnabled()) {
            return $this->getEmptyResult();
        }

        // Don't lookup private/reserved IPs
        if ($this->isPrivateIp($ip)) {
            return $this->getEmptyResult();
        }

        // Check cache first
        $cached = $this->getCached($ip);
        if ($cached !== null) {
            return $cached;
        }

        // Lookup via provider
        $result = $this->fetchFromProvider($ip);

        // Cache the result
        $this->cacheResult($ip, $result);

        return $result;
    }

    /**
     * Check if geolocation is enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) config('visitor-tracker.geolocation.enabled', false);
    }

    /**
     * Fetch geolocation data from the configured provider.
     *
     * @return array{country: string|null, country_code: string|null, city: string|null, region: string|null, latitude: float|null, longitude: float|null}
     */
    protected function fetchFromProvider(string $ip): array
    {
        $provider = config('visitor-tracker.geolocation.provider', 'ip-api');
        $timeout = (int) config('visitor-tracker.geolocation.timeout', 5);

        try {
            $response = match ($provider) {
                'ip-api' => $this->fetchFromIpApi($ip, $timeout),
                'ipinfo' => $this->fetchFromIpInfo($ip, $timeout),
                'ipapi' => $this->fetchFromIpApiCo($ip, $timeout),
                default => $this->getEmptyResult(),
            };

            return $response;
        } catch (\Exception $e) {
            report($e);

            return $this->getEmptyResult();
        }
    }

    /**
     * Fetch from ip-api.com (free, no API key required for non-commercial use).
     *
     * @return array{country: string|null, country_code: string|null, city: string|null, region: string|null, latitude: float|null, longitude: float|null}
     */
    protected function fetchFromIpApi(string $ip, int $timeout): array
    {
        $url = sprintf(self::PROVIDERS['ip-api'], $ip);

        $response = Http::timeout($timeout)->get($url);

        if (! $response->successful()) {
            return $this->getEmptyResult();
        }

        $data = $response->json();

        if (($data['status'] ?? '') !== 'success') {
            return $this->getEmptyResult();
        }

        return [
            'country' => $data['country'] ?? null,
            'country_code' => $data['countryCode'] ?? null,
            'city' => $data['city'] ?? null,
            'region' => $data['regionName'] ?? null,
            'latitude' => isset($data['lat']) ? (float) $data['lat'] : null,
            'longitude' => isset($data['lon']) ? (float) $data['lon'] : null,
        ];
    }

    /**
     * Fetch from ipinfo.io (requires API key for production use).
     *
     * @return array{country: string|null, country_code: string|null, city: string|null, region: string|null, latitude: float|null, longitude: float|null}
     */
    protected function fetchFromIpInfo(string $ip, int $timeout): array
    {
        $url = sprintf(self::PROVIDERS['ipinfo'], $ip);
        $apiKey = config('visitor-tracker.geolocation.api_key');

        $request = Http::timeout($timeout);

        if ($apiKey) {
            $request = $request->withToken($apiKey);
        }

        $response = $request->get($url);

        if (! $response->successful()) {
            return $this->getEmptyResult();
        }

        $data = $response->json();

        // Parse location coordinates (format: "lat,lng")
        $lat = null;
        $lng = null;
        if (isset($data['loc'])) {
            $coords = explode(',', $data['loc']);
            if (count($coords) === 2) {
                $lat = (float) $coords[0];
                $lng = (float) $coords[1];
            }
        }

        return [
            'country' => $this->getCountryName($data['country'] ?? null),
            'country_code' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'region' => $data['region'] ?? null,
            'latitude' => $lat,
            'longitude' => $lng,
        ];
    }

    /**
     * Fetch from ipapi.co (free tier available).
     *
     * @return array{country: string|null, country_code: string|null, city: string|null, region: string|null, latitude: float|null, longitude: float|null}
     */
    protected function fetchFromIpApiCo(string $ip, int $timeout): array
    {
        $url = sprintf(self::PROVIDERS['ipapi'], $ip);
        $apiKey = config('visitor-tracker.geolocation.api_key');

        if ($apiKey) {
            $url .= '?key='.$apiKey;
        }

        $response = Http::timeout($timeout)->get($url);

        if (! $response->successful()) {
            return $this->getEmptyResult();
        }

        $data = $response->json();

        if (isset($data['error']) && $data['error']) {
            return $this->getEmptyResult();
        }

        return [
            'country' => $data['country_name'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'city' => $data['city'] ?? null,
            'region' => $data['region'] ?? null,
            'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
        ];
    }

    /**
     * Get cached geolocation data.
     *
     * @return array{country: string|null, country_code: string|null, city: string|null, region: string|null, latitude: float|null, longitude: float|null}|null
     */
    protected function getCached(string $ip): ?array
    {
        $key = $this->getCacheKey($ip);

        return Cache::get($key);
    }

    /**
     * Cache geolocation data.
     *
     * @param  array{country: string|null, country_code: string|null, city: string|null, region: string|null, latitude: float|null, longitude: float|null}  $data
     */
    protected function cacheResult(string $ip, array $data): void
    {
        $key = $this->getCacheKey($ip);
        $days = (int) config('visitor-tracker.geolocation.cache_days', 7);

        Cache::put($key, $data, now()->addDays($days));
    }

    /**
     * Get the cache key for an IP.
     */
    protected function getCacheKey(string $ip): string
    {
        $prefix = config('visitor-tracker.cache.prefix', 'visitor_tracker_');

        return $prefix.'geo_'.$ip;
    }

    /**
     * Check if the IP is a private/reserved address.
     */
    protected function isPrivateIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /**
     * Get country name from country code.
     */
    protected function getCountryName(?string $countryCode): ?string
    {
        if (! $countryCode) {
            return null;
        }

        $countries = [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'JP' => 'Japan',
            'CN' => 'China',
            'IN' => 'India',
            'BR' => 'Brazil',
            'RU' => 'Russia',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'MX' => 'Mexico',
            'KR' => 'South Korea',
            'NL' => 'Netherlands',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'PL' => 'Poland',
            'BE' => 'Belgium',
            'AT' => 'Austria',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'IE' => 'Ireland',
            'NZ' => 'New Zealand',
            'SG' => 'Singapore',
            'HK' => 'Hong Kong',
            'TW' => 'Taiwan',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'CO' => 'Colombia',
            'ZA' => 'South Africa',
            'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia',
            'IL' => 'Israel',
            'TR' => 'Turkey',
            'TH' => 'Thailand',
            'MY' => 'Malaysia',
            'ID' => 'Indonesia',
            'PH' => 'Philippines',
            'VN' => 'Vietnam',
            'PK' => 'Pakistan',
            'BD' => 'Bangladesh',
            'EG' => 'Egypt',
            'NG' => 'Nigeria',
            'KE' => 'Kenya',
            'UA' => 'Ukraine',
            'CZ' => 'Czech Republic',
            'RO' => 'Romania',
            'HU' => 'Hungary',
            'PT' => 'Portugal',
            'GR' => 'Greece',
        ];

        return $countries[$countryCode] ?? $countryCode;
    }

    /**
     * Get empty result array.
     *
     * @return array{country: null, country_code: null, city: null, region: null, latitude: null, longitude: null}
     */
    protected function getEmptyResult(): array
    {
        return [
            'country' => null,
            'country_code' => null,
            'city' => null,
            'region' => null,
            'latitude' => null,
            'longitude' => null,
        ];
    }
}
