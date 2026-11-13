<?php

declare(strict_types=1);

namespace Ghdj\VisitorTracker\Services;

use DateTimeInterface;
use Ghdj\VisitorTracker\Models\Visit;
use Ghdj\VisitorTracker\Models\Visitor;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Statistics service for visitor analytics.
 */
class StatisticsService
{
    /**
     * Get total number of unique visitors.
     */
    public function totalVisitors(?DateTimeInterface $since = null): int
    {
        return $this->cached('total_visitors_'.($since?->getTimestamp() ?? 'all'), function () use ($since) {
            $query = Visitor::humans();

            if ($since) {
                $query->where('created_at', '>=', $since);
            }

            return $query->count();
        });
    }

    /**
     * Get total number of page views.
     */
    public function totalPageViews(?DateTimeInterface $since = null): int
    {
        return $this->cached('total_page_views_'.($since?->getTimestamp() ?? 'all'), function () use ($since) {
            $query = Visit::humans();

            if ($since) {
                $query->where('created_at', '>=', $since);
            }

            return $query->count();
        });
    }

    /**
     * Get number of distinct visitors who viewed at least one page, optionally
     * since a given date. Unlike totalVisitors($since), this includes returning
     * visitors whose visitor row was created before $since.
     */
    public function activeVisitors(?DateTimeInterface $since = null): int
    {
        return $this->cached('active_visitors_'.($since?->getTimestamp() ?? 'all'), function () use ($since) {
            $visitsTable = config('visitor-tracker.tables.visits', 'visits');

            return $this->humanVisits($since)
                ->distinct()
                ->count("{$visitsTable}.visitor_id");
        });
    }

    /**
     * Get number of visitors today.
     */
    public function todayVisitors(): int
    {
        return $this->cached('today_visitors', function () {
            return Visitor::humans()
                ->whereDate('created_at', today())
                ->count();
        }, 5); // Cache for 5 minutes
    }

    /**
     * Get number of page views today.
     */
    public function todayPageViews(): int
    {
        return $this->cached('today_page_views', function () {
            return Visit::humans()
                ->whereDate('created_at', today())
                ->count();
        }, 5);
    }

    /**
     * Get number of currently online visitors.
     */
    public function onlineVisitors(?int $minutes = null): int
    {
        $minutes = $minutes ?? (int) config('visitor-tracker.online_threshold', 5);

        // Don't cache this, it needs to be real-time
        return Visitor::humans()
            ->online($minutes)
            ->count();
    }

    /**
     * Get visitors by time period.
     */
    public function visitorsByPeriod(string $period = 'day', int $limit = 30): Collection
    {
        return $this->cached("visitors_by_{$period}_{$limit}", function () use ($period, $limit) {
            $visitorsTable = config('visitor-tracker.tables.visitors', 'visitors');
            $dateExpression = $this->getDateExpression('created_at', $period);

            return DB::table($visitorsTable)
                ->select(DB::raw("{$dateExpression} as period"))
                ->selectRaw('COUNT(*) as count')
                ->where('is_bot', false)
                ->groupBy('period')
                ->orderByDesc('period')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get page views by time period.
     */
    public function pageViewsByPeriod(string $period = 'day', int $limit = 30): Collection
    {
        return $this->cached("page_views_by_{$period}_{$limit}", function () use ($period, $limit) {
            $visitsTable = config('visitor-tracker.tables.visits', 'visits');
            $visitorsTable = config('visitor-tracker.tables.visitors', 'visitors');
            $dateExpression = $this->getDateExpression("{$visitsTable}.created_at", $period);

            return DB::table($visitsTable)
                ->join($visitorsTable, "{$visitsTable}.visitor_id", '=', "{$visitorsTable}.id")
                ->select(DB::raw("{$dateExpression} as period"))
                ->selectRaw('COUNT(*) as count')
                ->where("{$visitorsTable}.is_bot", false)
                ->groupBy('period')
                ->orderByDesc('period')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get most visited pages.
     *
     * @return Collection<int, object>
     */
    public function mostVisitedPages(int $limit = 10, ?DateTimeInterface $since = null): Collection
    {
        $cacheKey = 'most_visited_pages_'.$limit.'_'.($since?->getTimestamp() ?? 'all');

        return $this->cached($cacheKey, function () use ($limit, $since) {
            $visitsTable = config('visitor-tracker.tables.visits', 'visits');
            $visitorsTable = config('visitor-tracker.tables.visitors', 'visitors');

            $query = DB::table($visitsTable)
                ->join($visitorsTable, "{$visitsTable}.visitor_id", '=', "{$visitorsTable}.id")
                ->select("{$visitsTable}.path")
                ->selectRaw('COUNT(*) as visits')
                ->selectRaw('COUNT(DISTINCT visitor_id) as unique_visitors')
                ->where("{$visitorsTable}.is_bot", false);

            if ($since) {
                $query->where("{$visitsTable}.created_at", '>=', $since);
            }

            return $query->groupBy("{$visitsTable}.path")
                ->orderByDesc('visits')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get top referrers.
     *
     * @return Collection<int, object>
     */
    public function topReferrers(int $limit = 10, ?DateTimeInterface $since = null): Collection
    {
        $cacheKey = 'top_referrers_'.$limit.'_'.($since?->getTimestamp() ?? 'all');

        return $this->cached($cacheKey, function () use ($limit, $since) {
            $visitsTable = config('visitor-tracker.tables.visits', 'visits');
            $visitorsTable = config('visitor-tracker.tables.visitors', 'visitors');

            $query = DB::table($visitsTable)
                ->join($visitorsTable, "{$visitsTable}.visitor_id", '=', "{$visitorsTable}.id")
                ->select("{$visitsTable}.referrer")
                ->selectRaw('COUNT(*) as visits')
                ->selectRaw('COUNT(DISTINCT visitor_id) as unique_visitors')
                ->where("{$visitorsTable}.is_bot", false)
                ->whereNotNull("{$visitsTable}.referrer")
                ->where("{$visitsTable}.referrer", '!=', '');

            if ($since) {
                $query->where("{$visitsTable}.created_at", '>=', $since);
            }

            return $query->groupBy("{$visitsTable}.referrer")
                ->orderByDesc('visits')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get browser statistics.
     *
     * @return Collection<int, object>
     */
    public function browserStats(int $limit = 10, ?DateTimeInterface $since = null): Collection
    {
        return $this->cached("browser_stats_{$limit}".$this->sinceSuffix($since), function () use ($limit, $since) {
            return $this->activeHumanVisitors($since)
                ->select('browser')
                ->selectRaw('COUNT(*) as count')
                ->whereNotNull('browser')
                ->groupBy('browser')
                ->orderByDesc('count')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get platform/OS statistics.
     *
     * @return Collection<int, object>
     */
    public function platformStats(int $limit = 10, ?DateTimeInterface $since = null): Collection
    {
        return $this->cached("platform_stats_{$limit}".$this->sinceSuffix($since), function () use ($limit, $since) {
            return $this->activeHumanVisitors($since)
                ->select('platform')
                ->selectRaw('COUNT(*) as count')
                ->whereNotNull('platform')
                ->groupBy('platform')
                ->orderByDesc('count')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get device type statistics.
     *
     * @return Collection<int, object>
     */
    public function deviceStats(?DateTimeInterface $since = null): Collection
    {
        return $this->cached('device_stats'.$this->sinceSuffix($since), function () use ($since) {
            return $this->activeHumanVisitors($since)
                ->select('device_type')
                ->selectRaw('COUNT(*) as count')
                ->whereNotNull('device_type')
                ->groupBy('device_type')
                ->orderByDesc('count')
                ->get();
        });
    }

    /**
     * Get geographic distribution statistics.
     *
     * @return Collection<int, object>
     */
    public function countryStats(int $limit = 10, ?DateTimeInterface $since = null): Collection
    {
        return $this->cached("country_stats_{$limit}".$this->sinceSuffix($since), function () use ($limit, $since) {
            return $this->activeHumanVisitors($since)
                ->select('country', 'country_code')
                ->selectRaw('COUNT(*) as count')
                ->whereNotNull('country_code')
                ->groupBy('country', 'country_code')
                ->orderByDesc('count')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get a summary of all statistics.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'total_visitors' => $this->totalVisitors(),
            'total_page_views' => $this->totalPageViews(),
            'today_visitors' => $this->todayVisitors(),
            'today_page_views' => $this->todayPageViews(),
            'online_visitors' => $this->onlineVisitors(),
            'this_week_visitors' => $this->totalVisitors(now()->startOfWeek()),
            'this_month_visitors' => $this->totalVisitors(now()->startOfMonth()),
        ];
    }

    /**
     * Get detailed statistics.
     *
     * @return array<string, mixed>
     */
    public function detailed(): array
    {
        return [
            'summary' => $this->summary(),
            'browsers' => $this->browserStats(),
            'platforms' => $this->platformStats(),
            'devices' => $this->deviceStats(),
            'countries' => $this->countryStats(),
            'top_pages' => $this->mostVisitedPages(),
            'top_referrers' => $this->topReferrers(),
        ];
    }

    /**
     * Get bounce rate: percentage of visitors (with at least one page view in
     * the period) who viewed exactly one page in that period.
     */
    public function bounceRate(?DateTimeInterface $since = null): float
    {
        $cacheKey = 'bounce_rate_'.($since?->getTimestamp() ?? 'all');

        return $this->cached($cacheKey, function () use ($since) {
            $visitsTable = config('visitor-tracker.tables.visits', 'visits');

            $perVisitor = $this->humanVisits($since)
                ->select("{$visitsTable}.visitor_id")
                ->selectRaw('COUNT(*) as views')
                ->groupBy("{$visitsTable}.visitor_id");

            $row = DB::query()
                ->fromSub($perVisitor, 'per_visitor')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN views = 1 THEN 1 ELSE 0 END) as bounced')
                ->first();

            if ($row === null || (int) $row->total === 0) {
                return 0.0;
            }

            return round(((int) $row->bounced / (int) $row->total) * 100, 2);
        });
    }

    /**
     * Get average pages per visit: page views divided by the distinct visitors
     * who generated them in the same period.
     */
    public function averagePagesPerVisit(?DateTimeInterface $since = null): float
    {
        $cacheKey = 'avg_pages_per_visit_'.($since?->getTimestamp() ?? 'all');

        return $this->cached($cacheKey, function () use ($since) {
            $visitors = $this->activeVisitors($since);

            if ($visitors === 0) {
                return 0.0;
            }

            return round($this->totalPageViews($since) / $visitors, 2);
        });
    }

    /**
     * Query builder for human (non-bot) visits, optionally since a date.
     */
    protected function humanVisits(?DateTimeInterface $since = null): QueryBuilder
    {
        $visitsTable = config('visitor-tracker.tables.visits', 'visits');
        $visitorsTable = config('visitor-tracker.tables.visitors', 'visitors');

        $query = DB::table($visitsTable)
            ->join($visitorsTable, "{$visitsTable}.visitor_id", '=', "{$visitorsTable}.id")
            ->where("{$visitorsTable}.is_bot", false);

        if ($since) {
            $query->where("{$visitsTable}.created_at", '>=', $since);
        }

        return $query;
    }

    /**
     * Eloquent query for human visitors, restricted to those with at least one
     * visit since $since when given.
     *
     * @return EloquentBuilder<Visitor>
     */
    protected function activeHumanVisitors(?DateTimeInterface $since = null): EloquentBuilder
    {
        $query = Visitor::humans();

        if ($since) {
            $visitsTable = config('visitor-tracker.tables.visits', 'visits');

            $query->whereIn('id', function ($sub) use ($visitsTable, $since) {
                $sub->select('visitor_id')
                    ->from($visitsTable)
                    ->where('created_at', '>=', $since);
            });
        }

        return $query;
    }

    /**
     * Cache-key suffix for an optional $since boundary.
     */
    protected function sinceSuffix(?DateTimeInterface $since): string
    {
        return $since ? '_'.$since->getTimestamp() : '';
    }

    /**
     * Clear all cached statistics.
     *
     * Forgets every key the service has ever cached during this app instance's
     * lifetime via the internal key registry. The registry is itself a cache
     * entry so it survives across requests (works on file/database/redis drivers
     * — no cache-tag support required).
     */
    public function clearCache(): void
    {
        $prefix = config('visitor-tracker.cache.prefix', 'visitor_tracker_');
        $registryKey = $prefix.'__keys';

        $keys = Cache::get($registryKey, []);
        if (! is_array($keys)) {
            $keys = [];
        }

        foreach ($keys as $key) {
            if (is_string($key)) {
                Cache::forget($key);
            }
        }

        Cache::forget($registryKey);
    }

    /**
     * Record a cache key in the registry so clearCache() can purge it later.
     */
    protected function registerCacheKey(string $fullKey): void
    {
        $prefix = config('visitor-tracker.cache.prefix', 'visitor_tracker_');
        $registryKey = $prefix.'__keys';

        $keys = Cache::get($registryKey, []);
        if (! is_array($keys)) {
            $keys = [];
        }

        if (in_array($fullKey, $keys, true)) {
            return;
        }

        $keys[] = $fullKey;

        // Registry has no natural TTL; keep it for ~30 days. clearCache() also wipes it.
        Cache::put($registryKey, $keys, now()->addDays(30));
    }

    /**
     * Get database-agnostic date expression for grouping.
     *
     * @throws \InvalidArgumentException when the column name doesn't match a
     *                                   simple identifier (defends raw-SQL interpolation).
     */
    protected function getDateExpression(string $column, string $period): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return match ($period) {
                'hour' => "strftime('%Y-%m-%d %H:00', {$column})",
                'day' => "date({$column})",
                'week' => "strftime('%Y-%W', {$column})",
                'month' => "strftime('%Y-%m', {$column})",
                'year' => "strftime('%Y', {$column})",
                default => "date({$column})",
            };
        }

        // MySQL/MariaDB/PostgreSQL
        $format = match ($period) {
            'hour' => '%Y-%m-%d %H:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%W',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m-%d',
        };

        if ($driver === 'pgsql') {
            $pgFormat = match ($period) {
                'hour' => 'YYYY-MM-DD HH24:00',
                'day' => 'YYYY-MM-DD',
                'week' => 'IYYY-IW',
                'month' => 'YYYY-MM',
                'year' => 'YYYY',
                default => 'YYYY-MM-DD',
            };

            return "to_char({$column}, '{$pgFormat}')";
        }

        return "DATE_FORMAT({$column}, '{$format}')";
    }

    /**
     * Cache helper with configurable TTL.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function cached(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if (! config('visitor-tracker.cache.enabled', true)) {
            return $callback();
        }

        $prefix = config('visitor-tracker.cache.prefix', 'visitor_tracker_');
        $ttl = $ttl ?? (int) config('visitor-tracker.cache.ttl', 60);
        $fullKey = $prefix.$key;

        $this->registerCacheKey($fullKey);

        return Cache::remember($fullKey, now()->addMinutes($ttl), $callback);
    }
}
