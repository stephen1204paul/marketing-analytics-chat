# Marketing Analytics MCP - WordPress Plugin

> Exposes marketing analytics data (Microsoft Clarity, Google Analytics 4, Google Search Console) via Model Context Protocol for AI assistants.

[![WordPress Version](https://img.shields.io/badge/WordPress-6.9%2B-blue)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-purple)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](LICENSE)

## 🚀 Overview

This WordPress plugin bridges your marketing analytics platforms with AI assistants using the **Model Context Protocol (MCP)**. Built on WordPress 6.9+ Abilities API and the WordPress MCP Adapter, it provides a pure-PHP solution for exposing analytics data to Claude and other MCP-compatible AI tools.

### Supported Platforms

- **Microsoft Clarity** - Session recordings, heatmaps, user behavior insights
- **Google Analytics 4** - Traffic metrics, conversion tracking, real-time data
- **Google Search Console** - Search performance, indexing status, query analytics

## ✨ Features

### MCP Tools (13 Total)
Execute analytics operations via AI assistants:
- Clarity insights and session recordings
- GA4 metrics, events, and real-time data
- Search Console performance and indexing status
- Cross-platform comparisons and unified reports

### MCP Resources (4 Total)
Quick access to structured analytics snapshots:
- Dashboard summary
- Platform connection status
- Top-performing content
- Alerts and notifications

### MCP Prompts (5 Total)
Pre-configured analysis workflows:
- Traffic drop analysis
- Weekly performance reports
- SEO health checks
- Content performance audits
- Conversion funnel analysis

## 📋 Requirements

- **WordPress**: 6.9+ ( recommended for native Abilities API)
- **PHP**: 8.3+ recommended
- **PHP Extensions**: `json`, `curl`, `openssl`, `sodium`
- **Composer**: For dependency management

## 🔧 Installation

### Prerequisites

**IMPORTANT**: This plugin requires the **WordPress MCP Adapter** to be installed first.

```bash
# Install WordPress MCP Adapter and Abilities API
cd wp-content/plugins/
git clone https://github.com/WordPress/abilities-api.git
git clone https://github.com/WordPress/mcp-adapter.git

cd abilities-api && composer install --no-dev && cd ..
cd mcp-adapter && composer install --no-dev && cd ..

# Activate prerequisite plugins
wp plugin activate abilities-api mcp-adapter
```

> **Troubleshooting**: If you get HTML responses from the MCP endpoint, see [MCP Adapter Installation Guide](docs/MCP_ADAPTER_INSTALLATION.md)

### 1. Clone or Download

```bash
cd wp-content/plugins/
git clone https://github.com/yourusername/marketing-analytics-chat.git
cd marketing-analytics-chat
```

### 2. Install Dependencies

```bash
composer install --no-dev
```

### 3. Activate Plugin

```bash
wp plugin activate marketing-analytics-chat
```

Or activate via WordPress Admin → Plugins → Marketing Analytics MCP → Activate

### 4. Verify Installation

Test the MCP endpoint:

```bash
curl -X POST https://your-site.com/wp-json/mcp/mcp-adapter-default-server \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic $(echo -n 'username:app-password' | base64)" \
  -H "Mcp-Session-Id: test-session-123" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}'
```

Expected: JSON response with list of 10 tools

**Note:** The `Mcp-Session-Id` header is required by the MCP protocol.

## ⚙️ Configuration

### 1. Connect Analytics Platforms

Navigate to **Marketing Analytics → Connections** in WordPress admin:

- **Microsoft Clarity**: Enter API token and project ID
- **Google Analytics 4**: Complete OAuth authentication flow (Phase 2)
- **Google Search Console**: Complete OAuth authentication flow (Phase 2)

### 2. Configure MCP Client

Add to your Claude Desktop config (`~/Library/Application Support/Claude/claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "wordpress-marketing": {
      "transport": {
        "type": "http",
        "url": "https://your-wordpress-site.com/wp-json/mcp/mcp-adapter-default-server",
        "headers": {
          "Authorization": "Basic <base64-encoded-credentials>"
        }
      }
    }
  }
}
```

Generate application password: **WordPress Admin → Users → Application Passwords**

### 3. Test Connection

Ask Claude:
```
Can you show me my marketing analytics dashboard summary?
```

## 📚 Documentation

- **[n8n Quick Start](docs/N8N_QUICK_START.md)** - Connect to n8n in 5 minutes
- **[n8n Integration Guide](docs/N8N_INTEGRATION.md)** - Complete n8n setup guide
- **[MCP Adapter Installation](docs/MCP_ADAPTER_INSTALLATION.md)** - Fix HTML response issues
- [User Guide](docs/user-guide.md) - Setup and usage instructions
- [Developer Guide](docs/developer-guide.md) - Architecture and extending the plugin
- [API Reference](docs/api-reference.md) - All MCP tools, resources, and prompts
- [Setup Guides](docs/setup-guides/) - Platform-specific configuration

## 🏗️ Development Status

**Current Phase**: Phase 6 Complete - Core MCP Integration ✅

This plugin is under active development following a 10-phase build plan:

- ✅ **Phase 1**: Foundation Setup (Plugin structure, admin interface)
- ✅ **Phase 2**: Credential Management (Encryption, OAuth)
- ✅ **Phase 3**: API Client Layer (Clarity, GA4, GSC clients)
- ✅ **Phase 4-6**: Abilities Registration (10 tools + 3 resources)
- ⏳ **Phase 7**: Cross-Platform Features (compare-periods, top-content, summary reports)
- ⏳ **Phase 8**: MCP Prompts (5 prompt templates)
- ⏳ **Phase 9**: UI Polish
- ⏳ **Phase 10**: Testing & Documentation

**Beta Status**: Ready for testing with n8n and Claude Desktop!

See [plan.md](plan.md) for the complete build roadmap.

## 🧪 Testing

```bash
# Run all tests
composer test

# Run specific test suite
vendor/bin/phpunit tests/unit/
vendor/bin/phpunit tests/integration/

# Code quality checks
composer phpcs      # Check coding standards
composer phpstan    # Static analysis
```

## 🔒 Security

- **Credential Encryption**: libsodium `crypto_secretbox` with per-site keys
- **OAuth Security**: State parameter CSRF protection, HTTPS-only callbacks
- **WordPress Security**: Nonces, capability checks, input sanitization, output escaping
- **No Credential Logging**: Sensitive data never appears in logs or error messages

Found a security issue? Please email security@example.com (do not open public issues).

## 🤝 Contributing

Contributions are welcome! Please read our [Contributing Guidelines](CONTRIBUTING.md) first.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This plugin is licensed under the GPL v2 or later.

```
Copyright (C) 2024 Marketing Analytics MCP

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

See [LICENSE](LICENSE) for full details.

## 🙏 Credits

Built with:
- [WordPress Abilities API](https://github.com/WordPress/abilities-api)
- [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter)
- [Google API PHP Client](https://github.com/googleapis/google-api-php-client)
- [Guzzle HTTP Client](https://github.com/guzzle/guzzle)

## 📧 Support

- **Issues**: [GitHub Issues](https://github.com/yourusername/marketing-analytics-chat/issues)
- **Discussions**: [GitHub Discussions](https://github.com/yourusername/marketing-analytics-chat/discussions)
- **Documentation**: [docs/](docs/)

---

Made with ❤️ for the WordPress and AI communities
