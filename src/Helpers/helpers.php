<?php

declare(strict_types=1);

use Ghdj\VisitorTracker\VisitorTracker;

if (! function_exists('country_flag')) {
    /**
     * Convert country code to flag emoji.
     */
    function country_flag(?string $countryCode): string
    {
        if (! $countryCode || strlen($countryCode) !== 2) {
            return '';
        }

        $code = strtoupper($countryCode);

        return implode('', array_map(
            fn ($char) => mb_chr(ord($char) - ord('A') + 0x1F1E6),
            str_split($code)
        ));
    }
}

if (! function_exists('visitor')) {
    /**
     * Get the visitor tracker instance.
     */
    function visitor(): VisitorTracker
    {
        return app(VisitorTracker::class);
    }
}

if (! function_exists('visitor_stats')) {
    /**
     * Get the statistics service.
     */
    function visitor_stats(): \Ghdj\VisitorTracker\Services\StatisticsService
    {
        return app(VisitorTracker::class)->stats();
    }
}

if (! function_exists('total_visitors')) {
    /**
     * Get total visitors count.
     */
    function total_visitors(): int
    {
        return app(VisitorTracker::class)->totalVisitors();
    }
}

if (! function_exists('total_page_views')) {
    /**
     * Get total page views count.
     */
    function total_page_views(): int
    {
        return app(VisitorTracker::class)->totalPageViews();
    }
}

if (! function_exists('online_visitors')) {
    /**
     * Get online visitors count.
     */
    function online_visitors(?int $minutes = null): int
    {
        return app(VisitorTracker::class)->onlineVisitors($minutes);
    }
}

if (! function_exists('today_visitors')) {
    /**
     * Get today's visitors count.
     */
    function today_visitors(): int
    {
        return app(VisitorTracker::class)->todayVisitors();
    }
}
