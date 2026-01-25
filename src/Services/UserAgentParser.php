<?php

declare(strict_types=1);

namespace Ghdj\VisitorTracker\Services;

/**
 * Native User Agent Parser - No external dependencies.
 *
 * Parses user agent strings to extract browser, platform, and device information.
 */
class UserAgentParser
{
    /**
     * Browser detection patterns (order matters - more specific first).
     *
     * @var array<string, string>
     */
    protected array $browsers = [
        'Edge' => '/Edg(?:e|A|iOS)?\/([0-9.]+)/',
        'Opera' => '/(?:Opera|OPR)[\/ ]([0-9.]+)/',
        'Brave' => '/Brave\/([0-9.]+)/',
        'Vivaldi' => '/Vivaldi\/([0-9.]+)/',
        'Firefox' => '/Firefox\/([0-9.]+)/',
        'Samsung Browser' => '/SamsungBrowser\/([0-9.]+)/',
        'UC Browser' => '/UCBrowser\/([0-9.]+)/',
        'Yandex' => '/YaBrowser\/([0-9.]+)/',
        'Chrome' => '/(?:Chrome|CriOS)\/([0-9.]+)/',
        'Safari' => '/Version\/([0-9.]+).*Safari/',
        'IE' => '/(?:MSIE |rv:)([0-9.]+)/',
    ];

    /**
     * Platform detection patterns.
     *
     * @var array<string, array<string, mixed>|string>
     */
    protected array $platforms = [
        'Windows' => [
            'pattern' => '/Windows NT ([0-9.]+)/',
            'versions' => [
                '10.0' => '10/11',
                '6.3' => '8.1',
                '6.2' => '8',
                '6.1' => '7',
                '6.0' => 'Vista',
                '5.2' => 'XP x64',
                '5.1' => 'XP',
                '5.0' => '2000',
            ],
        ],
        'macOS' => [
            'pattern' => '/Mac OS X ([0-9_\.]+)/',
            'normalize' => true,
        ],
        'iOS' => [
            'pattern' => '/(?:iPhone|iPad|iPod).*OS ([0-9_]+)/',
            'normalize' => true,
        ],
        'Android' => '/Android ([0-9.]+)/',
        'Chrome OS' => '/CrOS [a-z0-9_]+ ([0-9.]+)/',
        'Linux' => '/Linux/',
        'Ubuntu' => '/Ubuntu/',
        'FreeBSD' => '/FreeBSD/',
    ];

    /**
     * Mobile device patterns.
     *
     * @var array<string>
     */
    protected array $mobilePatterns = [
        '/Mobile/i',
        '/Android.*Mobile/i',
        '/iPhone/i',
        '/iPod/i',
        '/BlackBerry/i',
        '/IEMobile/i',
        '/Opera Mini/i',
        '/Opera Mobi/i',
        '/Windows Phone/i',
        '/webOS/i',
        '/Symbian/i',
    ];

    /**
     * Tablet device patterns.
     *
     * @var array<string>
     */
    protected array $tabletPatterns = [
        '/iPad/i',
        '/Android(?!.*Mobile)/i',
        '/Tablet/i',
        '/Kindle/i',
        '/Silk/i',
        '/PlayBook/i',
        '/\bGT-P\d{4}\b/i', // Samsung Galaxy Tab
        '/\bSM-T\d{3}\b/i', // Samsung Tab
        '/Surface/i',
    ];

    /**
     * Parse the user agent string and extract all information.
     *
     * @return array{browser: string|null, browser_version: string|null, platform: string|null, platform_version: string|null, device_type: string}
     */
    public function parse(?string $userAgent): array
    {
        if (! $userAgent) {
            return $this->getEmptyResult();
        }

        // Merge additional patterns from config
        $this->mergeAdditionalPatterns();

        return [
            'browser' => $this->detectBrowser($userAgent),
            'browser_version' => $this->detectBrowserVersion($userAgent),
            'platform' => $this->detectPlatform($userAgent),
            'platform_version' => $this->detectPlatformVersion($userAgent),
            'device_type' => $this->detectDeviceType($userAgent),
        ];
    }

    /**
     * Detect the browser name.
     */
    public function detectBrowser(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        foreach ($this->browsers as $browser => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $browser;
            }
        }

        return null;
    }

    /**
     * Detect the browser version.
     */
    public function detectBrowserVersion(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        foreach ($this->browsers as $pattern) {
            if (preg_match($pattern, $userAgent, $matches)) {
                return $matches[1] ?? null;
            }
        }

        return null;
    }

    /**
     * Detect the platform/OS name.
     */
    public function detectPlatform(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        foreach ($this->platforms as $platform => $config) {
            $pattern = is_array($config) ? $config['pattern'] : $config;

            if (preg_match($pattern, $userAgent)) {
                return $platform;
            }
        }

        return null;
    }

    /**
     * Detect the platform/OS version.
     */
    public function detectPlatformVersion(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        foreach ($this->platforms as $platform => $config) {
            $pattern = is_array($config) ? $config['pattern'] : $config;

            if (preg_match($pattern, $userAgent, $matches)) {
                $version = $matches[1] ?? null;

                if (! $version) {
                    return null;
                }

                // Handle Windows version mapping
                if ($platform === 'Windows' && is_array($config) && isset($config['versions'])) {
                    return $config['versions'][$version] ?? $version;
                }

                // Normalize underscores to dots (iOS, macOS)
                if (is_array($config) && ($config['normalize'] ?? false)) {
                    return str_replace('_', '.', $version);
                }

                return $version;
            }
        }

        return null;
    }

    /**
     * Detect the device type.
     */
    public function detectDeviceType(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'unknown';
        }

        // Check for tablets first (before mobile, since some tablets match mobile patterns)
        foreach ($this->tabletPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return 'tablet';
            }
        }

        // Check for mobile devices
        foreach ($this->mobilePatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return 'mobile';
            }
        }

        // Default to desktop
        return 'desktop';
    }

    /**
     * Check if the user agent is a mobile device.
     */
    public function isMobile(?string $userAgent): bool
    {
        return $this->detectDeviceType($userAgent) === 'mobile';
    }

    /**
     * Check if the user agent is a tablet.
     */
    public function isTablet(?string $userAgent): bool
    {
        return $this->detectDeviceType($userAgent) === 'tablet';
    }

    /**
     * Check if the user agent is a desktop.
     */
    public function isDesktop(?string $userAgent): bool
    {
        return $this->detectDeviceType($userAgent) === 'desktop';
    }

    /**
     * Add custom browser patterns.
     *
     * @param  array<string, string>  $patterns
     */
    public function addBrowserPatterns(array $patterns): self
    {
        $this->browsers = array_merge($patterns, $this->browsers);

        return $this;
    }

    /**
     * Add custom platform patterns.
     *
     * @param  array<string, array<string, mixed>|string>  $patterns
     */
    public function addPlatformPatterns(array $patterns): self
    {
        $this->platforms = array_merge($patterns, $this->platforms);

        return $this;
    }

    /**
     * Add custom mobile patterns.
     *
     * @param  array<string>  $patterns
     */
    public function addMobilePatterns(array $patterns): self
    {
        $this->mobilePatterns = array_merge($this->mobilePatterns, $patterns);

        return $this;
    }

    /**
     * Add custom tablet patterns.
     *
     * @param  array<string>  $patterns
     */
    public function addTabletPatterns(array $patterns): self
    {
        $this->tabletPatterns = array_merge($this->tabletPatterns, $patterns);

        return $this;
    }

    /**
     * Merge additional patterns from configuration.
     */
    protected function mergeAdditionalPatterns(): void
    {
        $additionalBrowsers = config('visitor-tracker.parser.additional_browsers', []);
        $additionalPlatforms = config('visitor-tracker.parser.additional_platforms', []);

        if (! empty($additionalBrowsers)) {
            $this->addBrowserPatterns($additionalBrowsers);
        }

        if (! empty($additionalPlatforms)) {
            $this->addPlatformPatterns($additionalPlatforms);
        }
    }

    /**
     * Get empty result array.
     *
     * @return array{browser: null, browser_version: null, platform: null, platform_version: null, device_type: string}
     */
    protected function getEmptyResult(): array
    {
        return [
            'browser' => null,
            'browser_version' => null,
            'platform' => null,
            'platform_version' => null,
            'device_type' => 'unknown',
        ];
    }
}
