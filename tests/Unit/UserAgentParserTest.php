<?php

use Ghdj\VisitorTracker\Services\UserAgentParser;

beforeEach(function () {
    $this->parser = new UserAgentParser;
});

describe('browser detection', function () {
    it('detects Chrome', function () {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        expect($this->parser->detectBrowser($ua))->toBe('Chrome');
        expect($this->parser->detectBrowserVersion($ua))->toBe('120.0.0.0');
    });

    it('detects Firefox', function () {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0';

        expect($this->parser->detectBrowser($ua))->toBe('Firefox');
        expect($this->parser->detectBrowserVersion($ua))->toBe('121.0');
    });

    it('detects Safari', function () {
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15';

        expect($this->parser->detectBrowser($ua))->toBe('Safari');
        expect($this->parser->detectBrowserVersion($ua))->toBe('17.2');
    });

    it('detects Edge', function () {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0';

        expect($this->parser->detectBrowser($ua))->toBe('Edge');
        expect($this->parser->detectBrowserVersion($ua))->toBe('120.0.0.0');
    });

    it('returns null for empty user agent', function () {
        expect($this->parser->detectBrowser(null))->toBeNull();
        expect($this->parser->detectBrowser(''))->toBeNull();
    });
});

describe('platform detection', function () {
    it('detects Windows', function () {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

        expect($this->parser->detectPlatform($ua))->toBe('Windows');
        expect($this->parser->detectPlatformVersion($ua))->toBe('10/11');
    });

    it('detects macOS', function () {
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2) AppleWebKit/605.1.15';

        expect($this->parser->detectPlatform($ua))->toBe('macOS');
        expect($this->parser->detectPlatformVersion($ua))->toBe('14.2');
    });

    it('detects iOS', function () {
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15';

        expect($this->parser->detectPlatform($ua))->toBe('iOS');
        expect($this->parser->detectPlatformVersion($ua))->toBe('17.2');
    });

    it('detects Android', function () {
        $ua = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';

        expect($this->parser->detectPlatform($ua))->toBe('Android');
        expect($this->parser->detectPlatformVersion($ua))->toBe('14');
    });
});

describe('device type detection', function () {
    it('detects mobile devices', function () {
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1';

        expect($this->parser->detectDeviceType($ua))->toBe('mobile');
        expect($this->parser->isMobile($ua))->toBeTrue();
        expect($this->parser->isTablet($ua))->toBeFalse();
        expect($this->parser->isDesktop($ua))->toBeFalse();
    });

    it('detects tablets', function () {
        $ua = 'Mozilla/5.0 (iPad; CPU OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1';

        expect($this->parser->detectDeviceType($ua))->toBe('tablet');
        expect($this->parser->isTablet($ua))->toBeTrue();
        expect($this->parser->isMobile($ua))->toBeFalse();
    });

    it('detects desktop devices', function () {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        expect($this->parser->detectDeviceType($ua))->toBe('desktop');
        expect($this->parser->isDesktop($ua))->toBeTrue();
        expect($this->parser->isMobile($ua))->toBeFalse();
        expect($this->parser->isTablet($ua))->toBeFalse();
    });

    it('detects Android tablets', function () {
        $ua = 'Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        expect($this->parser->detectDeviceType($ua))->toBe('tablet');
    });
});

describe('parse method', function () {
    it('returns complete parsed data', function () {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        $result = $this->parser->parse($ua);

        expect($result)->toHaveKeys(['browser', 'browser_version', 'platform', 'platform_version', 'device_type']);
        expect($result['browser'])->toBe('Chrome');
        expect($result['platform'])->toBe('Windows');
        expect($result['device_type'])->toBe('desktop');
    });

    it('handles null user agent', function () {
        $result = $this->parser->parse(null);

        expect($result['browser'])->toBeNull();
        expect($result['platform'])->toBeNull();
        expect($result['device_type'])->toBe('unknown');
    });
});

describe('custom patterns', function () {
    it('allows adding custom browser patterns', function () {
        $this->parser->addBrowserPatterns([
            'CustomBrowser' => '/CustomBrowser\/([0-9.]+)/',
        ]);

        $ua = 'CustomBrowser/1.0.0';

        expect($this->parser->detectBrowser($ua))->toBe('CustomBrowser');
    });
});
