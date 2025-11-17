# Google OAuth Setup Guide

This guide will walk you through creating OAuth 2.0 credentials in Google Cloud Console for connecting Google Analytics 4 and Google Search Console to the Marketing Analytics MCP plugin.

## Prerequisites

- A Google account with access to:
  - Google Cloud Console
  - Google Analytics 4 property (for GA4 integration)
  - Google Search Console property (for GSC integration)
- Admin access to your WordPress site

## Overview

You'll need to:
1. Create a Google Cloud project
2. Enable the required APIs
3. Configure the OAuth consent screen
4. Create OAuth 2.0 credentials
5. Add the credentials to your WordPress plugin

**Total setup time:** 10-15 minutes

---

## Step 1: Create a Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)

2. Click the project dropdown in the top navigation bar

3. Click **"New Project"**

4. Enter a project name (e.g., "WordPress Marketing Analytics")

5. Click **"Create"**

6. Wait for the project to be created (you'll see a notification)

7. **Select your new project** from the project dropdown

---

## Step 2: Enable Required APIs

### Enable Google Analytics Data API

1. In your Google Cloud project, go to **APIs & Services** → **Library**

2. Search for **"Google Analytics Data API"**

3. Click on **"Google Analytics Data API"**

4. Click **"Enable"**

5. Wait for the API to be enabled

### Enable Google Analytics Admin API

1. Still in the API Library, search for **"Google Analytics Admin API"**

2. Click on it and click **"Enable"**

### Enable Google Search Console API

1. Search for **"Google Search Console API"**

2. Click on it and click **"Enable"**

**Verification:** You should now have 3 APIs enabled. Check via **APIs & Services** → **Enabled APIs & services**

---

## Step 3: Configure OAuth Consent Screen

1. Go to **APIs & Services** → **OAuth consent screen**

2. Choose **User Type:**
   - **External** (if you'll only use this for your own WordPress site)
   - **Internal** (if you're part of a Google Workspace organization and want to restrict to your org)

3. Click **"Create"**

4. Fill in the **App information:**
   - **App name:** `Marketing Analytics MCP` (or your preferred name)
   - **User support email:** Your email address
   - **App logo:** (Optional) Upload a logo if desired

5. Fill in **Developer contact information:**
   - **Email addresses:** Your email address

6. Click **"Save and Continue"**

7. On the **Scopes** page:
   - Click **"Add or Remove Scopes"**
   - Search for and select these scopes:
     - `https://www.googleapis.com/auth/analytics.readonly` (Google Analytics)
     - `https://www.googleapis.com/auth/webmasters.readonly` (Search Console)
   - Click **"Update"**
   - Click **"Save and Continue"**

8. On the **Test users** page (if using External user type):
   - Click **"Add Users"**
   - Add your Google account email
   - Click **"Save and Continue"**

9. Review the summary and click **"Back to Dashboard"**

---

## Step 4: Create OAuth 2.0 Credentials

1. Go to **APIs & Services** → **Credentials**

2. Click **"Create Credentials"** → **"OAuth client ID"**

3. Select **Application type:** **Web application**

4. Enter a **Name:** `WordPress MCP Plugin` (or your preferred name)

5. Under **Authorized redirect URIs:**
   - Click **"Add URI"**
   - **⚠️ IMPORTANT:** Go to your WordPress admin panel
   - Navigate to **Marketing Analytics** → **Connections** → **Google Analytics 4** tab
   - Copy the **Redirect URI** shown on that page
   - It will look like: `https://yoursite.com/wp-admin/admin.php?page=marketing-analytics-mcp-connections&oauth_callback=1`
   - Paste this URI into the Google Cloud Console
   - ⚠️ **Make sure it's an exact match (including https:// and any parameters)**

6. Click **"Create"**

7. A popup will appear showing your credentials:
   - **Client ID:** (looks like `123456789-abc123.apps.googleusercontent.com`)
   - **Client Secret:** (looks like `GOCSPX-abc123xyz789`)
   - **Copy both of these** or click **"Download JSON"**

8. Click **"OK"**

---

## Step 5: Add Credentials to WordPress

1. Go to your WordPress admin panel

2. Navigate to **Marketing Analytics** → **Connections**

3. Click the **Google Analytics 4** tab

4. You'll see a form to enter OAuth credentials:
   - **OAuth Client ID:** Paste your Client ID from Step 4
   - **OAuth Client Secret:** Paste your Client Secret from Step 4
   - **Redirect URI:** This is displayed for reference - you added this to Google Cloud in Step 4

5. Click **"Save OAuth Credentials"**

6. You should see a success message

---

## Step 6: Authorize Google Analytics

1. After saving OAuth credentials, you'll see a **"Connect to Google Analytics"** button

2. Click this button

3. You'll be redirected to Google's authorization page

4. **Sign in** with your Google account (if not already signed in)

5. Review the permissions requested:
   - View your Google Analytics data
   - Read-only access

6. Click **"Continue"** or **"Allow"**

7. You'll be redirected back to WordPress

8. You should see a success message: **"Successfully connected to Google services"**

9. Click **"Test Connection"** to verify everything works

---

## Step 7: Authorize Google Search Console

1. Click the **Google Search Console** tab

2. Click **"Connect to Google Search Console"**

3. Follow the same authorization process as in Step 6

4. After authorization, click **"Test Connection"** to verify

---

## Troubleshooting

### Error: "redirect_uri_mismatch"

**Cause:** The redirect URI in Google Cloud Console doesn't match your WordPress site's URI exactly.

**Solution:**
1. Go to Google Cloud Console → **APIs & Services** → **Credentials**
2. Click on your OAuth 2.0 Client ID
3. Under **Authorized redirect URIs**, ensure it matches **exactly** what's shown in WordPress
4. Common issues:
   - Missing `https://` or using `http://`
   - Missing `www.` or having extra `www.`
   - Missing or incorrect query parameters (`?page=...&oauth_callback=1`)

### Error: "Access blocked: This app's request is invalid"

**Cause:** OAuth consent screen not properly configured.

**Solution:**
1. Complete Step 3 (OAuth Consent Screen) fully
2. Make sure you've added the required scopes
3. If using "External" user type, make sure you've added yourself as a test user

### Error: "This app isn't verified"

**Cause:** Google shows this warning for unverified apps requesting sensitive scopes.

**Solution:**
1. This is normal for personal/small business use
2. Click **"Advanced"** at the bottom left
3. Click **"Go to [Your App Name] (unsafe)"**
4. Proceed with authorization
5. For production use, consider submitting your app for Google verification

### Error: "Invalid Credentials" after connecting

**Cause:** Token expired or API not properly enabled.

**Solution:**
1. Disconnect and reconnect
2. Verify all APIs are enabled (Step 2)
3. Check that your Google account has access to the GA4/GSC properties you want to connect

### Connection test fails

**Cause:** Your Google account doesn't have access to any GA4 properties or GSC sites.

**Solution:**
1. Ensure you have at least **Viewer** access to a GA4 property
2. Ensure you have access to at least one Search Console property
3. Wait a few minutes after granting access for changes to propagate

---

## Security Best Practices

1. **Keep your Client Secret secure:**
   - Never share it publicly
   - Don't commit it to version control
   - The plugin stores it encrypted in WordPress database

2. **Use HTTPS:**
   - Always use HTTPS on your WordPress site for OAuth
   - Google requires HTTPS for production OAuth apps

3. **Limit scope permissions:**
   - The plugin only requests read-only access
   - Never grant write permissions unless absolutely necessary

4. **Review authorized apps:**
   - Periodically review authorized apps in your Google account
   - Go to https://myaccount.google.com/permissions
   - Revoke access for any apps you no longer use

5. **Test users (External apps):**
   - Only add trusted email addresses as test users
   - Remove test users when app goes to production

---

## Required Scopes Explained

### `https://www.googleapis.com/auth/analytics.readonly`
- Allows reading Google Analytics data
- Includes metrics, dimensions, reports
- **Read-only** - cannot modify or delete data
- Used for: GA4 metrics, events, real-time data, traffic sources

### `https://www.googleapis.com/auth/webmasters.readonly`
- Allows reading Search Console data
- Includes search performance, indexing status, sitemaps
- **Read-only** - cannot modify or delete data
- Used for: Search queries, page performance, indexing coverage, URL inspection

---

## FAQ

### Do I need separate credentials for GA4 and GSC?

No, you use the **same OAuth credentials** for both. The plugin requests both scopes during authorization.

### Can I use the same credentials across multiple WordPress sites?

Yes, but you'll need to add each site's redirect URI to the **Authorized redirect URIs** list in Google Cloud Console.

### What happens if I delete the Google Cloud project?

Your WordPress plugin will lose access to Google services. You'll need to create new credentials and reconnect.

### Can other people use my OAuth credentials?

The credentials are tied to your Google Cloud project and your WordPress site's redirect URI. They won't work on other domains unless you explicitly add those domains as authorized redirect URIs.

### How do I revoke access?

**From WordPress:**
1. Go to **Marketing Analytics** → **Connections**
2. Click the appropriate tab (GA4 or GSC)
3. Click **"Disconnect"**

**From Google:**
1. Go to https://myaccount.google.com/permissions
2. Find "Marketing Analytics MCP" (or your app name)
3. Click **"Remove Access"**

### Do I need a Google Cloud billing account?

For typical usage (personal sites, small businesses), **no**. The Google Analytics Data API and Search Console API have generous free quotas. You only need billing if you exceed these quotas, which is unlikely for normal plugin use.

---

## Next Steps

After successfully connecting:

1. **Test your connections:**
   - Use the "Test Connection" button on each platform
   - Verify you see your GA4 properties and GSC sites

2. **Configure your MCP client:**
   - Follow the main README to connect Claude Desktop or n8n
   - Start querying your analytics data via AI!

3. **Explore the tools:**
   - Try the MCP tools to fetch analytics data
   - Use prompts for automated analysis
   - Set up resources for quick data access

---

## Support

If you encounter issues not covered in this guide:

1. Check the [Troubleshooting MCP Guide](TROUBLESHOOTING_MCP.md)
2. Review WordPress plugin logs (via WP_DEBUG)
3. Check Google Cloud Console logs for API errors
4. Open an issue on GitHub with:
   - Steps you followed
   - Error messages (redact sensitive info)
   - WordPress and PHP versions

---

**Last Updated:** 2024-11-14
**Plugin Version:** 1.0.0
