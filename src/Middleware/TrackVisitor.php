<?php

declare(strict_types=1);

namespace Ghdj\VisitorTracker\Middleware;

use Closure;
use Ghdj\VisitorTracker\Jobs\TrackVisitorJob;
use Ghdj\VisitorTracker\VisitorTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for automatic visitor tracking.
 */
class TrackVisitor
{
    public function __construct(
        protected VisitorTracker $tracker
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Check if tracking is enabled
        if (! config('visitor-tracker.enabled', true)) {
            return $response;
        }

        // Check if the request should be tracked
        if (! $this->shouldTrack($request, $response)) {
            return $response;
        }

        // Track the visit
        if (config('visitor-tracker.queue.enabled', false)) {
            $this->trackAsync($request, $response);
        } else {
            $this->tracker->track($request, $response->getStatusCode());
        }

        return $response;
    }

    /**
     * Determine if the request should be tracked.
     */
    protected function shouldTrack(Request $request, Response $response): bool
    {
        // Check DNT header
        if (config('visitor-tracker.privacy.respect_dnt', true)) {
            if ($request->header('DNT') === '1' || $request->header('Sec-GPC') === '1') {
                return false;
            }
        }

        // Check excluded methods
        if ($this->isExcludedMethod($request->method())) {
            return false;
        }

        // Check excluded paths
        if ($this->isExcludedPath($request->path())) {
            return false;
        }

        // Check excluded status codes
        if ($this->isExcludedStatusCode($response->getStatusCode())) {
            return false;
        }

        // Check excluded IPs
        if ($this->isExcludedIp($request->ip())) {
            return false;
        }

        // Check excluded user agents
        if ($this->isExcludedUserAgent($request->userAgent())) {
            return false;
        }

        return true;
    }

    /**
     * Check if the HTTP method is excluded.
     */
    protected function isExcludedMethod(string $method): bool
    {
        $excludedMethods = config('visitor-tracker.exclude.methods', ['OPTIONS', 'HEAD']);

        return in_array(strtoupper($method), $excludedMethods, true);
    }

    /**
     * Check if the path is excluded.
     */
    protected function isExcludedPath(string $path): bool
    {
        $excludedPaths = config('visitor-tracker.exclude.paths', []);

        foreach ($excludedPaths as $pattern) {
            if (Str::is($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the status code is excluded.
     */
    protected function isExcludedStatusCode(int $statusCode): bool
    {
        $excludedCodes = config('visitor-tracker.exclude.status_codes', []);

        return in_array($statusCode, $excludedCodes, true);
    }

    /**
     * Check if the IP is excluded.
     */
    protected function isExcludedIp(?string $ip): bool
    {
        if (! $ip) {
            return false;
        }

        $excludedIps = config('visitor-tracker.exclude.ips', []);

        foreach ($excludedIps as $excludedIp) {
            if ($this->ipMatches($ip, $excludedIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP matches a pattern (supports CIDR notation).
     */
    protected function ipMatches(string $ip, string $pattern): bool
    {
        // Direct match
        if ($ip === $pattern) {
            return true;
        }

        // CIDR notation
        if (str_contains($pattern, '/')) {
            return $this->ipInCidr($ip, $pattern);
        }

        return false;
    }

    /**
     * Check if an IP is within a CIDR range.
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskLong = -1 << (32 - (int) $mask);
        $subnetLong &= $maskLong;

        return ($ipLong & $maskLong) === $subnetLong;
    }

    /**
     * Check if the user agent is excluded.
     */
    protected function isExcludedUserAgent(?string $userAgent): bool
    {
        if (! $userAgent) {
            return false;
        }

        $excludedAgents = config('visitor-tracker.exclude.user_agents', []);
        $userAgentLower = strtolower($userAgent);

        foreach ($excludedAgents as $pattern) {
            if (str_contains($userAgentLower, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Track the visit asynchronously via queue.
     */
    protected function trackAsync(Request $request, Response $response): void
    {
        $data = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'method' => $request->method(),
            'referrer' => $request->header('referer'),
            'status_code' => $response->getStatusCode(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'session_id' => $request->cookie(config('visitor-tracker.cookie.name', 'visitor_tracker')),
        ];

        TrackVisitorJob::dispatch($data);
    }
}
