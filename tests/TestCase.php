<?php

namespace Ghdj\VisitorTracker\Tests;

use Ghdj\VisitorTracker\VisitorTrackerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            VisitorTrackerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('visitor-tracker.enabled', true);
        config()->set('visitor-tracker.bots.track', false);
        config()->set('visitor-tracker.bots.detect', true);
    }
}
