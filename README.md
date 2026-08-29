# Marketing Analytics Chat

> **This repository is archived.** Development moved to [specflux/marketing-analytics-chat](https://github.com/specflux/marketing-analytics-chat), which is the source for the plugin published at [wordpress.org/plugins/specflux-marketing-analytics-chat](https://wordpress.org/plugins/specflux-marketing-analytics-chat/). This copy is kept for its early history only.

> Expose Google Analytics 4, Search Console, and Microsoft Clarity data to AI assistants via the Model Context Protocol.

[![WordPress Version](https://img.shields.io/badge/WordPress-6.9%2B-blue)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-purple)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](LICENSE)

## Overview

This WordPress plugin bridges your marketing analytics platforms with AI assistants using the **Model Context Protocol (MCP)**. Built on WordPress 6.9+ Abilities API and the MCP Adapter plugin, it exposes analytics data as MCP abilities that any compatible AI client can use.

### Supported Platforms

- **Google Analytics 4** — Traffic metrics, user behavior, conversions, real-time data
- **Google Search Console** — Search performance, queries, indexing status
- **Microsoft Clarity** — Session recordings, heatmaps, user behavior insights

## Features

### MCP Abilities (10)

- Get GA4 Metrics, Realtime, and Audience data
- Get Search Performance, Top Queries, and Page Performance
- Get Clarity Insights and Metrics
- Cross-Platform Summary and Compare Platforms

### Additional Features

- Interactive onboarding wizard with guided setup
- Analytics-at-a-glance dashboard widget with sparkline trends
- Admin bar analytics pulse indicator
- Smart prompt templates for common analytics questions
- Abilities catalog — browse all available MCP abilities
- Connection depth prompts that adapt to connected platforms
- Secure OAuth 2.0 and encrypted API key storage (libsodium)
- Smart caching with configurable TTLs per platform
- Role-based access control

## Requirements

- **WordPress**: 6.9+
- **PHP**: 8.1+
- **PHP Extensions**: `json`, `curl`, `openssl`, `sodium`
- **Required Plugin**: [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/)

## Installation

### From WordPress.org

1. Install and activate the **MCP Adapter** plugin from WordPress.org
2. Upload `marketing-analytics-chat` to `/wp-content/plugins/`
3. Activate through the Plugins menu
4. Go to **Marketing Analytics > Settings > Google API** to configure OAuth
5. Connect your platforms from the **Connections** page

### From Source

```bash
cd wp-content/plugins/
git clone https://github.com/stephen1204paul/marketing-analytics-chat.git
cd marketing-analytics-chat
composer install --no-dev
wp plugin activate marketing-analytics-chat
```

## Configuration

### 1. Connect Analytics Platforms

Navigate to **Marketing Analytics > Connections** in WordPress admin:

- **Google Analytics 4** — Complete OAuth flow, select your GA4 property
- **Google Search Console** — Complete OAuth flow (shared credentials with GA4), select your property
- **Microsoft Clarity** — Enter API token and project ID

### 2. Configure MCP Client

Add to your Claude Desktop config (`~/Library/Application Support/Claude/claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "wordpress-marketing": {
      "transport": {
        "type": "http",
        "url": "https://your-site.com/wp-json/mcp/mcp-adapter-default-server",
        "headers": {
          "Authorization": "Basic <base64(username:application-password)>"
        }
      }
    }
  }
}
```

Generate an application password: **WordPress Admin > Users > Application Passwords**

Works with Claude Desktop, ChatGPT, Cursor, and any MCP-compatible client.

## Access Control

By default, only **Administrators** can access the plugin. Grant access to other roles via **Marketing Analytics > Settings > Access Control**.

The plugin uses the custom capability `access_marketing_analytics` assigned to selected roles, with nonce verification on all endpoints.

## Development

```bash
composer install
vendor/bin/phpunit        # Run tests
vendor/bin/phpcs          # Check coding standards
vendor/bin/phpcbf         # Auto-fix standards
```

## Pro Add-on

Additional platforms and features are available through the Pro add-on:

- Meta Business Suite, DataForSEO
- WooCommerce analytics
- AI-powered insights and anomaly detection
- Slack and WhatsApp notifications
- Google Sheets export
- Multisite support

Visit [specflux.com](https://www.specflux.com/) for details.

## Security

- **Credential Encryption**: libsodium `crypto_secretbox` with per-site keys
- **OAuth Security**: State parameter CSRF protection, HTTPS-only callbacks
- **WordPress Security**: Nonces, capability checks, input sanitization, output escaping
- **No Credential Logging**: Sensitive data never appears in logs

Found a security issue? Email stephenpaul@specflux.com (do not open public issues).

## License

GPL v2 or later. See [LICENSE](LICENSE) for details.

## Credits

Built with:
- [WordPress MCP Adapter](https://wordpress.org/plugins/mcp-adapter/)
- [Google API PHP Client](https://github.com/googleapis/google-api-php-client)

## Support

- **Issues**: [GitHub Issues](https://github.com/stephen1204paul/marketing-analytics-chat/issues)
- **Website**: [specflux.com](https://www.specflux.com/)
