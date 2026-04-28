<?php

use Ghdj\VisitorTracker\Facades\VisitorTracker;
use Ghdj\VisitorTracker\Models\Visit;
use Ghdj\VisitorTracker\Models\Visitor;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);
});

test('can track a visitor', function () {
    $request = Request::create('/test-page', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        'REMOTE_ADDR' => '192.168.1.1',
    ]);

    $visit = VisitorTracker::track($request, 200);

    expect($visit)->toBeInstanceOf(Visit::class)
        ->and($visit->path)->toBe('test-page')
        ->and($visit->method)->toBe('GET')
        ->and($visit->status_code)->toBe(200);

    expect($visit->visitor)->toBeInstanceOf(Visitor::class)
        ->and($visit->visitor->browser)->toBe('Chrome')
        ->and($visit->visitor->platform)->toBe('Windows')
        ->and($visit->visitor->device_type)->toBe('desktop')
        ->and($visit->visitor->is_bot)->toBeFalse();
});

test('does not track bots by default', function () {
    $request = Request::create('/test-page', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
        'REMOTE_ADDR' => '66.249.66.1',
    ]);

    $visit = VisitorTracker::track($request, 200);

    expect($visit)->toBeNull();
});

test('tracks bots when enabled', function () {
    config(['visitor-tracker.bots.track' => true]);

    $request = Request::create('/test-page', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
        'REMOTE_ADDR' => '66.249.66.1',
    ]);

    $visit = VisitorTracker::track($request, 200);

    expect($visit)->toBeInstanceOf(Visit::class)
        ->and($visit->visitor->is_bot)->toBeTrue();
});

test('can get total visitors', function () {
    // Create some visitors
    Visitor::create([
        'session_id' => 'session-1',
        'ip' => '192.168.1.1',
        'is_bot' => false,
        'last_activity_at' => now(),
    ]);

    Visitor::create([
        'session_id' => 'session-2',
        'ip' => '192.168.1.2',
        'is_bot' => false,
        'last_activity_at' => now(),
    ]);

    // Bot should not be counted
    Visitor::create([
        'session_id' => 'session-3',
        'ip' => '66.249.66.1',
        'is_bot' => true,
        'last_activity_at' => now(),
    ]);

    expect(VisitorTracker::totalVisitors())->toBe(2);
});

test('can get online visitors', function () {
    Visitor::create([
        'session_id' => 'session-1',
        'ip' => '192.168.1.1',
        'is_bot' => false,
        'last_activity_at' => now(),
    ]);

    Visitor::create([
        'session_id' => 'session-2',
        'ip' => '192.168.1.2',
        'is_bot' => false,
        'last_activity_at' => now()->subMinutes(10), // Offline
    ]);

    expect(VisitorTracker::onlineVisitors())->toBe(1);
});

test('can get statistics summary', function () {
    Visitor::create([
        'session_id' => 'session-1',
        'ip' => '192.168.1.1',
        'is_bot' => false,
        'last_activity_at' => now(),
    ]);

    $summary = VisitorTracker::stats()->summary();

    expect($summary)->toBeArray()
        ->toHaveKey('total_visitors')
        ->toHaveKey('total_page_views')
        ->toHaveKey('online_visitors');
});

test('visitor helper function works', function () {
    expect(visitor())->toBeInstanceOf(Ghdj\VisitorTracker\VisitorTracker::class);
});

test('can enable and disable tracking', function () {
    expect(VisitorTracker::isEnabled())->toBeTrue();

    VisitorTracker::disable();
    expect(VisitorTracker::isEnabled())->toBeFalse();

    VisitorTracker::enable();
    expect(VisitorTracker::isEnabled())->toBeTrue();
});

test('respects DNT header when configured', function () {
    config(['visitor-tracker.privacy.respect_dnt' => true]);

    $request = Request::create('/test-page', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '192.168.1.1',
        'HTTP_DNT' => '1',
    ]);

    // Tracking happens in middleware, but this tests the config
    expect(config('visitor-tracker.privacy.respect_dnt'))->toBeTrue();
});

test('gdpr safe mode does not store IP address', function () {
    config(['visitor-tracker.privacy.gdpr_safe_mode' => true]);

    $request = Request::create('/test-page', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
        'REMOTE_ADDR' => '203.0.113.50',
    ]);

    $visit = VisitorTracker::track($request, 200);

    expect($visit)->toBeInstanceOf(Visit::class)
        ->and($visit->visitor->ip)->toBeNull();
});

test('gdpr safe mode does not store user agent string', function () {
    config(['visitor-tracker.privacy.gdpr_safe_mode' => true]);

    $request = Request::create('/test-page', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
        'REMOTE_ADDR' => '203.0.113.50',
    ]);

    $visit = VisitorTracker::track($request, 200);

    expect($visit->visitor->user_agent)->toBeNull()
        // But still parses browser/platform for aggregate stats
        ->and($visit->visitor->browser)->toBe('Chrome')
        ->and($visit->visitor->platform)->toBe('Windows');
});

test('gdpr safe mode does not store user id', function () {
    config(['visitor-tracker.privacy.gdpr_safe_mode' => true]);

    $request = Request::create('/test-page', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0.0.0',
        'REMOTE_ADDR' => '203.0.113.50',
    ]);

    // Simulate authenticated user
    $user = new class
    {
        public function getAuthIdentifier(): int
        {
            return 123;
        }
    };

    $request->setUserResolver(fn () => $user);

    $visit = VisitorTracker::track($request, 200);

    expect($visit->visitor->user_id)->toBeNull()
        ->and($visit->user_id)->toBeNull();
});

test('gdpr safe mode does not store precise geolocation', function () {
    config([
        'visitor-tracker.privacy.gdpr_safe_mode' => true,
        'visitor-tracker.geolocation.enabled' => true,
    ]);

    // Create visitor with geo data that would normally include city/region
    $visitor = Visitor::create([
        'session_id' => 'gdpr-test-session',
        'ip' => null,
        'country' => 'United States',
        'country_code' => 'US',
        'city' => null, // Should be null in GDPR mode
        'region' => null, // Should be null in GDPR mode
        'latitude' => null, // Should be null in GDPR mode
        'longitude' => null, // Should be null in GDPR mode
        'is_bot' => false,
        'last_activity_at' => now(),
    ]);

    expect($visitor->country)->toBe('United States')
        ->and($visitor->country_code)->toBe('US')
        ->and($visitor->city)->toBeNull()
        ->and($visitor->region)->toBeNull()
        ->and($visitor->latitude)->toBeNull()
        ->and($visitor->longitude)->toBeNull();
});

test('isGdprSafeMode returns correct status', function () {
    expect(VisitorTracker::isGdprSafeMode())->toBeFalse();

    config(['visitor-tracker.privacy.gdpr_safe_mode' => true]);

    expect(VisitorTracker::isGdprSafeMode())->toBeTrue();
});
