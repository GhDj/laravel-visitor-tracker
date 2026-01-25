<?php

declare(strict_types=1);

namespace Ghdj\VisitorTracker\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to authorize access to the visitor tracker dashboard.
 *
 * Supports multiple authentication methods:
 * 1. Secret token (for sites without authentication)
 * 2. Laravel Gate (for sites with authentication)
 * 3. Standard Laravel auth middleware (configured separately)
 */
class AuthorizeDashboard
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if token authentication is configured
        $configuredToken = config('visitor-tracker.dashboard.token');

        if ($configuredToken) {
            // Token is required - validate it
            if (! $this->validateToken($request, $configuredToken)) {
                return $this->unauthorized('Invalid or missing access token.');
            }
        }

        // Check Laravel Gate if configured (for role-based access)
        $gate = config('visitor-tracker.dashboard.gate');
        if ($gate && Gate::has($gate) && Gate::denies($gate)) {
            return $this->unauthorized('You do not have permission to view visitor statistics.');
        }

        return $next($request);
    }

    /**
     * Validate the access token from the request.
     *
     * Token can be provided via:
     * - Query parameter: ?token=xxx
     * - Header: X-Visitor-Tracker-Token: xxx
     * - Header: Authorization: Bearer xxx
     */
    protected function validateToken(Request $request, string $configuredToken): bool
    {
        // Check query parameter
        $queryToken = $request->query('token');
        if (is_string($queryToken) && hash_equals($configuredToken, $queryToken)) {
            return true;
        }

        // Check custom header
        $headerToken = $request->header('X-Visitor-Tracker-Token');
        if (is_string($headerToken) && hash_equals($configuredToken, $headerToken)) {
            return true;
        }

        // Check Authorization Bearer header
        $bearerToken = $request->bearerToken();
        if (is_string($bearerToken) && hash_equals($configuredToken, $bearerToken)) {
            return true;
        }

        return false;
    }

    /**
     * Return an unauthorized response.
     */
    protected function unauthorized(string $message): Response
    {
        if (request()->expectsJson()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => $message,
            ], 403);
        }

        abort(403, $message);
    }
}
