<?php

use Ghdj\VisitorTracker\Services\GeoLocationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    config()->set('visitor-tracker.geolocation.enabled', true);
    config()->set('visitor-tracker.geolocation.provider', 'ip-api');
});

test('returns empty result when geolocation is disabled', function () {
    config()->set('visitor-tracker.geolocation.enabled', false);

    $service = new GeoLocationService;
    $result = $service->lookup('8.8.8.8');

    expect($result['country'])->toBeNull()
        ->and($result['country_code'])->toBeNull();
});

test('returns empty result for private IPs without hitting the API', function () {
    Http::fake();

    $service = new GeoLocationService;
    $result = $service->lookup('192.168.1.1');

    expect($result['country'])->toBeNull();
    Http::assertNothingSent();
});

test('parses ip-api response correctly', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'country' => 'United States',
            'countryCode' => 'US',
            'regionName' => 'California',
            'city' => 'Mountain View',
            'lat' => 37.4192,
            'lon' => -122.0574,
        ], 200),
    ]);

    $service = new GeoLocationService;
    $result = $service->lookup('8.8.8.8');

    expect($result['country'])->toBe('United States')
        ->and($result['country_code'])->toBe('US')
        ->and($result['city'])->toBe('Mountain View')
        ->and($result['latitude'])->toBe(37.4192);
});

test('caches results so subsequent lookups skip the API', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'country' => 'Germany',
            'countryCode' => 'DE',
        ], 200),
    ]);

    $service = new GeoLocationService;
    $service->lookup('8.8.8.8');
    $service->lookup('8.8.8.8');

    Http::assertSentCount(1);
});

test('returns empty result on ip-api status=fail', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'fail',
            'message' => 'reserved range',
        ], 200),
    ]);

    $service = new GeoLocationService;
    $result = $service->lookup('8.8.8.8');

    expect($result['country'])->toBeNull();
});

test('returns empty result on HTTP error', function () {
    Http::fake([
        'ip-api.com/*' => Http::response('', 500),
    ]);

    $service = new GeoLocationService;
    $result = $service->lookup('8.8.8.8');

    expect($result['country'])->toBeNull();
});

test('parses ipinfo response and splits loc coordinates', function () {
    config()->set('visitor-tracker.geolocation.provider', 'ipinfo');
    Http::fake([
        'ipinfo.io/*' => Http::response([
            'country' => 'US',
            'region' => 'California',
            'city' => 'Mountain View',
            'loc' => '37.4192,-122.0574',
        ], 200),
    ]);

    $service = new GeoLocationService;
    $result = $service->lookup('8.8.8.8');

    expect($result['country_code'])->toBe('US')
        ->and($result['country'])->toBe('United States')
        ->and($result['latitude'])->toBe(37.4192)
        ->and($result['longitude'])->toBe(-122.0574);
});

test('parses ipapi.co response correctly', function () {
    config()->set('visitor-tracker.geolocation.provider', 'ipapi');
    Http::fake([
        'ipapi.co/*' => Http::response([
            'country_name' => 'Japan',
            'country_code' => 'JP',
            'region' => 'Tokyo',
            'city' => 'Tokyo',
            'latitude' => 35.6895,
            'longitude' => 139.6917,
        ], 200),
    ]);

    $service = new GeoLocationService;
    $result = $service->lookup('1.1.1.1');

    expect($result['country'])->toBe('Japan')
        ->and($result['country_code'])->toBe('JP');
});

test('returns empty result on ipapi.co error payload', function () {
    config()->set('visitor-tracker.geolocation.provider', 'ipapi');
    Http::fake([
        'ipapi.co/*' => Http::response([
            'error' => true,
            'reason' => 'rate limited',
        ], 200),
    ]);

    $service = new GeoLocationService;
    $result = $service->lookup('1.1.1.1');

    expect($result['country'])->toBeNull();
});
