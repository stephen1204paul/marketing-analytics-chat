# STDIO Transport for WordPress MCP Server

This guide explains how to use STDIO (Standard Input/Output) transport with the WordPress MCP Adapter instead of HTTP.

## Overview

MCP supports two transport types:
- **HTTP/SSE**: For remote connections (recommended for n8n, web clients)
- **STDIO**: For local command-line connections (recommended for Claude Desktop, local development)

## STDIO vs HTTP

| Feature | HTTP Transport | STDIO Transport |
|---------|----------------|-----------------|
| **Use Case** | Remote connections, n8n workflows | Local development, Claude Desktop |
| **Performance** | Network latency | Direct process communication (faster) |
| **Authentication** | HTTP headers (Basic Auth) | WordPress user context via WP-CLI |
| **Port Required** | Yes (443 for HTTPS) | No |
| **Firewall** | Must allow connections | Not needed |
| **Best For** | Production, n8n, web APIs | Local testing, Claude Desktop |

## STDIO Setup via WP-CLI

### Prerequisites

1. **WP-CLI installed** on your server or local machine
2. **WordPress MCP Adapter** plugin activated
3. **Marketing Analytics MCP** plugin activated
4. **SSH access** to server (if remote)

### Basic STDIO Commands

#### 1. List Available MCP Servers

```bash
wp mcp-adapter list
```

**Expected output:**
```
Server Name: mcp-adapter-default-server
Description: Default MCP server exposing all registered abilities
```

#### 2. Test Tool Listing via STDIO

```bash
echo '{"jsonrpc":"2.0","method":"tools/list","id":1}' | \
  wp mcp-adapter serve --server=mcp-adapter-default-server --user=stephen
```

**Expected output:**
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
      }
      // ... 9 more tools
    ]
  }
}
```

#### 3. Execute a Tool via STDIO

```bash
echo '{
  "jsonrpc":"2.0",
  "method":"tools/call",
  "params":{
    "name":"marketing-analytics/get-ga4-metrics",
    "arguments":{
      "metrics":["activeUsers","sessions"],
      "date_range":"7daysAgo"
    }
  },
  "id":2
}' | wp mcp-adapter serve --server=mcp-adapter-default-server --user=stephen
```

#### 4. Get Resources via STDIO

```bash
echo '{"jsonrpc":"2.0","method":"resources/list","id":3}' | \
  wp mcp-adapter serve --server=mcp-adapter-default-server --user=stephen
```

## STDIO Configuration for n8n

**⚠️ Important:** n8n's MCP Client Tool doesn't directly support STDIO transport. STDIO requires a local command-line process, while n8n runs as a web service.

### Workaround Options:

#### Option 1: Use HTTP Transport (Recommended for n8n)

Continue using HTTP as documented in the main guide. This is the standard approach for n8n.

#### Option 2: Create STDIO-to-HTTP Bridge (Advanced)

If you need STDIO for specific reasons, create a local bridge:

```bash
#!/bin/bash
# mcp-stdio-bridge.sh

while IFS= read -r line; do
    # Read JSON-RPC from stdin
    # Forward to WordPress via WP-CLI
    echo "$line" | wp mcp-adapter serve --server=mcp-adapter-default-server --user=stephen --path=/var/www/html/wp_875857f0
done
```

Then configure n8n to call this script (requires n8n to have SSH access to WordPress server).

## STDIO Configuration for Claude Desktop

Claude Desktop has native STDIO support. Configure it to connect to your WordPress MCP server:

### Remote Server Setup

**1. Create SSH wrapper script on your local machine:**

```bash
#!/bin/bash
# ~/mcp-servers/wordpress-marketing.sh

ssh user@demo.specflux.com "cd /var/www/html/wp_875857f0 && wp mcp-adapter serve --server=mcp-adapter-default-server --user=stephen"
```

Make executable:
```bash
chmod +x ~/mcp-servers/wordpress-marketing.sh
```

**2. Configure Claude Desktop:**

Edit `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS) or equivalent:

```json
{
  "mcpServers": {
    "wordpress-marketing": {
      "command": "/Users/yourusername/mcp-servers/wordpress-marketing.sh",
      "args": [],
      "env": {}
    }
  }
}
```

### Local WordPress Setup

If WordPress is running locally (e.g., Local by Flywheel, MAMP, Docker):

**1. Find your WP-CLI path:**

```bash
which wp
# Output: /usr/local/bin/wp
```

**2. Configure Claude Desktop:**

```json
{
  "mcpServers": {
    "wordpress-marketing": {
      "command": "/usr/local/bin/wp",
      "args": [
        "mcp-adapter",
        "serve",
        "--server=mcp-adapter-default-server",
        "--user=stephen",
        "--path=/Users/yourname/Local Sites/mysite/app/public"
      ],
      "env": {}
    }
  }
}
```

### Docker WordPress Setup

If using Docker:

```json
{
  "mcpServers": {
    "wordpress-marketing": {
      "command": "docker",
      "args": [
        "exec",
        "-i",
        "wordpress_container",
        "wp",
        "mcp-adapter",
        "serve",
        "--server=mcp-adapter-default-server",
        "--user=stephen",
        "--allow-root"
      ],
      "env": {}
    }
  }
}
```

## Testing STDIO Connection

### Test 1: Basic Connection

```bash
echo '{"jsonrpc":"2.0","method":"initialize","params":{},"id":1}' | \
  wp mcp-adapter serve --server=mcp-adapter-default-server --user=stephen
```

**Expected:** Server info and capabilities

### Test 2: List All Tools

```bash
echo '{"jsonrpc":"2.0","method":"tools/list","id":1}' | \
  wp mcp-adapter serve --server=mcp-adapter-default-server --user=stephen
```

**Expected:** 10 marketing analytics tools

### Test 3: Interactive Session

```bash
# Start interactive MCP session
wp mcp-adapter serve --server=mcp-adapter-default-server --user=stephen

# Then paste JSON-RPC commands:
{"jsonrpc":"2.0","method":"tools/list","id":1}
{"jsonrpc":"2.0","method":"resources/list","id":2}
```

## Debugging STDIO Connection

### Enable WP-CLI Debug Mode

```bash
wp mcp-adapter serve --server=mcp-adapter-default-server --user=stephen --debug
```

### Check for Errors

```bash
# Run with verbose output
echo '{"jsonrpc":"2.0","method":"tools/list","id":1}' | \
  wp mcp-adapter serve --server=mcp-adapter-default-server --user=stephen 2>&1 | tee mcp-debug.log
```

### Common STDIO Issues

| Issue | Solution |
|-------|----------|
| `Error: User doesn't exist` | Use valid WordPress username in `--user` flag |
| `Command not found: wp` | Install WP-CLI or provide full path |
| `Permission denied` | Check file permissions, use `--allow-root` if needed |
| `Server not found` | Verify MCP Adapter plugin is activated |
| `No tools returned` | Check marketing-mcp plugin is active and registered abilities |

## Performance Comparison

**HTTP Transport:**
```bash
time curl -X POST https://demo.specflux.com/wp-json/mcp/mcp-adapter-default-server \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic ..." \
  -H "Mcp-Session-Id: test" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}'
# ~200-500ms (network + processing)
```

**STDIO Transport:**
```bash
time echo '{"jsonrpc":"2.0","method":"tools/list","id":1}' | \
  wp mcp-adapter serve --server=mcp-adapter-default-server --user=stephen
# ~50-150ms (processing only, no network)
```

## When to Use STDIO

✅ **Use STDIO for:**
- Claude Desktop integration
- Local development and testing
- Fastest possible response times
- No network/firewall concerns
- Direct CLI access available

❌ **Don't use STDIO for:**
- n8n workflows (use HTTP instead)
- Remote web services
- Multiple concurrent clients
- Production deployments without CLI access
- When you need HTTP-based authentication

## Advanced: STDIO with Remote SSH

### Setup SSH Key Authentication

```bash
# Generate key if needed
ssh-keygen -t ed25519 -C "mcp-wordpress"

# Copy to server
ssh-copy-id user@demo.specflux.com
```

### Create Wrapper Script

```bash
#!/bin/bash
# ~/mcp-servers/wordpress-remote.sh

# SSH to remote server and run WP-CLI command
ssh -o ControlMaster=auto \
    -o ControlPath=~/.ssh/mcp-wordpress-%r@%h:%p \
    -o ControlPersist=1m \
    user@demo.specflux.com \
    "cd /var/www/html/wp_875857f0 && sudo -u www-data wp mcp-adapter serve --server=mcp-adapter-default-server --user=stephen"
```

The `ControlMaster` options keep SSH connection alive for faster subsequent requests.

## Comparison: HTTP vs STDIO for Your Setup

### Your Current Setup (demo.specflux.com)

**HTTP Transport (Current):**
```
n8n → HTTPS → Cloudflare → WordPress → MCP Adapter
```
- ✅ Works remotely
- ✅ No SSH needed
- ✅ Standard n8n integration
- ⚠️ Network latency

**STDIO Transport (Alternative):**
```
Claude Desktop → SSH → WP-CLI → MCP Adapter
```
- ✅ Faster (no network)
- ✅ Direct execution
- ❌ Requires SSH access
- ❌ Not compatible with n8n MCP Client Tool

## Recommendation for n8n

**Continue using HTTP transport** as you're currently doing. STDIO is not suitable for n8n because:

1. n8n MCP Client Tool expects HTTP/SSE endpoints
2. n8n runs as a web service, not a CLI tool
3. HTTP transport scales better for multiple workflows
4. Your server is remote (demo.specflux.com), HTTP is more appropriate

**Use STDIO only if:**
- Connecting Claude Desktop to your WordPress MCP server
- Running local development tests
- Need to debug MCP responses quickly via command line

## Quick Reference Commands

```bash
# List servers
wp mcp-adapter list

# List tools
echo '{"jsonrpc":"2.0","method":"tools/list","id":1}' | \
  wp mcp-adapter serve --server=mcp-adapter-default-server --user=stephen

# Call a tool
echo '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"marketing-analytics/get-ga4-metrics","arguments":{"metrics":["activeUsers"],"date_range":"7daysAgo"}},"id":2}' | \
  wp mcp-adapter serve --server=mcp-adapter-default-server --user=stephen

# List resources
echo '{"jsonrpc":"2.0","method":"resources/list","id":3}' | \
  wp mcp-adapter serve --server=mcp-adapter-default-server --user=stephen

# Get resource
echo '{"jsonrpc":"2.0","method":"resources/read","params":{"uri":"ga4://overview"},"id":4}' | \
  wp mcp-adapter serve --server=mcp-adapter-default-server --user=stephen
```

## Next Steps

- **For n8n**: Continue using HTTP transport (see [N8N_INTEGRATION.md](N8N_INTEGRATION.md))
- **For Claude Desktop**: Use STDIO configuration above
- **For local testing**: Use WP-CLI commands for quick debugging

## Additional Resources

- [MCP STDIO Specification](https://spec.modelcontextprotocol.io/specification/basic/transports/#stdio)
- [WP-CLI Documentation](https://wp-cli.org/)
- [Claude Desktop MCP Configuration](https://docs.anthropic.com/claude/docs/mcp)
