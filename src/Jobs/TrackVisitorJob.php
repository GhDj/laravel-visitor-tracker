<?php

declare(strict_types=1);

namespace Ghdj\VisitorTracker\Jobs;

use Ghdj\VisitorTracker\VisitorTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job for asynchronous visitor tracking.
 */
class TrackVisitorJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 10;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $trackingData
     */
    public function __construct(
        protected array $trackingData
    ) {
        $this->onConnection(config('visitor-tracker.queue.connection', 'default'));
        $this->onQueue(config('visitor-tracker.queue.queue', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(VisitorTracker $tracker): void
    {
        $tracker->trackFromData($this->trackingData);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return ['visitor-tracker', 'tracking'];
    }
}
