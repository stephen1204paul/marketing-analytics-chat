# MCP Tool Cost Tracking & Optimization

## Overview

This document explains how to track and optimize the cost of loading MCP tools in AI chat requests. Claude API charges for input tokens, and each tool definition adds to the input token count.

## How It Works

### 1. Tool Filtering System

The plugin now supports filtering which MCP tools are sent to Claude API:

- **All Tools** (default): Sends all available MCP tools (~13 tools)
- **Microsoft Clarity Tools**: Only Clarity-related tools
- **Google Analytics 4 Tools**: Only GA4-related tools
- **Google Search Console Tools**: Only GSC-related tools

### 2. Token Tracking

Each AI response now displays:
- **Input tokens**: Tokens used for conversation + tools
- **Output tokens**: Tokens generated in response
- **Tool count**: Number of tools sent to Claude
- **Filter status**: Whether tools were filtered or all sent

**Display format:**
```
📊 1,234 in / 567 out 🔧 13 tools
```

If filtered:
```
📊 890 in / 567 out 🔍 4 tools (filtered from 13)
```

## Configuration

### Settings Page

1. Navigate to **Marketing Analytics MCP > Settings**
2. Scroll to **AI Chat Tool Selection**
3. Choose tool categories:
   - ✅ **All Tools** (recommended) - AI can use any tool
   - Or select specific categories to reduce token costs

### Effect on Token Usage

**Example comparison** (approximate):

| Configuration | Tools Sent | Estimated Input Tokens | Cost per Message |
|--------------|------------|----------------------|------------------|
| All Tools    | 13 tools   | ~2,000 + conversation | $0.006 (Sonnet 4) |
| Clarity Only | 4 tools    | ~1,200 + conversation | $0.0036 |
| GA4 Only     | 5 tools    | ~1,400 + conversation | $0.0042 |
| GSC Only     | 4 tools    | ~1,200 + conversation | $0.0036 |

**Claude Sonnet 4 pricing:**
- Input: $3 per million tokens
- Output: $15 per million tokens

## Testing Cost Comparison

### Method 1: Visual Comparison in Chat

1. **Test with All Tools:**
   ```
   Settings > Tool Selection > ✅ All Tools
   Chat > Ask: "What is my website's bounce rate?"
   Observe: "📊 X in / Y out 🔧 13 tools"
   ```

2. **Test with Filtered Tools:**
   ```
   Settings > Tool Selection > ✅ Clarity Only
   Chat > Ask: "What is my website's bounce rate?"
   Observe: "📊 X in / Y out 🔍 4 tools"
   ```

3. **Compare Input Tokens:**
   - Difference in "X in" = token savings per request
   - Multiply by your expected monthly requests
   - Calculate cost savings

### Method 2: WP-CLI Testing

```bash
# Test with all tools (default)
wp option update marketing_analytics_mcp_settings \
  '{"enabled_tool_categories":["all"],"claude_api_key":"sk-ant-..."}' \
  --format=json

# Send test message and check logs
wp eval 'error_log(print_r(get_option("marketing_analytics_mcp_settings"), true));'
```

## Cost Optimization Strategies

### Strategy 1: Category-Based Filtering

**When to use:** You know which analytics platform you'll query most often.

**Example:** If you primarily use Clarity for behavior analysis:
- Enable: ✅ Microsoft Clarity Tools
- Disable: GA4, GSC
- **Savings:** ~40% input tokens
- **Trade-off:** AI can't access GA4 or GSC data

### Strategy 2: Dynamic Filtering (Advanced)

**Future enhancement:** Automatically detect which tools are needed based on user's question.

**Example workflow:**
1. User asks: "What is my bounce rate?"
2. System detects keywords: "bounce rate" → Clarity tool needed
3. Only send Clarity tools to Claude
4. **Savings:** ~40-60% input tokens per request

### Strategy 3: Tool Call Caching

**Future enhancement:** Cache tool definitions across requests in the same conversation.

**Example:**
1. First message: Send all tool definitions (2,000 tokens)
2. Subsequent messages: Reference cached tools (100 tokens)
3. **Savings:** ~95% tool token costs for follow-up messages

## Real-World Cost Analysis

### Scenario: 1,000 messages per month

| Configuration | Input Tokens/Msg | Output Tokens/Msg | Monthly Cost |
|--------------|------------------|-------------------|--------------|
| All Tools (13) | 2,500 avg | 800 avg | $19.50 |
| Clarity Only (4) | 1,700 avg | 800 avg | $17.10 |
| **Savings** | | | **$2.40/month** |

### Scenario: 10,000 messages per month

| Configuration | Input Tokens/Msg | Output Tokens/Msg | Monthly Cost |
|--------------|------------------|-------------------|--------------|
| All Tools (13) | 2,500 avg | 800 avg | $195 |
| Clarity Only (4) | 1,700 avg | 800 avg | $171 |
| **Savings** | | | **$24/month** |

## Recommendations

### For Most Users (< 1,000 msgs/month)
✅ **Use "All Tools"**
- Cost difference is minimal ($2-3/month)
- AI has full flexibility to answer any question
- Better user experience

### For High Volume (> 10,000 msgs/month)
🔍 **Consider Filtering**
- Analyze your actual tool usage patterns
- Filter to most commonly used categories
- Savings can be $20-50/month
- Monitor AI response quality

### For Cost-Sensitive Deployments
💰 **Aggressive Filtering + Monitoring**
- Enable only 1-2 categories at a time
- Review token usage weekly
- Adjust based on user questions
- Consider prompt caching (future)

## Monitoring Token Usage

### Current Capabilities

1. **Per-message tracking:**
   - Visible below each AI response
   - Shows input, output, and tool count

2. **Tool metadata:**
   - Hover over tool count to see details
   - "Filtered from X tools" indicator

### Future Enhancements

1. **Usage dashboard:**
   - Total tokens used per day/week/month
   - Cost breakdown by conversation
   - Tool usage analytics

2. **Cost alerts:**
   - Set budget limits
   - Email notifications when exceeded

3. **A/B testing:**
   - Compare response quality across configurations
   - Automated cost-benefit analysis

## Technical Implementation

### Code Structure

```php
// includes/chat/class-chat-ajax-handler.php

private function filter_tools($tools) {
    $settings = get_option('marketing_analytics_mcp_settings', array());
    $enabled_categories = $settings['enabled_tool_categories'] ?? array('all');

    if (in_array('all', $enabled_categories)) {
        return $tools; // No filtering
    }

    // Filter by category prefix
    $filtered = array();
    foreach ($tools as $tool) {
        if (strpos($tool['name'], 'clarity_') === 0 &&
            in_array('clarity', $enabled_categories)) {
            $filtered[] = $tool;
        }
        // ... other categories
    }

    return $filtered;
}
```

### Response Metadata

```json
{
  "content": "Your bounce rate is 45%...",
  "usage": {
    "input_tokens": 1234,
    "output_tokens": 567
  },
  "tool_metadata": {
    "total_available": 13,
    "tools_sent": 4,
    "filtered": true
  }
}
```

## Troubleshooting

### Issue: Tool count shows 0

**Cause:** MCP server is not running or not connected.

**Solution:**
1. Check MCP server status: `wp mcp list-tools`
2. Verify WordPress MCP Adapter is active
3. Check error logs: `wp-content/debug.log`

### Issue: Filtering not working

**Cause:** Settings not saving or cache issue.

**Solution:**
1. Check settings: `wp option get marketing_analytics_mcp_settings`
2. Clear cache: Settings > Cache Management > Clear All
3. Test with fresh conversation

### Issue: Token count seems high

**Cause:** Long conversation history or complex tools.

**Solution:**
1. Start new conversation to reset history
2. Enable only needed tool categories
3. Use shorter, more focused questions

## Next Steps

1. **Test current implementation:**
   - Send test messages with different configurations
   - Compare token usage in chat interface

2. **Monitor your usage:**
   - Track costs for 1-2 weeks
   - Identify most-used tool categories

3. **Optimize configuration:**
   - Adjust enabled categories based on usage
   - Balance cost vs. functionality

4. **Provide feedback:**
   - Report any issues or suggestions
   - Share your cost optimization results

## References

- [Claude API Pricing](https://www.anthropic.com/pricing)
- [Model Context Protocol Spec](https://github.com/modelcontextprotocol/specification)
- [WordPress MCP Adapter Docs](https://github.com/Automattic/wordpress-mcp-adapter)
