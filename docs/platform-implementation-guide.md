# Platform Implementation Guide
## WordPress Marketing Analytics MCP Plugin - Recommended Platforms

**Focus Platforms:** Meta Business Suite API, DataForSEO API

---

## Meta Business Suite Implementation

### 1. OAuth 2.0 Setup

#### Admin UI for OAuth Connection

```php
// admin/views/connections/meta-business.php

<div class="meta-business-connection">
    <h3>Meta Business Suite Connection</h3>

    <?php if (!$this->is_connected()): ?>
        <p>Connect your Facebook and Instagram business accounts to access analytics data.</p>

        <a href="<?php echo esc_url($this->get_oauth_url()); ?>" class="button button-primary">
            Connect with Meta
        </a>
    <?php else: ?>
        <div class="connection-status">
            <span class="dashicons dashicons-yes-alt"></span>
            Connected as: <?php echo esc_html($this->get_connected_account_name()); ?>
        </div>

        <div class="connected-pages">
            <h4>Connected Pages:</h4>
            <ul>
                <?php foreach ($this->get_connected_pages() as $page): ?>
                    <li>
                        <?php echo esc_html($page['name']); ?>
                        (<?php echo esc_html($page['platform']); ?>)
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <button class="button" id="disconnect-meta">Disconnect</button>
    <?php endif; ?>
</div>
```

#### OAuth Flow Implementation

```php
// includes/api-clients/class-meta-oauth-handler.php

namespace Marketing_Analytics_MCP\API_Clients;

class Meta_OAuth_Handler {

    const OAUTH_BASE = 'https://www.facebook.com/v18.0/dialog/oauth';
    const TOKEN_ENDPOINT = 'https://graph.facebook.com/v18.0/oauth/access_token';

    private $app_id;
    private $app_secret;
    private $redirect_uri;

    public function __construct() {
        $credentials = get_option('marketing_mcp_meta_app_credentials');
        $this->app_id = $credentials['app_id'] ?? '';
        $this->app_secret = $credentials['app_secret'] ?? '';
        $this->redirect_uri = admin_url('admin.php?page=marketing-analytics-chat-oauth-callback');
    }

    /**
     * Get OAuth authorization URL
     */
    public function get_authorization_url() {
        $state = wp_create_nonce('meta_oauth_' . get_current_user_id());
        set_transient('meta_oauth_state_' . get_current_user_id(), $state, HOUR_IN_SECONDS);

        $params = [
            'client_id' => $this->app_id,
            'redirect_uri' => $this->redirect_uri,
            'state' => $state,
            'scope' => implode(',', $this->get_required_scopes()),
            'response_type' => 'code'
        ];

        return self::OAUTH_BASE . '?' . http_build_query($params);
    }

    /**
     * Handle OAuth callback
     */
    public function handle_callback($code, $state) {
        // Verify state
        $stored_state = get_transient('meta_oauth_state_' . get_current_user_id());
        if (!$stored_state || $state !== $stored_state) {
            return new \WP_Error('invalid_state', 'Invalid OAuth state parameter');
        }

        // Exchange code for access token
        $response = wp_remote_post(self::TOKEN_ENDPOINT, [
            'body' => [
                'client_id' => $this->app_id,
                'client_secret' => $this->app_secret,
                'redirect_uri' => $this->redirect_uri,
                'code' => $code
            ]
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['access_token'])) {
            // Exchange for long-lived token
            $long_lived = $this->exchange_for_long_lived_token($body['access_token']);

            // Store encrypted
            $this->store_access_token($long_lived);

            // Get and store connected accounts
            $this->fetch_and_store_accounts($long_lived);

            return true;
        }

        return new \WP_Error('token_exchange_failed', 'Failed to exchange code for token');
    }

    /**
     * Exchange short-lived token for long-lived token (60+ days)
     */
    private function exchange_for_long_lived_token($short_token) {
        $response = wp_remote_get(self::TOKEN_ENDPOINT . '?' . http_build_query([
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->app_id,
            'client_secret' => $this->app_secret,
            'fb_exchange_token' => $short_token
        ]));

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['access_token'] ?? $short_token;
    }

    /**
     * Get required permission scopes
     */
    private function get_required_scopes() {
        return [
            'pages_show_list',
            'pages_read_engagement',
            'pages_read_user_content',
            'instagram_basic',
            'instagram_manage_insights',
            'instagram_manage_comments',
            'read_insights',
            'ads_read' // Optional: for ad campaign data
        ];
    }

    /**
     * Fetch and store connected accounts
     */
    private function fetch_and_store_accounts($access_token) {
        $client = new Meta_Business_Client(['access_token' => $access_token]);

        // Get Facebook pages
        $pages = $client->get_user_pages();

        // Get Instagram accounts
        $instagram_accounts = [];
        foreach ($pages as $page) {
            $ig_account = $client->get_instagram_account($page['id']);
            if ($ig_account) {
                $instagram_accounts[] = $ig_account;
            }
        }

        // Store account data
        update_option('marketing_mcp_meta_accounts', [
            'facebook_pages' => $pages,
            'instagram_accounts' => $instagram_accounts,
            'last_updated' => current_time('timestamp')
        ]);
    }
}
```

### 2. Meta Business Client Implementation

```php
// includes/api-clients/class-meta-business-client.php

namespace Marketing_Analytics_MCP\API_Clients;

class Meta_Business_Client extends Base_API_Client {

    const GRAPH_API_VERSION = 'v18.0';
    const BASE_URL = 'https://graph.facebook.com/';

    private $access_token;

    public function __construct($credentials = null) {
        if (!$credentials) {
            $encrypted = get_option('marketing_mcp_meta_credentials');
            $credentials = Encryption_Manager::decrypt($encrypted);
        }
        $this->access_token = $credentials['access_token'] ?? '';
    }

    /**
     * Get Facebook page insights
     */
    public function get_page_insights($page_id, $metrics, $period = 'day', $since = null, $until = null) {
        $cache_key = 'meta_page_insights_' . md5(serialize(func_get_args()));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $params = [
            'metric' => implode(',', $metrics),
            'period' => $period
        ];

        if ($since && $until) {
            $params['since'] = $since;
            $params['until'] = $until;
        }

        $endpoint = "{$page_id}/insights";
        $response = $this->make_request($endpoint, $params);

        if (!is_wp_error($response)) {
            set_transient($cache_key, $response, 30 * MINUTE_IN_SECONDS);
        }

        return $response;
    }

    /**
     * Get Instagram account insights
     */
    public function get_instagram_insights($ig_account_id, $metrics, $period = 'day', $since = null, $until = null) {
        $cache_key = 'meta_ig_insights_' . md5(serialize(func_get_args()));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $params = [
            'metric' => implode(',', $metrics),
            'period' => $period
        ];

        if ($since && $until) {
            $params['since'] = strtotime($since);
            $params['until'] = strtotime($until);
        }

        $endpoint = "{$ig_account_id}/insights";
        $response = $this->make_request($endpoint, $params);

        if (!is_wp_error($response)) {
            set_transient($cache_key, $response, 30 * MINUTE_IN_SECONDS);
        }

        return $response;
    }

    /**
     * Get media insights for specific posts
     */
    public function get_media_insights($media_id, $metrics) {
        $params = [
            'metric' => implode(',', $metrics)
        ];

        return $this->make_request("{$media_id}/insights", $params);
    }

    /**
     * Get audience demographics
     */
    public function get_audience_demographics($account_id, $platform = 'facebook') {
        $cache_key = "meta_demographics_{$platform}_{$account_id}";
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Demographics are part of insights with lifetime period
        $metrics = ['audience_gender_age', 'audience_country', 'audience_city'];

        $endpoint = $platform === 'instagram'
            ? "{$account_id}/insights"
            : "{$account_id}/insights";

        $params = [
            'metric' => implode(',', $metrics),
            'period' => 'lifetime'
        ];

        $response = $this->make_request($endpoint, $params);

        if (!is_wp_error($response)) {
            set_transient($cache_key, $response, DAY_IN_SECONDS);
        }

        return $response;
    }

    /**
     * Make API request
     */
    protected function make_request($endpoint, $params = []) {
        $params['access_token'] = $this->access_token;

        $url = self::BASE_URL . self::GRAPH_API_VERSION . '/' . $endpoint;
        $url .= '?' . http_build_query($params);

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            do_action('marketing_mcp_api_error', 'meta', $response);
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Check for API errors
        if (isset($body['error'])) {
            $error = new \WP_Error(
                'meta_api_error',
                $body['error']['message'],
                $body['error']
            );
            do_action('marketing_mcp_api_error', 'meta', $error);
            return $error;
        }

        do_action('marketing_mcp_api_success', 'meta', $endpoint, $body);

        return $body;
    }
}
```

### 3. Meta Business Abilities Registration

```php
// includes/abilities/class-meta-business-abilities.php

namespace Marketing_Analytics_MCP\Abilities;

class Meta_Business_Abilities {

    public function __construct() {
        add_action('abilities_api_init', [$this, 'register_abilities']);
    }

    public function register_abilities($abilities_api) {
        // Tool: Get Page Insights
        $abilities_api->register_tool([
            'name' => 'marketing-analytics/meta-page-insights',
            'description' => 'Get Facebook page performance metrics including reach, engagement, and impressions',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'page_id' => [
                        'type' => 'string',
                        'description' => 'Facebook Page ID'
                    ],
                    'metrics' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'default' => ['page_impressions', 'page_engaged_users', 'page_fans'],
                        'description' => 'Metrics to retrieve'
                    ],
                    'period' => [
                        'type' => 'string',
                        'enum' => ['day', 'week', 'days_28'],
                        'default' => 'day'
                    ],
                    'days' => [
                        'type' => 'integer',
                        'default' => 7,
                        'description' => 'Number of days to look back'
                    ]
                ],
                'required' => ['page_id']
            ],
            'handler' => [$this, 'handle_page_insights']
        ]);

        // Tool: Get Instagram Insights
        $abilities_api->register_tool([
            'name' => 'marketing-analytics/meta-instagram-insights',
            'description' => 'Get Instagram account and media performance metrics',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'instagram_account_id' => [
                        'type' => 'string',
                        'description' => 'Instagram Business Account ID'
                    ],
                    'metrics' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'default' => ['impressions', 'reach', 'profile_views'],
                        'description' => 'Metrics to retrieve'
                    ],
                    'period' => [
                        'type' => 'string',
                        'enum' => ['day', 'week', 'days_28', 'lifetime'],
                        'default' => 'day'
                    ],
                    'days' => [
                        'type' => 'integer',
                        'default' => 7
                    ]
                ],
                'required' => ['instagram_account_id']
            ],
            'handler' => [$this, 'handle_instagram_insights']
        ]);

        // Tool: Compare Platform Performance
        $abilities_api->register_tool([
            'name' => 'marketing-analytics/meta-compare-platforms',
            'description' => 'Compare performance metrics across Facebook and Instagram',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'days' => [
                        'type' => 'integer',
                        'default' => 30
                    ],
                    'metrics' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'default' => ['reach', 'engagement', 'impressions']
                    ]
                ],
                'required' => []
            ],
            'handler' => [$this, 'handle_platform_comparison']
        ]);

        // Resource: Social Media Dashboard
        $abilities_api->register_resource([
            'name' => 'marketing-analytics/social-dashboard',
            'description' => 'Comprehensive social media performance dashboard',
            'mimeType' => 'application/json',
            'handler' => [$this, 'handle_social_dashboard']
        ]);
    }

    public function handle_page_insights($params) {
        $client = new \Marketing_Analytics_MCP\API_Clients\Meta_Business_Client();

        $since = date('Y-m-d', strtotime("-{$params['days']} days"));
        $until = date('Y-m-d');

        $insights = $client->get_page_insights(
            $params['page_id'],
            $params['metrics'],
            $params['period'],
            $since,
            $until
        );

        if (is_wp_error($insights)) {
            return [
                'error' => $insights->get_error_message()
            ];
        }

        return [
            'success' => true,
            'data' => $this->format_insights_response($insights),
            'period' => [
                'start' => $since,
                'end' => $until
            ]
        ];
    }

    private function format_insights_response($insights) {
        $formatted = [];

        foreach ($insights['data'] as $metric) {
            $formatted[$metric['name']] = [
                'title' => $metric['title'],
                'description' => $metric['description'],
                'values' => $metric['values']
            ];
        }

        return $formatted;
    }
}
```

---

## DataForSEO Implementation

### 1. DataForSEO Client

```php
// includes/api-clients/class-dataforseo-client.php

namespace Marketing_Analytics_MCP\API_Clients;

class DataForSEO_Client extends Base_API_Client {

    const BASE_URL = 'https://api.dataforseo.com/v3/';

    private $auth_header;
    private $remaining_credits;

    public function __construct($credentials = null) {
        if (!$credentials) {
            $encrypted = get_option('marketing_mcp_dataforseo_credentials');
            $credentials = Encryption_Manager::decrypt($encrypted);
        }

        $this->auth_header = 'Basic ' . base64_encode(
            $credentials['login'] . ':' . $credentials['password']
        );

        // Check remaining credits on init
        $this->check_credits();
    }

    /**
     * Check remaining credits
     */
    public function check_credits() {
        $response = $this->make_request('appendix/user_data');
        if (!is_wp_error($response)) {
            $this->remaining_credits = $response['tasks'][0]['result'][0]['money']['balance'] ?? 0;
            update_option('marketing_mcp_dataforseo_credits', $this->remaining_credits);
        }
        return $this->remaining_credits;
    }

    /**
     * Get SERP rankings for keywords
     */
    public function get_serp_rankings($domain, $keywords, $location_code = 2840, $language_code = 'en') {
        $cache_key = 'dataforseo_serp_' . md5(serialize(func_get_args()));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $tasks = [];
        foreach ($keywords as $keyword) {
            $tasks[] = [
                'keyword' => $keyword,
                'location_code' => $location_code,
                'language_code' => $language_code,
                'device' => 'desktop',
                'os' => 'windows',
                'depth' => 100
            ];
        }

        $response = $this->make_request('serp/google/organic/task_post', $tasks);

        if (!is_wp_error($response)) {
            // Process results to find domain rankings
            $rankings = $this->extract_domain_rankings($response, $domain);
            set_transient($cache_key, $rankings, HOUR_IN_SECONDS);
            return $rankings;
        }

        return $response;
    }

    /**
     * Get keyword suggestions
     */
    public function get_keyword_ideas($seed_keywords, $location_code = 2840, $language_code = 'en') {
        $cache_key = 'dataforseo_keywords_' . md5(serialize(func_get_args()));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $task = [
            'keywords' => $seed_keywords,
            'location_code' => $location_code,
            'language_code' => $language_code,
            'include_serp_info' => true,
            'include_clickstream_data' => true
        ];

        $response = $this->make_request('keywords_data/google_ads/keywords_for_keywords/task_post', [$task]);

        if (!is_wp_error($response)) {
            set_transient($cache_key, $response, 6 * HOUR_IN_SECONDS);
        }

        return $response;
    }

    /**
     * Get competitor analysis
     */
    public function get_competitors($domain, $location_code = 2840, $language_code = 'en') {
        $cache_key = 'dataforseo_competitors_' . md5(serialize(func_get_args()));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $task = [
            'target' => $domain,
            'location_code' => $location_code,
            'language_code' => $language_code,
            'filters' => [
                ['avg_position', '<=', 30]
            ]
        ];

        $response = $this->make_request('dataforseo_labs/google/competitors_domain/live', [$task]);

        if (!is_wp_error($response)) {
            set_transient($cache_key, $response, DAY_IN_SECONDS);
        }

        return $response;
    }

    /**
     * Get backlink profile
     */
    public function get_backlinks($target, $mode = 'domain', $limit = 100) {
        $cache_key = 'dataforseo_backlinks_' . md5(serialize(func_get_args()));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $task = [
            'target' => $target,
            'mode' => $mode,
            'limit' => $limit,
            'order_by' => ['rank,desc'],
            'backlinks_status_type' => 'live'
        ];

        $response = $this->make_request('backlinks/backlinks/live', [$task]);

        if (!is_wp_error($response)) {
            set_transient($cache_key, $response, 12 * HOUR_IN_SECONDS);
        }

        return $response;
    }

    /**
     * Make API request
     */
    protected function make_request($endpoint, $data = null) {
        $args = [
            'headers' => [
                'Authorization' => $this->auth_header,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 60
        ];

        if ($data !== null) {
            $args['body'] = json_encode($data);
            $response = wp_remote_post(self::BASE_URL . $endpoint, $args);
        } else {
            $response = wp_remote_get(self::BASE_URL . $endpoint, $args);
        }

        if (is_wp_error($response)) {
            do_action('marketing_mcp_api_error', 'dataforseo', $response);
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Check for API errors
        if ($body['status_code'] !== 20000) {
            $error = new \WP_Error(
                'dataforseo_api_error',
                $body['status_message'] ?? 'API request failed',
                $body
            );
            do_action('marketing_mcp_api_error', 'dataforseo', $error);
            return $error;
        }

        // Update credit balance
        if (isset($body['cost'])) {
            $this->remaining_credits -= $body['cost'];
            update_option('marketing_mcp_dataforseo_credits', $this->remaining_credits);
        }

        do_action('marketing_mcp_api_success', 'dataforseo', $endpoint, $body);

        return $body;
    }

    /**
     * Extract domain rankings from SERP results
     */
    private function extract_domain_rankings($serp_response, $domain) {
        $rankings = [];

        foreach ($serp_response['tasks'] as $task) {
            if (!isset($task['result'][0]['items'])) {
                continue;
            }

            $keyword = $task['data']['keyword'];
            $found = false;

            foreach ($task['result'][0]['items'] as $index => $item) {
                if ($item['type'] !== 'organic') {
                    continue;
                }

                if (strpos($item['domain'], $domain) !== false) {
                    $rankings[] = [
                        'keyword' => $keyword,
                        'position' => $index + 1,
                        'url' => $item['url'],
                        'title' => $item['title'],
                        'description' => $item['description']
                    ];
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $rankings[] = [
                    'keyword' => $keyword,
                    'position' => null,
                    'url' => null,
                    'title' => 'Not ranking in top 100',
                    'description' => null
                ];
            }
        }

        return $rankings;
    }
}
```

### 2. DataForSEO Abilities Registration

```php
// includes/abilities/class-dataforseo-abilities.php

namespace Marketing_Analytics_MCP\Abilities;

class DataForSEO_Abilities {

    public function __construct() {
        add_action('abilities_api_init', [$this, 'register_abilities']);
    }

    public function register_abilities($abilities_api) {
        // Tool: Get SERP Rankings
        $abilities_api->register_tool([
            'name' => 'marketing-analytics/dataforseo-serp-rankings',
            'description' => 'Get current search engine rankings for target keywords',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'domain' => [
                        'type' => 'string',
                        'description' => 'Domain to check rankings for'
                    ],
                    'keywords' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Keywords to check rankings for'
                    ],
                    'location' => [
                        'type' => 'string',
                        'default' => 'United States',
                        'description' => 'Location for search results'
                    ]
                ],
                'required' => ['domain', 'keywords']
            ],
            'handler' => [$this, 'handle_serp_rankings']
        ]);

        // Tool: Keyword Research
        $abilities_api->register_tool([
            'name' => 'marketing-analytics/dataforseo-keyword-research',
            'description' => 'Get keyword ideas with search volume and competition data',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'seed_keywords' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Seed keywords for research'
                    ],
                    'include_serp_features' => [
                        'type' => 'boolean',
                        'default' => true
                    ]
                ],
                'required' => ['seed_keywords']
            ],
            'handler' => [$this, 'handle_keyword_research']
        ]);

        // Tool: Competitor Analysis
        $abilities_api->register_tool([
            'name' => 'marketing-analytics/dataforseo-competitors',
            'description' => 'Analyze competitor domains and their rankings',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'domain' => [
                        'type' => 'string',
                        'description' => 'Your domain'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'default' => 10,
                        'description' => 'Number of competitors to return'
                    ]
                ],
                'required' => ['domain']
            ],
            'handler' => [$this, 'handle_competitors']
        ]);

        // Resource: SEO Overview
        $abilities_api->register_resource([
            'name' => 'marketing-analytics/seo-overview',
            'description' => 'Comprehensive SEO performance overview',
            'mimeType' => 'application/json',
            'handler' => [$this, 'handle_seo_overview']
        ]);
    }

    public function handle_serp_rankings($params) {
        // Check credits before making request
        $client = new \Marketing_Analytics_MCP\API_Clients\DataForSEO_Client();

        if ($client->check_credits() < 0.1) {
            return [
                'error' => 'Insufficient DataForSEO credits. Please top up your account.'
            ];
        }

        // Map location to code (simplified - should use full mapping)
        $location_code = $this->get_location_code($params['location']);

        $rankings = $client->get_serp_rankings(
            $params['domain'],
            $params['keywords'],
            $location_code
        );

        if (is_wp_error($rankings)) {
            return [
                'error' => $rankings->get_error_message()
            ];
        }

        return [
            'success' => true,
            'domain' => $params['domain'],
            'rankings' => $rankings,
            'credits_remaining' => $client->check_credits()
        ];
    }

    private function get_location_code($location_name) {
        $locations = [
            'United States' => 2840,
            'United Kingdom' => 2826,
            'Canada' => 2124,
            'Australia' => 2036,
            // Add more as needed
        ];

        return $locations[$location_name] ?? 2840;
    }
}
```

---

## Credit Management System

```php
// includes/admin/class-credit-manager.php

namespace Marketing_Analytics_MCP\Admin;

class Credit_Manager {

    public function __construct() {
        add_action('admin_notices', [$this, 'show_credit_warnings']);
        add_action('wp_ajax_check_dataforseo_credits', [$this, 'ajax_check_credits']);
    }

    /**
     * Show admin notices for low credits
     */
    public function show_credit_warnings() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $credits = get_option('marketing_mcp_dataforseo_credits', null);

        if ($credits !== null && $credits < 10) {
            $class = $credits < 1 ? 'error' : 'warning';
            ?>
            <div class="notice notice-<?php echo esc_attr($class); ?> is-dismissible">
                <p>
                    <strong>DataForSEO Credits Low:</strong>
                    You have $<?php echo number_format($credits, 2); ?> remaining.
                    <a href="https://app.dataforseo.com/" target="_blank">Top up your account</a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * AJAX endpoint to check credits
     */
    public function ajax_check_credits() {
        check_ajax_referer('marketing_mcp_nonce');

        if (!current_user_can('manage_options')) {
            wp_die();
        }

        $client = new \Marketing_Analytics_MCP\API_Clients\DataForSEO_Client();
        $credits = $client->check_credits();

        wp_send_json_success([
            'credits' => $credits,
            'formatted' => '$' . number_format($credits, 2)
        ]);
    }
}
```

---

## Testing Implementation

```php
// tests/test-meta-business-api.php

class Test_Meta_Business_API extends WP_UnitTestCase {

    private $client;

    public function setUp() {
        parent::setUp();

        // Mock credentials
        update_option('marketing_mcp_meta_credentials', [
            'access_token' => 'test_token'
        ]);

        $this->client = new \Marketing_Analytics_MCP\API_Clients\Meta_Business_Client();
    }

    public function test_get_page_insights() {
        // Mock HTTP response
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if (strpos($url, 'graph.facebook.com') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'data' => [
                            [
                                'name' => 'page_impressions',
                                'period' => 'day',
                                'values' => [
                                    ['value' => 1000, 'end_time' => '2025-11-18']
                                ]
                            ]
                        ]
                    ])
                ];
            }
            return $preempt;
        }, 10, 3);

        $insights = $this->client->get_page_insights(
            'test_page_id',
            ['page_impressions'],
            'day'
        );

        $this->assertArrayHasKey('data', $insights);
        $this->assertEquals('page_impressions', $insights['data'][0]['name']);
    }
}
```

---

## Admin UI Integration

```php
// admin/js/meta-business-admin.js

jQuery(document).ready(function($) {
    // Handle OAuth connection
    $('#connect-meta-business').on('click', function(e) {
        e.preventDefault();

        const authWindow = window.open(
            $(this).attr('href'),
            'meta-oauth',
            'width=600,height=600'
        );

        // Check for successful callback
        const checkInterval = setInterval(function() {
            if (authWindow.closed) {
                clearInterval(checkInterval);

                // Reload to show connection status
                $.post(ajaxurl, {
                    action: 'check_meta_connection',
                    nonce: marketing_mcp.nonce
                }, function(response) {
                    if (response.success && response.data.connected) {
                        location.reload();
                    }
                });
            }
        }, 1000);
    });

    // Handle disconnection
    $('#disconnect-meta').on('click', function() {
        if (confirm('Are you sure you want to disconnect Meta Business Suite?')) {
            $.post(ajaxurl, {
                action: 'disconnect_meta',
                nonce: marketing_mcp.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        }
    });

    // Credit monitoring for DataForSEO
    function checkDataForSEOCredits() {
        $.post(ajaxurl, {
            action: 'check_dataforseo_credits',
            nonce: marketing_mcp.nonce
        }, function(response) {
            if (response.success) {
                $('#dataforseo-credits').text(response.data.formatted);

                if (response.data.credits < 10) {
                    $('#dataforseo-credits').addClass('credits-low');
                }
            }
        });
    }

    // Check credits every 5 minutes
    if ($('#dataforseo-credits').length) {
        checkDataForSEOCredits();
        setInterval(checkDataForSEOCredits, 300000);
    }
});
```

---

## Deployment Checklist

### Pre-Deployment
- [ ] Create Meta App at developers.facebook.com
- [ ] Submit for App Review (required permissions)
- [ ] Set up DataForSEO account and fund with credits
- [ ] Configure OAuth redirect URLs
- [ ] Test authentication flows

### Security
- [ ] Implement credential encryption
- [ ] Add nonce verification to all AJAX endpoints
- [ ] Sanitize all user inputs
- [ ] Escape all outputs
- [ ] Implement rate limiting

### Testing
- [ ] Unit tests for API clients
- [ ] Integration tests for OAuth flow
- [ ] Test credit management system
- [ ] Test caching layer
- [ ] Test error handling

### Documentation
- [ ] User setup guide
- [ ] API credential configuration
- [ ] Troubleshooting guide
- [ ] MCP usage examples

---

*Implementation guide prepared for WordPress Marketing Analytics MCP Plugin*
*Version 1.0.0 - November 18, 2025*