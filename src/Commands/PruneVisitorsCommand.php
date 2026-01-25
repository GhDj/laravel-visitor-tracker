<?php

declare(strict_types=1);

namespace Ghdj\VisitorTracker\Commands;

use Ghdj\VisitorTracker\Models\Visit;
use Ghdj\VisitorTracker\Models\Visitor;
use Illuminate\Console\Command;

/**
 * Command to prune old visitor data.
 */
class PruneVisitorsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'visitor-tracker:prune
                            {--days= : Number of days to retain data (default from config)}
                            {--visits-only : Only prune visits, keep visitor records}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Prune old visitor tracking data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('visitor-tracker.retention.days', 90));
        $visitsOnly = (bool) $this->option('visits-only');
        $dryRun = (bool) $this->option('dry-run');

        if ($days <= 0) {
            $this->error('Retention days must be greater than 0.');

            return self::FAILURE;
        }

        $cutoffDate = now()->subDays($days);

        $this->info("Pruning data older than {$days} days (before {$cutoffDate->toDateTimeString()})");

        if ($dryRun) {
            $this->warn('DRY RUN - No data will be deleted');
        }

        // Count records to be deleted
        $visitsCount = Visit::where('created_at', '<', $cutoffDate)->count();
        $visitorsCount = 0;

        if (! $visitsOnly) {
            $visitorsCount = Visitor::where('created_at', '<', $cutoffDate)
                ->whereDoesntHave('visits', function ($query) use ($cutoffDate) {
                    $query->where('created_at', '>=', $cutoffDate);
                })
                ->count();
        }

        $this->info("Found {$visitsCount} visits to prune");
        if (! $visitsOnly) {
            $this->info("Found {$visitorsCount} visitors to prune");
        }

        if ($dryRun) {
            $this->info('Dry run complete. No records were deleted.');

            return self::SUCCESS;
        }

        if ($visitsCount === 0 && $visitorsCount === 0) {
            $this->info('No records to prune.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Do you want to proceed with deletion?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        // Delete visits first (due to foreign key constraint)
        $deletedVisits = Visit::where('created_at', '<', $cutoffDate)->delete();
        $this->info("Deleted {$deletedVisits} visits.");

        // Delete orphaned visitors
        if (! $visitsOnly) {
            $deletedVisitors = Visitor::where('created_at', '<', $cutoffDate)
                ->whereDoesntHave('visits')
                ->delete();
            $this->info("Deleted {$deletedVisitors} visitors.");
        }

        $this->info('Pruning complete!');

        return self::SUCCESS;
    }
}
