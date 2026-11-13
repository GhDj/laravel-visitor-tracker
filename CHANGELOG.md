# Changelog

All notable changes to `laravel-visitor-tracker` will be documented in this file.

## [1.2.0] - 2026-11-13

### Changed
- **Dashboard periods now use calendar boundaries** — "This Week", "This Month"
  and "This Year" start at `startOfWeek()`, `startOfMonth()` and
  `startOfYear()` instead of rolling 7/30/365-day windows, matching their
  labels and the `this_week_visitors`/`this_month_visitors` values in
  `summary()`.
- `bounceRate()` without a date now counts only visitors with at least one
  recorded page view in its denominator.

### Fixed
- **Bounce rate could exceed 100%** for a period: numerator and denominator now
  count the same population (visitors with at least one page view in the period).
- **Pages per visit was inflated** by returning visitors: it now divides by the
  distinct visitors active in the period.
- **Period "Visitors" card only counted new visitors**: it now counts every
  visitor active in the period, including returning ones.
- **Period stats were never cached**: rolling windows (`now()->subWeek()`)
  produced a new cache key every second and grew the key registry unbounded.
  Periods now use calendar boundaries (`startOfWeek()`, `startOfMonth()`, …),
  matching the "This Week"/"This Month" labels and `summary()`.
- **Browser, platform, device and country panels ignored the period filter**:
  they now reflect visitors active in the selected period. The corresponding
  `StatisticsService` methods accept an optional `$since` argument.
- Dashboard no longer runs unused `summary()` queries.
- README documented a non-existent `visitorsLastDays()` method; replaced with
  working examples.

### Added
- `StatisticsService::activeVisitors(?DateTimeInterface $since)`.

### Security
- **XSS via `?period=`** — the period value was rendered unvalidated into an
  Alpine `x-data` expression. It is now checked against an allow-list (falling
  back to `week`) and emitted with `@js()`. Array values (`?period[]=`) no
  longer cause a 500 error.

## [1.1.1] - 2026-10-03

### Fixed
- **Dashboard period filter ignored** — the summary cards (visitors, page views,
  bounce rate, avg pages/visit) always showed all-time totals regardless of the
  selected period tab (today/week/month/year). They now correctly reflect the
  chosen period, with all-time totals shown as a subtitle.

## [1.1.0] - 2026-04-28

### Fixed
- **IPv6 CIDR exclusions** — `exclude.ips` entries in IPv6 CIDR notation
  (e.g. `2001:db8::/32`) are now matched correctly. Previously they were
  silently ignored because the implementation only used `ip2long()`.
- **Race condition on visitor creation** — `Visitor::firstOrCreate()` is now
  wrapped in a transaction with a recovery path for concurrent inserts that
  hit the `session_id` unique constraint.
- **`StatisticsService::clearCache()` was incomplete** — it only forgot a
  hardcoded list of keys, leaving period-bucketed stats (`visitors_by_*`,
  `most_visited_pages_*`, `bounce_rate_*`, …) stale. The service now keeps
  an internal key registry so `clearCache()` purges every cached statistic
  it has produced. Works on file/database/redis/memcached drivers.

### Security
- **Dashboard misconfiguration guard** — when `dashboard.enabled` is `true`
  the service provider refuses to register routes unless at least one of
  `dashboard.token`, `dashboard.gate`, or an `auth*` middleware entry is
  configured. The check is auto-skipped in the `testing` environment so
  application test suites can exercise the controller in isolation. To
  intentionally bypass elsewhere (e.g. behind a network-level access
  control), set `visitor-tracker.dashboard.allow_unprotected = true`.
- **Defensive validation in `getDateExpression()`** — column names are now
  matched against a strict identifier regex before being interpolated into
  raw SQL.

### Added
- **Indexes migration** (`2026_04_28_000001_add_stats_indexes_to_visitor_tables`)
  — adds indexes on `visitors.created_at`, `(created_at, is_bot)`, `browser`,
  `platform`, `country_code`, and `(visitor_id, created_at)` on `visits` to
  match the actual query patterns used by `StatisticsService`. Run
  `php artisan migrate` after upgrading.
- New tests: `StatisticsServiceTest` (13 cases), `GeoLocationServiceTest`
  (10 cases, with mocked HTTP), `DashboardGuardTest` (7 cases that boot
  the provider in a non-testing env to exercise the protection logic),
  plus IPv6 CIDR coverage in `MiddlewareTest`. Total: 132 passing tests
  (up from 100).

### Documentation
- README: new **Behind a Reverse Proxy / CDN** section explaining why
  Laravel's `TrustProxies` must be configured before tracking middleware.
- README + config: clarified that GDPR Safe Mode falls back to a daily
  User-Agent hash when no Laravel session is available, so users understand
  the actual privacy posture.
- README: documented the new dashboard auto-protection behavior.

### Internal
- Drop PHP 8.1 from the CI test matrix — the current pest 2.x ecosystem
  no longer supports it (pest ≥ 2.36.1 and brianium/paratest both require
  PHP ≥ 8.2, and pest 2.34.x conflicts with phpunit 10.5.63 on Laravel 10).
  The runtime `php: ^8.1` constraint stays — the package source itself
  still works on 8.1.
- Bump `pestphp/pest` floor to `^2.34` and `pestphp/pest-plugin-laravel`
  to `^2.3`, and add `guzzlehttp/guzzle ^7.0` to require-dev so HTTP
  faking in the geolocation tests works on every supported Laravel.

### Upgrade notes
1. `composer update ghdj/laravel-visitor-tracker`.
2. `php artisan migrate` to pick up the new index migration.
3. If you have `dashboard.enabled = true` with no token, gate, or `auth`
   middleware, the package will now throw at boot — add one (see README),
   or set `dashboard.allow_unprotected = true` if you protect it at the
   network layer.
4. If you sit behind a proxy/CDN, configure Laravel's `TrustProxies`
   middleware before the tracking middleware (see README).

## [1.0.0] - 2026-01

### Added
- Initial release.
- Visitor tracking with session identification (cookie-based).
- Page view tracking with URL, method, referrer, and status code.
- **Native bot detection** with 100+ patterns (no external dependencies).
- **Native user agent parsing** for browser/platform/device detection.
- Geolocation support using Laravel's HTTP client (ip-api, ipinfo, ipapi.co).
- **GDPR Safe Mode** — anonymous aggregate tracking without consent
  (no IP, no user ID, no persistent cookie, no full UA, country-level only).
  Enable via `VISITOR_TRACKER_GDPR_SAFE=true`.
- IP anonymization (IPv4 + IPv6) and DNT/Sec-GPC header support.
- Statistics service with caching.
- Built-in dashboard with three auth modes: token (for sites without login),
  Laravel auth middleware, and Gate-based authorization.
- Blade directives: `@totalVisitors`, `@totalPageViews`, `@onlineVisitors`,
  `@todayVisitors`, `@todayPageViews`.
- Artisan commands: `visitor-tracker:stats`, `visitor-tracker:prune`,
  `visitor-tracker:install-dashboard`.
- Queue support for async tracking.
- Configurable exclusions (paths, IPs, user agents, methods, status codes).
- `VisitorTracked` event for custom processing.
- Laravel 10, 11, and 12 support.
- Comprehensive test suite (100 tests).
- Zero external dependencies (only `illuminate/*` packages).
