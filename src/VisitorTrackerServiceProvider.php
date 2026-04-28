<?php

declare(strict_types=1);

namespace Ghdj\VisitorTracker;

use Ghdj\VisitorTracker\Commands\InstallDashboardCommand;
use Ghdj\VisitorTracker\Commands\PruneVisitorsCommand;
use Ghdj\VisitorTracker\Commands\VisitorStatsCommand;
use Ghdj\VisitorTracker\Middleware\AuthorizeDashboard;
use Ghdj\VisitorTracker\Middleware\TrackVisitor;
use Ghdj\VisitorTracker\Services\BotDetector;
use Ghdj\VisitorTracker\Services\GeoLocationService;
use Ghdj\VisitorTracker\Services\StatisticsService;
use Ghdj\VisitorTracker\Services\UserAgentParser;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Visitor Tracker package.
 */
class VisitorTrackerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/visitor-tracker.php',
            'visitor-tracker'
        );

        // Register services as singletons
        $this->app->singleton(UserAgentParser::class, function () {
            return new UserAgentParser;
        });

        $this->app->singleton(BotDetector::class, function () {
            return new BotDetector;
        });

        $this->app->singleton(GeoLocationService::class, function () {
            return new GeoLocationService;
        });

        $this->app->singleton(StatisticsService::class, function () {
            return new StatisticsService;
        });

        // Register main tracker
        $this->app->singleton(VisitorTracker::class, function ($app) {
            return new VisitorTracker(
                $app->make(UserAgentParser::class),
                $app->make(BotDetector::class),
                $app->make(GeoLocationService::class),
                $app->make(StatisticsService::class)
            );
        });

        // Alias for convenience
        $this->app->alias(VisitorTracker::class, 'visitor-tracker');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPublishables();
        $this->registerMiddleware();
        $this->registerCommands();
        $this->registerBladeDirectives();
        $this->loadHelpers();
        $this->loadViews();
        $this->loadRoutes();
    }

    /**
     * Register publishable resources.
     */
    protected function registerPublishables(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Config
        $this->publishes([
            __DIR__.'/../config/visitor-tracker.php' => config_path('visitor-tracker.php'),
        ], 'visitor-tracker-config');

        // Migrations
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'visitor-tracker-migrations');

        // Views
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/visitor-tracker'),
        ], 'visitor-tracker-views');

        // All publishables
        $this->publishes([
            __DIR__.'/../config/visitor-tracker.php' => config_path('visitor-tracker.php'),
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'visitor-tracker');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Register the middleware.
     */
    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('track-visitor', TrackVisitor::class);
        $router->aliasMiddleware('visitor-tracker-auth', AuthorizeDashboard::class);
    }

    /**
     * Register console commands.
     */
    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallDashboardCommand::class,
            PruneVisitorsCommand::class,
            VisitorStatsCommand::class,
        ]);
    }

    /**
     * Register Blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        Blade::directive('totalVisitors', function () {
            return '<?php echo \Ghdj\VisitorTracker\Facades\VisitorTracker::totalVisitors(); ?>';
        });

        Blade::directive('totalPageViews', function () {
            return '<?php echo \Ghdj\VisitorTracker\Facades\VisitorTracker::totalPageViews(); ?>';
        });

        Blade::directive('onlineVisitors', function ($expression) {
            $minutes = $expression ?: 'null';

            return "<?php echo \\Ghdj\\VisitorTracker\\Facades\\VisitorTracker::onlineVisitors({$minutes}); ?>";
        });

        Blade::directive('todayVisitors', function () {
            return '<?php echo \Ghdj\VisitorTracker\Facades\VisitorTracker::todayVisitors(); ?>';
        });

        Blade::directive('todayPageViews', function () {
            return '<?php echo \Ghdj\VisitorTracker\Facades\VisitorTracker::todayPageViews(); ?>';
        });
    }

    /**
     * Load helper functions.
     */
    protected function loadHelpers(): void
    {
        $helpersPath = __DIR__.'/Helpers/helpers.php';

        if (file_exists($helpersPath)) {
            require_once $helpersPath;
        }
    }

    /**
     * Load package views.
     */
    protected function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'visitor-tracker');
    }

    /**
     * Load dashboard routes if enabled.
     */
    protected function loadRoutes(): void
    {
        if (! config('visitor-tracker.dashboard.enabled', false)) {
            return;
        }

        $this->assertDashboardIsProtected();

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    /**
     * Refuse to register dashboard routes when no authentication mechanism is set.
     *
     * The dashboard exposes potentially sensitive analytics, so we require at least
     * one of: a secret token, a Gate, or an 'auth*' middleware in dashboard.middleware.
     *
     * Skipped in the testing environment so the package's own test suite can
     * exercise the controller in isolation. Consumers can also opt out per-app
     * by setting visitor-tracker.dashboard.allow_unprotected to true.
     *
     * @throws \RuntimeException
     */
    protected function assertDashboardIsProtected(): void
    {
        if ($this->app->environment('testing')) {
            return;
        }

        if (config('visitor-tracker.dashboard.allow_unprotected', false)) {
            return;
        }

        $token = config('visitor-tracker.dashboard.token');
        $gate = config('visitor-tracker.dashboard.gate');
        $middleware = (array) config('visitor-tracker.dashboard.middleware', []);

        $hasAuthMiddleware = false;
        foreach ($middleware as $entry) {
            if (is_string($entry) && (str_starts_with($entry, 'auth') || str_contains($entry, ':auth'))) {
                $hasAuthMiddleware = true;

                break;
            }
        }

        if ($token || $gate || $hasAuthMiddleware) {
            return;
        }

        throw new \RuntimeException(
            'Visitor Tracker dashboard is enabled but unprotected. Configure at least one of: '
            .'visitor-tracker.dashboard.token (env VISITOR_TRACKER_TOKEN), '
            .'visitor-tracker.dashboard.gate, or '
            .'add an auth middleware (e.g. "auth") to visitor-tracker.dashboard.middleware. '
            .'To intentionally allow this (not recommended), set visitor-tracker.dashboard.allow_unprotected = true.'
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            VisitorTracker::class,
            UserAgentParser::class,
            BotDetector::class,
            GeoLocationService::class,
            StatisticsService::class,
            'visitor-tracker',
        ];
    }
}
