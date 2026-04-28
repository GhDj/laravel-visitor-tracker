<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Tracking
    |--------------------------------------------------------------------------
    |
    | Enable or disable visitor tracking globally.
    |
    */
    'enabled' => env('VISITOR_TRACKER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    |
    | Customize the table names used by the package.
    |
    */
    'tables' => [
        'visitors' => 'visitors',
        'visits' => 'visits',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cookie Settings
    |--------------------------------------------------------------------------
    |
    | Settings for the visitor identification cookie.
    |
    */
    'cookie' => [
        'name' => 'visitor_tracker',
        'expiration' => 60 * 24 * 365, // 1 year in minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking Exclusions
    |--------------------------------------------------------------------------
    |
    | Define what should be excluded from tracking.
    |
    */
    'exclude' => [
        /*
        |----------------------------------------------------------------------
        | Excluded Paths
        |----------------------------------------------------------------------
        |
        | Path patterns to exclude from tracking. Supports wildcards (*).
        |
        */
        'paths' => [
            'api/*',
            'admin/*',
            '_debugbar/*',
            'telescope/*',
            'horizon/*',
            'livewire/*',
            'sanctum/*',
        ],

        /*
        |----------------------------------------------------------------------
        | Excluded HTTP Methods
        |----------------------------------------------------------------------
        |
        | HTTP methods to exclude from tracking.
        |
        */
        'methods' => [
            'OPTIONS',
            'HEAD',
        ],

        /*
        |----------------------------------------------------------------------
        | Excluded Status Codes
        |----------------------------------------------------------------------
        |
        | Response status codes to exclude from tracking.
        |
        */
        'status_codes' => [
            301,
            302,
            307,
            308,
            404,
            500,
        ],

        /*
        |----------------------------------------------------------------------
        | Excluded IPs
        |----------------------------------------------------------------------
        |
        | IP addresses to exclude from tracking. Supports CIDR notation.
        |
        */
        'ips' => [
            // '127.0.0.1',
            // '192.168.0.0/16',
            // '2001:db8::/32',  // IPv6 CIDR is supported
        ],

        /*
        |----------------------------------------------------------------------
        | Excluded User Agents
        |----------------------------------------------------------------------
        |
        | User agent patterns to exclude (case-insensitive partial match).
        |
        */
        'user_agents' => [
            // 'curl',
            // 'postman',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bot Detection
    |--------------------------------------------------------------------------
    |
    | Configuration for detecting and handling bots/crawlers.
    |
    */
    'bots' => [
        'track' => false, // Set to true to track bot visits
        'detect' => true, // Enable bot detection

        // Additional bot patterns (merged with built-in patterns)
        'additional_patterns' => [
            // 'custom-bot',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Agent Parser
    |--------------------------------------------------------------------------
    |
    | Configuration for the native user agent parser.
    |
    */
    'parser' => [
        // Additional browser patterns (merged with built-in patterns)
        'additional_browsers' => [
            // 'CustomBrowser' => '/CustomBrowser\/([0-9.]+)/',
        ],

        // Additional platform patterns (merged with built-in patterns)
        'additional_platforms' => [
            // 'CustomOS' => '/CustomOS ([0-9.]+)/',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Geolocation
    |--------------------------------------------------------------------------
    |
    | Configuration for IP geolocation services.
    | Uses Laravel's HTTP client - no external packages required.
    |
    */
    'geolocation' => [
        'enabled' => env('VISITOR_TRACKER_GEOLOCATION', false),
        'provider' => env('VISITOR_TRACKER_GEO_PROVIDER', 'ip-api'), // ip-api, ipinfo, ipapi
        'api_key' => env('VISITOR_TRACKER_GEO_API_KEY'),
        'cache_days' => 7,
        'timeout' => 5, // HTTP request timeout in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy & GDPR
    |--------------------------------------------------------------------------
    |
    | Privacy-related settings for compliance.
    |
    */
    'privacy' => [
        /*
        |----------------------------------------------------------------------
        | GDPR Safe Mode
        |----------------------------------------------------------------------
        |
        | When enabled, the tracker will NOT collect any personal data,
        | allowing you to track anonymous aggregate statistics without
        | requiring user consent under GDPR.
        |
        | This mode disables:
        | - IP address storage (not even anonymized)
        | - User ID association
        | - Persistent cookies (uses session-only identification)
        | - Full user agent storage
        | - Precise geolocation (city, region, coordinates)
        |
        | NOTE: GDPR Safe Mode prefers Laravel's session ID for identification.
        | If sessions are not available on the request, the tracker falls back to
        | a daily hash of the User-Agent string. This is non-persistent across
        | days but groups requests with the same UA within the same calendar day
        | for aggregate counting. Make sure Laravel sessions are configured
        | (web middleware group) for the strongest privacy posture.
        |
        | Only collected:
        | - Page view counts (aggregate)
        | - Browser name (Chrome, Firefox, etc.)
        | - Platform name (Windows, macOS, etc.)
        | - Device type (mobile, desktop, tablet)
        | - Country (broad location only)
        | - Referrer domain
        |
        */
        'gdpr_safe_mode' => env('VISITOR_TRACKER_GDPR_SAFE', false),

        'anonymize_ip' => env('VISITOR_TRACKER_ANONYMIZE_IP', false),
        'respect_dnt' => env('VISITOR_TRACKER_RESPECT_DNT', true), // Do Not Track header
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | How long to keep visitor data. Set to null for indefinite retention.
    |
    */
    'retention' => [
        'days' => env('VISITOR_TRACKER_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Enable queue for async tracking to improve performance.
    |
    */
    'queue' => [
        'enabled' => env('VISITOR_TRACKER_QUEUE', false),
        'connection' => env('VISITOR_TRACKER_QUEUE_CONNECTION', 'default'),
        'queue' => env('VISITOR_TRACKER_QUEUE_NAME', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Cache settings for statistics.
    |
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 60, // Cache TTL in minutes
        'prefix' => 'visitor_tracker_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Online Threshold
    |--------------------------------------------------------------------------
    |
    | Minutes of inactivity before a visitor is considered offline.
    |
    */
    'online_threshold' => 5,

    /*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    |
    | Enable built-in API routes for statistics.
    |
    */
    'api' => [
        'enabled' => false,
        'prefix' => 'api/visitor-tracker',
        'middleware' => ['api', 'auth:sanctum'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    | Enable built-in dashboard routes. Run `php artisan visitor-tracker:install-dashboard`
    | to enable the dashboard and publish the necessary files.
    |
    | IMPORTANT: Always protect your dashboard with at least one of these methods:
    | - token: Secret token for sites without authentication
    | - middleware: Laravel auth middleware for sites with authentication
    | - gate: Laravel Gate for role-based access control
    |
    */
    'dashboard' => [
        'enabled' => false,
        'prefix' => 'admin/visitor-tracker',

        /*
        |----------------------------------------------------------------------
        | Secret Token Authentication
        |----------------------------------------------------------------------
        |
        | For sites WITHOUT user authentication, use a secret token to protect
        | the dashboard. Store this in your .env file (never commit it!).
        |
        | Access via:
        | - Query: /admin/visitor-tracker?token=your-secret-token
        | - Header: X-Visitor-Tracker-Token: your-secret-token
        | - Header: Authorization: Bearer your-secret-token
        |
        */
        'token' => env('VISITOR_TRACKER_TOKEN'),

        /*
        |----------------------------------------------------------------------
        | Middleware
        |----------------------------------------------------------------------
        |
        | For sites WITH user authentication, use Laravel's auth middleware.
        | Remove 'auth' if using token-only authentication.
        |
        */
        'middleware' => ['web'],

        /*
        |----------------------------------------------------------------------
        | Gate Authorization
        |----------------------------------------------------------------------
        |
        | Optional: Define a Laravel Gate for role-based access control.
        | Example: 'view-visitor-stats' - only users who pass this gate can access.
        |
        */
        'gate' => null,
    ],
];
