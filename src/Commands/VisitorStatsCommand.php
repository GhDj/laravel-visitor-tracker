<?php

declare(strict_types=1);

namespace Ghdj\VisitorTracker\Commands;

use Ghdj\VisitorTracker\Services\StatisticsService;
use Illuminate\Console\Command;

/**
 * Command to display visitor statistics.
 */
class VisitorStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'visitor-tracker:stats
                            {--detailed : Show detailed statistics}
                            {--json : Output as JSON}';

    /**
     * The console command description.
     */
    protected $description = 'Display visitor tracking statistics';

    public function __construct(
        protected StatisticsService $stats
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $detailed = (bool) $this->option('detailed');
        $json = (bool) $this->option('json');

        if ($json) {
            $this->outputJson($detailed);

            return self::SUCCESS;
        }

        $this->displayStats($detailed);

        return self::SUCCESS;
    }

    /**
     * Display statistics in table format.
     */
    protected function displayStats(bool $detailed): void
    {
        $this->newLine();
        $this->info('=== Visitor Tracker Statistics ===');
        $this->newLine();

        // Summary
        $summary = $this->stats->summary();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Visitors', number_format($summary['total_visitors'])],
                ['Total Page Views', number_format($summary['total_page_views'])],
                ['Today\'s Visitors', number_format($summary['today_visitors'])],
                ['Today\'s Page Views', number_format($summary['today_page_views'])],
                ['Online Now', number_format($summary['online_visitors'])],
                ['This Week', number_format($summary['this_week_visitors'])],
                ['This Month', number_format($summary['this_month_visitors'])],
            ]
        );

        if (! $detailed) {
            return;
        }

        $this->newLine();
        $this->info('=== Browser Statistics ===');
        $browsers = $this->stats->browserStats(5);
        if ($browsers->isNotEmpty()) {
            $this->table(
                ['Browser', 'Visitors'],
                $browsers->map(fn ($b) => [$b->browser, number_format($b->count)])->toArray()
            );
        }

        $this->newLine();
        $this->info('=== Platform Statistics ===');
        $platforms = $this->stats->platformStats(5);
        if ($platforms->isNotEmpty()) {
            $this->table(
                ['Platform', 'Visitors'],
                $platforms->map(fn ($p) => [$p->platform, number_format($p->count)])->toArray()
            );
        }

        $this->newLine();
        $this->info('=== Device Statistics ===');
        $devices = $this->stats->deviceStats();
        if ($devices->isNotEmpty()) {
            $this->table(
                ['Device Type', 'Visitors'],
                $devices->map(fn ($d) => [ucfirst($d->device_type), number_format($d->count)])->toArray()
            );
        }

        $this->newLine();
        $this->info('=== Top Pages ===');
        $pages = $this->stats->mostVisitedPages(5);
        if ($pages->isNotEmpty()) {
            $this->table(
                ['Path', 'Views', 'Unique Visitors'],
                $pages->map(fn ($p) => [
                    Str($p->path)->limit(50),
                    number_format($p->visits),
                    number_format($p->unique_visitors),
                ])->toArray()
            );
        }

        $this->newLine();
        $this->info('=== Country Statistics ===');
        $countries = $this->stats->countryStats(5);
        if ($countries->isNotEmpty()) {
            $this->table(
                ['Country', 'Visitors'],
                $countries->map(fn ($c) => [$c->country ?? $c->country_code, number_format($c->count)])->toArray()
            );
        }

        // Additional metrics
        $this->newLine();
        $this->info('=== Performance Metrics ===');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Bounce Rate', $this->stats->bounceRate().'%'],
                ['Avg. Pages/Visit', $this->stats->averagePagesPerVisit()],
            ]
        );
    }

    /**
     * Output statistics as JSON.
     */
    protected function outputJson(bool $detailed): void
    {
        if ($detailed) {
            $data = $this->stats->detailed();
            $data['bounce_rate'] = $this->stats->bounceRate();
            $data['avg_pages_per_visit'] = $this->stats->averagePagesPerVisit();
        } else {
            $data = $this->stats->summary();
        }

        $json = json_encode($data, JSON_PRETTY_PRINT);
        $this->line($json !== false ? $json : '{}');
    }
}
