# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added (Phase 1 - Foundation Setup) ✅
- Initial plugin structure and main plugin file
- Composer configuration with all required dependencies
- Plugin activation/deactivation/uninstall handlers
- WordPress admin interface (Dashboard, Connections, Settings pages)
- Admin CSS and JavaScript assets
- Hook loader system for organizing WordPress hooks
- Stub ability classes for future implementation
- Basic plugin documentation (README, CHANGELOG)

### Planned

#### Phase 2 - Credential Management
- Encrypted credential storage using libsodium
- Google OAuth 2.0 authentication flow
- Connection testing for all platforms
- Secure credential management UI

#### Phase 3 - API Client Layer
- Microsoft Clarity API client
- Google Analytics 4 API client
- Google Search Console API client
- Caching layer with WordPress Transients API
- Rate limiting implementation

#### Phase 4 - Clarity Abilities
- `marketing-analytics/get-clarity-insights` tool
- `marketing-analytics/get-clarity-recordings` tool
- `marketing-analytics/analyze-clarity-heatmaps` tool
- Clarity dashboard resource

#### Phase 5 - GA4 Abilities
- `marketing-analytics/get-ga4-metrics` tool
- `marketing-analytics/get-ga4-events` tool
- `marketing-analytics/get-ga4-realtime` tool
- `marketing-analytics/get-traffic-sources` tool
- GA4 overview resource

#### Phase 6 - Search Console Abilities
- `marketing-analytics/get-search-performance` tool
- `marketing-analytics/get-top-queries` tool
- `marketing-analytics/get-indexing-status` tool
- Search Console overview resource

#### Phase 7 - Cross-Platform Features
- `marketing-analytics/compare-periods` tool
- `marketing-analytics/get-top-content` tool
- `marketing-analytics/generate-summary-report` tool
- Dashboard summary resource
- Platform status resource
- Alerts resource

#### Phase 8 - MCP Prompts
- `marketing-analytics/analyze-traffic-drop` prompt
- `marketing-analytics/weekly-report` prompt
- `marketing-analytics/seo-health-check` prompt
- `marketing-analytics/content-performance-audit` prompt
- `marketing-analytics/conversion-funnel-analysis` prompt

#### Phase 9 - Admin UI Polish
- Enhanced dashboard with status widgets
- Activity feed for API calls
- Quick stats widgets
- MCP server configuration helper
- Debug mode with request logging
- Responsive design improvements

#### Phase 10 - Testing & Documentation
- PHPUnit test suite (unit + integration)
- Security audit and fixes
- Complete user and developer documentation
- WordPress.org preparation
- Version 1.0.0 release

## [1.0.0] - TBD

Initial release - coming soon!

### Added
- Full support for Microsoft Clarity, Google Analytics 4, and Google Search Console
- 13 MCP tools for analytics operations
- 4 MCP resources for quick data access
- 5 MCP prompts for common analysis workflows
- Secure credential management with encryption
- OAuth 2.0 authentication for Google services
- Comprehensive caching system
- WordPress admin interface
- Complete documentation

[Unreleased]: https://github.com/yourusername/marketing-analytics-mcp/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/yourusername/marketing-analytics-mcp/releases/tag/v1.0.0
