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
    public function __construct(
        protected StatisticsService $stats
    ) {}

    /**
     * Display the visitor statistics dashboard.
     */
    public function index(Request $request): View
    {
        $period = $request->get('period', 'week');
        $since = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            default => now()->subWeek(),
        };

        return view('visitor-tracker::dashboard', [
            'summary' => $this->stats->summary(),
            'period' => $period,
            'browsers' => $this->stats->browserStats(10),
            'platforms' => $this->stats->platformStats(10),
            'devices' => $this->stats->deviceStats(),
            'countries' => $this->stats->countryStats(10),
            'topPages' => $this->stats->mostVisitedPages(10, $since),
            'topReferrers' => $this->stats->topReferrers(10, $since),
            'visitorsByDay' => $this->stats->visitorsByPeriod('day', 30),
            'pageViewsByDay' => $this->stats->pageViewsByPeriod('day', 30),
            'bounceRate' => $this->stats->bounceRate(),
            'avgPagesPerVisit' => $this->stats->averagePagesPerVisit(),
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
