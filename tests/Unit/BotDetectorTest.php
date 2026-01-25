<?php

use Ghdj\VisitorTracker\Services\BotDetector;

beforeEach(function () {
    $this->detector = new BotDetector;
});

describe('bot detection', function () {
    it('detects Googlebot', function () {
        $ua = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

        expect($this->detector->isBot($ua))->toBeTrue();
        expect($this->detector->getBotName($ua))->toBe('Googlebot');
        expect($this->detector->isSearchEngine($ua))->toBeTrue();
    });

    it('detects Bingbot', function () {
        $ua = 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)';

        expect($this->detector->isBot($ua))->toBeTrue();
        expect($this->detector->getBotName($ua))->toBe('Bingbot');
        expect($this->detector->isSearchEngine($ua))->toBeTrue();
    });

    it('detects Facebook bot', function () {
        $ua = 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)';

        expect($this->detector->isBot($ua))->toBeTrue();
        expect($this->detector->getBotName($ua))->toBe('Facebook');
        expect($this->detector->isSocialMediaBot($ua))->toBeTrue();
    });

    it('detects Twitter bot', function () {
        $ua = 'Twitterbot/1.0';

        expect($this->detector->isBot($ua))->toBeTrue();
        expect($this->detector->isSocialMediaBot($ua))->toBeTrue();
    });

    it('detects AI bots', function () {
        $ua = 'GPTBot/1.0 (+https://openai.com/gptbot)';

        expect($this->detector->isBot($ua))->toBeTrue();
        expect($this->detector->isAiBot($ua))->toBeTrue();
    });

    it('detects Claude bot', function () {
        $ua = 'ClaudeBot/1.0';

        expect($this->detector->isBot($ua))->toBeTrue();
        expect($this->detector->getBotName($ua))->toBe('ClaudeBot');
        expect($this->detector->isAiBot($ua))->toBeTrue();
    });

    it('detects curl', function () {
        $ua = 'curl/7.64.1';

        expect($this->detector->isBot($ua))->toBeTrue();
        expect($this->detector->getBotName($ua))->toBe('cURL');
        expect($this->detector->isHttpClient($ua))->toBeTrue();
    });

    it('detects Python requests', function () {
        $ua = 'python-requests/2.28.0';

        expect($this->detector->isBot($ua))->toBeTrue();
        expect($this->detector->isHttpClient($ua))->toBeTrue();
    });

    it('detects Postman', function () {
        $ua = 'PostmanRuntime/7.32.1';

        expect($this->detector->isBot($ua))->toBeTrue();
        expect($this->detector->getBotName($ua))->toBe('Postman');
    });

    it('detects monitoring bots', function () {
        $ua = 'UptimeRobot/2.0';

        expect($this->detector->isBot($ua))->toBeTrue();
        expect($this->detector->isMonitoringBot($ua))->toBeTrue();
    });
});

describe('non-bot detection', function () {
    it('does not flag Chrome as bot', function () {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        expect($this->detector->isBot($ua))->toBeFalse();
        expect($this->detector->getBotName($ua))->toBeNull();
    });

    it('does not flag Firefox as bot', function () {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0';

        expect($this->detector->isBot($ua))->toBeFalse();
    });

    it('does not flag Safari as bot', function () {
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15';

        expect($this->detector->isBot($ua))->toBeFalse();
    });

    it('does not flag mobile browsers as bot', function () {
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1';

        expect($this->detector->isBot($ua))->toBeFalse();
    });
});

describe('edge cases', function () {
    it('treats empty user agent as bot', function () {
        expect($this->detector->isBot(null))->toBeTrue();
        expect($this->detector->isBot(''))->toBeTrue();
    });

    it('treats very short user agent as bot', function () {
        expect($this->detector->isBot('short'))->toBeTrue();
    });
});

describe('bot categories', function () {
    it('categorizes search engine bots', function () {
        $ua = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

        expect($this->detector->getBotCategory($ua))->toBe('search_engine');
    });

    it('categorizes social media bots', function () {
        $ua = 'facebookexternalhit/1.1';

        expect($this->detector->getBotCategory($ua))->toBe('social_media');
    });

    it('categorizes AI bots', function () {
        $ua = 'GPTBot/1.0';

        expect($this->detector->getBotCategory($ua))->toBe('ai_bot');
    });

    it('categorizes monitoring bots', function () {
        $ua = 'UptimeRobot/2.0';

        expect($this->detector->getBotCategory($ua))->toBe('monitoring');
    });

    it('categorizes HTTP clients', function () {
        $ua = 'curl/7.64.1';

        expect($this->detector->getBotCategory($ua))->toBe('http_client');
    });

    it('returns null for non-bots', function () {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0';

        expect($this->detector->getBotCategory($ua))->toBeNull();
    });
});

describe('custom patterns', function () {
    it('allows adding custom bot patterns', function () {
        $this->detector->addPatterns(['mycustombot']);

        expect($this->detector->isBot('MyCustomBot/1.0'))->toBeTrue();
    });

    it('allows adding custom bot names', function () {
        $this->detector->addPatterns(['specialbot']);
        $this->detector->addBotNames(['specialbot' => 'Special Bot']);

        expect($this->detector->getBotName('SpecialBot/1.0'))->toBe('Special Bot');
    });
});
