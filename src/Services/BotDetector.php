<?php

declare(strict_types=1);

namespace Ghdj\VisitorTracker\Services;

/**
 * Native Bot Detector - No external dependencies.
 *
 * Detects bots and crawlers by analyzing user agent strings.
 */
class BotDetector
{
    /**
     * Known bot user agent patterns.
     *
     * @var array<string>
     */
    protected array $botPatterns = [
        // Search Engine Bots
        'googlebot',
        'google-inspectiontool',
        'googleother',
        'google-extended',
        'bingbot',
        'bingpreview',
        'slurp',
        'duckduckbot',
        'baiduspider',
        'yandexbot',
        'yandexmobilebot',
        'sogou',
        'exabot',
        'seznambot',
        'mojeekbot',
        'petalbot',
        'qwantify',

        // Social Media Bots
        'facebookexternalhit',
        'facebookcatalog',
        'twitterbot',
        'linkedinbot',
        'pinterest',
        'pinterestbot',
        'slackbot',
        'slack-imgproxy',
        'telegrambot',
        'whatsapp',
        'discordbot',
        'skypeuripreview',
        'viberuripreview',
        'redditbot',
        'tumblr',

        // SEO & Analytics Tools
        'ahrefs',
        'ahrefsbot',
        'semrush',
        'semrushbot',
        'moz.com',
        'rogerbot',
        'dotbot',
        'mj12bot',
        'majestic',
        'screaming frog',
        'screamingfrog',
        'sistrix',
        'seokicks',
        'seostar',
        'serpstatbot',
        'dataforseo',

        // Monitoring & Uptime Bots
        'uptimerobot',
        'pingdom',
        'site24x7',
        'statuscake',
        'newrelic',
        'datadog',
        'dynatrace',
        'gtmetrix',
        'pagespeed',
        'webpagetest',
        'monitis',

        // Feed Readers
        'feedly',
        'feedfetcher',
        'feedburner',
        'feedvalidator',
        'newsblur',
        'inoreader',
        'netvibes',

        // AI & LLM Bots
        'gptbot',
        'chatgpt-user',
        'claudebot',
        'claude-web',
        'anthropic-ai',
        'ccbot',
        'cohere-ai',
        'perplexitybot',
        'youbot',
        'omgili',
        'omgilibot',
        'diffbot',
        'applebot-extended',

        // Archive & Research Bots
        'archive.org_bot',
        'ia_archiver',
        'wayback',
        'commoncrawl',
        'ccbot',

        // Generic Bot Patterns (keep these last)
        'bot',
        'spider',
        'crawler',
        'scraper',
        'fetcher',
        'slurper',
        'archiver',
        'indexer',

        // HTTP Clients & Development Tools
        'curl',
        'wget',
        'httpie',
        'python-requests',
        'python-urllib',
        'python/',
        'java/',
        'perl/',
        'ruby/',
        'php/',
        'httpclient',
        'http_request',
        'libwww',
        'lwp-',
        'okhttp',
        'go-http-client',
        'node-fetch',
        'axios',
        'got/',
        'undici',
        'postman',
        'insomnia',
        'paw/',
        'httpx',
        'aiohttp',

        // Other Known Bots
        'applebot',
        'bytespider',
        'bytedance',
        'yisouspider',
        'headlesschrome',
        'phantomjs',
        'slimerjs',
        'splash',
        'prerender',
        'rendertron',
        'lighthouse',
        'speed insights',
        'chrome-lighthouse',
    ];

    /**
     * Bot names mapping for identification.
     *
     * @var array<string, string>
     */
    protected array $botNames = [
        'googlebot' => 'Googlebot',
        'google-inspectiontool' => 'Google Inspection Tool',
        'bingbot' => 'Bingbot',
        'slurp' => 'Yahoo Slurp',
        'duckduckbot' => 'DuckDuckBot',
        'baiduspider' => 'Baidu Spider',
        'yandexbot' => 'YandexBot',
        'facebookexternalhit' => 'Facebook',
        'twitterbot' => 'Twitter',
        'linkedinbot' => 'LinkedIn',
        'pinterest' => 'Pinterest',
        'slackbot' => 'Slack',
        'telegrambot' => 'Telegram',
        'whatsapp' => 'WhatsApp',
        'discordbot' => 'Discord',
        'ahrefs' => 'Ahrefs',
        'semrush' => 'SEMrush',
        'uptimerobot' => 'UptimeRobot',
        'pingdom' => 'Pingdom',
        'applebot' => 'Applebot',
        'gptbot' => 'GPTBot',
        'chatgpt-user' => 'ChatGPT',
        'claudebot' => 'ClaudeBot',
        'claude-web' => 'Claude Web',
        'anthropic-ai' => 'Anthropic AI',
        'ccbot' => 'Common Crawl',
        'petalbot' => 'PetalBot',
        'bytespider' => 'ByteSpider',
        'mj12bot' => 'Majestic',
        'dotbot' => 'DotBot',
        'rogerbot' => 'Moz (Roger)',
        'moz.com' => 'Moz',
        'curl' => 'cURL',
        'wget' => 'Wget',
        'postman' => 'Postman',
        'python-requests' => 'Python Requests',
    ];

    /**
     * Search engine bot patterns.
     *
     * @var array<string>
     */
    protected array $searchEngineBots = [
        'googlebot',
        'bingbot',
        'slurp',
        'duckduckbot',
        'baiduspider',
        'yandexbot',
        'sogou',
        'seznambot',
        'applebot',
    ];

    /**
     * Social media bot patterns.
     *
     * @var array<string>
     */
    protected array $socialMediaBots = [
        'facebookexternalhit',
        'facebookcatalog',
        'twitterbot',
        'linkedinbot',
        'pinterest',
        'slackbot',
        'telegrambot',
        'whatsapp',
        'discordbot',
        'redditbot',
    ];

    /**
     * AI/LLM bot patterns.
     *
     * @var array<string>
     */
    protected array $aiBots = [
        'gptbot',
        'chatgpt-user',
        'claudebot',
        'claude-web',
        'anthropic-ai',
        'ccbot',
        'cohere-ai',
        'perplexitybot',
    ];

    /**
     * Check if the user agent belongs to a bot.
     */
    public function isBot(?string $userAgent): bool
    {
        if (! $userAgent) {
            return true; // Empty user agents are often bots
        }

        // Very short user agents are suspicious
        if (strlen($userAgent) < 20) {
            return true;
        }

        // Merge additional patterns from config
        $additionalPatterns = config('visitor-tracker.bots.additional_patterns', []);
        $patterns = array_merge($this->botPatterns, $additionalPatterns);

        $userAgentLower = strtolower($userAgent);

        foreach ($patterns as $pattern) {
            if (str_contains($userAgentLower, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the bot name if detected.
     */
    public function getBotName(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return 'Unknown';
        }

        $userAgentLower = strtolower($userAgent);

        foreach ($this->botNames as $pattern => $name) {
            if (str_contains($userAgentLower, $pattern)) {
                return $name;
            }
        }

        // Check for generic patterns
        if ($this->isBot($userAgent)) {
            return 'Unknown Bot';
        }

        return null;
    }

    /**
     * Get the bot category.
     */
    public function getBotCategory(?string $userAgent): ?string
    {
        if (! $userAgent || ! $this->isBot($userAgent)) {
            return null;
        }

        if ($this->isSearchEngine($userAgent)) {
            return 'search_engine';
        }

        if ($this->isSocialMediaBot($userAgent)) {
            return 'social_media';
        }

        if ($this->isAiBot($userAgent)) {
            return 'ai_bot';
        }

        if ($this->isMonitoringBot($userAgent)) {
            return 'monitoring';
        }

        if ($this->isHttpClient($userAgent)) {
            return 'http_client';
        }

        return 'other';
    }

    /**
     * Check if the user agent is a search engine bot.
     */
    public function isSearchEngine(?string $userAgent): bool
    {
        if (! $userAgent) {
            return false;
        }

        $userAgentLower = strtolower($userAgent);

        foreach ($this->searchEngineBots as $bot) {
            if (str_contains($userAgentLower, $bot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user agent is a social media bot.
     */
    public function isSocialMediaBot(?string $userAgent): bool
    {
        if (! $userAgent) {
            return false;
        }

        $userAgentLower = strtolower($userAgent);

        foreach ($this->socialMediaBots as $bot) {
            if (str_contains($userAgentLower, $bot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user agent is an AI/LLM bot.
     */
    public function isAiBot(?string $userAgent): bool
    {
        if (! $userAgent) {
            return false;
        }

        $userAgentLower = strtolower($userAgent);

        foreach ($this->aiBots as $bot) {
            if (str_contains($userAgentLower, $bot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user agent is a monitoring/uptime bot.
     */
    public function isMonitoringBot(?string $userAgent): bool
    {
        if (! $userAgent) {
            return false;
        }

        $monitoringBots = [
            'uptimerobot', 'pingdom', 'site24x7', 'statuscake',
            'newrelic', 'datadog', 'dynatrace', 'gtmetrix',
        ];

        $userAgentLower = strtolower($userAgent);

        foreach ($monitoringBots as $bot) {
            if (str_contains($userAgentLower, $bot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user agent is an HTTP client/development tool.
     */
    public function isHttpClient(?string $userAgent): bool
    {
        if (! $userAgent) {
            return false;
        }

        $httpClients = [
            'curl', 'wget', 'httpie', 'python-requests', 'python-urllib',
            'java/', 'httpclient', 'okhttp', 'go-http-client', 'node-fetch',
            'axios', 'postman', 'insomnia',
        ];

        $userAgentLower = strtolower($userAgent);

        foreach ($httpClients as $client) {
            if (str_contains($userAgentLower, $client)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add custom bot patterns.
     *
     * @param  array<string>  $patterns
     */
    public function addPatterns(array $patterns): self
    {
        $this->botPatterns = array_merge($this->botPatterns, $patterns);

        return $this;
    }

    /**
     * Add custom bot names.
     *
     * @param  array<string, string>  $names
     */
    public function addBotNames(array $names): self
    {
        $this->botNames = array_merge($this->botNames, $names);

        return $this;
    }

    /**
     * Get all bot patterns.
     *
     * @return array<string>
     */
    public function getPatterns(): array
    {
        return $this->botPatterns;
    }

    /**
     * Get all bot names.
     *
     * @return array<string, string>
     */
    public function getBotNames(): array
    {
        return $this->botNames;
    }
}
