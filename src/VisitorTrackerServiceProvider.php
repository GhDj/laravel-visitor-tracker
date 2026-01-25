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

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
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
