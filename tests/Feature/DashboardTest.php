<?php

use Ghdj\VisitorTracker\Middleware\AuthorizeDashboard;
use Ghdj\VisitorTracker\Models\Visitor;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);
});

describe('dashboard controller', function () {
    beforeEach(function () {
        config(['visitor-tracker.dashboard.enabled' => true]);
        config(['visitor-tracker.dashboard.gate' => null]);
        config(['visitor-tracker.dashboard.token' => null]);

        $this->app['router']->group([
            'prefix' => config('visitor-tracker.dashboard.prefix'),
            'middleware' => ['web'],
        ], function ($router) {
            $router->get('/', [\Ghdj\VisitorTracker\Http\Controllers\DashboardController::class, 'index'])
                ->name('visitor-tracker.dashboard');
            $router->get('/stats', [\Ghdj\VisitorTracker\Http\Controllers\DashboardController::class, 'stats'])
                ->name('visitor-tracker.stats');
        });
    });

    it('displays dashboard with statistics', function () {
        Visitor::create([
            'session_id' => 'test-session-1',
            'ip' => '192.168.1.1',
            'browser' => 'Chrome',
            'platform' => 'Windows',
            'device_type' => 'desktop',
            'is_bot' => false,
            'last_activity_at' => now(),
        ]);

        $response = $this->get('/admin/visitor-tracker');

        $response->assertStatus(200);
        $response->assertViewIs('visitor-tracker::dashboard');
        $response->assertViewHas('summary');
        $response->assertViewHas('browsers');
        $response->assertViewHas('platforms');
        $response->assertViewHas('devices');
    });

    it('accepts period filter parameter', function () {
        $response = $this->get('/admin/visitor-tracker?period=month');

        $response->assertStatus(200);
        $response->assertViewHas('period', 'month');
    });

    it('returns json stats endpoint', function () {
        Visitor::create([
            'session_id' => 'test-session-1',
            'ip' => '192.168.1.1',
            'is_bot' => false,
            'last_activity_at' => now(),
        ]);

        $response = $this->getJson('/admin/visitor-tracker/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'summary' => [
                'total_visitors',
                'total_page_views',
                'today_visitors',
                'online_visitors',
            ],
            'online',
        ]);
    });

    it('uses custom route prefix', function () {
        // Register route with custom prefix
        $this->app['router']->group([
            'prefix' => 'custom/stats',
            'middleware' => ['web'],
        ], function ($router) {
            $router->get('/', [\Ghdj\VisitorTracker\Http\Controllers\DashboardController::class, 'index']);
        });

        $response = $this->get('/custom/stats');

        $response->assertStatus(200);
    });
});

describe('dashboard gate authorization', function () {
    beforeEach(function () {
        config([
            'visitor-tracker.dashboard.enabled' => true,
            'visitor-tracker.dashboard.gate' => 'view-visitor-stats',
            'visitor-tracker.dashboard.token' => null,
        ]);

        // Register routes with the authorization middleware
        $this->app['router']->aliasMiddleware('visitor-tracker-auth', AuthorizeDashboard::class);

        $this->app['router']->group([
            'prefix' => config('visitor-tracker.dashboard.prefix'),
            'middleware' => ['web', 'visitor-tracker-auth'],
        ], function ($router) {
            $router->get('/', [\Ghdj\VisitorTracker\Http\Controllers\DashboardController::class, 'index']);
            $router->get('/stats', [\Ghdj\VisitorTracker\Http\Controllers\DashboardController::class, 'stats']);
        });
    });

    it('denies access when gate is defined and returns false', function () {
        Gate::define('view-visitor-stats', fn ($user = null) => false);

        $response = $this->get('/admin/visitor-tracker');

        expect($response->getStatusCode())->toBe(403);
    });

    it('allows access when gate is defined and returns true', function () {
        Gate::define('view-visitor-stats', fn ($user = null) => true);

        $response = $this->get('/admin/visitor-tracker');

        expect($response->getStatusCode())->toBe(200);
    });

    it('denies json stats when gate returns false', function () {
        Gate::define('view-visitor-stats', fn ($user = null) => false);

        $response = $this->getJson('/admin/visitor-tracker/stats');

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Unauthorized']);
    });

    it('allows json stats when gate returns true', function () {
        Gate::define('view-visitor-stats', fn ($user = null) => true);

        $response = $this->getJson('/admin/visitor-tracker/stats');

        $response->assertStatus(200);
    });
});

describe('dashboard token authorization', function () {
    beforeEach(function () {
        config([
            'visitor-tracker.dashboard.enabled' => true,
            'visitor-tracker.dashboard.gate' => null,
            'visitor-tracker.dashboard.token' => 'secret-test-token',
        ]);

        // Register routes with the authorization middleware
        $this->app['router']->aliasMiddleware('visitor-tracker-auth', AuthorizeDashboard::class);

        $this->app['router']->group([
            'prefix' => config('visitor-tracker.dashboard.prefix'),
            'middleware' => ['web', 'visitor-tracker-auth'],
        ], function ($router) {
            $router->get('/', [\Ghdj\VisitorTracker\Http\Controllers\DashboardController::class, 'index']);
            $router->get('/stats', [\Ghdj\VisitorTracker\Http\Controllers\DashboardController::class, 'stats']);
        });
    });

    it('denies access without token', function () {
        $response = $this->get('/admin/visitor-tracker');

        expect($response->getStatusCode())->toBe(403);
    });

    it('denies access with invalid token', function () {
        $response = $this->get('/admin/visitor-tracker?token=wrong-token');

        expect($response->getStatusCode())->toBe(403);
    });

    it('allows access with valid token in query string', function () {
        $response = $this->get('/admin/visitor-tracker?token=secret-test-token');

        expect($response->getStatusCode())->toBe(200);
    });

    it('allows access with valid token in header', function () {
        $response = $this->get('/admin/visitor-tracker', [
            'X-Visitor-Tracker-Token' => 'secret-test-token',
        ]);

        expect($response->getStatusCode())->toBe(200);
    });

    it('allows access with valid bearer token', function () {
        $response = $this->get('/admin/visitor-tracker', [
            'Authorization' => 'Bearer secret-test-token',
        ]);

        expect($response->getStatusCode())->toBe(200);
    });

    it('returns json error for json requests without token', function () {
        $response = $this->getJson('/admin/visitor-tracker/stats');

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Invalid or missing access token.',
        ]);
    });

    it('returns json stats with valid token', function () {
        $response = $this->getJson('/admin/visitor-tracker/stats?token=secret-test-token');

        $response->assertStatus(200);
        $response->assertJsonStructure(['summary', 'online']);
    });
});

describe('install dashboard command', function () {
    it('runs without errors', function () {
        $this->artisan('visitor-tracker:install-dashboard')
            ->assertSuccessful();
    });

    it('displays next steps', function () {
        $this->artisan('visitor-tracker:install-dashboard')
            ->expectsOutputToContain('Next steps')
            ->assertSuccessful();
    });
});
