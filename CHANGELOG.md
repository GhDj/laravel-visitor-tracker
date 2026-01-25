# Changelog

All notable changes to `laravel-visitor-tracker` will be documented in this file.

## [Unreleased]

### Added
- **GDPR Safe Mode** - Track anonymous aggregate statistics without requiring user consent
  - No IP address storage (not even anonymized)
  - No user ID association
  - No persistent cookies (session-only identification)
  - No full user agent storage
  - No precise geolocation (only country-level)
  - Enable via `VISITOR_TRACKER_GDPR_SAFE=true`

- **Dashboard Token Authentication** - Protect stats dashboard on sites without user authentication
  - Secret token stored securely in `.env` file
  - Access via query parameter, header, or Bearer token
  - Configure via `VISITOR_TRACKER_TOKEN=your-secret-token`

- **Laravel 12 Support** - Package now supports Laravel 10, 11, and 12

- **Comprehensive Middleware Tests** - 29 tests covering all exclusion rules
  - Path exclusions with wildcards
  - IP exclusions with CIDR notation
  - User agent pattern matching
  - HTTP method filtering
  - Status code filtering
  - DNT/GPC header support

## [1.0.0] - 2024-XX-XX

### Added
- Initial release
- Visitor tracking with session identification
- Page view tracking with URL, method, and status code
- **Native bot detection** with 100+ patterns (no external dependencies)
- **Native user agent parsing** for browser/platform/device detection
- Geolocation support using Laravel HTTP client (ip-api, ipinfo providers)
- GDPR compliance (IP anonymization, DNT header support)
- Statistics service with caching
- Blade directives for common stats
- Artisan commands for stats and data pruning
- Queue support for async tracking
- Configurable exclusions (paths, IPs, user agents, status codes)
- Events for custom processing
- Comprehensive test suite
- Zero external dependencies (only Laravel illuminate packages)
