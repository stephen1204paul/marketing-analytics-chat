# WordPress MCP Adapter Installation Guide

## Problem: HTML Response Instead of JSON

If you're getting an HTML response from the MCP endpoint, it means the **WordPress MCP Adapter plugin is not installed or activated** on your WordPress site.

## What You're Seeing

```bash
curl -X POST https://your-site.com/wp-json/mcp/mcp-adapter-default-server \
  -H "Authorization: Basic ..." \
  -H "Mcp-Session-Id: test-session-123" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}'

# Returns: HTML page instead of JSON
```

## Why This Happens

The Marketing Analytics MCP Plugin depends on **two separate WordPress plugins**:

1. **WordPress Abilities API** - Registers abilities/tools
2. **WordPress MCP Adapter** - Exposes abilities via REST API endpoint

Without the MCP Adapter, the `/wp-json/mcp/mcp-adapter-default-server` endpoint doesn't exist, so WordPress returns a 404 page (in HTML).

## Solution: Install WordPress MCP Adapter

### Option 1: Install via Composer (Recommended)

SSH into your WordPress server:

```bash
cd /path/to/wordpress/wp-content/plugins/marketing-analytics-chat

# Install both required packages
composer require wordpress/abilities-api wordpress/mcp-adapter

# Ensure autoloader is optimized
composer dump-autoload --optimize
```

### Option 2: Install as Separate WordPress Plugin

**Step 1: Download MCP Adapter**

```bash
cd /path/to/wordpress/wp-content/plugins/

# Download latest release
wget https://github.com/WordPress/mcp-adapter/archive/refs/heads/trunk.zip -O mcp-adapter.zip

# Extract
unzip mcp-adapter.zip
mv mcp-adapter-trunk mcp-adapter

# Install dependencies
cd mcp-adapter
composer install --no-dev
```

**Step 2: Download Abilities API**

```bash
cd /path/to/wordpress/wp-content/plugins/

# Download Abilities API
wget https://github.com/WordPress/abilities-api/archive/refs/heads/trunk.zip -O abilities-api.zip

# Extract
unzip abilities-api.zip
mv abilities-api-trunk abilities-api

# Install dependencies
cd abilities-api
composer install --no-dev
```

**Step 3: Activate Plugins**

Via WordPress Admin:
1. Go to **Plugins → Installed Plugins**
2. Activate **WordPress Abilities API**
3. Activate **WordPress MCP Adapter**

Via WP-CLI:
```bash
wp plugin activate abilities-api
wp plugin activate mcp-adapter
```

### Option 3: For Beta Testing (Packaged Plugin)

If you're using the packaged `marketing-analytics-chat-v1.0.0.zip` file, you **still need** to install the MCP Adapter separately because it's a WordPress-level dependency.

**Quick Install Script:**

```bash
#!/bin/bash
# install-mcp-adapter.sh

WP_PLUGINS="/path/to/wordpress/wp-content/plugins"

# Install Abilities API
cd "$WP_PLUGINS"
git clone https://github.com/WordPress/abilities-api.git
cd abilities-api
composer install --no-dev

# Install MCP Adapter
cd "$WP_PLUGINS"
git clone https://github.com/WordPress/mcp-adapter.git
cd mcp-adapter
composer install --no-dev

# Activate via WP-CLI
wp plugin activate abilities-api
wp plugin activate mcp-adapter

echo "✅ WordPress MCP Adapter installed and activated"
```

Make executable and run:
```bash
chmod +x install-mcp-adapter.sh
./install-mcp-adapter.sh
```

## Verification

### Step 1: Check Plugin Activation

Via WP-CLI:
```bash
wp plugin list --status=active | grep -E 'abilities-api|mcp-adapter'
```

Expected output:
```
abilities-api     active
mcp-adapter       active
```

Via WordPress Admin:
- Go to **Plugins → Installed Plugins**
- Verify both plugins show as "Active"

### Step 2: Test REST API Endpoint

```bash
curl https://demo.specflux.com/wp-json/mcp/
```

Expected response (JSON):
```json
{
  "namespace": "mcp",
  "routes": {
    "/mcp/mcp-adapter-default-server": {
      "methods": ["POST"],
      "endpoints": [...]
    }
  }
}
```

### Step 3: Test MCP Tools List

```bash
curl -X POST https://demo.specflux.com/wp-json/mcp/mcp-adapter-default-server \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic c3RlcGhlbjpzRXh4IGRaMTcgVEExeCB1MXpsIHVsRWQgdnI3VQ==" \
  -H "Mcp-Session-Id: test-session-123" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}'
```

Expected response (JSON):
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "tools": [
      {
        "name": "marketing-analytics/get-clarity-insights",
        "description": "Get Microsoft Clarity insights...",
        "inputSchema": {...}
      },
      {
        "name": "marketing-analytics/get-ga4-metrics",
        "description": "Get Google Analytics 4 metrics...",
        "inputSchema": {...}
      }
      // ... 8 more tools
    ]
  }
}
```

### Step 4: Test via WP-CLI

```bash
# List available servers
wp mcp-adapter list

# Test tool execution
wp mcp-adapter serve --server=mcp-adapter-default-server --user=admin <<EOF
{"jsonrpc":"2.0","method":"tools/list","id":1}
EOF
```

## Troubleshooting

### Issue: "Class WP\MCP\Core\McpAdapter not found"

**Cause:** MCP Adapter not installed

**Fix:**
```bash
cd wp-content/plugins/marketing-analytics-chat
composer require wordpress/mcp-adapter
```

### Issue: "REST API endpoint returns 404"

**Cause:** Permalinks not configured or MCP Adapter not activated

**Fix 1 - Flush Permalinks:**
```bash
wp rewrite flush
```

Or via WordPress Admin:
1. Go to **Settings → Permalinks**
2. Click "Save Changes" (no need to modify anything)

**Fix 2 - Verify Plugin Active:**
```bash
wp plugin activate mcp-adapter
```

### Issue: "401 Unauthorized"

**Cause:** Invalid Application Password

**Fix:**
1. Regenerate Application Password in WordPress
2. Remove all spaces: `sExx dZ17 TA1x u1zl ulEd vr7U` → `sExxdZ17TA1xu1zlulEdvr7U`
3. Encode correctly:
   ```bash
   echo -n 'stephen:sExxdZ17TA1xu1zlulEdvr7U' | base64
   ```

### Issue: "Empty tools list"

**Cause:** Marketing Analytics MCP plugin not registering abilities

**Fix:**
```bash
# Check if marketing-analytics-chat plugin is active
wp plugin list | grep marketing

# Activate if needed
wp plugin activate marketing-analytics-chat

# Check for PHP errors
tail -f wp-content/debug.log
```

### Issue: "Composer dependencies missing"

**Cause:** Vendor directory not included or composer install not run

**Fix:**
```bash
cd wp-content/plugins/marketing-analytics-chat
composer install --no-dev --optimize-autoloader
```

## System Requirements

| Component | Requirement |
|-----------|-------------|
| PHP | >= 8.1 |
| WordPress | >= 6.0 (6.9+ recommended) |
| Composer | >= 2.0 |
| WP-CLI | >= 2.5 (optional but recommended) |
| HTTPS | Required for production |

## Architecture Diagram

```
┌────────────────────────────────────────────────┐
│         WordPress Installation                  │
│                                                 │
│  ┌──────────────────────────────────────────┐ │
│  │  WordPress Abilities API Plugin          │ │
│  │  - Registers ability system              │ │
│  └──────────────────────────────────────────┘ │
│                      ↓                          │
│  ┌──────────────────────────────────────────┐ │
│  │  Marketing Analytics MCP Plugin          │ │
│  │  - Registers 10 MCP tools                │ │
│  │  - Clarity, GA4, GSC clients             │ │
│  └──────────────────────────────────────────┘ │
│                      ↓                          │
│  ┌──────────────────────────────────────────┐ │
│  │  WordPress MCP Adapter Plugin            │ │
│  │  - Exposes REST API endpoint             │ │
│  │  - /wp-json/mcp/mcp-adapter-*           │ │
│  └──────────────────────────────────────────┘ │
└────────────────────────────────────────────────┘
                       ↓
              REST API (HTTP/JSON-RPC)
                       ↓
┌────────────────────────────────────────────────┐
│              MCP Clients                        │
│  - n8n AI Agent (MCP Client Tool)             │
│  - Claude Desktop                              │
│  - Custom integrations                         │
└────────────────────────────────────────────────┘
```

## Next Steps

After installation:

1. ✅ Verify MCP endpoint responds with JSON
2. ✅ Configure analytics platform credentials
3. ✅ Test each tool via curl or WP-CLI
4. ✅ Connect to n8n using MCP Client Tool
5. ✅ Build AI agent workflows

## Additional Resources

- **MCP Adapter GitHub**: https://github.com/WordPress/mcp-adapter
- **Abilities API GitHub**: https://github.com/WordPress/abilities-api
- **MCP Specification**: https://spec.modelcontextprotocol.io/
- **WordPress REST API Docs**: https://developer.wordpress.org/rest-api/

## Quick Reference Commands

```bash
# Install dependencies
composer require wordpress/abilities-api wordpress/mcp-adapter

# Activate plugins
wp plugin activate abilities-api mcp-adapter marketing-analytics-chat

# Flush permalinks
wp rewrite flush

# Test endpoint
curl -X POST https://your-site.com/wp-json/mcp/mcp-adapter-default-server \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic $(echo -n 'user:pass' | base64)" \
  -H "Mcp-Session-Id: test-session-123" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}'

# Check logs
tail -f wp-content/debug.log
```

## Support

If you encounter issues after following this guide:

1. Check WordPress error logs
2. Verify all three plugins are activated
3. Ensure composer dependencies are installed
4. Test REST API accessibility
5. Verify Application Password is correct

For debugging, enable WordPress debug mode:
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```
