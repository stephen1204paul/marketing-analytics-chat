# WordPress MCP Marketing Analytics Plugin - REVISED Build Plan
## Using WordPress Abilities API + MCP Adapter

## Executive Summary

This revised plan leverages the **official WordPress Abilities API** (WordPress 6.9+) and **WordPress MCP Adapter** to create a pure-PHP WordPress plugin that exposes marketing analytics data via the Model Context Protocol. This approach eliminates the Node.js dependency and provides native WordPress integration.

## 1. Architecture Overview

### 1.1 Pure PHP Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│              WordPress Marketing Analytics Plugin               │
│                         (Pure PHP)                               │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  WordPress Admin Interface                                  │ │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐ │ │
│  │  │ Dashboard    │  │ Connections  │  │ Settings         │ │ │
│  │  │ - Status     │  │ - Clarity    │  │ - Permissions    │ │ │
│  │  │ - Health     │  │ - GA4        │  │ - Cache Config   │ │ │
│  │  │ - Activity   │  │ - GSC        │  │ - Debug Mode     │ │ │
│  │  └──────────────┘  └──────────────┘  └──────────────────┘ │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  Abilities Registration (abilities_api_init hook)           │ │
│  │                                                              │ │
│  │  ┌─────────────────────────────────────────────────────┐   │ │
│  │  │ Tools (Executable Actions)                          │   │ │
│  │  │ - marketing-analytics/get-clarity-insights          │   │ │
│  │  │ - marketing-analytics/get-clarity-recordings        │   │ │
│  │  │ - marketing-analytics/get-ga4-metrics               │   │ │
│  │  │ - marketing-analytics/get-ga4-events                │   │ │
│  │  │ - marketing-analytics/get-search-performance        │   │ │
│  │  │ - marketing-analytics/get-top-queries               │   │ │
│  │  │ - marketing-analytics/compare-periods               │   │ │
│  │  └─────────────────────────────────────────────────────┘   │ │
│  │                                                              │ │
│  │  ┌─────────────────────────────────────────────────────┐   │ │
│  │  │ Resources (Structured Data)                         │   │ │
│  │  │ - marketing-analytics/dashboard-summary             │   │ │
│  │  │ - marketing-analytics/platform-status               │   │ │
│  │  │ - marketing-analytics/top-content                   │   │ │
│  │  └─────────────────────────────────────────────────────┘   │ │
│  │                                                              │ │
│  │  ┌─────────────────────────────────────────────────────┐   │ │
│  │  │ Prompts (Templates for Common Tasks)                │   │ │
│  │  │ - marketing-analytics/analyze-traffic-drop          │   │ │
│  │  │ - marketing-analytics/weekly-report                 │   │ │
│  │  │ - marketing-analytics/seo-health-check              │   │ │
│  │  └─────────────────────────────────────────────────────┘   │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  API Integration Layer (PHP Classes)                        │ │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐ │ │
│  │  │ Clarity      │  │ GA4 Client   │  │ Search Console   │ │ │
│  │  │ API Client   │  │ (googleapis) │  │ Client           │ │ │
│  │  └──────────────┘  └──────────────┘  └──────────────────┘ │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  Data Management                                            │ │
│  │  - Encrypted Credentials Storage (wp_options)               │ │
│  │  - OAuth Token Management & Refresh                         │ │
│  │  - Response Caching (Transients API)                        │ │
│  │  - Rate Limit Tracking                                      │ │
│  └────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ (Automatic)
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│              WordPress MCP Adapter (Composer Package)            │
│                  wordpress/mcp-adapter                           │
│                                                                  │
│  - Bridges Abilities API → Model Context Protocol               │
│  - Automatic protocol handling (JSON-RPC 2.0)                   │
│  - HTTP Transport: /wp-json/mcp/mcp-adapter-default-server      │
│  - STDIO Transport: WP-CLI commands (local dev)                 │
│  - Permission validation & security                             │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                    MCP Clients                                   │
│  - Claude Desktop (HTTP/STDIO)                                   │
│  - ChatGPT with MCP support                                      │
│  - Other MCP-compatible AI assistants                            │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│              External Analytics APIs                             │
│  - Microsoft Clarity Data Export API                             │
│  - Google Analytics Data API v1                                  │
│  - Google Search Console API                                     │
└─────────────────────────────────────────────────────────────────┘
```

### 1.2 Why This Architecture?

✅ **Pure PHP** - No Node.js dependency, runs on any WordPress host
✅ **Native Integration** - Uses WordPress hooks, permissions, and patterns
✅ **Automatic MCP** - MCP Adapter handles all protocol complexity
✅ **Future-Proof** - Built on WordPress 6.9+ core features
✅ **Developer-Friendly** - Familiar WordPress development workflow
✅ **Easy Distribution** - Ready for WordPress.org plugin directory

## 2. Technology Stack

### 2.1 Core Dependencies

```json
{
  "require": {
    "php": ">=7.4",
    "wordpress/abilities-api": "^0.4",
    "wordpress/mcp-adapter": "^0.1",
    "automattic/jetpack-autoloader": "^3.0",
    "google/apiclient": "^2.15",
    "guzzlehttp/guzzle": "^7.8"
  },
  "require-dev": {
    "phpunit/phpunit": "^9.0",
    "phpstan/phpstan": "^1.10",
    "wp-coding-standards/wpcs": "^3.0"
  }
}
```

### 2.2 WordPress Requirements

- **WordPress**: 6.9+ (6.9+ for native Abilities API)
- **PHP**: 7.4+ (8.3+ recommended)
- **PHP Extensions**: `openssl`, `json`, `curl`
- **Capabilities**: `manage_options` for admin access

### 2.3 External APIs

- **Microsoft Clarity Data Export API**
- **Google Analytics Data API v1** (GA4)
- **Google Search Console API**

## 3. Plugin Features

### 3.1 MCP Tools (13 Total)

#### Microsoft Clarity Tools (3)

1. **`marketing-analytics/get-clarity-insights`**
   ```php
   Input: {num_of_days: 1|2|3, dimension1?, dimension2?, dimension3?}
   Output: {metrics: object, insights: array}
   Description: Retrieve dashboard analytics data from Clarity
   ```

2. **`marketing-analytics/get-clarity-recordings`**
   ```php
   Input: {filters: object, limit: int, sort_by?: string}
   Output: {recordings: array, total_count: int}
   Description: Fetch session recordings based on filters
   ```

3. **`marketing-analytics/analyze-clarity-heatmaps`**
   ```php
   Input: {page_url: string, heatmap_type: 'click'|'scroll'}
   Output: {heatmap_data: object, insights: string}
   Description: Get heatmap data and AI-friendly insights
   ```

#### Google Analytics Tools (4)

4. **`marketing-analytics/get-ga4-metrics`**
   ```php
   Input: {metrics: string[], date_range: string, dimensions?: string[]}
   Output: {data: array, totals: object}
   Description: Retrieve GA4 metrics for specified date range
   ```

5. **`marketing-analytics/get-ga4-events`**
   ```php
   Input: {event_name?: string, date_range: string, limit?: int}
   Output: {events: array, event_count: int}
   Description: Query custom event data from GA4
   ```

6. **`marketing-analytics/get-ga4-realtime`**
   ```php
   Input: {metrics?: string[]}
   Output: {active_users: int, data: object}
   Description: Get real-time user activity from GA4
   ```

7. **`marketing-analytics/get-traffic-sources`**
   ```php
   Input: {date_range: string, limit?: int}
   Output: {sources: array, channels: object}
   Description: Analyze traffic sources and acquisition channels
   ```

#### Google Search Console Tools (3)

8. **`marketing-analytics/get-search-performance`**
   ```php
   Input: {date_range: string, dimensions?: string[], filters?: object}
   Output: {rows: array, totals: object}
   Description: Retrieve search performance data (clicks, impressions, CTR, position)
   ```

9. **`marketing-analytics/get-top-queries`**
   ```php
   Input: {date_range: string, limit?: int, min_impressions?: int}
   Output: {queries: array}
   Description: Get top-performing search queries
   ```

10. **`marketing-analytics/get-indexing-status`**
    ```php
    Input: {page_url?: string}
    Output: {coverage: object, errors: array, warnings: array}
    Description: Check page indexing status and coverage issues
    ```

#### Cross-Platform Tools (3)

11. **`marketing-analytics/compare-periods`**
    ```php
    Input: {metric: string, period1: object, period2: object, platforms: string[]}
    Output: {comparison: object, change_percent: float, insights: string}
    Description: Compare metrics across time periods and platforms
    ```

12. **`marketing-analytics/get-top-content`**
    ```php
    Input: {date_range: string, metric: string, limit?: int}
    Output: {pages: array}
    Description: Get top-performing pages/posts across all platforms
    ```

13. **`marketing-analytics/generate-summary-report`**
    ```php
    Input: {date_range: string, include_platforms: string[]}
    Output: {report: object, insights: string[], recommendations: string[]}
    Description: Generate comprehensive multi-platform analytics summary
    ```

### 3.2 MCP Resources (4 Total)

1. **`marketing-analytics/dashboard-summary`**
   - Overall site performance snapshot
   - All connected platforms at a glance
   - Key metrics and trends

2. **`marketing-analytics/platform-status`**
   - Connection status for each platform
   - API quota usage
   - Last sync timestamps
   - Error/warning states

3. **`marketing-analytics/top-content`**
   - Most viewed pages (GA4)
   - Best performing in search (GSC)
   - Most engaging sessions (Clarity)

4. **`marketing-analytics/alerts`**
   - Significant traffic changes
   - Indexing errors
   - API connection issues

### 3.3 MCP Prompts (5 Total)

1. **`marketing-analytics/analyze-traffic-drop`**
   ```
   When user reports traffic decrease, this prompt:
   - Checks GA4 for traffic trends
   - Reviews Search Console for ranking changes
   - Examines Clarity for UX issues
   - Provides comprehensive analysis
   ```

2. **`marketing-analytics/weekly-report`**
   ```
   Generates weekly summary including:
   - Week-over-week changes
   - Top content by platform
   - Key wins and concerns
   - Actionable recommendations
   ```

3. **`marketing-analytics/seo-health-check`**
   ```
   Comprehensive SEO analysis:
   - Search Console errors/warnings
   - Indexing coverage
   - Top queries performance
   - Click-through rate analysis
   ```

4. **`marketing-analytics/content-performance-audit`**
   ```
   Analyzes content effectiveness:
   - GA4 engagement metrics
   - Search visibility (GSC)
   - User behavior (Clarity)
   - Content optimization suggestions
   ```

5. **`marketing-analytics/conversion-funnel-analysis`**
   ```
   Examines conversion paths:
   - GA4 conversion events
   - Clarity session recordings of converters
   - Drop-off points identification
   - Optimization recommendations
   ```

## 4. Implementation Phases

### Phase 1: Foundation Setup (Week 1)

**Plugin Initialization**
- [ ] Create plugin directory structure
- [ ] Initialize `composer.json` with dependencies
- [ ] Create main plugin file with headers
- [ ] Set up autoloading (Jetpack Autoloader)
- [ ] Implement activation/deactivation hooks
- [ ] Create uninstall.php for cleanup

**Composer Dependencies**
- [ ] Install WordPress Abilities API
- [ ] Install WordPress MCP Adapter
- [ ] Install Jetpack Autoloader
- [ ] Install Google API Client
- [ ] Install Guzzle HTTP client

**Basic Admin Interface**
- [ ] Register admin menu
- [ ] Create dashboard page skeleton
- [ ] Create settings page structure
- [ ] Enqueue admin CSS/JS
- [ ] Add WordPress admin notices support

**Database Schema**
- [ ] Design encrypted credentials storage structure
- [ ] Create database migration function
- [ ] Implement encryption/decryption utilities
- [ ] Set up default options on activation

**Deliverables:**
- ✅ Plugin installable and activatable
- ✅ Admin menu appears
- ✅ Basic pages render
- ✅ Composer dependencies loaded

### Phase 2: Credential Management System (Week 2)

**Encryption Infrastructure**
- [ ] Implement credential encryption class
  - Use `sodium_crypto_secretbox()` (PHP 7.2+)
  - Generate unique encryption keys per site
  - Store keys securely (not in database)
- [ ] Create credential storage interface
  - Save encrypted credentials to wp_options
  - Retrieve and decrypt on demand
  - Delete credentials securely

**Admin UI - Credentials Page**
- [ ] Create tabbed interface (Clarity, GA4, GSC)
- [ ] Microsoft Clarity tab:
  - API token input field
  - Project ID input
  - Test connection button
  - Save functionality
- [ ] Google Analytics tab:
  - OAuth 2.0 authorization flow
  - Service account JSON upload option
  - Property selection dropdown
  - Test connection button
- [ ] Google Search Console tab:
  - OAuth 2.0 authorization flow (shared with GA)
  - Site property selection
  - Verification status display
  - Test connection button

**OAuth Implementation**
- [ ] Register Google Cloud OAuth app
- [ ] Implement OAuth callback handler
- [ ] Store OAuth tokens encrypted
- [ ] Implement token refresh mechanism
- [ ] Handle OAuth errors gracefully

**Connection Testing**
- [ ] Clarity: Test Data Export API access
- [ ] GA4: Test property read access
- [ ] GSC: Test site data access
- [ ] Display connection status in admin
- [ ] Show helpful error messages

**Deliverables:**
- ✅ Users can securely add API credentials
- ✅ OAuth flows working for Google services
- ✅ Connection tests validate access
- ✅ Credentials encrypted in database

### Phase 3: API Client Layer (Week 3)

**Microsoft Clarity API Client**
- [ ] Create `Clarity_API_Client` class
- [ ] Implement authentication (Bearer token)
- [ ] Method: `get_dashboard_insights($days, $dimensions)`
- [ ] Method: `get_session_recordings($filters)`
- [ ] Error handling and rate limiting (10 req/day)
- [ ] Response caching logic
- [ ] Unit tests

**Google Analytics API Client**
- [ ] Create `GA4_API_Client` class
- [ ] Initialize Google Analytics Data API
- [ ] Method: `run_report($metrics, $dimensions, $date_range)`
- [ ] Method: `get_realtime_data($metrics)`
- [ ] Method: `get_event_data($event_name, $date_range)`
- [ ] Batch request support
- [ ] Error handling
- [ ] Unit tests

**Google Search Console API Client**
- [ ] Create `Search_Console_API_Client` class
- [ ] Initialize Google Search Console API
- [ ] Method: `query_search_analytics($date_range, $dimensions, $filters)`
- [ ] Method: `get_sitemap_status()`
- [ ] Method: `get_url_inspection($url)`
- [ ] Error handling
- [ ] Unit tests

**Caching Layer**
- [ ] Implement cache interface using Transients API
- [ ] Set appropriate TTLs per platform
  - Clarity: 1 hour
  - GA4: 30 minutes
  - GSC: 24 hours
- [ ] Cache invalidation methods
- [ ] Admin UI: Manual cache clear button

**Deliverables:**
- ✅ Three fully functional API clients
- ✅ Proper error handling and retries
- ✅ Response caching implemented
- ✅ Unit tests passing

### Phase 4: Abilities Registration - Clarity (Week 4)

**Register Clarity Tools**
- [ ] Tool: `get-clarity-insights`
  - Input schema: num_of_days, dimensions
  - Output schema: metrics object
  - Execute callback implementation
  - Permission callback
  - Test with MCP client

- [ ] Tool: `get-clarity-recordings`
  - Input schema: filters, limit, sort
  - Output schema: recordings array
  - Execute callback implementation
  - Permission callback
  - Test with MCP client

- [ ] Tool: `analyze-clarity-heatmaps`
  - Input schema: page_url, heatmap_type
  - Output schema: heatmap data
  - Execute callback implementation
  - Permission callback
  - Test with MCP client

**Register Clarity Resource**
- [ ] Resource: `clarity-dashboard`
  - Returns current Clarity project summary
  - Session counts, user metrics
  - Recent insights

**Testing**
- [ ] Test each ability via MCP HTTP endpoint
- [ ] Test STDIO transport locally (WP-CLI)
- [ ] Verify input validation
- [ ] Verify permission checks
- [ ] Integration tests with real Clarity API

**Deliverables:**
- ✅ 3 Clarity tools registered and working
- ✅ MCP clients can invoke Clarity abilities
- ✅ Proper error handling
- ✅ Tests passing

### Phase 5: Abilities Registration - Google Analytics (Week 5)

**Register GA4 Tools**
- [ ] Tool: `get-ga4-metrics`
  - Input schema: metrics array, date_range, dimensions
  - Output schema: data array, totals
  - Execute callback with caching
  - Permission callback
  - Test with MCP client

- [ ] Tool: `get-ga4-events`
  - Input schema: event_name, date_range, limit
  - Output schema: events array
  - Execute callback
  - Test with MCP client

- [ ] Tool: `get-ga4-realtime`
  - Input schema: metrics array
  - Output schema: active_users, data
  - Execute callback (no caching)
  - Test with MCP client

- [ ] Tool: `get-traffic-sources`
  - Input schema: date_range, limit
  - Output schema: sources, channels
  - Execute callback
  - Test with MCP client

**Register GA4 Resources**
- [ ] Resource: `ga4-overview`
  - Returns property summary
  - Key metrics snapshot
  - Configured events

**Testing**
- [ ] Test each GA4 ability
- [ ] Verify metric calculations
- [ ] Test date range handling
- [ ] Integration tests with real GA4 property

**Deliverables:**
- ✅ 4 GA4 tools registered and working
- ✅ Complex metric queries supported
- ✅ Real-time data accessible
- ✅ Tests passing

### Phase 6: Abilities Registration - Search Console (Week 6)

**Register GSC Tools**
- [ ] Tool: `get-search-performance`
  - Input schema: date_range, dimensions, filters
  - Output schema: rows array, totals
  - Execute callback
  - Support multiple dimensions
  - Test with MCP client

- [ ] Tool: `get-top-queries`
  - Input schema: date_range, limit, min_impressions
  - Output schema: queries array
  - Execute callback with sorting
  - Test with MCP client

- [ ] Tool: `get-indexing-status`
  - Input schema: page_url (optional)
  - Output schema: coverage, errors, warnings
  - Execute callback
  - Test with MCP client

**Register GSC Resources**
- [ ] Resource: `search-console-overview`
  - Returns site verification status
  - Total indexed pages
  - Current errors/warnings
  - Top queries snapshot

**Testing**
- [ ] Test search analytics queries
- [ ] Verify dimension filtering
- [ ] Test URL inspection
- [ ] Integration tests with real GSC property

**Deliverables:**
- ✅ 3 GSC tools registered and working
- ✅ Complex search analytics queries supported
- ✅ Indexing status checks working
- ✅ Tests passing

### Phase 7: Cross-Platform Features (Week 7)

**Cross-Platform Tools**
- [ ] Tool: `compare-periods`
  - Fetch same metric from multiple platforms
  - Calculate period-over-period changes
  - Generate insights string
  - Test with various metrics

- [ ] Tool: `get-top-content`
  - Aggregate top pages from GA4 + GSC
  - Merge and rank by selected metric
  - Include data from both platforms
  - Test ranking logic

- [ ] Tool: `generate-summary-report`
  - Fetch key metrics from all platforms
  - Combine into unified report object
  - Generate AI-friendly insights
  - Generate recommendations array
  - Test comprehensive report generation

**Cross-Platform Resources**
- [ ] Resource: `dashboard-summary`
  - Snapshot from all connected platforms
  - Key metrics side-by-side
  - Status indicators

- [ ] Resource: `platform-status`
  - Connection health for each platform
  - API quota usage tracking
  - Last successful sync times
  - Error states

- [ ] Resource: `alerts`
  - Detect significant metric changes
  - Surface GSC errors
  - Flag API connection issues

**Deliverables:**
- ✅ 3 cross-platform tools working
- ✅ Data successfully merged from multiple APIs
- ✅ Unified reporting functional
- ✅ Dashboard resources provide overview

### Phase 8: MCP Prompts (Week 8)

**Prompt Templates**
- [ ] Prompt: `analyze-traffic-drop`
  - Pre-configured to check GA4 traffic trends
  - Reviews GSC for ranking changes
  - Checks Clarity for UX issues
  - Combines insights
  - Test with Claude Desktop

- [ ] Prompt: `weekly-report`
  - Fetches last 7 days vs previous 7 days
  - Compiles top content
  - Identifies wins and concerns
  - Generates recommendations
  - Test report generation

- [ ] Prompt: `seo-health-check`
  - Queries GSC for errors/warnings
  - Checks indexing coverage
  - Analyzes top queries CTR
  - Provides SEO recommendations
  - Test SEO analysis

- [ ] Prompt: `content-performance-audit`
  - GA4 engagement by page
  - GSC search visibility
  - Clarity behavior insights
  - Content optimization suggestions
  - Test audit generation

- [ ] Prompt: `conversion-funnel-analysis`
  - GA4 conversion event flow
  - Clarity recordings of converters
  - Drop-off point identification
  - Optimization recommendations
  - Test funnel analysis

**Prompt Registration**
- [ ] Register prompts using Abilities API
- [ ] Define prompt arguments schemas
- [ ] Implement prompt execution logic
- [ ] Test prompts via MCP client

**Deliverables:**
- ✅ 5 sophisticated prompts working
- ✅ AI can invoke complex analysis workflows
- ✅ Prompts generate actionable insights
- ✅ Tests passing

### Phase 9: Admin UI Polish (Week 9)

**Enhanced Dashboard**
- [ ] Connection status cards for each platform
  - Visual indicators (green/yellow/red)
  - Last sync timestamps
  - Quick reconnect buttons
- [ ] Activity feed
  - Recent MCP tool invocations (optional)
  - API call history
  - Error log
- [ ] Quick stats widgets
  - Total API calls this month
  - Cache hit rate
  - Active connections

**Settings Page Improvements**
- [ ] Better form organization
- [ ] Inline help text and tooltips
- [ ] Documentation links
- [ ] Visual feedback for save actions
- [ ] Validation messages

**MCP Server Configuration**
- [ ] Display MCP endpoint URL
- [ ] Show connection instructions for Claude Desktop
- [ ] Copy-to-clipboard for config snippets
- [ ] STDIO WP-CLI command examples
- [ ] Test MCP endpoint button

**Debug Mode**
- [ ] Add debug mode toggle in settings
- [ ] When enabled, log API requests/responses
- [ ] Display logs in admin (with clear button)
- [ ] Never log sensitive credentials

**Responsive Design**
- [ ] Ensure admin pages work on mobile
- [ ] Test in WordPress 6.9, 6.3, 6.9
- [ ] Browser compatibility check

**Deliverables:**
- ✅ Polished, professional admin interface
- ✅ Clear connection status visibility
- ✅ Easy MCP client configuration
- ✅ Helpful debugging tools

### Phase 10: Testing, Security & Documentation (Week 10)

**Security Audit**
- [ ] Credential storage review
  - Verify encryption strength
  - Check for credential leakage in logs/errors
  - Test key storage security
- [ ] WordPress security best practices
  - All forms use nonces
  - Capability checks on all admin pages
  - Input sanitization (`sanitize_text_field`, etc.)
  - Output escaping (`esc_html`, `esc_url`, etc.)
  - Prepared statements for DB queries
- [ ] MCP ability security
  - Permission callbacks on all abilities
  - Input validation in execute callbacks
  - No SQL injection vulnerabilities
  - Safe error messages (no sensitive data)
- [ ] Third-party security scan
  - Use Plugin Check (PCP)
  - Address any findings

**Comprehensive Testing**
- [ ] Unit tests for all classes (PHPUnit)
  - API clients (mocked responses)
  - Credential manager
  - Encryption utilities
- [ ] Integration tests
  - Full workflow: connect → invoke → response
  - Test with real API credentials (sandbox)
- [ ] WordPress compatibility
  - Test on WordPress 6.9, 6.3, 6.9
  - Test on PHP 7.4, 8.3, 8.1, 8.2
  - Test with popular themes
  - Test with common plugins (conflict check)
- [ ] MCP client testing
  - Claude Desktop (HTTP transport)
  - WP-CLI (STDIO transport)
  - Verify all 13 tools work
  - Verify all 4 resources work
  - Verify all 5 prompts work
- [ ] Edge cases
  - Invalid credentials
  - API rate limit hit
  - Network timeouts
  - Expired OAuth tokens
  - Missing permissions

**Documentation**
- [ ] README.md
  - Plugin description
  - Features list
  - Requirements
  - Installation instructions
  - Quick start guide
  - Screenshots
- [ ] User Guide (docs/user-guide.md)
  - How to get Clarity API token
  - How to set up Google OAuth
  - Connecting each platform
  - Troubleshooting common issues
  - FAQ
- [ ] Developer Guide (docs/developer-guide.md)
  - Architecture overview
  - Adding new analytics platforms
  - Extending abilities
  - Hooks and filters available
  - Contributing guidelines
- [ ] API Reference (docs/api-reference.md)
  - All MCP tools documented
  - Input/output schemas
  - Example invocations
  - Error codes
- [ ] readme.txt (WordPress.org format)
  - Short description
  - Installation
  - Frequently Asked Questions
  - Changelog

**WordPress.org Preparation** (if distributing publicly)
- [ ] Plugin header complete with all fields
- [ ] License: GPL v2 or later
- [ ] Assets folder (banner, icon, screenshots)
- [ ] Sanitization/escaping review
- [ ] Internationalization (i18n) ready
  - All strings use `__()` or `_e()`
  - Text domain: `marketing-analytics-chat`
  - Generate .pot file

**Release Preparation**
- [ ] Version 1.0.0 tagging
- [ ] CHANGELOG.md created
- [ ] Final code review
- [ ] Performance profiling
- [ ] Create release build (zip file)

**Deliverables:**
- ✅ Secure, production-ready plugin
- ✅ Comprehensive test coverage
- ✅ Complete documentation
- ✅ Ready for public release

### Phase 11: AI Chat Interface (NEW - 5 Weeks)

Build a WordPress admin chat interface that acts as an MCP client, allowing users to query marketing analytics data through natural language conversation with AI.

#### Architecture

```
WordPress Admin Chat UI (Browser)
    ↓ (EventSource/SSE for Streaming)
PHP Chat Backend (LLM Providers: Claude/OpenAI)
    ↓ (HTTP via wp_remote_post)
Local MCP Server (/wp-json/mcp/mcp-adapter-default-server)
    ↓ (Abilities API)
Marketing Analytics Tools (13+ tools: Clarity, GA4, GSC)
    ↓
External Analytics APIs
```

#### Week 1: Foundation & Database

**Database Schema**
- [ ] Create `wp_marketing_analytics_mcp_conversations` table
  - Fields: id, user_id, title, created_at, updated_at
  - Indexes: user_created (user_id, created_at)
- [ ] Create `wp_marketing_analytics_mcp_messages` table
  - Fields: id, conversation_id, role, content, tool_calls, tool_call_id, tool_name, metadata, created_at
  - Indexes: conversation_created (conversation_id, created_at)
  - Foreign key: conversation_id → conversations(id) ON DELETE CASCADE

**Chat Manager Class**
- [ ] Create `includes/chat/class-chat-manager.php`
  - Method: `create_conversation($user_id, $title)`
  - Method: `get_conversations($user_id, $limit, $offset)`
  - Method: `get_conversation($conversation_id)`
  - Method: `add_message($conversation_id, $role, $content, $metadata)`
  - Method: `get_messages($conversation_id, $limit)`
  - Method: `delete_conversation($conversation_id)`
  - Method: `update_conversation_title($conversation_id, $title)`

**Basic UI (No AI Yet)**
- [ ] Create `admin/views/chat.php`
  - Conversation list sidebar
  - Message display area
  - Message input form
  - "New Conversation" button
- [ ] Register admin menu item "AI Assistant"
- [ ] Create basic CSS for chat layout

**Deliverables:**
- ✅ Database tables created on plugin activation
- ✅ Chat page accessible in WordPress admin
- ✅ Can create/view/delete conversations (hardcoded messages for testing)

#### Week 2: MCP Client Integration & Claude Provider

**MCP Client**
- [ ] Add Composer dependency: `composer require php-mcp/client`
- [ ] Create `includes/chat/class-mcp-client.php`
  - Method: `list_tools()` - Discover available MCP tools
  - Method: `call_tool($tool_name, $arguments)` - Execute tool via local MCP server
  - Method: `get_resources()` - Get available resources
  - Method: `get_prompts()` - Get available prompts
  - Connect to: `rest_url('mcp/mcp-adapter-default-server')`
  - Authentication: WordPress nonce or internal JWT

**LLM Provider Interface**
- [ ] Create `includes/chat/class-llm-provider-interface.php`
  - Method: `send_message($messages, $tools, $stream)`
  - Method: `parse_tool_calls($response)`
  - Method: `format_tool_result($tool_name, $result)`

**Claude Provider**
- [ ] Add Composer dependency: `composer require anthropic-ai/anthropic-sdk-php`
- [ ] Create `includes/chat/class-claude-provider.php`
  - Implement `LLM_Provider_Interface`
  - Method: `send_message()` - Non-streaming first
  - Method: `send_message_with_tools()` - Handle tool calling loop
  - Method: `convert_mcp_tools_to_claude_format()`
  - API key from encrypted storage

**Tool Calling Flow**
- [ ] Implement automatic tool execution:
  1. User sends message
  2. Claude API call with MCP tools
  3. Claude returns tool_use blocks
  4. Execute tools via MCP client
  5. Send tool results back to Claude
  6. Claude generates final response
  7. Save conversation to database

**AJAX Handler**
- [ ] Create `includes/chat/class-chat-ajax-handler.php`
  - Action: `marketing_analytics_mcp_send_message`
  - Nonce validation, capability check
  - Call Chat Manager and LLM provider
  - Return JSON response

**Deliverables:**
- ✅ Can send messages and get Claude responses (non-streaming)
- ✅ AI automatically calls MCP tools when needed
- ✅ Tool execution results visible in conversation

#### Week 3: Streaming Responses & OpenAI Provider

**Server-Sent Events (SSE)**
- [ ] Create custom SSE endpoint: `admin-post.php?action=marketing_analytics_mcp_chat_stream`
- [ ] Disable output buffering for streaming
- [ ] Set SSE headers (`Content-Type: text/event-stream`)
- [ ] Update `Claude_Provider` to support streaming
  - Stream content deltas
  - Stream tool execution events
  - Stream final completion event

**JavaScript Streaming Client**
- [ ] Create `admin/js/chat-interface.js`
  - EventSource for SSE connection
  - Real-time message rendering
  - Typing indicators during streaming
  - Tool execution visualization
  - Error handling and reconnection

**OpenAI Provider**
- [ ] Add Composer dependency: `composer require openai-php/client`
- [ ] Create `includes/chat/class-openai-provider.php`
  - Implement `LLM_Provider_Interface`
  - Handle OpenAI function calling format (different from Claude)
  - Streaming support

**Provider Factory**
- [ ] Create `includes/chat/class-llm-provider-factory.php`
  - Method: `create($provider_name, $api_key)`
  - Supports: 'claude', 'openai'
  - Load configuration from settings

**Deliverables:**
- ✅ Real-time streaming responses in chat UI
- ✅ Both Claude and OpenAI providers work
- ✅ Smooth user experience with typing indicators

#### Week 4: Visualization, Settings & Polish

**Chat Settings Page**
- [ ] Create `admin/views/chat-settings.php`
  - LLM provider selection (Claude/OpenAI)
  - API key input (encrypted storage)
  - Model selection dropdown
  - Max tokens slider
  - Enable/disable streaming toggle
  - Chat history retention settings

**Chat Settings Class**
- [ ] Create `includes/chat/class-chat-settings.php`
  - Save/retrieve LLM settings (encrypted)
  - Validate API keys
  - Test connection to LLM APIs

**Data Visualization**
- [ ] Integrate Chart.js (via WordPress CDN)
- [ ] Detect chart data in tool results
- [ ] Render inline charts for:
  - Traffic trends (line chart)
  - Traffic sources (pie chart)
  - Top pages (bar chart)
  - Period comparisons (grouped bar chart)

**Suggested Prompts**
- [ ] Add suggested prompts UI above message input
- [ ] Pre-defined prompts:
  - "Show me traffic trends for the last 30 days"
  - "What are my top performing pages?"
  - "Compare this week vs last week"
  - "Analyze my search console performance"
  - "Show me Clarity session insights"
- [ ] Click prompt to auto-fill message input

**Enhanced UI**
- [ ] Create `admin/css/chat-interface.css`
  - Message bubbles (user vs assistant)
  - Tool execution cards with icons
  - Loading states and animations
  - Chart containers
  - Mobile-responsive layout
  - Dark mode support (follows WordPress admin)

**Error Handling & Rate Limiting**
- [ ] Implement rate limiting (10 messages per user per hour)
- [ ] Handle API errors gracefully
- [ ] Show user-friendly error messages
- [ ] Log errors server-side

**Deliverables:**
- ✅ Full settings page for LLM configuration
- ✅ Charts rendered inline from analytics data
- ✅ Suggested prompts for quick queries
- ✅ Polished, professional UI
- ✅ Rate limiting and error handling

#### Week 5: Testing, Security & Documentation

**Security Audit**
- [ ] API key encryption verification
- [ ] Input sanitization for all user inputs
- [ ] XSS prevention with DOMPurify
- [ ] Capability checks on all endpoints
- [ ] Nonce validation on all AJAX calls
- [ ] SQL injection prevention (prepared statements)
- [ ] Rate limiting implementation
- [ ] Error message sanitization (no key exposure)

**Testing**
- [ ] Unit tests for Chat_Manager
- [ ] Integration tests for MCP Client
- [ ] End-to-end test: User message → Tool call → Response
- [ ] Test both Claude and OpenAI providers
- [ ] Test streaming and non-streaming modes
- [ ] Test conversation history persistence
- [ ] Test rate limiting
- [ ] Test with various MCP tools

**Performance Optimization**
- [ ] Database query optimization
- [ ] Conversation history pagination
- [ ] Tool result caching
- [ ] Lazy loading for old conversations
- [ ] Minify JavaScript and CSS

**Documentation**
- [ ] Create `docs/CHAT_INTERFACE.md`
  - User guide for chat interface
  - LLM provider setup instructions
  - Troubleshooting SSE issues
  - Example conversations
- [ ] Update main README with chat feature
- [ ] Add inline code documentation

**Known Limitations & Workarounds**
- [ ] Document SSE compatibility issues
  - nginx/Apache configuration
  - Fallback to long-polling
- [ ] Document hosting requirements
- [ ] Document LLM API costs

**Deliverables:**
- ✅ Comprehensive security audit passed
- ✅ Full test coverage
- ✅ Complete user and developer documentation
- ✅ Production-ready chat interface

#### File Structure for Chat Feature

```
marketing-analytics-chat/
├── includes/
│   ├── chat/                                    [NEW DIRECTORY]
│   │   ├── class-chat-manager.php              [NEW]
│   │   ├── class-mcp-client.php                [NEW]
│   │   ├── class-llm-provider-interface.php    [NEW]
│   │   ├── class-claude-provider.php           [NEW]
│   │   ├── class-openai-provider.php           [NEW]
│   │   ├── class-llm-provider-factory.php      [NEW]
│   │   ├── class-chat-ajax-handler.php         [NEW]
│   │   └── class-chat-settings.php             [NEW]
│   ├── class-activator.php                     [UPDATE - add chat tables]
│   └── class-plugin.php                        [UPDATE - register chat hooks]
├── admin/
│   ├── views/
│   │   ├── chat.php                            [NEW]
│   │   └── chat-settings.php                   [NEW]
│   ├── js/
│   │   ├── chat-interface.js                   [NEW]
│   │   └── chat-settings.js                    [NEW]
│   └── css/
│       └── chat-interface.css                  [NEW]
├── docs/
│   └── CHAT_INTERFACE.md                       [NEW]
└── composer.json                               [UPDATE - add dependencies]
```

#### Composer Dependencies to Add

```json
{
  "require": {
    "php-mcp/client": "^1.0",
    "anthropic-ai/anthropic-sdk-php": "^0.1",
    "openai-php/client": "^0.8"
  }
}
```

#### Database Schema

**Table: wp_marketing_analytics_mcp_conversations**
```sql
CREATE TABLE wp_marketing_analytics_mcp_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) DEFAULT 'New Conversation',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Table: wp_marketing_analytics_mcp_messages**
```sql
CREATE TABLE wp_marketing_analytics_mcp_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    role ENUM('user', 'assistant', 'system', 'tool') NOT NULL,
    content LONGTEXT,
    tool_calls LONGTEXT NULL COMMENT 'JSON array of tool calls',
    tool_call_id VARCHAR(100) NULL,
    tool_name VARCHAR(100) NULL,
    metadata LONGTEXT NULL COMMENT 'JSON (model, tokens, etc)',
    created_at DATETIME NOT NULL,
    INDEX conversation_created (conversation_id, created_at),
    FOREIGN KEY (conversation_id)
        REFERENCES wp_marketing_analytics_mcp_conversations(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Key Features

1. **Multi-Provider Support**
   - Toggle between Claude and OpenAI
   - Extensible architecture for additional providers
   - Provider-specific optimizations

2. **Real-Time Streaming**
   - Server-Sent Events (SSE)
   - Typing indicators
   - Progressive content rendering

3. **Automatic Tool Calling**
   - AI automatically uses MCP tools
   - Tool execution visualization
   - Results integrated in conversation

4. **Data Visualization**
   - Chart.js integration
   - Automatic chart generation from analytics data
   - Interactive charts in chat

5. **Conversation Management**
   - Persistent chat history
   - Multiple conversations
   - Search and export

6. **Security**
   - Encrypted API key storage
   - Admin-only access
   - Rate limiting
   - Input sanitization

#### Success Criteria

- ✅ Users can chat with AI about their analytics data
- ✅ AI automatically calls appropriate MCP tools
- ✅ Responses stream in real-time
- ✅ Analytics data visualized as charts
- ✅ Conversations persist across sessions
- ✅ Both Claude and OpenAI work seamlessly
- ✅ Admin can configure LLM providers in settings
- ✅ No security vulnerabilities
- ✅ Full documentation available

---

## 5. Detailed Plugin Structure

```
marketing-analytics-chat/
├── marketing-analytics-chat.php          # Main plugin file
├── composer.json                        # Dependencies
├── composer.lock                        # Locked versions
├── package.json                         # (optional) For admin JS build tools
├── README.md                            # GitHub README
├── readme.txt                           # WordPress.org README
├── LICENSE                              # GPL v2+
├── CHANGELOG.md                         # Version history
├── .gitignore
├── .phpcs.xml.dist                      # PHP_CodeSniffer config
├── phpunit.xml.dist                     # PHPUnit config
│
├── includes/                            # PHP Backend
│   ├── class-plugin.php                # Main plugin class
│   ├── class-loader.php                # Hook loader
│   │
│   ├── admin/                          # Admin functionality
│   │   ├── class-admin.php            # Admin page setup
│   │   ├── class-dashboard-page.php   # Dashboard page
│   │   ├── class-settings-page.php    # Settings page
│   │   └── class-connections-page.php # Connections page
│   │
│   ├── abilities/                      # Abilities registration
│   │   ├── class-abilities-registrar.php
│   │   ├── class-clarity-abilities.php
│   │   ├── class-ga4-abilities.php
│   │   ├── class-gsc-abilities.php
│   │   ├── class-cross-platform-abilities.php
│   │   └── class-prompts.php
│   │
│   ├── api-clients/                    # API integration layer
│   │   ├── class-clarity-client.php
│   │   ├── class-ga4-client.php
│   │   ├── class-gsc-client.php
│   │   └── interfaces/
│   │       └── interface-api-client.php
│   │
│   ├── credentials/                    # Credential management
│   │   ├── class-credential-manager.php
│   │   ├── class-encryption.php
│   │   ├── class-oauth-handler.php
│   │   └── class-connection-tester.php
│   │
│   ├── cache/                          # Caching layer
│   │   ├── class-cache-manager.php
│   │   └── class-cache-key-generator.php
│   │
│   ├── utils/                          # Utilities
│   │   ├── class-logger.php
│   │   ├── class-rate-limiter.php
│   │   └── class-helper-functions.php
│   │
│   └── traits/                         # Reusable traits
│       └── trait-singleton.php
│
├── admin/                               # Admin UI assets
│   ├── css/
│   │   ├── admin-styles.css
│   │   └── dashboard.css
│   ├── js/
│   │   ├── admin-scripts.js
│   │   ├── connection-tester.js
│   │   └── oauth-handler.js
│   ├── images/
│   │   ├── clarity-logo.png
│   │   ├── ga4-logo.png
│   │   └── gsc-logo.png
│   └── views/                          # PHP templates
│       ├── dashboard.php
│       ├── settings.php
│       ├── connections/
│       │   ├── clarity.php
│       │   ├── google-analytics.php
│       │   └── search-console.php
│       └── partials/
│           ├── header.php
│           ├── footer.php
│           └── connection-status.php
│
├── languages/                           # Internationalization
│   └── marketing-analytics-chat.pot     # Translation template
│
├── assets/                              # WordPress.org assets
│   ├── banner-1544x500.png
│   ├── banner-772x250.png
│   ├── icon-128x128.png
│   ├── icon-256x256.png
│   └── screenshots/
│       ├── screenshot-1.png
│       └── screenshot-2.png
│
├── tests/                               # Tests
│   ├── bootstrap.php                   # Test bootstrap
│   ├── unit/                           # Unit tests
│   │   ├── test-encryption.php
│   │   ├── test-clarity-client.php
│   │   ├── test-ga4-client.php
│   │   └── test-gsc-client.php
│   └── integration/                    # Integration tests
│       ├── test-abilities-registration.php
│       ├── test-mcp-endpoint.php
│       └── test-oauth-flow.php
│
├── docs/                                # Documentation
│   ├── user-guide.md
│   ├── developer-guide.md
│   ├── api-reference.md
│   ├── architecture.md
│   └── setup-guides/
│       ├── clarity-setup.md
│       ├── google-analytics-setup.md
│       └── search-console-setup.md
│
└── vendor/                              # Composer dependencies (gitignored)
    └── autoload.php
```

## 6. Core Code Examples

### 6.1 Main Plugin File

```php
<?php
/**
 * Plugin Name: Marketing Analytics MCP
 * Plugin URI: https://github.com/yourusername/marketing-analytics-chat
 * Description: Exposes marketing analytics data (Clarity, GA4, Search Console) via Model Context Protocol for AI assistants.
 * Version: 1.0.0
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: marketing-analytics-chat
 * Domain Path: /languages
 */

namespace Marketing_Analytics_MCP;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Plugin version
define( 'MARKETING_ANALYTICS_MCP_VERSION', '1.0.0' );
define( 'MARKETING_ANALYTICS_MCP_PATH', plugin_dir_path( __FILE__ ) );
define( 'MARKETING_ANALYTICS_MCP_URL', plugin_dir_url( __FILE__ ) );

// Load Composer autoloader
require_once MARKETING_ANALYTICS_MCP_PATH . 'vendor/autoload.php';

// Activation hook
register_activation_hook( __FILE__, function() {
    require_once MARKETING_ANALYTICS_MCP_PATH . 'includes/class-activator.php';
    Activator::activate();
} );

// Deactivation hook
register_deactivation_hook( __FILE__, function() {
    require_once MARKETING_ANALYTICS_MCP_PATH . 'includes/class-deactivator.php';
    Deactivator::deactivate();
} );

// Initialize plugin
function run_marketing_analytics_mcp() {
    $plugin = new Plugin();
    $plugin->run();
}
run_marketing_analytics_mcp();
```

### 6.2 Abilities Registration Example

```php
<?php
namespace Marketing_Analytics_MCP\Abilities;

class Clarity_Abilities {

    public function register() {
        add_action( 'abilities_api_init', [ $this, 'register_abilities' ] );
    }

    public function register_abilities() {

        // Tool: Get Clarity Insights
        wp_register_ability( 'marketing-analytics/get-clarity-insights', [
            'label'       => __( 'Get Microsoft Clarity Insights', 'marketing-analytics-chat' ),
            'description' => __( 'Retrieve analytics dashboard data from Microsoft Clarity for a specified time period with optional dimension filters.', 'marketing-analytics-chat' ),

            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'num_of_days' => [
                        'type'        => 'integer',
                        'description' => 'Number of days: 1 (last 24h), 2 (last 48h), or 3 (last 72h)',
                        'enum'        => [ 1, 2, 3 ],
                    ],
                    'dimension1' => [
                        'type'        => 'string',
                        'description' => 'First dimension to break down insights',
                        'enum'        => [ 'OS', 'Browser', 'Device', 'Country' ],
                    ],
                    'dimension2' => [
                        'type'        => 'string',
                        'description' => 'Second dimension (optional)',
                        'enum'        => [ 'OS', 'Browser', 'Device', 'Country' ],
                    ],
                    'dimension3' => [
                        'type'        => 'string',
                        'description' => 'Third dimension (optional)',
                        'enum'        => [ 'OS', 'Browser', 'Device', 'Country' ],
                    ],
                ],
                'required' => [ 'num_of_days' ],
            ],

            'output_schema' => [
                'type'       => 'object',
                'properties' => [
                    'metrics' => [
                        'type'        => 'object',
                        'description' => 'Clarity dashboard metrics',
                    ],
                    'insights' => [
                        'type'        => 'array',
                        'description' => 'Array of insight objects',
                    ],
                    'period' => [
                        'type'        => 'string',
                        'description' => 'Time period covered',
                    ],
                ],
            ],

            'execute_callback'    => [ $this, 'get_clarity_insights' ],
            'permission_callback' => [ $this, 'check_permissions' ],

            'thinking_message' => __( 'Fetching Clarity analytics data...', 'marketing-analytics-chat' ),
            'success_message'  => __( 'Clarity insights retrieved successfully.', 'marketing-analytics-chat' ),
        ] );

        // Tool: Get Session Recordings
        wp_register_ability( 'marketing-analytics/get-clarity-recordings', [
            'label'       => __( 'Get Clarity Session Recordings', 'marketing-analytics-chat' ),
            'description' => __( 'Fetch session recording URLs from Microsoft Clarity based on filters.', 'marketing-analytics-chat' ),

            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'filters' => [
                        'type'        => 'object',
                        'description' => 'Filters for recordings (device, browser, country, etc.)',
                    ],
                    'limit' => [
                        'type'        => 'integer',
                        'description' => 'Number of recordings to return (max 100)',
                        'default'     => 10,
                        'minimum'     => 1,
                        'maximum'     => 100,
                    ],
                    'sort_by' => [
                        'type'        => 'string',
                        'description' => 'Sort recordings by criteria',
                        'enum'        => [ 'date', 'duration', 'pages_viewed' ],
                        'default'     => 'date',
                    ],
                ],
            ],

            'output_schema' => [
                'type'       => 'object',
                'properties' => [
                    'recordings' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'session_id' => [ 'type' => 'string' ],
                                'url'        => [ 'type' => 'string' ],
                                'duration'   => [ 'type' => 'integer' ],
                                'device'     => [ 'type' => 'string' ],
                            ],
                        ],
                    ],
                    'total_count' => [
                        'type'        => 'integer',
                        'description' => 'Total recordings matching filters',
                    ],
                ],
            ],

            'execute_callback'    => [ $this, 'get_clarity_recordings' ],
            'permission_callback' => [ $this, 'check_permissions' ],

            'thinking_message' => __( 'Searching for session recordings...', 'marketing-analytics-chat' ),
            'success_message'  => __( 'Session recordings retrieved.', 'marketing-analytics-chat' ),
        ] );
    }

    /**
     * Execute callback for get-clarity-insights
     */
    public function get_clarity_insights( $args ) {
        $clarity_client = new \Marketing_Analytics_MCP\API_Clients\Clarity_Client();

        try {
            $data = $clarity_client->get_dashboard_insights(
                $args['num_of_days'],
                $args['dimension1'] ?? null,
                $args['dimension2'] ?? null,
                $args['dimension3'] ?? null
            );

            return [
                'success' => true,
                'data'    => $data,
            ];

        } catch ( \Exception $e ) {
            return new \WP_Error(
                'clarity_api_error',
                $e->getMessage()
            );
        }
    }

    /**
     * Execute callback for get-clarity-recordings
     */
    public function get_clarity_recordings( $args ) {
        $clarity_client = new \Marketing_Analytics_MCP\API_Clients\Clarity_Client();

        try {
            $recordings = $clarity_client->get_session_recordings(
                $args['filters'] ?? [],
                $args['limit'] ?? 10,
                $args['sort_by'] ?? 'date'
            );

            return [
                'success' => true,
                'data'    => $recordings,
            ];

        } catch ( \Exception $e ) {
            return new \WP_Error(
                'clarity_api_error',
                $e->getMessage()
            );
        }
    }

    /**
     * Permission callback
     */
    public function check_permissions() {
        return current_user_can( 'manage_options' );
    }
}
```

### 6.3 API Client Example (Clarity)

```php
<?php
namespace Marketing_Analytics_MCP\API_Clients;

use Marketing_Analytics_MCP\Credentials\Credential_Manager;
use Marketing_Analytics_MCP\Cache\Cache_Manager;

class Clarity_Client {

    const API_BASE_URL = 'https://www.clarity.ms/export-data/api/v1';
    const RATE_LIMIT = 10; // requests per day

    private $credential_manager;
    private $cache_manager;

    public function __construct() {
        $this->credential_manager = new Credential_Manager();
        $this->cache_manager      = new Cache_Manager();
    }

    /**
     * Get dashboard insights
     */
    public function get_dashboard_insights( $num_of_days, $dimension1 = null, $dimension2 = null, $dimension3 = null ) {

        // Check cache first
        $cache_key = $this->cache_manager->generate_key( 'clarity_insights', [
            'days' => $num_of_days,
            'dim1' => $dimension1,
            'dim2' => $dimension2,
            'dim3' => $dimension3,
        ] );

        $cached = $this->cache_manager->get( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        // Get credentials
        $credentials = $this->credential_manager->get_credentials( 'clarity' );
        if ( ! $credentials || empty( $credentials['api_token'] ) ) {
            throw new \Exception( 'Clarity API token not configured' );
        }

        // Build request
        $endpoint = self::API_BASE_URL . '/project-live-insights';
        $params   = [ 'numOfDays' => $num_of_days ];

        if ( $dimension1 ) $params['dimension1'] = $dimension1;
        if ( $dimension2 ) $params['dimension2'] = $dimension2;
        if ( $dimension3 ) $params['dimension3'] = $dimension3;

        $url = add_query_arg( $params, $endpoint );

        // Make request
        $response = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $credentials['api_token'],
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \Exception( 'Clarity API request failed: ' . $response->get_error_message() );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            throw new \Exception( 'Clarity API returned status ' . $status_code );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new \Exception( 'Failed to parse Clarity API response' );
        }

        // Cache for 1 hour
        $this->cache_manager->set( $cache_key, $data, HOUR_IN_SECONDS );

        return $data;
    }

    /**
     * Get session recordings
     */
    public function get_session_recordings( $filters = [], $limit = 10, $sort_by = 'date' ) {
        // Implementation for fetching recordings
        // Note: This may require a different Clarity API endpoint
        // Placeholder implementation

        throw new \Exception( 'Session recordings API not yet implemented' );
    }
}
```

### 6.4 Encryption Class

```php
<?php
namespace Marketing_Analytics_MCP\Credentials;

class Encryption {

    const KEY_OPTION = 'marketing_analytics_mcp_encryption_key';

    /**
     * Get or generate encryption key
     */
    private static function get_key() {
        $key = get_option( self::KEY_OPTION );

        if ( ! $key ) {
            // Generate new key
            $key = base64_encode( random_bytes( SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) );
            update_option( self::KEY_OPTION, $key, false ); // Don't autoload
        }

        return base64_decode( $key );
    }

    /**
     * Encrypt data
     */
    public static function encrypt( $data ) {
        $key   = self::get_key();
        $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

        $ciphertext = sodium_crypto_secretbox( $data, $nonce, $key );

        // Combine nonce and ciphertext
        $encrypted = base64_encode( $nonce . $ciphertext );

        // Clear sensitive data from memory
        sodium_memzero( $key );

        return $encrypted;
    }

    /**
     * Decrypt data
     */
    public static function decrypt( $encrypted ) {
        $key     = self::get_key();
        $decoded = base64_decode( $encrypted );

        $nonce      = mb_substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit' );
        $ciphertext = mb_substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit' );

        $plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );

        // Clear sensitive data from memory
        sodium_memzero( $key );

        if ( false === $plaintext ) {
            throw new \Exception( 'Decryption failed' );
        }

        return $plaintext;
    }
}
```

## 7. MCP Client Configuration

### 7.1 Claude Desktop (HTTP Transport)

```json
{
  "mcpServers": {
    "wordpress-marketing": {
      "transport": {
        "type": "http",
        "url": "https://your-wordpress-site.com/wp-json/mcp/mcp-adapter-default-server",
        "headers": {
          "Authorization": "Basic base64(username:application-password)"
        }
      }
    }
  }
}
```

### 7.2 WP-CLI (STDIO Transport)

```bash
# List available tools
wp mcp list-tools --server=marketing-analytics-chat

# Invoke a tool
wp mcp call-tool marketing-analytics/get-clarity-insights \
  --arguments='{"num_of_days": 3, "dimension1": "Device"}' \
  --user=admin

# Get a resource
wp mcp get-resource marketing-analytics/dashboard-summary \
  --user=admin
```

## 8. Security Considerations

### 8.1 Credential Security
- ✅ **Encryption at rest**: libsodium `crypto_secretbox`
- ✅ **Unique keys per site**: Generated on activation
- ✅ **Memory cleanup**: `sodium_memzero()` after use
- ✅ **No logging**: Credentials never appear in logs

### 8.2 WordPress Security
- ✅ **Capability checks**: `current_user_can('manage_options')`
- ✅ **Nonces**: All forms use `wp_nonce_field()`
- ✅ **Sanitization**: `sanitize_text_field()`, `sanitize_url()`
- ✅ **Escaping**: `esc_html()`, `esc_url()`, `esc_attr()`
- ✅ **Prepared statements**: `$wpdb->prepare()`

### 8.3 MCP Ability Security
- ✅ **Permission callbacks**: Every ability checks permissions
- ✅ **Input validation**: Schema validation via Abilities API
- ✅ **Rate limiting**: Respect API quotas
- ✅ **Error messages**: Never expose credentials or internal paths

### 8.4 OAuth Security
- ✅ **State parameter**: CSRF protection in OAuth flow
- ✅ **Secure callbacks**: HTTPS only
- ✅ **Token refresh**: Automatic refresh with retry
- ✅ **Scope limitation**: Minimal scopes requested

## 9. Performance Optimization

### 9.1 Caching Strategy

| Platform | Cache TTL | Reason |
|----------|-----------|--------|
| **Clarity** | 1 hour | Data updated periodically, 10 req/day limit |
| **GA4** | 30 minutes | Near real-time available but expensive |
| **GSC** | 24 hours | Data has 2-3 day delay anyway |

### 9.2 Cache Invalidation
- Manual "Clear Cache" button in admin
- Automatic invalidation on credential update
- Cache keys include all query parameters

### 9.3 Rate Limit Management
- Track API calls in WordPress options
- Reset counters daily (cron job)
- Return cached data when limit hit
- User-friendly error messages

## 10. Extensibility

### 10.1 WordPress Hooks

```php
// Filter credentials before encryption
apply_filters( 'marketing_analytics_mcp_encrypt_credentials', $credentials, $platform );

// Filter API client configuration
apply_filters( 'marketing_analytics_mcp_api_config', $config, $platform );

// Action when ability is registered
do_action( 'marketing_analytics_mcp_ability_registered', $ability_name );

// Filter cache TTL
apply_filters( 'marketing_analytics_mcp_cache_ttl', $ttl, $platform );

// Action when API call succeeds
do_action( 'marketing_analytics_mcp_api_success', $platform, $endpoint, $response );

// Action when API call fails
do_action( 'marketing_analytics_mcp_api_error', $platform, $error );
```

### 10.2 Adding New Platforms

To add a new analytics platform:

1. Create API client class in `includes/api-clients/`
2. Create abilities class in `includes/abilities/`
3. Register abilities on `abilities_api_init` hook
4. Add admin UI tab for credentials
5. Add connection test method
6. Update documentation

## 11. Deployment Checklist

### 11.1 Pre-Release
- [ ] All tests passing (PHPUnit)
- [ ] PHPStan level 8 clean
- [ ] WordPress Coding Standards compliant
- [ ] Security audit complete
- [ ] Documentation complete
- [ ] Screenshots prepared
- [ ] Version numbers updated
- [ ] Changelog updated

### 11.2 WordPress.org Submission (if applicable)
- [ ] Plugin header complete
- [ ] readme.txt formatted correctly
- [ ] GPL v2+ license
- [ ] All strings internationalized
- [ ] .pot file generated
- [ ] Assets folder ready (banner, icon)
- [ ] First submission via SVN

### 11.3 Post-Release
- [ ] Monitor error logs
- [ ] Set up support channel
- [ ] Create announcement post
- [ ] Submit to plugin directories
- [ ] Gather user feedback

## 12. Success Metrics

Track these metrics post-launch:

- **Adoption**: Active installations
- **Engagement**: Average MCP calls per site per day
- **Platform Mix**: % using Clarity vs GA4 vs GSC
- **Support**: Support requests per 100 installs
- **Ratings**: WordPress.org rating (if published)
- **Performance**: Average API response time

## 13. Future Enhancements (v2.0+)

### Phase 2 Platforms
- [ ] Facebook/Meta Ads API
- [ ] LinkedIn Analytics
- [ ] Twitter/X Analytics
- [ ] Adobe Analytics
- [ ] Matomo (self-hosted)
- [ ] Plausible Analytics

### Advanced Features
- [ ] Custom dashboard widgets
- [ ] Scheduled reports (email)
- [ ] Anomaly detection & alerts
- [ ] Multi-site support
- [ ] White-label mode
- [ ] REST API for non-MCP access

### Enterprise Features
- [ ] Team permissions (role-based access)
- [ ] Multiple property support
- [ ] Audit logging
- [ ] SaaS hosted option

---

## Ready to Build!

This revised plan leverages the **official WordPress Abilities API + MCP Adapter** for a pure-PHP, WordPress-native solution. The architecture is:

- ✅ **Simpler** - No Node.js, no separate server process
- ✅ **Native** - Uses WordPress patterns throughout
- ✅ **Future-Proof** - Built on WordPress 6.9+ core features
- ✅ **Maintainable** - Standard PHP, familiar workflows
- ✅ **Distributable** - Ready for WordPress.org

**Total estimated timeline: 10 weeks** from start to v1.0 release.
