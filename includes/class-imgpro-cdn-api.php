<?php
/**
 * ImgPro CDN API Client
 *
 * Single point of contact for all API communication with the billing service.
 * Handles requests, responses, errors, and caching.
 *
 * @package ImgPro_CDN
 * @since   0.1.7
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Client class
 *
 * @since 0.1.7
 */
class ImgPro_CDN_API {

    /**
     * API base URL
     *
     * @var string
     */
    const BASE_URL = 'https://cloud.wp.img.pro';

    /**
     * Cache TTL in seconds (1 hour)
     *
     * @var int
     */
    const CACHE_TTL = 3600;

    /**
     * Request timeout in seconds
     *
     * @var int
     */
    const TIMEOUT = 15;

    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * In-memory cache for current request
     *
     * @var array|null
     */
    private $site_cache = null;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct() {}

    // =========================================================================
    // PUBLIC API METHODS
    // =========================================================================

    /**
     * Get site data
     *
     * Primary method for fetching site information. Returns cached data
     * if available and not expired, otherwise fetches fresh data.
     *
     * @param string $api_key      Site API key.
     * @param bool   $force_refresh Force fresh fetch, ignoring cache.
     * @return array|WP_Error Site data array or error.
     */
    public function get_site($api_key, $force_refresh = false) {
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key is required', 'bandwidth-saver'));
        }

        // Check cache first (unless forcing refresh)
        if (!$force_refresh) {
            $cached = $this->get_cached_site();
            if ($cached && ($cached['api_key'] ?? '') === $api_key) {
                return $cached;
            }
        }

        // Fetch from API
        $response = $this->request('GET', '/api/sites/' . $api_key);

        if (is_wp_error($response)) {
            return $response;
        }

        // Extract site data from response
        $site = $response['site'] ?? $response;
        $this->cache_site($site);

        return $site;
    }

    /**
     * Find site by URL (for account recovery)
     *
     * @deprecated Use request_recovery() and verify_recovery() instead.
     * @param string $site_url WordPress site URL.
     * @return array|WP_Error Site data array or error.
     */
    public function find_site($site_url) {
        if (empty($site_url)) {
            return new WP_Error('missing_site_url', __('Site URL is required', 'bandwidth-saver'));
        }

        // This endpoint is now disabled for security - use recovery flow instead
        return new WP_Error(
            'deprecated_endpoint',
            __('Direct site lookup is no longer available. Please use email verification for account recovery.', 'bandwidth-saver')
        );
    }

    /**
     * Request account recovery (step 1)
     *
     * Sends a verification code to the registered email address.
     *
     * @since 0.1.9
     * @param string $site_url WordPress site URL.
     * @return array|WP_Error Response with email_hint or error.
     */
    public function request_recovery($site_url) {
        if (empty($site_url)) {
            return new WP_Error('missing_site_url', __('Site URL is required', 'bandwidth-saver'));
        }

        return $this->request('POST', '/api/recovery/request', [
            'site_url' => $site_url,
        ]);
    }

    /**
     * Verify recovery code (step 2)
     *
     * Verifies the code sent to email and returns site data if valid.
     *
     * @since 0.1.9
     * @param string $site_url WordPress site URL.
     * @param string $code     6-digit verification code from email.
     * @return array|WP_Error Site data array or error.
     */
    public function verify_recovery($site_url, $code) {
        if (empty($site_url)) {
            return new WP_Error('missing_site_url', __('Site URL is required', 'bandwidth-saver'));
        }
        if (empty($code)) {
            return new WP_Error('missing_code', __('Verification code is required', 'bandwidth-saver'));
        }

        $response = $this->request('POST', '/api/recovery/verify', [
            'site_url' => $site_url,
            'code'     => $code,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $site = $response['site'] ?? $response;
        $this->cache_site($site);

        return $site;
    }

    /**
     * Create new site account
     *
     * @param string $email          User email.
     * @param string $site_url       WordPress site URL.
     * @param bool   $marketing_opt_in Marketing consent.
     * @return array|WP_Error Site data array or error.
     */
    public function create_site($email, $site_url, $marketing_opt_in = false) {
        if (empty($email) || empty($site_url)) {
            return new WP_Error('missing_fields', __('Email and site URL are required', 'bandwidth-saver'));
        }

        $response = $this->request('POST', '/api/sites', [
            'email'            => $email,
            'site_url'         => $site_url,
            'marketing_opt_in' => $marketing_opt_in,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $site = $response['site'] ?? $response;
        $this->cache_site($site);

        return $site;
    }

    /**
     * Create Stripe checkout session for upgrade
     *
     * @param string $api_key Site API key.
     * @param string $tier_id Target tier ID (default: 'pro').
     * @return array|WP_Error Checkout data with URL or error.
     */
    public function create_checkout($api_key, $tier_id = 'pro') {
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key is required', 'bandwidth-saver'));
        }

        return $this->request('POST', '/api/sites/' . $api_key . '/checkout', [
            'tier_id' => $tier_id,
        ]);
    }

    /**
     * Create Stripe billing portal session
     *
     * @param string $api_key Site API key.
     * @return array|WP_Error Portal data with URL or error.
     */
    public function create_portal($api_key) {
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key is required', 'bandwidth-saver'));
        }

        return $this->request('POST', '/api/sites/' . $api_key . '/portal');
    }

    /**
     * Set custom domain
     *
     * @param string $api_key Site API key.
     * @param string $domain  Custom domain name.
     * @return array|WP_Error Domain data or error.
     */
    public function set_domain($api_key, $domain) {
        if (empty($api_key) || empty($domain)) {
            return new WP_Error('missing_fields', __('API key and domain are required', 'bandwidth-saver'));
        }

        $response = $this->request('PUT', '/api/sites/' . $api_key . '/domain', [
            'domain' => $domain,
        ]);

        if (!is_wp_error($response)) {
            $this->invalidate_cache();
        }

        return $response;
    }

    /**
     * Get custom domain status
     *
     * Always fetches fresh data (not cached) since domain verification
     * status can change at any time.
     *
     * @param string $api_key Site API key.
     * @return array|WP_Error Domain status data or error.
     */
    public function get_domain($api_key) {
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key is required', 'bandwidth-saver'));
        }

        return $this->request('GET', '/api/sites/' . $api_key . '/domain');
    }

    /**
     * Remove custom domain
     *
     * @param string $api_key Site API key.
     * @return array|WP_Error Success response or error.
     */
    public function remove_domain($api_key) {
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key is required', 'bandwidth-saver'));
        }

        $response = $this->request('DELETE', '/api/sites/' . $api_key . '/domain');

        if (!is_wp_error($response)) {
            $this->invalidate_cache();
        }

        return $response;
    }

    // =========================================================================
    // DATA HELPERS
    // =========================================================================

    /**
     * Get all available tiers
     *
     * Returns cached tiers or fetches from API.
     *
     * @param bool $force_refresh Force fresh fetch.
     * @return array Tiers data.
     */
    public function get_tiers($force_refresh = false) {
        // Check cache first
        if (!$force_refresh) {
            $cached = get_transient('imgpro_cdn_tiers');
            if (false !== $cached) {
                return $cached;
            }
        }

        // Fetch from API
        $response = $this->request('GET', '/api/tiers');

        if (is_wp_error($response)) {
            // Return fallback tiers
            return $this->get_fallback_tiers();
        }

        $tiers = $response['tiers'] ?? [];
        set_transient('imgpro_cdn_tiers', $tiers, self::CACHE_TTL);

        return $tiers;
    }

    /**
     * Get fallback tiers when API is unavailable
     *
     * @return array Fallback tiers.
     */
    private function get_fallback_tiers() {
        return [
            [
                'id' => 'free',
                'name' => 'Free',
                'description' => 'Get started',
                'highlight' => false,
                'price' => ['cents' => 0, 'formatted' => 'Free', 'period' => null],
                'limits' => [
                    'storage' => ['bytes' => 10737418240, 'formatted' => '10 GB'],
                    'bandwidth' => ['bytes' => 53687091200, 'formatted' => '50 GB', 'unlimited' => false],
                ],
                'features' => ['custom_domain' => false, 'priority_support' => false],
            ],
            [
                'id' => 'lite',
                'name' => 'Lite',
                'description' => 'Small sites',
                'highlight' => false,
                'price' => ['cents' => 499, 'formatted' => '$4.99', 'period' => '/mo'],
                'limits' => [
                    'storage' => ['bytes' => 26843545600, 'formatted' => '25 GB'],
                    'bandwidth' => ['bytes' => 214748364800, 'formatted' => '200 GB', 'unlimited' => false],
                ],
                'features' => ['custom_domain' => false, 'priority_support' => false],
            ],
            [
                'id' => 'pro',
                'name' => 'Pro',
                'description' => 'Most popular',
                'highlight' => true,
                'price' => ['cents' => 1499, 'formatted' => '$14.99', 'period' => '/mo'],
                'limits' => [
                    'storage' => ['bytes' => 128849018880, 'formatted' => '120 GB'],
                    'bandwidth' => ['bytes' => 2199023255552, 'formatted' => '2 TB', 'unlimited' => false],
                ],
                'features' => ['custom_domain' => true, 'priority_support' => false],
            ],
            [
                'id' => 'business',
                'name' => 'Business',
                'description' => 'High-traffic sites',
                'highlight' => false,
                'price' => ['cents' => 4900, 'formatted' => '$49', 'period' => '/mo'],
                'limits' => [
                    'storage' => ['bytes' => 536870912000, 'formatted' => '500 GB'],
                    'bandwidth' => ['bytes' => 10995116277760, 'formatted' => '10 TB', 'unlimited' => false],
                ],
                'features' => ['custom_domain' => true, 'priority_support' => true],
            ],
        ];
    }

    /**
     * Get pricing information
     *
     * Returns cached pricing from site data, or fallback defaults.
     *
     * @return array Pricing data.
     */
    public function get_pricing() {
        // Check dedicated pricing cache
        $cached = get_transient('imgpro_cdn_pricing');
        if (false !== $cached) {
            return $cached;
        }

        // Try to extract from cached site data
        $site = $this->get_cached_site();
        if ($site && isset($site['tier']['price'])) {
            $pricing = $this->format_pricing($site['tier']['price']);
            set_transient('imgpro_cdn_pricing', $pricing, self::CACHE_TTL);
            return $pricing;
        }

        // Fallback defaults
        return [
            'amount'    => 1499,
            'currency'  => 'USD',
            'interval'  => 'month',
            'formatted' => [
                'amount' => '$14.99',
                'period' => '/mo',
                'full'   => '$14.99/mo',
            ],
        ];
    }

    /**
     * Extract tier ID from site data
     *
     * @param array $site Site data.
     * @return string Tier ID.
     */
    public function get_tier_id($site) {
        if (isset($site['tier']['id'])) {
            return $site['tier']['id'];
        }
        if (isset($site['tier']) && is_string($site['tier'])) {
            return $site['tier'];
        }
        return 'free';
    }

    /**
     * Extract usage data from site
     *
     * @param array $site Site data.
     * @return array Normalized usage data.
     */
    public function get_usage($site) {
        $usage = $site['usage'] ?? [];

        return [
            'storage_used'     => $usage['storage']['used_bytes'] ?? 0,
            'storage_limit'    => $usage['storage']['limit_bytes'] ?? 0,
            'bandwidth_used'   => $usage['bandwidth']['used_bytes'] ?? 0,
            'bandwidth_limit'  => $usage['bandwidth']['limit_bytes'] ?? 0,
            'images_cached'    => $usage['images_cached'] ?? 0,
        ];
    }

    /**
     * Extract custom domain data from site
     *
     * @param array $site Site data.
     * @return array|null Domain data or null if not configured.
     */
    public function get_custom_domain($site) {
        $domain = $site['custom_domain'] ?? null;

        if (!$domain || !is_array($domain) || empty($domain['domain'])) {
            return null;
        }

        return [
            'domain' => $domain['domain'],
            'status' => $domain['status'] ?? 'pending_dns',
        ];
    }

    // =========================================================================
    // CACHE MANAGEMENT
    // =========================================================================

    /**
     * Get cached site data
     *
     * @return array|null Cached site data or null.
     */
    public function get_cached_site() {
        // Check in-memory cache first (same request)
        if (null !== $this->site_cache) {
            return $this->site_cache;
        }

        // Check transient cache
        $cached = get_transient('imgpro_cdn_site_data');
        if (false !== $cached) {
            $this->site_cache = $cached;
            return $cached;
        }

        return null;
    }

    /**
     * Invalidate all cached data
     *
     * Call this after mutations that change site state.
     */
    public function invalidate_cache() {
        $this->site_cache = null;
        delete_transient('imgpro_cdn_site_data');
        delete_transient('imgpro_cdn_pricing');
        delete_transient('imgpro_cdn_last_sync');
    }

    /**
     * Check if cache is fresh
     *
     * @return bool True if cache exists and is not expired.
     */
    public function is_cache_fresh() {
        return false !== get_transient('imgpro_cdn_site_data');
    }

    // =========================================================================
    // PRIVATE METHODS
    // =========================================================================

    /**
     * Make HTTP request to API
     *
     * @param string     $method   HTTP method (GET, POST, PUT, DELETE).
     * @param string     $endpoint API endpoint path.
     * @param array|null $data     Request data (query params for GET, body for others).
     * @return array|WP_Error Response data or error.
     */
    private function request($method, $endpoint, $data = null) {
        $url = $this->get_base_url() . $endpoint;

        $args = [
            'method'  => $method,
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
        ];

        // GET requests: add data as query params
        if ('GET' === $method && $data) {
            $url = add_query_arg(array_map('rawurlencode', $data), $url);
        }
        // Other methods: add data as JSON body
        elseif ($data) {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        // Handle connection errors
        if (is_wp_error($response)) {
            do_action('imgpro_cdn_api_error', $response, $endpoint);
            return new WP_Error(
                'connection_error',
                __('Could not connect to the service. Please try again.', 'bandwidth-saver'),
                ['original' => $response]
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Handle API errors
        if ($status_code >= 400) {
            $error_message = $body['error'] ?? __('An error occurred', 'bandwidth-saver');
            $error_code = $this->get_error_code($status_code);

            do_action('imgpro_cdn_api_error', [
                'status'   => $status_code,
                'message'  => $error_message,
                'endpoint' => $endpoint,
            ], $endpoint);

            return new WP_Error($error_code, $error_message, [
                'status' => $status_code,
                'body'   => $body,
            ]);
        }

        return $body ?? [];
    }

    /**
     * Get API base URL
     *
     * @return string Base URL.
     */
    private function get_base_url() {
        return apply_filters('imgpro_cdn_api_base_url', self::BASE_URL);
    }

    /**
     * Cache site data
     *
     * @param array $site Site data to cache.
     */
    private function cache_site($site) {
        $this->site_cache = $site;
        set_transient('imgpro_cdn_site_data', $site, self::CACHE_TTL);

        // Also cache pricing for quick access
        if (isset($site['tier']['price'])) {
            $pricing = $this->format_pricing($site['tier']['price']);
            set_transient('imgpro_cdn_pricing', $pricing, self::CACHE_TTL);
        }
    }

    /**
     * Format pricing data
     *
     * @param array $price Raw price data from API.
     * @return array Formatted pricing.
     */
    private function format_pricing($price) {
        $amount = $price['formatted'] ?? '$14.99';
        $period = $price['period'] ?? '/mo';

        return [
            'amount'    => $price['cents'] ?? 1499,
            'currency'  => 'USD',
            'interval'  => 'month',
            'formatted' => [
                'amount' => $amount,
                'period' => $period,
                'full'   => $amount . $period,
            ],
        ];
    }

    /**
     * Map HTTP status code to error code
     *
     * @param int $status HTTP status code.
     * @return string Error code.
     */
    private function get_error_code($status) {
        switch ($status) {
            case 400:
                return 'bad_request';
            case 401:
            case 403:
                return 'unauthorized';
            case 404:
                return 'not_found';
            case 409:
                return 'conflict';
            case 429:
                return 'rate_limited';
            default:
                return 'api_error';
        }
    }
}
