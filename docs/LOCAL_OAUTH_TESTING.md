# Testing OAuth Locally with Google

Google OAuth doesn't allow redirect URIs with `.test`, `.local`, or other non-public top-level domains. Here are solutions for local development.

## Problem

When trying to add a redirect URI like:
```
https://marketing-mcp-wp.test/wp-admin/admin.php?page=marketing-analytics-mcp-connections&oauth_callback=1
```

You get this error:
```
Invalid redirect: Must end with a public top-level domain (such as .com or .org).
```

## Solutions

### Option 1: Use ngrok (Recommended for Local Testing)

**ngrok** creates a temporary public HTTPS URL that tunnels to your local WordPress site.

#### Step 1: Install ngrok

```bash
# macOS (using Homebrew)
brew install ngrok

# Or download from https://ngrok.com/download
```

#### Step 2: Sign up for ngrok (Free)

1. Go to https://ngrok.com/signup
2. Create a free account
3. Copy your authtoken from the dashboard
4. Run: `ngrok config add-authtoken YOUR_TOKEN`

#### Step 3: Start ngrok Tunnel

```bash
# If your site runs on port 80 (standard)
ngrok http 80

# If using Valet with custom port
ngrok http https://marketing-mcp-wp.test:443

# Or specify the exact Valet site
ngrok http marketing-mcp-wp.test:80
```

#### Step 4: Use ngrok URL in Google Cloud Console

ngrok will display output like:
```
Forwarding  https://abc123.ngrok.io -> http://localhost:80
```

**Use this ngrok URL** as your redirect URI in Google Cloud Console:
```
https://abc123.ngrok.io/wp-admin/admin.php?page=marketing-analytics-mcp-connections&oauth_callback=1
```

#### Step 5: Update WordPress Site URL (Temporarily)

```bash
# Update WordPress to recognize ngrok URL
wp option update home 'https://abc123.ngrok.io'
wp option update siteurl 'https://abc123.ngrok.io'

# Access your site via ngrok URL
open https://abc123.ngrok.io/wp-admin
```

#### Step 6: Test OAuth Flow

1. Go to: `https://abc123.ngrok.io/wp-admin/admin.php?page=marketing-analytics-mcp-connections&tab=ga4`
2. Add your Google OAuth credentials
3. Click "Connect to Google Analytics"
4. Complete OAuth flow
5. Test connection

#### Step 7: Restore Original URLs (After Testing)

```bash
# Restore your local URLs
wp option update home 'https://marketing-mcp-wp.test'
wp option update siteurl 'https://marketing-mcp-wp.test'
```

**⚠️ Note:** ngrok URLs are temporary and change each time you restart ngrok (unless you have a paid plan with reserved domains).

---

### Option 2: Use localhost with Port Forwarding

Google allows `http://localhost` redirect URIs for development.

#### If Using Laravel Valet

Valet sites are accessible via both `.test` domain and localhost. Find the port:

```bash
# List all Valet sites
valet links

# Access via localhost
# Usually accessible at http://localhost (port 80)
```

#### Configure Redirect URI

Use in Google Cloud Console:
```
http://localhost/wp-admin/admin.php?page=marketing-analytics-mcp-connections&oauth_callback=1
```

**⚠️ Important:**
- Must use `http://` (not `https://`) for localhost
- Google allows insecure HTTP only for localhost development

#### Update WordPress Temporarily

```bash
wp option update home 'http://localhost'
wp option update siteurl 'http://localhost'
```

Access your site at: `http://localhost/wp-admin`

#### Restore After Testing

```bash
wp option update home 'https://marketing-mcp-wp.test'
wp option update siteurl 'https://marketing-mcp-wp.test'
```

---

### Option 3: Use a Real Staging/Production Site (Best Practice)

The most reliable approach is to test OAuth on a real domain:

1. **Use your production site** (if live)
2. **Use a staging site** with a real domain
3. **Use a cheap domain** for testing (e.g., from Namecheap, $1/year)

#### Example with Staging Site

```
https://staging.yoursite.com/wp-admin/admin.php?page=marketing-analytics-mcp-connections&oauth_callback=1
```

**Benefits:**
- ✅ Works with HTTPS
- ✅ No temporary URLs
- ✅ Closest to production environment
- ✅ No need to change WordPress URLs repeatedly

---

## Recommended Workflow for Development

### During Initial Setup (Use Real Domain)

1. Test OAuth on production or staging site first
2. Verify everything works with real domain
3. Configure OAuth credentials in Google Cloud

### For Local Development (Use ngrok)

1. Start ngrok when testing OAuth flows
2. Use ngrok URL temporarily for OAuth testing
3. Stop ngrok when done with OAuth features
4. Switch back to `.test` domain for non-OAuth development

### For Production Deployment

1. Add production domain redirect URI to Google Cloud Console
2. Keep both staging and production URIs configured
3. OAuth will work on both environments

---

## Troubleshooting

### ngrok Connection Issues

**Error:** "ngrok not found"
```bash
# Ensure ngrok is installed
brew install ngrok

# Or download from https://ngrok.com/download
```

**Error:** "Failed to start tunnel"
```bash
# Check if another ngrok instance is running
pkill ngrok

# Restart ngrok
ngrok http 80
```

### WordPress Shows Wrong URL

If WordPress redirects to `.test` instead of ngrok URL:

```bash
# Force WordPress to use ngrok URL
wp option update home 'https://YOUR-NGROK-URL.ngrok.io' --skip-themes --skip-plugins

# Or edit wp-config.php temporarily
# Add these lines:
define('WP_HOME', 'https://YOUR-NGROK-URL.ngrok.io');
define('WP_SITEURL', 'https://YOUR-NGROK-URL.ngrok.io');
```

### OAuth Callback Returns to Wrong URL

Make sure the redirect URI in Google Cloud Console **exactly matches** what WordPress generates:

1. Start ngrok: `ngrok http 80`
2. Update WordPress URLs to ngrok
3. Go to WordPress admin: `https://abc123.ngrok.io/wp-admin`
4. Navigate to Connections page
5. **Copy the exact Redirect URI** shown on the page
6. Add that exact URI to Google Cloud Console

### Mixed Content Warnings

ngrok provides HTTPS by default, but if you see mixed content:

```bash
# Always use HTTPS ngrok URL
https://abc123.ngrok.io

# Not HTTP
http://abc123.ngrok.io
```

---

## Alternative: Skip OAuth for Local Development

If you only need to test **non-OAuth features** locally:

1. Configure OAuth on production/staging site
2. Export the encrypted credentials from database
3. Import them to local database
4. Tokens will work until they expire

**Export from production:**
```bash
wp option get marketing_analytics_mcp_credentials_ga4 > ga4_creds.txt
wp option get marketing_analytics_mcp_credentials_gsc > gsc_creds.txt
```

**Import to local:**
```bash
wp option update marketing_analytics_mcp_credentials_ga4 "$(cat ga4_creds.txt)"
wp option update marketing_analytics_mcp_credentials_gsc "$(cat gsc_creds.txt)"
```

**⚠️ Security Warning:** Never commit these files to version control!

---

## ngrok Tips for WordPress Development

### Keep Same URL with ngrok (Paid Feature)

With ngrok paid plan ($8/month), you get reserved domains:

```bash
# Reserve a domain in ngrok dashboard
# Then use it:
ngrok http 80 --domain=your-reserved-subdomain.ngrok.app
```

This gives you a **permanent URL** that doesn't change between restarts.

### ngrok Web Interface

While ngrok is running, access the inspector:
```
http://localhost:4040
```

This shows:
- All requests going through ngrok
- Request/response details
- Replay requests

### Multiple ngrok Tunnels

If you run multiple local sites:

```bash
# Terminal 1
ngrok http 80

# Terminal 2
ngrok http 8080
```

---

## Quick Reference

| Method | Pros | Cons | Best For |
|--------|------|------|----------|
| **ngrok** | Real HTTPS, Easy setup | Temporary URL | Active OAuth development |
| **localhost** | Simple, No tools needed | HTTP only, Limited testing | Quick local tests |
| **Real domain** | Production-like, Stable | Requires deployment | Final testing, Production |

---

## Recommended Setup

**For this specific plugin development:**

1. **Initial OAuth setup**: Use ngrok or staging site
2. **OAuth callback testing**: Use ngrok
3. **MCP endpoint testing**: Can use `.test` domain (doesn't need OAuth redirect)
4. **Production deployment**: Use real domain with HTTPS

The MCP endpoints themselves don't require special redirect URIs - only the **OAuth authorization flow** needs a public domain.

---

**Last Updated:** 2024-11-14
**Plugin Version:** 1.0.0
