<?php

declare(strict_types=1);

namespace Ghdj\VisitorTracker\Http\Controllers;

use Ghdj\VisitorTracker\Services\StatisticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Dashboard controller for visitor statistics.
 *
 * Note: Authorization is handled by the AuthorizeDashboard middleware.
 */
class DashboardController extends Controller
{
    /**
     * Allowed values for the dashboard period filter.
     */
    protected const PERIODS = ['today', 'week', 'month', 'year'];

    public function __construct(
        protected StatisticsService $stats
    ) {}

    /**
     * Display the visitor statistics dashboard.
     */
    public function index(Request $request): View
    {
        $period = $request->query('period');
        if (! is_string($period) || ! in_array($period, self::PERIODS, true)) {
            $period = 'week';
        }

        // Calendar boundaries (not rolling windows) so the labels "This Week" etc.
        // are accurate and the cache keys derived from $since stay stable.
        $since = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
        };

        return view('visitor-tracker::dashboard', [
            'summary' => [
                'total_visitors' => $this->stats->totalVisitors(),
                'total_page_views' => $this->stats->totalPageViews(),
                'online_visitors' => $this->stats->onlineVisitors(),
            ],
            'period' => $period,
            'periodVisitors' => $this->stats->activeVisitors($since),
            'periodPageViews' => $this->stats->totalPageViews($since),
            'browsers' => $this->stats->browserStats(10, $since),
            'platforms' => $this->stats->platformStats(10, $since),
            'devices' => $this->stats->deviceStats($since),
            'countries' => $this->stats->countryStats(10, $since),
            'topPages' => $this->stats->mostVisitedPages(10, $since),
            'topReferrers' => $this->stats->topReferrers(10, $since),
            'visitorsByDay' => $this->stats->visitorsByPeriod('day', 30),
            'pageViewsByDay' => $this->stats->pageViewsByPeriod('day', 30),
            'bounceRate' => $this->stats->bounceRate($since),
            'avgPagesPerVisit' => $this->stats->averagePagesPerVisit($since),
        ]);
    }

    /**
     * Get statistics as JSON (for AJAX updates).
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'summary' => $this->stats->summary(),
            'online' => $this->stats->onlineVisitors(),
        ]);
    }
}
