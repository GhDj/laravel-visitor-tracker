<?php

use Ghdj\VisitorTracker\Middleware\TrackVisitor;
use Ghdj\VisitorTracker\Models\Visit;
use Ghdj\VisitorTracker\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);

    // Reset config to defaults
    config([
        'visitor-tracker.enabled' => true,
        'visitor-tracker.privacy.respect_dnt' => true,
        'visitor-tracker.exclude.paths' => [],
        'visitor-tracker.exclude.methods' => ['OPTIONS', 'HEAD'],
        'visitor-tracker.exclude.status_codes' => [],
        'visitor-tracker.exclude.ips' => [],
        'visitor-tracker.exclude.user_agents' => [],
    ]);
});

/*
|--------------------------------------------------------------------------
| Basic Tracking Tests
|--------------------------------------------------------------------------
*/

test('middleware tracks a normal request', function () {
    $request = Request::create('/test-page', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $middleware = app(TrackVisitor::class);
    $response = $middleware->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200)
        ->and(Visit::count())->toBe(1)
        ->and(Visitor::count())->toBe(1);
});

test('middleware does not track when disabled', function () {
    config(['visitor-tracker.enabled' => false]);

    $request = Request::create('/test-page', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| DNT Header Tests
|--------------------------------------------------------------------------
*/

test('middleware respects DNT header when enabled', function () {
    config(['visitor-tracker.privacy.respect_dnt' => true]);

    $request = Request::create('/test-page', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.100',
        'HTTP_DNT' => '1',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(0);
});

test('middleware respects Sec-GPC header', function () {
    config(['visitor-tracker.privacy.respect_dnt' => true]);

    $request = Request::create('/test-page', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.100',
        'HTTP_SEC_GPC' => '1',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(0);
});

test('middleware ignores DNT header when disabled', function () {
    config(['visitor-tracker.privacy.respect_dnt' => false]);

    $request = Request::create('/test-page', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.100',
        'HTTP_DNT' => '1',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| HTTP Method Exclusion Tests
|--------------------------------------------------------------------------
*/

test('middleware excludes OPTIONS requests by default', function () {
    $request = Request::create('/test-page', 'OPTIONS', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(0);
});

test('middleware excludes HEAD requests by default', function () {
    $request = Request::create('/test-page', 'HEAD', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(0);
});

test('middleware tracks POST requests', function () {
    $request = Request::create('/test-page', 'POST', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(1)
        ->and(Visit::first()->method)->toBe('POST');
});

test('middleware can exclude custom methods', function () {
    config(['visitor-tracker.exclude.methods' => ['OPTIONS', 'HEAD', 'PUT', 'DELETE']]);

    $request = Request::create('/test-page', 'DELETE', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Path Exclusion Tests
|--------------------------------------------------------------------------
*/

test('middleware excludes exact path match', function () {
    config(['visitor-tracker.exclude.paths' => ['admin/dashboard']]);

    $request = Request::create('/admin/dashboard', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(0);
});

test('middleware excludes paths with wildcard', function () {
    config(['visitor-tracker.exclude.paths' => ['api/*']]);

    $request = Request::create('/api/users/123', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(0);
});

test('middleware excludes nested paths with wildcard', function () {
    config(['visitor-tracker.exclude.paths' => ['admin/*']]);

    // Test multiple nested paths
    $paths = ['admin/users', 'admin/settings/general', 'admin/reports/sales/2024'];

    foreach ($paths as $path) {
        $request = Request::create('/'.$path, 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
            'REMOTE_ADDR' => '192.168.1.100',
        ]);

        $middleware = app(TrackVisitor::class);
        $middleware->handle($request, fn () => new Response('OK', 200));
    }

    expect(Visit::count())->toBe(0);
});

test('middleware tracks non-excluded paths', function () {
    config(['visitor-tracker.exclude.paths' => ['api/*', 'admin/*']]);

    $request = Request::create('/public/page', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(1);
});

test('middleware supports multiple path patterns', function () {
    config(['visitor-tracker.exclude.paths' => ['api/*', 'admin/*', '_debugbar/*', 'telescope/*']]);

    $excludedPaths = ['api/test', 'admin/users', '_debugbar/assets', 'telescope/requests'];
    $middleware = app(TrackVisitor::class);

    foreach ($excludedPaths as $path) {
        $request = Request::create('/'.$path, 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
            'REMOTE_ADDR' => '192.168.1.100',
        ]);
        $middleware->handle($request, fn () => new Response('OK', 200));
    }

    expect(Visit::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Status Code Exclusion Tests
|--------------------------------------------------------------------------
*/

test('middleware excludes configured status codes', function () {
    config(['visitor-tracker.exclude.status_codes' => [404, 500]]);

    $request = Request::create('/not-found', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('Not Found', 404));

    expect(Visit::count())->toBe(0);
});

test('middleware excludes 500 errors', function () {
    config(['visitor-tracker.exclude.status_codes' => [500, 502, 503]]);

    $request = Request::create('/error', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('Error', 500));

    expect(Visit::count())->toBe(0);
});

test('middleware excludes redirect status codes', function () {
    config(['visitor-tracker.exclude.status_codes' => [301, 302, 307, 308]]);

    $request = Request::create('/old-page', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('', 302));

    expect(Visit::count())->toBe(0);
});

test('middleware tracks successful responses', function () {
    config(['visitor-tracker.exclude.status_codes' => [404, 500]]);

    $request = Request::create('/success', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(1)
        ->and(Visit::first()->status_code)->toBe(200);
});

/*
|--------------------------------------------------------------------------
| IP Exclusion Tests
|--------------------------------------------------------------------------
*/

test('middleware excludes exact IP match', function () {
    config(['visitor-tracker.exclude.ips' => ['192.168.1.100']]);

    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(0);
});

test('middleware excludes IP in CIDR range', function () {
    config(['visitor-tracker.exclude.ips' => ['192.168.1.0/24']]);

    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.50',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(0);
});

test('middleware tracks IPs outside CIDR range', function () {
    config(['visitor-tracker.exclude.ips' => ['192.168.1.0/24']]);

    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.2.50', // Different subnet
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(1);
});

test('middleware excludes localhost', function () {
    config(['visitor-tracker.exclude.ips' => ['127.0.0.1']]);

    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '127.0.0.1',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(0);
});

test('middleware supports multiple IP exclusions', function () {
    config(['visitor-tracker.exclude.ips' => ['127.0.0.1', '10.0.0.0/8', '192.168.0.0/16']]);

    $excludedIps = ['127.0.0.1', '10.50.100.200', '192.168.5.10'];
    $middleware = app(TrackVisitor::class);

    foreach ($excludedIps as $ip) {
        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
            'REMOTE_ADDR' => $ip,
        ]);
        $middleware->handle($request, fn () => new Response('OK', 200));
    }

    expect(Visit::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| User Agent Exclusion Tests
|--------------------------------------------------------------------------
*/

test('middleware excludes user agent by pattern', function () {
    config(['visitor-tracker.exclude.user_agents' => ['curl']]);

    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'curl/7.68.0',
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(0);
});

test('middleware user agent exclusion is case insensitive', function () {
    config(['visitor-tracker.exclude.user_agents' => ['postman']]);

    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'PostmanRuntime/7.26.8',
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(0);
});

test('middleware excludes multiple user agent patterns', function () {
    config(['visitor-tracker.exclude.user_agents' => ['curl', 'wget', 'postman', 'insomnia']]);

    $excludedAgents = [
        'curl/7.68.0',
        'Wget/1.21',
        'PostmanRuntime/7.26.8',
        'insomnia/2021.5.3',
    ];

    $middleware = app(TrackVisitor::class);

    foreach ($excludedAgents as $agent) {
        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => $agent,
            'REMOTE_ADDR' => '192.168.1.100',
        ]);
        $middleware->handle($request, fn () => new Response('OK', 200));
    }

    expect(Visit::count())->toBe(0);
});

test('middleware tracks normal browsers', function () {
    config(['visitor-tracker.exclude.user_agents' => ['curl', 'wget', 'postman']]);

    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Combined Exclusion Tests
|--------------------------------------------------------------------------
*/

test('middleware applies all exclusion rules together', function () {
    config([
        'visitor-tracker.exclude.paths' => ['api/*'],
        'visitor-tracker.exclude.ips' => ['10.0.0.0/8'],
        'visitor-tracker.exclude.user_agents' => ['bot'],
        'visitor-tracker.exclude.status_codes' => [404],
    ]);

    $middleware = app(TrackVisitor::class);

    // Should be excluded by path
    $request1 = Request::create('/api/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '203.0.113.50',
    ]);
    $middleware->handle($request1, fn () => new Response('OK', 200));

    // Should be excluded by IP
    $request2 = Request::create('/public', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '10.50.100.200',
    ]);
    $middleware->handle($request2, fn () => new Response('OK', 200));

    // Should be excluded by user agent
    $request3 = Request::create('/public', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'MyCustomBot/1.0',
        'REMOTE_ADDR' => '203.0.113.50',
    ]);
    $middleware->handle($request3, fn () => new Response('OK', 200));

    // Should be excluded by status code
    $request4 = Request::create('/not-found', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '203.0.113.50',
    ]);
    $middleware->handle($request4, fn () => new Response('Not Found', 404));

    // Should be tracked (no exclusions match)
    $request5 = Request::create('/public/page', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '203.0.113.50',
    ]);
    $middleware->handle($request5, fn () => new Response('OK', 200));

    expect(Visit::count())->toBe(1)
        ->and(Visit::first()->path)->toBe('public/page');
});

/*
|--------------------------------------------------------------------------
| Data Integrity Tests
|--------------------------------------------------------------------------
*/

test('middleware correctly stores visit data', function () {
    $request = Request::create('https://example.com/products/123?ref=google', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        'REMOTE_ADDR' => '203.0.113.50',
        'HTTP_REFERER' => 'https://google.com/search?q=products',
    ]);

    $middleware = app(TrackVisitor::class);
    $middleware->handle($request, fn () => new Response('OK', 200));

    $visit = Visit::first();
    $visitor = Visitor::first();

    expect($visit)->not->toBeNull()
        ->and($visit->path)->toBe('products/123')
        ->and($visit->method)->toBe('GET')
        ->and($visit->status_code)->toBe(200)
        ->and($visit->referrer)->toBe('https://google.com/search?q=products')
        ->and($visitor->browser)->toBe('Chrome')
        ->and($visitor->platform)->toBe('macOS')
        ->and($visitor->device_type)->toBe('desktop');
});
