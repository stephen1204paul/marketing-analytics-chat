# Troubleshooting MCP Endpoint Issues

Use these commands to diagnose why your MCP endpoint is still failing.

## Step 1: Verify Plugin Activation

```bash
# Check if all three plugins are active
wp plugin list | grep -E 'abilities-api|mcp-adapter|marketing-analytics-chat'
```

**Expected output:**
```
abilities-api           active
mcp-adapter             active
marketing-analytics-chat           active
```

If any show as "inactive", activate them:
```bash
wp plugin activate abilities-api mcp-adapter marketing-analytics-chat
```

## Step 2: Check REST API Base

```bash
# Test if WordPress REST API is accessible
curl https://demo.specflux.com/wp-json/
```

**Expected:** JSON response with namespaces and routes

**If you get HTML:** REST API is disabled or blocked. Enable it:
```bash
# Check if REST API is disabled in wp-config.php
grep "REST_API" /path/to/wp-config.php

# If disabled, remove or comment out these lines:
# define('REST_API_DISABLED', true);
```

## Step 3: Check MCP Namespace

```bash
# Check if MCP namespace is registered
curl https://demo.specflux.com/wp-json/mcp/
```

**Expected:** JSON response showing MCP routes

**If you get 404:** MCP Adapter isn't registering routes. Check next step.

## Step 4: Verify MCP Adapter Installation

```bash
# Check if MCP Adapter files exist
ls -la /path/to/wp-content/plugins/mcp-adapter/

# Check if vendor directory exists (dependencies installed)
ls -la /path/to/wp-content/plugins/mcp-adapter/vendor/

# Check if Abilities API vendor exists
ls -la /path/to/wp-content/plugins/abilities-api/vendor/
```

**If vendor directories are missing:**
```bash
cd /path/to/wp-content/plugins/mcp-adapter
composer install --no-dev

cd /path/to/wp-content/plugins/abilities-api
composer install --no-dev
```

## Step 5: Flush Permalinks

```bash
# Flush WordPress rewrite rules
wp rewrite flush

# Verify rewrite rules include REST API
wp rewrite list | grep rest
```

## Step 6: Check for PHP Errors

```bash
# Enable WordPress debugging
wp config set WP_DEBUG true --type=constant --raw
wp config set WP_DEBUG_LOG true --type=constant --raw
wp config set WP_DEBUG_DISPLAY false --type=constant --raw

# Tail the debug log while testing
tail -f /path/to/wp-content/debug.log &

# Test the endpoint again
curl -X POST https://demo.specflux.com/wp-json/mcp/mcp-adapter-default-server \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic c3RlcGhlbjpzRXh4ZFoxN1RBMXh1MXpsdWxFZHZyN1U=" \
  -H "Mcp-Session-Id: test-session-123" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}'
```

Check the debug.log for errors.

## Step 7: Test Without Authentication

```bash
# Test if endpoint responds at all (may fail with auth error, but shouldn't be HTML)
curl -X POST https://demo.specflux.com/wp-json/mcp/mcp-adapter-default-server \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}'
```

**If you get JSON error about authentication:** Good! Endpoint exists, just auth issue.
**If you get HTML:** Endpoint still not registered.

## Step 8: Check Application Password

```bash
# Test authentication with a simple REST API endpoint first
curl -X GET https://demo.specflux.com/wp-json/wp/v2/users/me \
  -H "Authorization: Basic c3RlcGhlbjpzRXh4ZFoxN1RBMXh1MXpsdWxFZHZyN1U="
```

**Expected:** JSON with your user info

**If 401 error:** Application password is wrong. Regenerate it:
1. WordPress Admin → Users → Your Profile
2. Scroll to Application Passwords
3. Revoke old password
4. Create new one: Name it "MCP Test"
5. Copy password (remove spaces): `sExx dZ17 TA1x u1zl ulEd vr7U` → `sExxdZ17TA1xu1zlulEdvr7U`
6. Re-encode:
   ```bash
   echo -n 'stephen:sExxdZ17TA1xu1zlulEdvr7U' | base64
   ```

## Step 9: Verify MCP Adapter Initialization

```bash
# Check if MCP Adapter class is loaded
wp eval "var_dump(class_exists('WP\\MCP\\Core\\McpAdapter'));"
```

**Expected:** `bool(true)`

**If false:** Autoloader not working. Check:
```bash
# Verify Composer autoloader
ls -la /path/to/wp-content/plugins/mcp-adapter/vendor/autoload.php

# Manually check if it's being loaded
grep "autoload.php" /path/to/wp-content/plugins/mcp-adapter/*.php
```

## Step 10: Test via WP-CLI (Alternative Method)

```bash
# List available MCP servers
wp mcp-adapter list

# If command doesn't exist, MCP Adapter isn't properly installed
# Try reinstalling:
cd /path/to/wp-content/plugins/
rm -rf mcp-adapter abilities-api
git clone https://github.com/WordPress/abilities-api.git
git clone https://github.com/WordPress/mcp-adapter.git
cd abilities-api && composer install --no-dev && cd ..
cd mcp-adapter && composer install --no-dev && cd ..
wp plugin activate abilities-api mcp-adapter
wp rewrite flush
```

## Common Issues and Fixes

### Issue: "404 Not Found" (HTML)

**Possible Causes:**
1. MCP Adapter not activated
2. REST API disabled
3. Permalinks not flushed
4. .htaccess missing mod_rewrite rules

**Fix:**
```bash
wp plugin activate mcp-adapter
wp rewrite flush
wp rewrite structure '/%postname%/'
```

### Issue: "Class WP\MCP\Core\McpAdapter not found"

**Cause:** Composer dependencies not installed

**Fix:**
```bash
cd /path/to/wp-content/plugins/mcp-adapter
composer install --no-dev
```

### Issue: "401 Unauthorized"

**Cause:** Invalid Application Password

**Fix:**
1. Ensure password has no spaces
2. Encode correctly: `echo -n 'user:pass' | base64`
3. Use `Basic` prefix in header: `Authorization: Basic <base64>`

### Issue: "Empty response" or "Connection refused"

**Cause:** HTTPS/SSL issue or server not responding

**Fix:**
```bash
# Test with curl verbose mode
curl -v -X POST https://demo.specflux.com/wp-json/mcp/mcp-adapter-default-server \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}'

# Check SSL certificate
curl -v https://demo.specflux.com/ 2>&1 | grep -i ssl
```

### Issue: "Fatal error: Class not found"

**Cause:** Jetpack Autoloader conflict or missing

**Fix:**
```bash
# Ensure all plugins use Jetpack Autoloader
cd /path/to/wp-content/plugins/mcp-adapter
composer require automattic/jetpack-autoloader
composer dump-autoload --optimize

cd /path/to/wp-content/plugins/abilities-api
composer require automattic/jetpack-autoloader
composer dump-autoload --optimize

cd /path/to/wp-content/plugins/marketing-analytics-chat
composer require automattic/jetpack-autoloader
composer dump-autoload --optimize
```

## Quick Diagnostic Script

Save this as `diagnose-mcp.sh` and run it:

```bash
#!/bin/bash

echo "=== MCP Endpoint Diagnostics ==="
echo ""

echo "1. Checking plugin activation..."
wp plugin list | grep -E 'abilities-api|mcp-adapter|marketing-analytics-chat'
echo ""

echo "2. Testing REST API base..."
curl -s https://demo.specflux.com/wp-json/ | jq '.namespaces' 2>/dev/null || echo "REST API not responding"
echo ""

echo "3. Testing MCP namespace..."
curl -s https://demo.specflux.com/wp-json/mcp/ 2>&1 | head -c 100
echo ""

echo "4. Checking MCP Adapter class..."
wp eval "var_dump(class_exists('WP\\MCP\\Core\\McpAdapter'));"
echo ""

echo "5. Testing authentication..."
curl -s -X GET https://demo.specflux.com/wp-json/wp/v2/users/me \
  -H "Authorization: Basic c3RlcGhlbjpzRXh4ZFoxN1RBMXh1MXpsdWxFZHZyN1U=" | jq '.name' 2>/dev/null || echo "Auth failed"
echo ""

echo "6. Testing MCP endpoint..."
curl -s -X POST https://demo.specflux.com/wp-json/mcp/mcp-adapter-default-server \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic c3RlcGhlbjpzRXh4ZFoxN1RBMXh1MXpsdWxFZHZyN1U=" \
  -H "Mcp-Session-Id: test-session-123" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}' | jq '.result.tools[0].name' 2>/dev/null || echo "MCP endpoint not working"
echo ""

echo "=== End Diagnostics ==="
```

Run it:
```bash
chmod +x diagnose-mcp.sh
./diagnose-mcp.sh
```

## What to Share

After running the diagnostics, share the output of:

1. Plugin status:
   ```bash
   wp plugin list | grep -E 'abilities-api|mcp-adapter|marketing-analytics-chat'
   ```

2. MCP namespace response:
   ```bash
   curl -v https://demo.specflux.com/wp-json/mcp/ 2>&1
   ```

3. Full curl output:
   ```bash
   curl -v -X POST https://demo.specflux.com/wp-json/mcp/mcp-adapter-default-server \
     -H "Content-Type: application/json" \
     -H "Authorization: Basic c3RlcGhlbjpzRXh4ZFoxN1RBMXh1MXpsdWxFZHZyN1U=" \
  -H "Mcp-Session-Id: test-session-123" \
     -d '{"jsonrpc":"2.0","method":"tools/list","id":1}' 2>&1
   ```

4. Debug log (last 50 lines):
   ```bash
   tail -n 50 /path/to/wp-content/debug.log
   ```

This will help identify the exact issue preventing the MCP endpoint from working.
