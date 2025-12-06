# n8n Integration Guide

This guide explains how to connect the WordPress Marketing Analytics MCP Plugin to n8n AI Agent workflows using the MCP Client Tool.

## Overview

The Marketing Analytics MCP plugin exposes analytics data via the Model Context Protocol (MCP) through WordPress REST API. n8n's MCP Client Tool can connect to this endpoint to use the analytics tools in AI agent workflows.

## Architecture

```
┌─────────────────┐         ┌──────────────────────┐         ┌─────────────────────┐
│                 │         │                      │         │                     │
│  n8n AI Agent   │────────▶│  WordPress MCP       │────────▶│  Analytics APIs     │
│  + MCP Client   │  HTTP   │  Adapter Endpoint    │         │  (Clarity/GA4/GSC)  │
│                 │◀────────│  (REST API)          │◀────────│                     │
└─────────────────┘         └──────────────────────┘         └─────────────────────┘
```

## Prerequisites

### WordPress Site Requirements

1. **WordPress 6.0+** (6.9+ recommended for native Abilities API)
2. **PHP 8.1+**
3. **Marketing Analytics MCP Plugin** installed and activated
4. **HTTPS enabled** (required for secure authentication)
5. **Permalinks enabled** (for REST API access)

### n8n Requirements

1. **n8n instance** (cloud or self-hosted)
2. **MCP Client Tool node** available (v1.0+)
3. **AI Agent node** configured
4. **Environment variable**: `N8N_COMMUNITY_PACKAGES_ALLOW_TOOL_USAGE=true`

## Step 1: Configure WordPress

### 1.1 Install the Plugin

Upload and activate the plugin:
1. Upload `marketing-analytics-chat-v1.0.0.zip` to WordPress
2. Navigate to **Plugins → Add New → Upload Plugin**
3. Activate the plugin

### 1.2 Create Application Password

WordPress Application Passwords provide secure API authentication:

1. Go to **Users → Your Profile**
2. Scroll to **Application Passwords** section
3. Enter name: `n8n MCP Client`
4. Click **Add New Application Password**
5. **Copy the generated password** (format: `xxxx xxxx xxxx xxxx xxxx xxxx`)
6. Remove spaces for use in n8n: `xxxxxxxxxxxxxxxxxxxxxxxx`

### 1.3 Configure Analytics Credentials

Configure credentials for the platforms you want to use:

1. Go to **Marketing Analytics → Connections**
2. Configure each platform:

**Microsoft Clarity:**
```
Project ID: your-clarity-project-id
API Token: your-api-token
```

**Google Analytics 4:**
- Click "Connect with Google OAuth"
- Authorize access
- Select property ID

**Google Search Console:**
- Click "Connect with Google OAuth"
- Authorize access
- Enter site URL

3. Test each connection using the "Test Connection" button

### 1.4 Verify MCP Endpoint

Your MCP endpoint URL will be:
```
https://your-wordpress-site.com/wp-json/mcp/mcp-adapter-default-server
```

Test the endpoint (should return MCP server info):
```bash
curl -X POST https://your-site.com/wp-json/mcp/mcp-adapter-default-server \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic $(echo -n 'username:app-password' | base64)" \
  -H "Mcp-Session-Id: test-session-123" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}'
```

**Important:** The `Mcp-Session-Id` header is required by the MCP protocol. Use any unique identifier (UUID recommended for production).

## Step 2: Configure n8n

### 2.1 Enable MCP Tool Usage

Set environment variable in your n8n instance:

**Docker:**
```yaml
# docker-compose.yml
services:
  n8n:
    environment:
      - N8N_COMMUNITY_PACKAGES_ALLOW_TOOL_USAGE=true
```

**Self-hosted:**
```bash
export N8N_COMMUNITY_PACKAGES_ALLOW_TOOL_USAGE=true
```

**n8n Cloud:**
- Contact support or check settings panel for MCP configuration

### 2.2 Create MCP Client Credential

In n8n:

1. Go to **Settings → Credentials**
2. Create new credential of type **MCP Client**
3. Configure authentication:

**Option A: Header Authentication (Recommended)**
```
Authentication: Header Auth
Header 1:
  Name: Authorization
  Value: Basic <base64(username:app-password)>
Header 2:
  Name: Mcp-Session-Id
  Value: {{$execution.id}} (or any unique ID)
```

To generate the Authorization header value:
```bash
echo -n 'admin:xxxx xxxx xxxx xxxx xxxx xxxx' | base64
# Output: YWRtaW46eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eA==
```

Then use:
```
Authorization: Basic YWRtaW46eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eA==
Mcp-Session-Id: {{$execution.id}}
```

**Note:** n8n's `{{$execution.id}}` provides a unique ID per workflow execution. You can also use a static value like `"n8n-session"`.

**Option B: Bearer Token** (if your WordPress setup uses JWT)
```
Authentication: Bearer Token
Token: your-jwt-token
```

### 2.3 Add MCP Client Node to Workflow

1. Create or open an n8n workflow
2. Add **MCP Client Tool** node
3. Configure the node:

**Basic Settings:**
```
Credentials: [Select your MCP credential]
Endpoint URL: https://your-site.com/wp-json/mcp/mcp-adapter-default-server
Transport: HTTP (or SSE if supported)
Tools to Include: Selected (or All)
```

**Selected Tools** (choose from):
- `marketing-analytics/get-clarity-insights`
- `marketing-analytics/get-clarity-recordings`
- `marketing-analytics/analyze-clarity-heatmaps`
- `marketing-analytics/get-ga4-metrics`
- `marketing-analytics/get-ga4-events`
- `marketing-analytics/get-ga4-realtime`
- `marketing-analytics/get-traffic-sources`
- `marketing-analytics/get-search-performance`
- `marketing-analytics/get-top-queries`
- `marketing-analytics/get-indexing-status`

## Step 3: Create AI Agent Workflow

### 3.1 Basic Workflow Structure

```
┌──────────────┐     ┌──────────────┐     ┌─────────────────┐
│   Trigger    │────▶│  AI Agent    │────▶│  Response       │
│ (Webhook/    │     │  (OpenAI/    │     │  (Format/Send)  │
│  Chat)       │     │  Anthropic)  │     │                 │
└──────────────┘     └──────────────┘     └─────────────────┘
                            │
                            ▼
                     ┌──────────────┐
                     │  MCP Client  │
                     │  Tool        │
                     └──────────────┘
```

### 3.2 Example Workflow Configuration

**1. Webhook Trigger Node**
```json
{
  "method": "POST",
  "path": "analytics-chat"
}
```

**2. AI Agent Node**
```json
{
  "model": "gpt-4",
  "systemMessage": "You are a marketing analytics assistant with access to Clarity, Google Analytics 4, and Search Console data. Help users analyze their website performance.",
  "tools": ["MCP Client Tool"]
}
```

**3. MCP Client Tool Node**
```json
{
  "credential": "WordPress MCP",
  "endpoint": "https://your-site.com/wp-json/mcp/mcp-adapter-default-server",
  "toolsToInclude": "selected",
  "selectedTools": [
    "marketing-analytics/get-ga4-metrics",
    "marketing-analytics/get-search-performance",
    "marketing-analytics/get-clarity-insights"
  ]
}
```

### 3.3 Example Use Cases

**Use Case 1: Weekly Traffic Report**
```
User Query: "Give me a summary of last week's traffic from GA4"

AI Agent:
1. Calls: marketing-analytics/get-ga4-metrics
   - metrics: ["activeUsers", "sessions", "pageviews"]
   - date_range: "7daysAgo"
   - dimensions: ["date"]

2. Formats response into readable report
```

**Use Case 2: SEO Performance Analysis**
```
User Query: "What are my top performing pages in search?"

AI Agent:
1. Calls: marketing-analytics/get-search-performance
   - dimensions: ["page"]
   - date_range: "28daysAgo"
   - limit: 10

2. Calls: marketing-analytics/get-top-queries
   - limit: 10

3. Combines data and provides insights
```

**Use Case 3: User Experience Analysis**
```
User Query: "How are users interacting with my homepage?"

AI Agent:
1. Calls: marketing-analytics/get-clarity-insights
   - num_of_days: 3
   - dimension1: "Page"

2. Calls: marketing-analytics/analyze-clarity-heatmaps
   - url: "/"

3. Provides UX recommendations
```

## Step 4: Testing

### 4.1 Test MCP Connection

1. Execute the workflow with a simple query:
```
User: "Are you connected to the analytics tools?"
Expected: AI confirms access to Clarity, GA4, and GSC tools
```

### 4.2 Test Individual Tools

Test each tool separately:

```
User: "Get GA4 metrics for the last 7 days"
Expected: AI calls get-ga4-metrics and returns data
```

```
User: "Show me top search queries"
Expected: AI calls get-top-queries and returns results
```

### 4.3 Debugging

**If connection fails:**

1. **Check WordPress Logs**: `wp-content/debug.log`
2. **Check n8n Execution**: View execution details in n8n
3. **Verify Authentication**: Test curl command from Step 1.4
4. **Check Firewall**: Ensure n8n can reach WordPress site
5. **Verify HTTPS**: MCP requires secure connections

**Common Issues:**

| Error | Solution |
|-------|----------|
| `401 Unauthorized` | Regenerate Application Password, check base64 encoding |
| `404 Not Found` | Verify REST API endpoint, check permalinks |
| `500 Server Error` | Check WordPress error logs, verify plugin activation |
| `CORS Error` | Add n8n domain to WordPress CORS whitelist |
| `Tool not found` | Verify analytics platform credentials configured |

## Step 5: Advanced Configuration

### 5.1 Custom Tool Prompts

Create reusable prompts in your AI agent:

```
"Analyze traffic drop": {
  "prompt": "Check GA4 metrics for the last 30 days, compare with previous period, identify any significant drops, check Search Console for indexing issues, and provide recommendations.",
  "tools": ["get-ga4-metrics", "get-search-performance", "get-indexing-status"]
}
```

### 5.2 Scheduled Reports

Create scheduled workflows:

```
1. Schedule Trigger (weekly, Monday 9 AM)
2. MCP Client calls multiple tools
3. Format data into report
4. Send via email/Slack/Discord
```

### 5.3 Multi-Agent Workflows

Chain multiple agents with different tool access:

```
Agent 1: Data Collection (MCP tools access)
   ↓
Agent 2: Analysis (no tool access, just analysis)
   ↓
Agent 3: Reporting (formatting and sending)
```

## Available MCP Tools Reference

### Microsoft Clarity Tools

**get-clarity-insights**
```json
{
  "num_of_days": 1-3,
  "dimension1": "Device|Country|Browser|OS|Page",
  "dimension2": "Country|Browser|OS|Page" // optional
}
```
Returns: Session metrics, user behavior, device breakdown

**get-clarity-recordings**
```json
{
  "date": "YYYY-MM-DD",
  "limit": 10-100
}
```
Returns: Session recording URLs with metadata

**analyze-clarity-heatmaps**
```json
{
  "url": "/page-path",
  "device_type": "Desktop|Mobile|Tablet" // optional
}
```
Returns: Click and scroll heatmap data

### Google Analytics 4 Tools

**get-ga4-metrics**
```json
{
  "metrics": ["activeUsers", "sessions", "pageviews", "bounceRate"],
  "dimensions": ["date", "deviceCategory", "country"],
  "date_range": "7daysAgo|30daysAgo|90daysAgo",
  "limit": 100
}
```

**get-ga4-events**
```json
{
  "event_name": "page_view|click|scroll",
  "date_range": "7daysAgo",
  "limit": 100
}
```

**get-ga4-realtime**
```json
{
  "minutes": 5-30
}
```

**get-traffic-sources**
```json
{
  "date_range": "7daysAgo",
  "group_by": "source|medium|campaign"
}
```

### Google Search Console Tools

**get-search-performance**
```json
{
  "date_range": "7daysAgo|28daysAgo",
  "dimensions": ["query", "page", "country", "device"],
  "limit": 100
}
```

**get-top-queries**
```json
{
  "date_range": "7daysAgo",
  "limit": 10-100
}
```

**get-indexing-status**
```json
{
  "url": "/page-path" // optional, if blank checks site-wide
}
```

## Security Considerations

1. **Use Application Passwords**: Never use main WordPress password
2. **HTTPS Only**: Enforce SSL/TLS for all connections
3. **Limit Capabilities**: Create dedicated WordPress user with `manage_options` capability
4. **Rate Limiting**: WordPress MCP respects API rate limits automatically
5. **IP Whitelisting**: Consider restricting REST API access to n8n IP
6. **Audit Logs**: Monitor API usage via WordPress logs

## Troubleshooting

### Connection Issues

```bash
# Test WordPress REST API
curl https://your-site.com/wp-json/

# Test MCP endpoint authentication
curl -X POST https://your-site.com/wp-json/mcp/mcp-adapter-default-server \
  -H "Authorization: Basic $(echo -n 'user:pass' | base64)" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}'
```

### Tool Execution Issues

Check WordPress logs:
```bash
tail -f wp-content/debug.log
```

Enable WordPress debugging:
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Support and Resources

- **Plugin Documentation**: `/docs/README.md`
- **MCP Specification**: https://spec.modelcontextprotocol.io/
- **n8n MCP Docs**: https://docs.n8n.io/integrations/builtin/cluster-nodes/sub-nodes/n8n-nodes-langchain.toolmcp/
- **WordPress REST API**: https://developer.wordpress.org/rest-api/

## Example n8n Workflow JSON

```json
{
  "name": "Marketing Analytics AI Assistant",
  "nodes": [
    {
      "name": "Webhook",
      "type": "n8n-nodes-base.webhook",
      "parameters": {
        "path": "analytics-chat",
        "responseMode": "responseNode",
        "options": {}
      }
    },
    {
      "name": "AI Agent",
      "type": "@n8n/n8n-nodes-langchain.agent",
      "parameters": {
        "model": "gpt-4",
        "systemMessage": "You are a marketing analytics expert...",
        "promptType": "auto"
      }
    },
    {
      "name": "MCP Client Tool",
      "type": "@n8n/n8n-nodes-langchain.toolMcp",
      "parameters": {
        "authentication": "mcpClientApi",
        "resource": "tool",
        "endpoint": "https://your-site.com/wp-json/mcp/mcp-adapter-default-server"
      },
      "credentials": {
        "mcpClientApi": {
          "id": "1",
          "name": "WordPress MCP"
        }
      }
    }
  ]
}
```

## Next Steps

1. Configure analytics platform credentials in WordPress
2. Test individual tools using WP-CLI or curl
3. Set up n8n workflow with basic queries
4. Expand to more complex multi-tool workflows
5. Set up scheduled reports and monitoring
