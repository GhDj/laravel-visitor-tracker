<?php

declare(strict_types=1);

namespace Ghdj\VisitorTracker;

use Ghdj\VisitorTracker\Events\VisitorTracked;
use Ghdj\VisitorTracker\Models\Visit;
use Ghdj\VisitorTracker\Models\Visitor;
use Ghdj\VisitorTracker\Services\BotDetector;
use Ghdj\VisitorTracker\Services\GeoLocationService;
use Ghdj\VisitorTracker\Services\StatisticsService;
use Ghdj\VisitorTracker\Services\UserAgentParser;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Main VisitorTracker class for tracking website visitors.
 */
class VisitorTracker
{
    protected ?Visitor $currentVisitor = null;

    protected ?Visit $currentVisit = null;

    public function __construct(
        protected UserAgentParser $parser,
        protected BotDetector $botDetector,
        protected GeoLocationService $geoService,
        protected StatisticsService $statsService
    ) {}

    /**
     * Track a visitor from the current request.
     */
    public function track(?Request $request = null, ?int $statusCode = null): ?Visit
    {
        $request = $request ?? request();

        // Check if tracking is enabled
        if (! config('visitor-tracker.enabled', true)) {
            return null;
        }

        $userAgent = $request->userAgent();
        $isBot = $this->botDetector->isBot($userAgent);

        // Skip bot tracking if configured
        if ($isBot && ! config('visitor-tracker.bots.track', false)) {
            return null;
        }

        // Get or create visitor
        $sessionId = $this->getOrCreateSessionId($request);
        $visitor = $this->getOrCreateVisitor($request, $sessionId);

        // Create visit record
        $visit = $this->createVisit($visitor, $request, $statusCode);

        // Update last activity
        $visitor->update(['last_activity_at' => now()]);

        // Store current visitor and visit
        $this->currentVisitor = $visitor;
        $this->currentVisit = $visit;

        // Dispatch event
        $isNewVisitor = $visitor->wasRecentlyCreated;
        event(new VisitorTracked($visitor, $visit, $isNewVisitor));

        return $visit;
    }

    /**
     * Track from pre-collected data (used by queue job).
     *
     * @param  array<string, mixed>  $data
     */
    public function trackFromData(array $data): ?Visit
    {
        // Check if tracking is enabled
        if (! config('visitor-tracker.enabled', true)) {
            return null;
        }

        $userAgent = $data['user_agent'] ?? null;
        $isBot = $this->botDetector->isBot($userAgent);
        $gdprSafe = $this->isGdprSafeMode();

        // Skip bot tracking if configured
        if ($isBot && ! config('visitor-tracker.bots.track', false)) {
            return null;
        }

        // Parse user agent
        $parsed = $this->parser->parse($userAgent);

        // Get IP address (null in GDPR safe mode)
        $ip = null;
        if (! $gdprSafe) {
            $ip = $data['ip'] ?? null;
            if ($ip && config('visitor-tracker.privacy.anonymize_ip', false)) {
                $ip = $this->anonymizeIp($ip);
            }
        }

        // Get session ID or generate new one
        $sessionId = $data['session_id'] ?? Str::uuid()->toString();

        // Get geolocation data (filtered in GDPR safe mode)
        $geoData = $this->geoService->lookup($data['ip'] ?? '');
        if ($gdprSafe) {
            $geoData = $this->filterGeoDataForGdpr($geoData);
        }

        // Get or create visitor (race-safe)
        $visitor = $this->firstOrCreateVisitor(
            $sessionId,
            array_merge([
                'ip' => $ip,
                'user_agent' => $gdprSafe ? null : $userAgent,
                'user_id' => $gdprSafe ? null : ($data['user_id'] ?? null),
                'is_bot' => $isBot,
                'last_activity_at' => now(),
            ], $parsed, $geoData)
        );

        // Create visit record
        $visit = $visitor->visits()->create([
            'user_id' => $gdprSafe ? null : ($data['user_id'] ?? null),
            'url' => $data['url'] ?? '',
            'path' => $data['path'] ?? '/',
            'method' => $data['method'] ?? 'GET',
            'referrer' => $data['referrer'] ?? null,
            'status_code' => $data['status_code'] ?? 200,
        ]);

        // Update last activity
        $visitor->update(['last_activity_at' => now()]);

        // Dispatch event
        $isNewVisitor = $visitor->wasRecentlyCreated;
        event(new VisitorTracked($visitor, $visit, $isNewVisitor));

        return $visit;
    }

    /**
     * Get or create the session ID for visitor identification.
     *
     * In GDPR safe mode, uses Laravel's session ID (session-only, no persistent cookie).
     * Otherwise, uses a persistent tracking cookie.
     */
    protected function getOrCreateSessionId(Request $request): string
    {
        // In GDPR safe mode, use session-based identification (no persistent cookie)
        if ($this->isGdprSafeMode()) {
            // Use Laravel's session ID if available, otherwise generate per-request ID
            if ($request->hasSession()) {
                return 'session_'.$request->session()->getId();
            }

            // Fallback: generate a hash based on non-identifying request characteristics
            // This groups requests from the same browser session without persistent tracking
            return 'anon_'.substr(md5(
                $request->userAgent().
                date('Y-m-d') // Groups by day only
            ), 0, 32);
        }

        $cookieName = config('visitor-tracker.cookie.name', 'visitor_tracker');
        /** @var string|null $sessionId */
        $sessionId = $request->cookie($cookieName);

        if (! $sessionId || ! is_string($sessionId)) {
            $sessionId = Str::uuid()->toString();
            $expiration = (int) config('visitor-tracker.cookie.expiration', 525600); // 1 year

            Cookie::queue($cookieName, $sessionId, $expiration);
        }

        return $sessionId;
    }

    /**
     * Get or create a visitor record.
     *
     * In GDPR safe mode, personal data is filtered out:
     * - No IP address
     * - No user ID
     * - No full user agent string
     * - No precise geolocation (only country)
     */
    protected function getOrCreateVisitor(Request $request, string $sessionId): Visitor
    {
        $userAgent = $request->userAgent();
        $parsed = $this->parser->parse($userAgent);
        $isBot = $this->botDetector->isBot($userAgent);
        $gdprSafe = $this->isGdprSafeMode();

        // Handle IP address
        $ip = null;
        if (! $gdprSafe) {
            $ip = $request->ip();
            if ($ip && config('visitor-tracker.privacy.anonymize_ip', false)) {
                $ip = $this->anonymizeIp($ip);
            }
        }

        // Get geolocation data (filtered in GDPR safe mode)
        $geoData = $this->geoService->lookup($request->ip() ?? '');
        if ($gdprSafe) {
            $geoData = $this->filterGeoDataForGdpr($geoData);
        }

        // Prepare visitor data
        $visitorData = array_merge([
            'ip' => $ip,
            'user_agent' => $gdprSafe ? null : $userAgent, // No UA in GDPR mode
            'user_id' => $gdprSafe ? null : $request->user()?->getAuthIdentifier(),
            'is_bot' => $isBot,
            'last_activity_at' => now(),
        ], $parsed, $geoData);

        // Find existing visitor or create new one (race-safe)
        $visitor = $this->firstOrCreateVisitor($sessionId, $visitorData);

        // Update user_id if visitor logged in (skip in GDPR safe mode)
        if (! $gdprSafe && $request->user() && ! $visitor->user_id) {
            $visitor->update(['user_id' => $request->user()->getAuthIdentifier()]);
        }

        return $visitor;
    }

    /**
     * Atomically find an existing visitor by session_id or create one.
     *
     * Wraps the lookup-then-insert in a transaction and recovers from a
     * concurrent insert (unique constraint violation on session_id) by
     * re-fetching the row that the other request just created.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function firstOrCreateVisitor(string $sessionId, array $attributes): Visitor
    {
        try {
            return DB::transaction(function () use ($sessionId, $attributes) {
                return Visitor::firstOrCreate(['session_id' => $sessionId], $attributes);
            });
        } catch (QueryException $e) {
            // Concurrent insert won the race — re-fetch the existing row.
            $existing = Visitor::where('session_id', $sessionId)->first();
            if ($existing) {
                return $existing;
            }

            throw $e;
        }
    }

    /**
     * Filter geolocation data for GDPR safe mode.
     *
     * Removes precise location data (city, region, coordinates),
     * keeping only country-level information.
     *
     * @param  array{country: string|null, country_code: string|null, city: string|null, region: string|null, latitude: float|null, longitude: float|null}  $geoData
     * @return array{country: string|null, country_code: string|null, city: null, region: null, latitude: null, longitude: null}
     */
    protected function filterGeoDataForGdpr(array $geoData): array
    {
        return [
            'country' => $geoData['country'],
            'country_code' => $geoData['country_code'],
            'city' => null,
            'region' => null,
            'latitude' => null,
            'longitude' => null,
        ];
    }

    /**
     * Create a visit record.
     *
     * In GDPR safe mode, user_id is not stored.
     */
    protected function createVisit(Visitor $visitor, Request $request, ?int $statusCode): Visit
    {
        return $visitor->visits()->create([
            'user_id' => $this->isGdprSafeMode() ? null : $request->user()?->getAuthIdentifier(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'method' => $request->method(),
            'referrer' => $request->header('referer'),
            'status_code' => $statusCode ?? 200,
        ]);
    }

    /**
     * Anonymize an IP address for GDPR compliance.
     * Removes the last octet for IPv4 or last 80 bits for IPv6.
     */
    protected function anonymizeIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: Replace last octet with 0
            return preg_replace('/\.\d+$/', '.0', $ip) ?? $ip;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: Replace last 80 bits (5 groups) with zeros
            return preg_replace('/:[0-9a-fA-F]{0,4}(:[0-9a-fA-F]{0,4}){4}$/', ':0:0:0:0:0', $ip) ?? $ip;
        }

        return $ip;
    }

    /**
     * Get the current visitor.
     */
    public function visitor(): ?Visitor
    {
        return $this->currentVisitor;
    }

    /**
     * Get the current visit.
     */
    public function visit(): ?Visit
    {
        return $this->currentVisit;
    }

    /**
     * Get the statistics service.
     */
    public function stats(): StatisticsService
    {
        return $this->statsService;
    }

    /**
     * Get the user agent parser.
     */
    public function parser(): UserAgentParser
    {
        return $this->parser;
    }

    /**
     * Get the bot detector.
     */
    public function botDetector(): BotDetector
    {
        return $this->botDetector;
    }

    /**
     * Get the geolocation service.
     */
    public function geoService(): GeoLocationService
    {
        return $this->geoService;
    }

    /**
     * Check if the current visitor is a bot.
     */
    public function isBot(?string $userAgent = null): bool
    {
        $userAgent = $userAgent ?? request()->userAgent();

        return $this->botDetector->isBot($userAgent);
    }

    /**
     * Parse a user agent string.
     *
     * @return array{browser: string|null, browser_version: string|null, platform: string|null, platform_version: string|null, device_type: string}
     */
    public function parseUserAgent(?string $userAgent = null): array
    {
        $userAgent = $userAgent ?? request()->userAgent();

        return $this->parser->parse($userAgent);
    }

    /**
     * Get total visitors count.
     */
    public function totalVisitors(): int
    {
        return $this->statsService->totalVisitors();
    }

    /**
     * Get total page views count.
     */
    public function totalPageViews(): int
    {
        return $this->statsService->totalPageViews();
    }

    /**
     * Get online visitors count.
     */
    public function onlineVisitors(?int $minutes = null): int
    {
        return $this->statsService->onlineVisitors($minutes);
    }

    /**
     * Get today's visitors count.
     */
    public function todayVisitors(): int
    {
        return $this->statsService->todayVisitors();
    }

    /**
     * Get today's page views count.
     */
    public function todayPageViews(): int
    {
        return $this->statsService->todayPageViews();
    }

    /**
     * Check if tracking is enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) config('visitor-tracker.enabled', true);
    }

    /**
     * Check if GDPR safe mode is enabled.
     *
     * When enabled, no personal data is collected:
     * - No IP addresses (not even anonymized)
     * - No user IDs
     * - No persistent cookies
     * - No full user agent strings
     * - No precise geolocation (city, region, coordinates)
     */
    public function isGdprSafeMode(): bool
    {
        return (bool) config('visitor-tracker.privacy.gdpr_safe_mode', false);
    }

    /**
     * Enable tracking.
     */
    public function enable(): void
    {
        config(['visitor-tracker.enabled' => true]);
    }

    /**
     * Disable tracking.
     */
    public function disable(): void
    {
        config(['visitor-tracker.enabled' => false]);
    }
}
