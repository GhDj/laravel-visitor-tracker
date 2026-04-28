<?php

use Ghdj\VisitorTracker\Models\Visit;
use Ghdj\VisitorTracker\Models\Visitor;
use Ghdj\VisitorTracker\Services\StatisticsService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);
    Cache::flush();
    $this->stats = new StatisticsService;
});

function makeVisitor(array $overrides = []): Visitor
{
    return Visitor::create(array_merge([
        'session_id' => 'sess_'.uniqid('', true),
        'ip' => '203.0.113.1',
        'user_agent' => 'Mozilla/5.0',
        'browser' => 'Chrome',
        'platform' => 'Windows',
        'device_type' => 'desktop',
        'is_bot' => false,
        'country' => 'United States',
        'country_code' => 'US',
        'last_activity_at' => now(),
    ], $overrides));
}

function makeVisit(Visitor $visitor, array $overrides = []): Visit
{
    return $visitor->visits()->create(array_merge([
        'url' => 'https://example.test/',
        'path' => '/',
        'method' => 'GET',
        'status_code' => 200,
    ], $overrides));
}

test('totalVisitors counts only humans', function () {
    makeVisitor();
    makeVisitor();
    makeVisitor(['is_bot' => true]);

    expect($this->stats->totalVisitors())->toBe(2);
});

test('totalPageViews counts only human visits', function () {
    $human = makeVisitor();
    makeVisit($human);
    makeVisit($human, ['path' => '/about']);

    $bot = makeVisitor(['is_bot' => true]);
    makeVisit($bot);

    expect($this->stats->totalPageViews())->toBe(2);
});

test('todayVisitors only counts visitors created today', function () {
    makeVisitor();
    $old = makeVisitor();
    // Eloquent rewrites created_at on insert; force it post-hoc.
    Visitor::where('id', $old->id)->update(['created_at' => now()->subDays(2)]);

    expect($this->stats->todayVisitors())->toBe(1);
});

test('onlineVisitors counts visitors with recent last_activity_at', function () {
    makeVisitor(['last_activity_at' => now()->subMinutes(2)]);
    makeVisitor(['last_activity_at' => now()->subMinutes(20)]);

    expect($this->stats->onlineVisitors(5))->toBe(1);
});

test('mostVisitedPages ranks by visit count', function () {
    $v = makeVisitor();
    makeVisit($v, ['path' => '/popular']);
    makeVisit($v, ['path' => '/popular']);
    makeVisit($v, ['path' => '/popular']);
    makeVisit($v, ['path' => '/quiet']);

    $top = $this->stats->mostVisitedPages(5);

    expect($top->first()->path)->toBe('/popular')
        ->and((int) $top->first()->visits)->toBe(3);
});

test('topReferrers groups by referrer', function () {
    $v = makeVisitor();
    makeVisit($v, ['referrer' => 'https://google.com']);
    makeVisit($v, ['referrer' => 'https://google.com']);
    makeVisit($v, ['referrer' => 'https://github.com']);
    makeVisit($v, ['referrer' => null]);

    $refs = $this->stats->topReferrers();

    expect($refs)->toHaveCount(2)
        ->and($refs->first()->referrer)->toBe('https://google.com');
});

test('browserStats groups by browser', function () {
    makeVisitor(['browser' => 'Chrome']);
    makeVisitor(['browser' => 'Chrome']);
    makeVisitor(['browser' => 'Firefox']);

    $stats = $this->stats->browserStats();

    expect($stats->first()->browser)->toBe('Chrome')
        ->and((int) $stats->first()->count)->toBe(2);
});

test('countryStats groups by country', function () {
    makeVisitor(['country' => 'United States', 'country_code' => 'US']);
    makeVisitor(['country' => 'United States', 'country_code' => 'US']);
    makeVisitor(['country' => 'France', 'country_code' => 'FR']);

    $stats = $this->stats->countryStats();

    expect($stats)->toHaveCount(2);
});

test('bounceRate computes percentage of single-page visitors', function () {
    $bouncer = makeVisitor();
    makeVisit($bouncer);

    $engaged = makeVisitor();
    makeVisit($engaged);
    makeVisit($engaged, ['path' => '/two']);

    expect($this->stats->bounceRate())->toBe(50.0);
});

test('averagePagesPerVisit divides views by visitors', function () {
    $a = makeVisitor();
    makeVisit($a);
    makeVisit($a, ['path' => '/x']);

    $b = makeVisitor();
    makeVisit($b);

    expect($this->stats->averagePagesPerVisit())->toBe(1.5);
});

test('summary returns expected keys', function () {
    expect($this->stats->summary())->toHaveKeys([
        'total_visitors',
        'total_page_views',
        'today_visitors',
        'today_page_views',
        'online_visitors',
        'this_week_visitors',
        'this_month_visitors',
    ]);
});

test('clearCache forgets every key the service registered', function () {
    makeVisitor();
    $this->stats->totalVisitors();
    $this->stats->browserStats();

    $prefix = config('visitor-tracker.cache.prefix', 'visitor_tracker_');
    expect(Cache::has($prefix.'total_visitors_all'))->toBeTrue();

    $this->stats->clearCache();

    expect(Cache::has($prefix.'total_visitors_all'))->toBeFalse()
        ->and(Cache::has($prefix.'browser_stats_10'))->toBeFalse();
});

test('getDateExpression rejects unsafe column names', function () {
    $service = new class extends StatisticsService
    {
        public function exposeGetDateExpression(string $column, string $period): string
        {
            return $this->getDateExpression($column, $period);
        }
    };

    $service->exposeGetDateExpression('created_at; DROP TABLE visitors;--', 'day');
})->throws(InvalidArgumentException::class);
