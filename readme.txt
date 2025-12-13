=== Marketing Analytics Chat ===
Contributors: stephenpaulsamynathan
Donate link: https://www.specflux.com/
Tags: marketing analytics, ai, chat, mcp
Requires at least: 6.9
Tested up to: 6.9
Stable tag: 0.1.2
Requires PHP: 8.1
Requires Plugins: mcp-adapter
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Chat with your marketing analytics using AI. Connect Google Analytics 4, Search Console, Microsoft Clarity, Meta, and more to get instant insights.

== Description ==

Marketing Analytics Chat lets you have conversations with your marketing data using AI. Connect your analytics platforms and ask questions in plain English to get instant insights and recommendations.

= Supported Platforms =

* **Google Analytics 4** - Traffic metrics, user behavior, conversions, real-time data
* **Google Search Console** - Search performance, queries, indexing status
* **Microsoft Clarity** - Session recordings, heatmaps, user behavior insights
* **Meta Business Suite** - Facebook and Instagram analytics
* **DataForSEO** - SEO metrics and keyword data

= Key Features =

* **AI-Powered Chat** - Ask questions about your analytics in plain English
* **Multi-Platform Support** - Connect all your marketing data sources
* **Secure Credentials** - OAuth 2.0 and encrypted API key storage
* **Smart Caching** - Reduce API calls with intelligent caching
* **Cross-Platform Analysis** - Compare data across all connected platforms

= How It Works =

1. Connect your analytics platforms via OAuth or API keys
2. Open the chat interface in your WordPress admin
3. Ask questions like "How did my traffic change this week?"
4. Get AI-powered insights and recommendations

= Requirements =

* WordPress 6.9 or higher (includes Abilities API in core)
* MCP Adapter plugin (from WordPress.org)
* PHP 8.1 or higher
* SSL certificate (HTTPS) for OAuth connections
* PHP extensions: json, curl, openssl, sodium

= External Services =

This plugin connects to the following third-party services:

* **Google APIs** - For GA4 and Search Console data
* **Microsoft Clarity API** - For behavior analytics
* **Meta Graph API** - For Facebook/Instagram data
* **DataForSEO API** - For SEO metrics
* **Anthropic API** - For AI chat functionality (optional)

== Installation ==

= Prerequisites =

1. Ensure you are running WordPress 6.9 or higher (includes Abilities API)
2. Install and activate the "MCP Adapter" plugin from WordPress.org

= Plugin Installation =

1. Upload the `marketing-analytics-chat` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Marketing Analytics > Settings > Google API to configure OAuth credentials
4. Connect your analytics platforms from the Connections page
5. Configure your MCP client (e.g., Claude Desktop) to use the plugin endpoint

= Configuring MCP Client =

Add this to your Claude Desktop configuration:

`{
  "mcpServers": {
    "wordpress-marketing": {
      "transport": {
        "type": "http",
        "url": "https://your-site.com/wp-json/mcp/mcp-adapter-default-server",
        "headers": {
          "Authorization": "Basic base64(username:application-password)"
        }
      }
    }
  }
}`

== Frequently Asked Questions ==

= How does the AI chat work? =

The plugin connects your analytics platforms and uses AI (Claude) to answer questions about your data. Just type a question like "What are my top traffic sources?" and get an instant answer.

= Do I need to pay for API access? =

The plugin itself is free. However, you may need API access for:
* Google Analytics and Search Console - Free with Google Cloud account
* Microsoft Clarity - Free
* DataForSEO - Paid service
* Anthropic Claude API - Paid for AI chat functionality

= Is my data secure? =

Yes. All API credentials are encrypted using libsodium before storage. OAuth tokens are handled securely with CSRF protection. No analytics data is stored permanently - it's fetched on demand.

= What can I ask the AI? =

You can ask questions like:
* "How did my traffic change compared to last week?"
* "What are my top performing pages?"
* "Show me my search console queries"
* "Compare my GA4 and Clarity data"

= What WordPress versions are supported? =

WordPress 6.9 and higher is required. The plugin uses the Abilities API (included in WordPress 6.9 core) and requires the MCP Adapter plugin.

== Screenshots ==

1. Dashboard overview with connected platforms
2. AI Assistant chat interface
3. Google Analytics 4 connection setup
4. Settings page with API configuration

== Changelog ==

= 0.1.2 - 2025-12-13 =
* Release version 0.1.2

= 0.1.1 - 2025-12-13 =
* Release version 0.1.1

= 0.1.0 - 2025-12-06 =
* Release version 0.1.0

= 1.0.0 =
* Initial release
* AI-powered chat interface for analytics
* Google Analytics 4 integration
* Google Search Console integration
* Microsoft Clarity integration
* Meta Business Suite integration
* DataForSEO integration
* Secure OAuth and credential management
* Cross-platform comparison tools
* Smart caching system

== Upgrade Notice ==

= 1.0.0 =
Initial release. Please backup your site before installing.

== Privacy Policy ==

This plugin:
* Stores encrypted API credentials in your WordPress database
* Connects to third-party analytics services you configure
* Does not track users or send data to the plugin author
* Does not store analytics data permanently (fetched on demand)

For full privacy information, see the plugin documentation.
