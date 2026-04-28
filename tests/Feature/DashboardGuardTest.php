<?php

use Ghdj\VisitorTracker\VisitorTrackerServiceProvider;

/**
 * The dashboard auto-protection guard auto-skips in the 'testing' env, so to
 * exercise the protection logic itself we boot the service provider against a
 * local-environment Application clone and assert the guard fires (or doesn't)
 * based on config.
 */
function bootProviderInLocalEnv(array $dashboardConfig): void
{
    config()->set('visitor-tracker.dashboard', array_merge([
        'enabled' => true,
        'prefix' => 'admin/visitor-tracker',
        'token' => null,
        'middleware' => ['web'],
        'gate' => null,
        'allow_unprotected' => false,
    ], $dashboardConfig));

    $app = app();
    $original = $app->environment();
    $app['env'] = 'production';

    try {
        (new VisitorTrackerServiceProvider($app))->boot();
    } finally {
        $app['env'] = $original;
    }
}

test('guard throws when dashboard is enabled with no auth', function () {
    bootProviderInLocalEnv([
        'enabled' => true,
        'token' => null,
        'gate' => null,
        'middleware' => ['web'],
    ]);
})->throws(RuntimeException::class, 'dashboard is enabled but unprotected');

test('guard accepts a configured token', function () {
    bootProviderInLocalEnv([
        'token' => 'secret',
    ]);
})->throwsNoExceptions();

test('guard accepts a configured gate', function () {
    bootProviderInLocalEnv([
        'gate' => 'view-visitor-stats',
    ]);
})->throwsNoExceptions();

test('guard accepts auth middleware', function () {
    bootProviderInLocalEnv([
        'middleware' => ['web', 'auth'],
    ]);
})->throwsNoExceptions();

test('guard accepts auth:guard syntax in middleware', function () {
    bootProviderInLocalEnv([
        'middleware' => ['web', 'auth:sanctum'],
    ]);
})->throwsNoExceptions();

test('guard skips when dashboard is disabled', function () {
    bootProviderInLocalEnv([
        'enabled' => false,
    ]);
})->throwsNoExceptions();

test('guard skips when allow_unprotected is explicitly set', function () {
    bootProviderInLocalEnv([
        'allow_unprotected' => true,
    ]);
})->throwsNoExceptions();
