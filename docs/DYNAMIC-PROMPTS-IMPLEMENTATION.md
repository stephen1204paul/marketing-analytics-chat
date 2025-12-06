# Dynamic Custom Prompts - Implementation Summary

## What Was Built

A complete dynamic prompt management system that allows users to create, manage, and customize MCP prompts through the WordPress admin interface.

## Architecture

### 1. Database Layer
**File:** `includes/prompts/class-prompt-manager.php`

- **Storage**: WordPress options API (`marketing_analytics_mcp_custom_prompts`)
- **Operations**: Full CRUD (Create, Read, Update, Delete)
- **Validation**: Name format, required fields, argument schema
- **Preset Library**: 5 pre-built templates included

```php
// Create a prompt
$manager = new Prompt_Manager();
$result = $manager->create_prompt( $prompt_data );

// Import a preset
$manager->import_preset( 'traffic-drop-analysis' );
```

### 2. Dynamic Registration Layer
**File:** `includes/abilities/class-prompts.php`

- **Auto-registration**: Reads all custom prompts from database and registers them
- **Schema generation**: Dynamically builds input schemas from arguments
- **Placeholder replacement**: Replaces `{{argument_name}}` with actual values
- **Execution**: Returns instructions to AI assistant

```php
// When MCP client calls the prompt, it automatically:
// 1. Gets prompt data from database
// 2. Replaces {{placeholders}} with argument values
// 3. Returns instructions to AI
```

### 3. Admin Interface Layer
**Files:**
- `admin/views/prompts.php` - UI view
- `includes/admin/class-admin.php` - Menu registration

Features:
- ✅ List all custom prompts
- ✅ View prompt details (modal)
- ✅ Create new prompts (form with validation)
- ✅ Delete prompts
- ✅ Import preset templates
- ✅ Real-time JSON validation
- ✅ Inline help and examples

## Features

### User-Facing Features

1. **Custom Prompt Creation**
   - Name, label, description
   - Step-by-step instructions
   - Optional argument schema (JSON)
   - Category assignment

2. **Preset Template Library**
   - 5 pre-built prompts ready to import:
     - Analyze Traffic Drop
     - Weekly Performance Report
     - SEO Health Check
     - Content Performance Audit
     - Conversion Funnel Analysis

3. **Prompt Management**
   - View all custom prompts
   - View detailed instructions
   - Delete prompts
   - Import presets with one click

4. **Argument System**
   - Define dynamic parameters (string, integer, boolean)
   - Required/optional flags
   - Default values
   - Automatic placeholder replacement

### Developer Features

1. **Programmatic API**
   ```php
   $manager = new Prompt_Manager();

   // CRUD operations
   $manager->create_prompt( $data );
   $manager->get_prompt( $id );
   $manager->update_prompt( $id, $data );
   $manager->delete_prompt( $id );

   // Presets
   $manager->get_preset_templates();
   $manager->import_preset( $key );
   ```

2. **Automatic MCP Registration**
   - Prompts are automatically registered with WordPress Abilities API
   - No manual registration needed
   - Updates take effect immediately

3. **Extensible**
   - Add more preset templates
   - Filter prompts before registration
   - Custom validation rules

## File Structure

```
marketing-analytics-chat/
├── includes/
│   ├── abilities/
│   │   └── class-prompts.php              # Dynamic registration
│   ├── admin/
│   │   └── class-admin.php                # Menu integration
│   └── prompts/
│       └── class-prompt-manager.php       # CRUD operations
├── admin/
│   └── views/
│       └── prompts.php                    # Admin UI
└── docs/
    ├── CUSTOM-PROMPTS.md                  # User guide
    └── DYNAMIC-PROMPTS-IMPLEMENTATION.md  # This file
```

## Database Schema

Stored in WordPress option: `marketing_analytics_mcp_custom_prompts`

```php
array(
    'marketing-analytics/prompt-name' => array(
        'id'           => 'marketing-analytics/prompt-name',
        'name'         => 'prompt-name',
        'label'        => 'Display Name',
        'description'  => 'What this prompt does',
        'instructions' => 'Step-by-step instructions...',
        'category'     => 'marketing-analytics',
        'arguments'    => array(
            array(
                'name'        => 'page_url',
                'type'        => 'string',
                'description' => 'Page to analyze',
                'required'    => true,
                'default'     => null,
            ),
        ),
        'created_at'   => '2024-01-15 10:30:00',
        'updated_at'   => '2024-01-15 10:30:00',
    ),
)
```

## Preset Templates Included

### 1. Analyze Traffic Drop
**Use Case:** Traffic decreased suddenly
**Arguments:** `page_url` (optional)
**Steps:** GA4 comparison, Clarity behavior, GSC search, traffic sources

### 2. Weekly Performance Report
**Use Case:** Monday morning reports
**Arguments:** None
**Steps:** GA4 metrics, traffic sources, Clarity insights, top content, GSC

### 3. SEO Health Check
**Use Case:** Monthly SEO audits
**Arguments:** None
**Steps:** GSC performance, indexing, query analysis, cross-reference GA4

### 4. Content Performance Audit
**Use Case:** "Which posts should I update?"
**Arguments:** `min_pageviews` (optional)
**Steps:** GA4 page metrics, GSC per-page, Clarity engagement, categorization

### 5. Conversion Funnel Analysis
**Use Case:** "Where do users drop off?"
**Arguments:** `conversion_event` (required)
**Steps:** GA4 events, funnel calculation, traffic sources, Clarity recordings

## Usage Examples

### Creating a Prompt via UI

1. Go to **Marketing Analytics → Custom Prompts**
2. Scroll to **Create Custom Prompt**
3. Fill in the form:
   - Name: `analyze-bounce-rate`
   - Label: `Analyze Bounce Rate`
   - Description: `Investigates high bounce rates`
   - Instructions: (step-by-step)
   - Arguments: (optional JSON)
4. Click **Create Prompt**

### Using a Prompt in Claude Desktop

```
User: "My bounce rate is too high, help me figure out why"

Claude: I'll use the analyze-bounce-rate prompt to investigate.
[Follows the instructions you defined]
```

### Using a Prompt via WP-CLI

```bash
# No arguments
wp mcp call-tool marketing-analytics/weekly-report --user=admin

# With arguments
wp mcp call-tool marketing-analytics/analyze-page-performance \
  --arguments='{"page_url": "/pricing", "date_range": "30daysAgo"}' \
  --user=admin
```

### Programmatic Creation

```php
use Marketing_Analytics_MCP\Prompts\Prompt_Manager;

$manager = new Prompt_Manager();

$prompt_data = array(
    'name'         => 'custom-analysis',
    'label'        => 'Custom Analysis',
    'description'  => 'My specific workflow',
    'instructions' => "1. Call marketing-analytics/get-ga4-metrics\n2. Analyze...",
    'arguments'    => array(
        array(
            'name'        => 'metric',
            'type'        => 'string',
            'description' => 'Which metric to analyze',
            'required'    => true,
        ),
    ),
);

$prompt_id = $manager->create_prompt( $prompt_data );
// Returns: 'marketing-analytics/custom-analysis'
```

## Benefits

### For End Users
- ✅ No coding required
- ✅ Visual interface for prompt management
- ✅ Pre-built templates to get started
- ✅ Customize workflows to their business
- ✅ Reusable across multiple AI interactions

### For Developers
- ✅ Clean API for CRUD operations
- ✅ Automatic MCP registration
- ✅ Extensible preset system
- ✅ JSON schema validation
- ✅ WordPress standards compliant

### For AI Assistants
- ✅ Consistent, structured instructions
- ✅ Parameter validation
- ✅ Clear step-by-step workflows
- ✅ Context-aware execution

## Testing Checklist

- [ ] Create a prompt via admin UI
- [ ] Import a preset template
- [ ] View prompt details
- [ ] Delete a prompt
- [ ] Create prompt with arguments
- [ ] Test placeholder replacement
- [ ] Verify MCP registration (`wp mcp list-tools`)
- [ ] Call prompt via WP-CLI
- [ ] Call prompt via Claude Desktop
- [ ] Test JSON validation (invalid JSON)
- [ ] Test name validation (uppercase, spaces)

## Future Enhancements

Potential improvements for future versions:

1. **Prompt Categories**
   - Group prompts by use case (SEO, Conversion, Traffic, etc.)
   - Filter by category in admin UI

2. **Prompt Sharing**
   - Export prompts as JSON
   - Import prompts from file
   - Community prompt library

3. **Version History**
   - Track prompt changes over time
   - Rollback to previous versions

4. **Conditional Logic**
   - IF/THEN conditions in instructions
   - Dynamic tool selection based on data

5. **Prompt Analytics**
   - Track usage frequency
   - Measure success rates
   - Optimize based on performance

6. **Team Collaboration**
   - Share prompts between team members
   - Role-based access control
   - Comments and annotations

## Migration Notes

If you previously had hardcoded prompts in `class-prompts.php`, you can:

1. Keep them as-is (they'll still work)
2. Migrate to custom prompts using the import system
3. Mix both approaches (hardcoded + custom)

The new system is **additive** - it doesn't break existing functionality.

## Performance Considerations

- **Storage**: WordPress options table (lightweight)
- **Caching**: Prompts loaded once per request
- **Scalability**: Suitable for 100s of prompts
- **Memory**: Minimal overhead (~1KB per prompt)

For 1000+ prompts, consider:
- Custom database table
- Object caching (Redis/Memcached)
- Lazy loading

## Security

- ✅ Nonce verification on all forms
- ✅ Capability checks (`manage_options`)
- ✅ Input sanitization
- ✅ JSON validation
- ✅ SQL injection protection (WordPress API)
- ✅ XSS prevention (escaping)

## Conclusion

You now have a fully dynamic prompt management system that:
- Empowers users to create their own workflows
- Provides pre-built templates for common tasks
- Integrates seamlessly with MCP
- Requires zero code changes for new prompts
- Scales with your needs

**Next Steps:**
1. Import a preset template
2. Test it via WP-CLI or Claude Desktop
3. Customize it for your use case
4. Create your first custom prompt
5. Share with your team!
