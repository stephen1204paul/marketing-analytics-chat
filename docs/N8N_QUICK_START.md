# n8n Quick Start Guide

**5-Minute Setup**: Connect WordPress Marketing Analytics MCP to n8n AI Agent

## Prerequisites Checklist

- [ ] **WordPress MCP Adapter installed and activated** (see [Installation Guide](MCP_ADAPTER_INSTALLATION.md) if missing)
- [ ] WordPress Abilities API installed and activated
- [ ] Marketing Analytics MCP plugin installed and activated
- [ ] Analytics platforms configured (Clarity, GA4, or GSC)
- [ ] WordPress Application Password created
- [ ] n8n instance with MCP support enabled
- [ ] n8n environment variable: `N8N_COMMUNITY_PACKAGES_ALLOW_TOOL_USAGE=true`

> **⚠️ Important**: If you get HTML responses from the MCP endpoint, the WordPress MCP Adapter is missing. See [MCP_ADAPTER_INSTALLATION.md](MCP_ADAPTER_INSTALLATION.md) for fix.

## Step 1: Get WordPress Application Password (2 min)

1. WordPress Admin → **Users → Your Profile**
2. Scroll to **Application Passwords**
3. Application Name: `n8n MCP Client`
4. Click **Add New Application Password**
5. Copy password (remove spaces): `xxxxxxxxxxxxxxxxxxxxxxxx`

## Step 2: Prepare Authentication Header (1 min)

Generate your Authorization header:

```bash
# Replace with your WordPress username and application password
echo -n 'admin:xxxx xxxx xxxx xxxx xxxx xxxx' | base64
```

Output example: `YWRtaW46eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eA==`

Your full header will be:
```
Authorization: Basic YWRtaW46eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eA==
```

## Step 3: Test MCP Endpoint (1 min)

Verify the endpoint works:

```bash
curl -X POST https://your-site.com/wp-json/mcp/mcp-adapter-default-server \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic YOUR_BASE64_STRING" \
  -H "Mcp-Session-Id: test-session-123" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}'
```

Expected response: JSON with list of 10 available tools

**Note:** The `Mcp-Session-Id` header is required by the MCP protocol.

## Step 4: Configure n8n (2 min)

### Create Credential

1. n8n → **Credentials → New Credential**
2. Type: **MCP Client** (or Header Auth)
3. Configuration:
   ```
   Authentication: Header Auth
   Header Name: Authorization
   Header Value: Basic YOUR_BASE64_STRING
   ```
4. Test and Save

### Create Workflow

1. **New Workflow** → Add nodes:

```
Webhook → AI Agent → Respond to Webhook
              ↓
        MCP Client Tool
```

2. **Configure MCP Client Tool**:
   - Credential: [Select your credential]
   - Endpoint: `https://your-site.com/wp-json/mcp/mcp-adapter-default-server`
   - Tools: Select All (or choose specific tools)

3. **Configure AI Agent**:
   - Model: OpenAI GPT-4 / Anthropic Claude
   - System Message: "You are a marketing analytics assistant with access to website analytics data."
   - Tools: Connect MCP Client Tool

4. **Save & Activate** workflow

## Step 5: Test (30 seconds)

Send a test request to your webhook:

```bash
curl -X POST https://your-n8n.com/webhook/analytics-chat \
  -H "Content-Type: application/json" \
  -d '{"message": "Show me GA4 metrics for the last 7 days"}'
```

Or use the n8n test button and enter:
```
"Show me my top search queries from Google Search Console"
```

## Available Tools Quick Reference

| Tool | Platform | Purpose |
|------|----------|---------|
| `get-clarity-insights` | Clarity | User behavior analytics |
| `get-clarity-recordings` | Clarity | Session recordings |
| `analyze-clarity-heatmaps` | Clarity | Click/scroll heatmaps |
| `get-ga4-metrics` | GA4 | Traffic & engagement metrics |
| `get-ga4-events` | GA4 | Event tracking data |
| `get-ga4-realtime` | GA4 | Real-time visitors |
| `get-traffic-sources` | GA4 | Traffic source breakdown |
| `get-search-performance` | GSC | Search impressions/clicks |
| `get-top-queries` | GSC | Top performing keywords |
| `get-indexing-status` | GSC | Page indexing status |

## Example Queries to Test

### Basic Queries
```
"What's my current traffic?"
"Show me top search queries"
"How many users are online right now?"
```

### Advanced Queries
```
"Compare GA4 metrics from last week vs the week before"
"Show me which pages have indexing issues"
"Analyze user behavior on my homepage using Clarity"
```

### Report Queries
```
"Generate a weekly traffic report"
"What are my best performing content pieces?"
"Identify any traffic anomalies in the last 30 days"
```

## Common Issues & Quick Fixes

| Issue | Fix |
|-------|-----|
| `401 Unauthorized` | Regenerate Application Password, verify base64 encoding |
| `404 Not Found` | Check endpoint URL, verify permalinks enabled |
| `Empty response` | Check analytics platform credentials in WordPress |
| `Tool not found` | Verify platform connections tested successfully |
| `Connection timeout` | Check firewall, ensure n8n can reach WordPress |

## Next Steps

1. ✅ **Test each platform** separately
2. ✅ **Create scheduled reports** (daily/weekly)
3. ✅ **Build custom prompts** for common queries
4. ✅ **Set up alerts** for anomalies
5. ✅ **Integrate with Slack/Discord** for notifications

## Full Documentation

- Complete setup: [`/docs/N8N_INTEGRATION.md`](./N8N_INTEGRATION.md)
- Plugin docs: [`/README.md`](../README.md)
- API reference: [`/docs/API_REFERENCE.md`](./API_REFERENCE.md)

## Support

If you encounter issues:

1. Check WordPress debug logs: `wp-content/debug.log`
2. Test endpoint with curl command above
3. Verify plugin activated and credentials configured
4. Check n8n execution logs for error details

---

**Ready in 5 minutes!** Follow these steps and you'll have AI-powered marketing analytics at your fingertips.
