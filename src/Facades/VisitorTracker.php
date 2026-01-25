<?php

declare(strict_types=1);

namespace Ghdj\VisitorTracker\Facades;

use Ghdj\VisitorTracker\Models\Visit;
use Ghdj\VisitorTracker\Models\Visitor;
use Ghdj\VisitorTracker\Services\BotDetector;
use Ghdj\VisitorTracker\Services\GeoLocationService;
use Ghdj\VisitorTracker\Services\StatisticsService;
use Ghdj\VisitorTracker\Services\UserAgentParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * VisitorTracker Facade.
 *
 * @method static Visit|null track(?Request $request = null, ?int $statusCode = null)
 * @method static Visit|null trackFromData(array $data)
 * @method static Visitor|null visitor()
 * @method static Visit|null visit()
 * @method static StatisticsService stats()
 * @method static UserAgentParser parser()
 * @method static BotDetector botDetector()
 * @method static GeoLocationService geoService()
 * @method static bool isBot(?string $userAgent = null)
 * @method static array parseUserAgent(?string $userAgent = null)
 * @method static int totalVisitors()
 * @method static int totalPageViews()
 * @method static int onlineVisitors(?int $minutes = null)
 * @method static int todayVisitors()
 * @method static int todayPageViews()
 * @method static bool isEnabled()
 * @method static bool isGdprSafeMode()
 * @method static void enable()
 * @method static void disable()
 *
 * @see \Ghdj\VisitorTracker\VisitorTracker
 */
class VisitorTracker extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Ghdj\VisitorTracker\VisitorTracker::class;
    }
}
