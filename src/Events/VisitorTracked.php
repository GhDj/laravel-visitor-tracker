<?php

declare(strict_types=1);

namespace Ghdj\VisitorTracker\Events;

use Ghdj\VisitorTracker\Models\Visit;
use Ghdj\VisitorTracker\Models\Visitor;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a visitor is tracked.
 */
class VisitorTracked
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Visitor $visitor,
        public readonly Visit $visit,
        public readonly bool $isNewVisitor = false
    ) {}
}
