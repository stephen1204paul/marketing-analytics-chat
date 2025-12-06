# Custom MCP Prompts Guide

## Overview

The Custom Prompts feature allows you to create dynamic, reusable workflow templates that guide AI assistants through complex marketing analytics tasks. Instead of hardcoding prompts, users can now create, manage, and customize their own prompts through the WordPress admin interface.

## What are MCP Prompts?

MCP Prompts are **workflow templates** that provide step-by-step instructions to AI assistants. Unlike tools (which execute actions) or resources (which provide data), prompts guide the AI through multi-step processes.

### Example Use Case

Instead of telling the AI:
```
"Get my GA4 data for the last 7 days, compare it to the previous 7 days,
check Clarity for behavioral changes, and give me recommendations"
```

You create a prompt called `analyze-traffic-drop` that automatically provides those exact instructions to the AI.

## Accessing Custom Prompts

1. Navigate to **Marketing Analytics → Custom Prompts** in WordPress admin
2. You'll see three sections:
   - **Your Custom Prompts**: Prompts you've created
   - **Import Preset Templates**: Pre-built prompts you can import
   - **Create Custom Prompt**: Form to create new prompts

## Creating a Custom Prompt

### Required Fields

1. **Prompt Name** (required)
   - Unique identifier (lowercase, hyphens only)
   - Example: `analyze-conversion-rate`
   - Will be prefixed with `marketing-analytics/` automatically
   - Final ID: `marketing-analytics/analyze-conversion-rate`

2. **Display Label** (required)
   - Human-readable name shown in MCP clients
   - Example: `Analyze Conversion Rate`

3. **Description** (required)
   - Brief description of what this prompt does
   - Example: `Analyzes conversion rate trends and identifies optimization opportunities`

4. **Instructions** (required)
   - Detailed step-by-step instructions for the AI
   - Can use `{{argument_name}}` placeholders for dynamic values
   - See examples below

5. **Arguments** (optional)
   - JSON array defining input parameters
   - Leave empty if no arguments needed
   - See schema below

### Example: Simple Prompt (No Arguments)

**Name:** `weekly-traffic-report`

**Instructions:**
```
Generate a comprehensive weekly traffic report:

1. Get GA4 metrics for the last 7 days:
   - Call marketing-analytics/get-ga4-metrics
   - metrics: ['activeUsers', 'sessions', 'screenPageViews', 'bounceRate']
   - date_range: '7daysAgo'

2. Get traffic sources:
   - Call marketing-analytics/get-traffic-sources
   - date_range: '7daysAgo'

3. Get top performing content:
   - Call marketing-analytics/get-ga4-metrics
   - metrics: ['screenPageViews', 'averageSessionDuration']
   - dimensions: ['pageTitle', 'pagePath']
   - limit: 10

4. Format the report with:
   - Executive Summary (3-5 key findings)
   - Traffic Overview
   - Top Traffic Sources
   - Top 10 Pages
   - Recommendations for next week
```

### Example: Prompt with Arguments

**Name:** `analyze-page-performance`

**Arguments:**
```json
[
  {
    "name": "page_url",
    "type": "string",
    "description": "URL of the page to analyze",
    "required": true
  },
  {
    "name": "date_range",
    "type": "string",
    "description": "Date range to analyze",
    "required": false,
    "default": "7daysAgo"
  }
]
```

**Instructions:**
```
Analyze performance for page: {{page_url}}

1. Get GA4 metrics for this specific page:
   - Call marketing-analytics/get-ga4-metrics
   - metrics: ['screenPageViews', 'averageSessionDuration', 'bounceRate']
   - dimensions: ['pagePath']
   - date_range: {{date_range}}
   - Filter for pagePath = {{page_url}}

2. Get search performance:
   - Call marketing-analytics/get-gsc-performance
   - Filter for page = {{page_url}}

3. Get user behavior insights:
   - Call marketing-analytics/get-clarity-insights
   - Check for high bounce rates or rage clicks

4. Provide analysis:
   - Traffic trends
   - Engagement quality
   - SEO performance
   - Specific recommendations to improve this page
```

When called, `{{page_url}}` and `{{date_range}}` will be replaced with actual values.

## Argument Schema

Arguments must be a JSON array. Each argument object supports:

```json
{
  "name": "argument_name",           // Required: parameter name
  "type": "string|integer|boolean",  // Required: data type
  "description": "What this does",   // Required: human-readable description
  "required": true|false,            // Optional: is this required?
  "default": "default_value"         // Optional: default value if not provided
}
```

### Complete Example

```json
[
  {
    "name": "conversion_event",
    "type": "string",
    "description": "GA4 event name for conversion (e.g., purchase, sign_up)",
    "required": true
  },
  {
    "name": "min_sessions",
    "type": "integer",
    "description": "Minimum sessions to include in analysis",
    "required": false,
    "default": 100
  },
  {
    "name": "include_bounce_rate",
    "type": "boolean",
    "description": "Whether to include bounce rate analysis",
    "required": false,
    "default": true
  }
]
```

## Using Preset Templates

The plugin includes 5 pre-built prompt templates:

1. **Analyze Traffic Drop** - Investigates sudden traffic decreases
2. **Weekly Performance Report** - Comprehensive weekly summary
3. **SEO Health Check** - Search Console analysis
4. **Content Performance Audit** - Top/bottom performing content
5. **Conversion Funnel Analysis** - User journey analysis

### How to Import Presets

1. Go to **Custom Prompts** page
2. Scroll to **Import Preset Templates** section
3. Click **Import** on any template
4. The prompt will be added to your custom prompts
5. You can then modify it or use as-is

## Using Prompts in MCP Clients

### Claude Desktop Example

Once created, your custom prompts appear in Claude's MCP tools list:

```
User: "I need to analyze my traffic drop"

Claude: I'll use the analyze-traffic-drop prompt to investigate.
[Automatically follows the step-by-step instructions you defined]
```

### Testing via WP-CLI

```bash
# List all prompts
wp mcp list-tools --server=marketing-analytics-chat | grep prompt

# Use a prompt
wp mcp call-tool marketing-analytics/analyze-traffic-drop \
  --user=admin

# Use a prompt with arguments
wp mcp call-tool marketing-analytics/analyze-page-performance \
  --arguments='{"page_url": "/blog/my-post", "date_range": "30daysAgo"}' \
  --user=admin
```

## Best Practices

### 1. Be Specific in Instructions

❌ Bad:
```
Check the traffic and give recommendations
```

✅ Good:
```
1. Call marketing-analytics/get-ga4-metrics with metrics: ['sessions', 'bounceRate']
2. If bounce rate > 60%, call marketing-analytics/get-clarity-insights
3. Provide 3 specific recommendations with data to support each
```

### 2. Reference Actual Tool Names

Always use the exact tool names from your MCP server:
- ✅ `marketing-analytics/get-ga4-metrics`
- ❌ `get GA4 data`

### 3. Use Arguments for Flexibility

If a value might change, make it an argument:
- Page URLs
- Date ranges
- Metric thresholds
- Event names

### 4. Provide Context

Help the AI understand the goal:
```
Goal: Identify why checkout completion rate dropped 15% this week

1. Get checkout event data...
2. Compare device performance...
3. Look for error patterns...
```

### 5. Format Output Requirements

Tell the AI exactly how to present results:
```
6. Provide analysis in this format:
   - Executive Summary (2-3 sentences)
   - Key Findings (bulleted list)
   - Priority Recommendations (numbered, most important first)
   - Supporting Data (table format)
```

## Advanced: Programmatic Prompt Management

### Using the Prompt_Manager Class

```php
use Marketing_Analytics_MCP\Prompts\Prompt_Manager;

$manager = new Prompt_Manager();

// Create a prompt
$prompt_data = array(
    'name'         => 'my-custom-analysis',
    'label'        => 'My Custom Analysis',
    'description'  => 'Does something specific',
    'instructions' => 'Step-by-step...',
    'category'     => 'marketing-analytics',
    'arguments'    => array(
        array(
            'name'        => 'metric',
            'type'        => 'string',
            'description' => 'Metric to analyze',
            'required'    => true,
        ),
    ),
);

$result = $manager->create_prompt( $prompt_data );

if ( is_wp_error( $result ) ) {
    echo 'Error: ' . $result->get_error_message();
} else {
    echo 'Created prompt: ' . $result; // Returns prompt ID
}

// Get all prompts
$prompts = $manager->get_all_prompts();

// Get specific prompt
$prompt = $manager->get_prompt( 'marketing-analytics/my-custom-analysis' );

// Update a prompt
$manager->update_prompt(
    'marketing-analytics/my-custom-analysis',
    array( 'description' => 'Updated description' )
);

// Delete a prompt
$manager->delete_prompt( 'marketing-analytics/my-custom-analysis' );

// Import a preset
$manager->import_preset( 'traffic-drop-analysis' );
```

### Available Preset Keys

- `traffic-drop-analysis`
- `weekly-report`
- `seo-health-check`
- `content-performance-audit`
- `conversion-funnel-analysis`

## Troubleshooting

### Prompt not appearing in MCP client

1. Ensure you have credentials configured for at least one platform (GA4, Clarity, or GSC)
2. Refresh your MCP client connection
3. Check that the prompt name follows the naming rules (lowercase, hyphens only)

### Arguments not working

1. Verify JSON syntax is valid (use a JSON validator)
2. Ensure argument names in instructions match exactly: `{{page_url}}` not `{{pageUrl}}`
3. Check that required arguments are marked correctly in the schema

### Instructions not executing correctly

1. Use exact tool names from `wp mcp list-tools`
2. Ensure tool calls include all required parameters
3. Test individual tools first to ensure they work
4. Add more specific instructions if the AI is misinterpreting

## Examples Library

### E-commerce Checkout Analysis

```json
{
  "name": "checkout-analysis",
  "label": "Checkout Funnel Analysis",
  "description": "Analyzes checkout process and identifies friction points",
  "arguments": [
    {
      "name": "conversion_event",
      "type": "string",
      "description": "GA4 conversion event name",
      "required": true,
      "default": "purchase"
    }
  ],
  "instructions": "..."
}
```

### Content Gap Analysis

```json
{
  "name": "content-gap-analysis",
  "label": "Content Gap Analysis",
  "description": "Identifies keyword opportunities where competitors rank but you don't",
  "arguments": [],
  "instructions": "..."
}
```

### Mobile vs Desktop Performance

```json
{
  "name": "device-performance-comparison",
  "label": "Device Performance Comparison",
  "description": "Compares metrics between mobile and desktop users",
  "arguments": [
    {
      "name": "date_range",
      "type": "string",
      "description": "Date range to analyze",
      "required": false,
      "default": "30daysAgo"
    }
  ],
  "instructions": "..."
}
```

## Next Steps

1. **Import a Preset**: Start with a pre-built template to understand the structure
2. **Customize It**: Modify the imported prompt to match your needs
3. **Create Your Own**: Build prompts for your specific workflows
4. **Test via WP-CLI**: Verify prompts work before using in production
5. **Share Knowledge**: Document your custom prompts for your team

## Support

For issues or questions:
- Check the plugin logs: `wp-content/debug.log` (if `WP_DEBUG` is enabled)
- Use `wp mcp list-tools` to verify prompts are registered
- Refer to the main plugin documentation in `/docs/`
