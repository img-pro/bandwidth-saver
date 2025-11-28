<?php
/**
 * ImgPro CDN Settings Management
 *
 * @package ImgPro_CDN
 * @since   0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings management class
 *
 * Handles storage, retrieval, validation, and sanitization of plugin settings.
 *
 * @since 0.1.0
 */
class ImgPro_CDN_Settings {

    /**
     * Option key for storing settings
     *
     * @since 0.1.0
     * @var string
     */
    const OPTION_KEY = 'imgpro_cdn_settings';

    /**
     * Setup mode: Cloud (Managed)
     *
     * @since 0.1.2
     * @var string
     */
    const MODE_CLOUD = 'cloud';

    /**
     * Setup mode: Cloudflare (Self-Hosted)
     *
     * @since 0.1.2
     * @var string
     */
    const MODE_CLOUDFLARE = 'cloudflare';

    /**
     * Subscription tier: None (not registered)
     *
     * @since 0.1.2
     * @var string
     */
    const TIER_NONE = 'none';

    /**
     * Subscription tier: Free
     *
     * @since 0.2.0
     * @var string
     */
    const TIER_FREE = 'free';

    /**
     * Subscription tier: Pro (paid)
     *
     * @since 0.2.0
     * @var string
     */
    const TIER_PRO = 'pro';

    /**
     * Subscription tier: Active (legacy, maps to pro)
     *
     * @since 0.1.2
     * @var string
     */
    const TIER_ACTIVE = 'active';

    /**
     * Subscription tier: Cancelled
     *
     * @since 0.1.2
     * @var string
     */
    const TIER_CANCELLED = 'cancelled';

    /**
     * Subscription tier: Past due (payment failed, grace period)
     *
     * @since 0.2.0
     * @var string
     */
    const TIER_PAST_DUE = 'past_due';

    /**
     * Subscription tier: Suspended (no access)
     *
     * @since 0.2.0
     * @var string
     */
    const TIER_SUSPENDED = 'suspended';

    /**
     * Free tier storage limit in bytes (1 GB)
     *
     * @since 0.2.0
     * @var int
     */
    const FREE_STORAGE_LIMIT = 1073741824;

    /**
     * Pro tier storage limit in bytes (100 GB)
     *
     * @since 0.2.0
     * @var int
     */
    const PRO_STORAGE_LIMIT = 107374182400;

    /**
     * API base URL for cloud services
     *
     * @since 0.1.3
     * @var string
     */
    const API_BASE_URL = 'https://cloud.wp.img.pro';

    /**
     * Default CDN domain for Cloud (Managed) mode
     *
     * Single-domain architecture: Worker serves images directly from R2.
     *
     * @since 0.1.5
     * @var string
     */
    const CLOUD_CDN_DOMAIN = 'px.img.pro';

    /**
     * Custom domain CNAME target for Cloud (Managed) mode
     *
     * Users point their custom domain CNAME to this target.
     *
     * @since 0.1.6
     * @var string
     */
    const CUSTOM_DOMAIN_TARGET = 'domains.img.pro';

    /**
     * Default settings
     *
     * @since 0.1.0
     * @var array
     */
    private $defaults = [
        'enabled'            => false,
        'previously_enabled' => false,
        'setup_mode'         => '',

        // Cloud mode settings
        'cloud_api_key'   => '',
        'cloud_email'     => '',
        'cloud_tier'      => self::TIER_NONE,

        // Custom domain settings (Cloud mode only)
        'custom_domain'        => '',
        'custom_domain_status' => '', // pending_dns, pending_ssl, active, error

        // Usage stats (synced from Cloud API)
        'storage_used'       => 0,
        'storage_limit'      => 0,
        'images_cached'      => 0,
        'bandwidth_saved'    => 0,
        'stats_updated_at'   => 0,

        // Onboarding state
        'onboarding_completed' => false,
        'onboarding_step'      => 1,
        'marketing_opt_in'     => false,

        // Cloudflare mode settings (single domain - worker serves images directly)
        'cdn_url'         => '',

        // Common settings
        'allowed_domains' => [],
        'debug_mode'      => false,
    ];

    /**
     * Cached settings
     *
     * @since 0.1.0
     * @var array|null
     */
    private $settings = null;

    /**
     * Get all settings
     *
     * @since 0.1.0
     * @return array
     */
    public function get_all() {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $stored = get_option(self::OPTION_KEY, []);
        $this->settings = wp_parse_args($stored, $this->defaults);

        return $this->settings;
    }

    /**
     * Get specific setting
     *
     * @since 0.1.0
     * @param string $key     Setting key.
     * @param mixed  $default Default value if not found.
     * @return mixed
     */
    public function get($key, $default = null) {
        $settings = $this->get_all();

        // Auto-configure Cloud mode CDN URL (single domain architecture)
        if (self::MODE_CLOUD === $settings['setup_mode']) {
            if ('cdn_url' === $key) {
                // Use custom domain if active, otherwise default cloud domain
                if (!empty($settings['custom_domain']) && 'active' === $settings['custom_domain_status']) {
                    return $settings['custom_domain'];
                }
                return self::CLOUD_CDN_DOMAIN;
            }
        }

        if (isset($settings[$key])) {
            return $settings[$key];
        }

        return $default !== null ? $default : ($this->defaults[$key] ?? null);
    }

    /**
     * Update settings
     *
     * @since 0.1.0
     * @param array $new_settings New settings to merge.
     * @return bool
     */
    public function update($new_settings) {
        $current = $this->get_all();
        $validated = $this->validate($new_settings);
        $updated = array_merge($current, $validated);

        // Settings are used on every page load, so autoload=true (default)
        $result = update_option(self::OPTION_KEY, $updated, true);
        $this->settings = null; // Clear cache

        /**
         * Fires after ImgPro CDN settings are updated
         *
         * @param array $updated The new settings
         * @param array $current The previous settings
         */
        do_action('imgpro_cdn_settings_updated', $updated, $current);

        return $result;
    }

    /**
     * Validate settings
     *
     * @since 0.1.0
     * @param array $settings Settings to validate.
     * @return array
     */
    public function validate($settings) {
        $validated = [];

        // Setup mode (string: 'cloud' or 'cloudflare')
        if (isset($settings['setup_mode'])) {
            $mode = sanitize_text_field($settings['setup_mode']);
            if (in_array($mode, [self::MODE_CLOUD, self::MODE_CLOUDFLARE], true)) {
                $validated['setup_mode'] = $mode;
            }
        }

        // Enabled (boolean)
        if (isset($settings['enabled'])) {
            $validated['enabled'] = (bool) $settings['enabled'];
        }

        // Previously enabled (boolean) - tracks enabled state when switching tabs
        if (isset($settings['previously_enabled'])) {
            $validated['previously_enabled'] = (bool) $settings['previously_enabled'];
        }

        // Cloud-specific fields
        if (isset($settings['cloud_api_key'])) {
            $validated['cloud_api_key'] = sanitize_text_field($settings['cloud_api_key']);
        }
        if (isset($settings['cloud_email'])) {
            $validated['cloud_email'] = sanitize_email($settings['cloud_email']);
        }
        if (isset($settings['cloud_tier'])) {
            $tier = sanitize_text_field($settings['cloud_tier']);
            if (in_array($tier, [self::TIER_NONE, self::TIER_FREE, self::TIER_PRO, self::TIER_ACTIVE, self::TIER_CANCELLED], true)) {
                $validated['cloud_tier'] = $tier;
            }
        }

        // Usage stats (integers)
        if (isset($settings['storage_used'])) {
            $validated['storage_used'] = absint($settings['storage_used']);
        }
        if (isset($settings['storage_limit'])) {
            $validated['storage_limit'] = absint($settings['storage_limit']);
        }
        if (isset($settings['images_cached'])) {
            $validated['images_cached'] = absint($settings['images_cached']);
        }
        if (isset($settings['bandwidth_saved'])) {
            $validated['bandwidth_saved'] = absint($settings['bandwidth_saved']);
        }
        if (isset($settings['stats_updated_at'])) {
            $validated['stats_updated_at'] = absint($settings['stats_updated_at']);
        }

        // Onboarding state
        if (isset($settings['onboarding_completed'])) {
            $validated['onboarding_completed'] = (bool) $settings['onboarding_completed'];
        }
        if (isset($settings['onboarding_step'])) {
            $step = absint($settings['onboarding_step']);
            $validated['onboarding_step'] = max(1, min(4, $step)); // Clamp 1-4
        }
        if (isset($settings['marketing_opt_in'])) {
            $validated['marketing_opt_in'] = (bool) $settings['marketing_opt_in'];
        }

        // Custom domain (Cloud mode only)
        if (isset($settings['custom_domain'])) {
            $validated['custom_domain'] = self::sanitize_domain($settings['custom_domain']);
        }
        if (isset($settings['custom_domain_status'])) {
            $status = sanitize_text_field($settings['custom_domain_status']);
            if (in_array($status, ['', 'pending_dns', 'pending_ssl', 'active', 'error'], true)) {
                $validated['custom_domain_status'] = $status;
            }
        }

        // CDN URL (domain only - single domain architecture)
        if (isset($settings['cdn_url'])) {
            $validated['cdn_url'] = self::sanitize_domain($settings['cdn_url']);
        }

        // Allowed domains (array)
        if (isset($settings['allowed_domains'])) {
            if (is_string($settings['allowed_domains'])) {
                $domains = array_map('trim', explode("\n", $settings['allowed_domains']));
            } else {
                $domains = (array) $settings['allowed_domains'];
            }

            $validated['allowed_domains'] = array_map(
                [self::class, 'sanitize_domain'],
                array_filter($domains)
            );
        }

        // Debug mode (boolean)
        if (isset($settings['debug_mode'])) {
            $validated['debug_mode'] = (bool) $settings['debug_mode'];
        }

        return $validated;
    }

    /**
     * Sanitize domain name
     *
     * Removes:
     * - Protocol (http://, https://)
     * - Paths and trailing slashes
     * - Leading dots (normalizes .example.com to example.com)
     * - Multiple consecutive dots
     * - Invalid characters
     * Converts to lowercase
     * Validates basic domain format
     *
     * @since 0.1.0
     * @param string $domain Domain to sanitize.
     * @return string Sanitized domain or empty string if invalid.
     */
    public static function sanitize_domain($domain) {
        // Remove protocol
        $domain = preg_replace('#^https?://#i', '', $domain);

        // Remove port number if present
        $domain = preg_replace('#:\d+$#', '', $domain);

        // Remove trailing slashes and paths
        $domain = rtrim($domain, '/');
        $domain = preg_replace('#/.*$#', '', $domain);

        // Sanitize text
        $domain = sanitize_text_field($domain);

        // Remove leading dots (normalize .example.com to example.com)
        $domain = ltrim($domain, '.');

        // Remove multiple consecutive dots (security measure)
        $domain = preg_replace('/\.{2,}/', '.', $domain);

        // Remove trailing dots
        $domain = rtrim($domain, '.');

        // Convert to lowercase
        $domain = strtolower($domain);

        // Validate domain format (basic validation)
        // Allows: letters, numbers, hyphens, dots
        // Doesn't allow: spaces, special chars, etc.
        if (!empty($domain) && !preg_match('/^[a-z0-9]([a-z0-9\-\.]*[a-z0-9])?$/i', $domain)) {
            // Invalid domain format
            return '';
        }

        return $domain;
    }

    /**
     * Reset to defaults
     *
     * @since 0.1.0
     * @return bool
     */
    public function reset() {
        $this->settings = null;
        return update_option(self::OPTION_KEY, $this->defaults);
    }

    /**
     * Delete all settings
     *
     * @since 0.1.0
     * @return bool
     */
    public function delete() {
        $this->settings = null;
        return delete_option(self::OPTION_KEY);
    }

    /**
     * Clear the settings cache
     *
     * Call this after direct update_option() calls to ensure
     * subsequent get_all() calls return fresh data.
     *
     * @since 0.1.0
     * @return void
     */
    public function clear_cache() {
        $this->settings = null;
    }

    /**
     * Get default value for a setting
     *
     * @since 0.1.0
     * @param string $key Setting key.
     * @return mixed
     */
    public function get_default($key) {
        return $this->defaults[$key] ?? null;
    }

    /**
     * Get API base URL with filter support
     *
     * Static method to allow usage without instance.
     *
     * @since 0.1.3
     * @return string API base URL.
     */
    public static function get_api_base_url() {
        /**
         * Filter the API base URL for cloud services.
         *
         * Useful for testing or staging environments.
         *
         * @since 0.1.2
         * @param string $api_base_url The default API base URL.
         */
        return apply_filters('imgpro_cdn_api_base_url', self::API_BASE_URL);
    }

    /**
     * Check if a given mode has valid configuration
     *
     * Cloud mode requires free or paid subscription.
     * Cloudflare mode requires CDN URL to be configured (single domain architecture).
     *
     * @since 0.1.3
     * @param string $mode     The mode to check ('cloud' or 'cloudflare').
     * @param array  $settings The settings array to check against.
     * @return bool True if the mode is properly configured.
     */
    public static function is_mode_valid($mode, $settings) {
        if (self::MODE_CLOUD === $mode) {
            $tier = $settings['cloud_tier'] ?? '';
            // Valid tiers: free, pro, active (legacy), past_due (grace period)
            return in_array($tier, [self::TIER_FREE, self::TIER_PRO, self::TIER_ACTIVE, self::TIER_PAST_DUE], true);
        } elseif (self::MODE_CLOUDFLARE === $mode) {
            return !empty($settings['cdn_url']);
        }
        return false;
    }

    /**
     * Check if user has a paid subscription
     *
     * @since 0.2.0
     * @param array $settings The settings array to check against.
     * @return bool True if user has pro/active subscription.
     */
    public static function is_pro($settings) {
        $tier = $settings['cloud_tier'] ?? '';
        // past_due still counts as pro (grace period)
        return in_array($tier, [self::TIER_PRO, self::TIER_ACTIVE, self::TIER_PAST_DUE], true);
    }

    /**
     * Check if user is on free tier
     *
     * @since 0.2.0
     * @param array $settings The settings array to check against.
     * @return bool True if user is on free tier.
     */
    public static function is_free($settings) {
        return self::TIER_FREE === ($settings['cloud_tier'] ?? '');
    }

    /**
     * Check if subscription is cancelled or suspended
     *
     * @since 0.2.0
     * @param array $settings The settings array to check against.
     * @return bool True if subscription is cancelled or suspended.
     */
    public static function is_subscription_inactive($settings) {
        $tier = $settings['cloud_tier'] ?? '';
        return in_array($tier, [self::TIER_CANCELLED, self::TIER_SUSPENDED], true);
    }

    /**
     * Check if subscription needs attention (past due)
     *
     * @since 0.2.0
     * @param array $settings The settings array to check against.
     * @return bool True if subscription is past due.
     */
    public static function is_past_due($settings) {
        return self::TIER_PAST_DUE === ($settings['cloud_tier'] ?? '');
    }

    /**
     * Get storage limit for current tier
     *
     * @since 0.2.0
     * @param array $settings The settings array to check against.
     * @return int Storage limit in bytes.
     */
    public static function get_storage_limit($settings) {
        if (self::is_pro($settings)) {
            return self::PRO_STORAGE_LIMIT;
        }
        if (self::is_free($settings)) {
            return self::FREE_STORAGE_LIMIT;
        }
        return 0;
    }

    /**
     * Get storage usage percentage
     *
     * @since 0.2.0
     * @param array $settings The settings array to check against.
     * @return float Percentage of storage used (0-100).
     */
    public static function get_storage_percentage($settings) {
        $limit = self::get_storage_limit($settings);
        if ($limit <= 0) {
            return 0;
        }
        $used = $settings['storage_used'] ?? 0;
        return min(100, ($used / $limit) * 100);
    }

    /**
     * Handle API error with action hook for logging
     *
     * Static method for error handling that fires an action hook.
     *
     * @since 0.1.3
     * @param WP_Error|array $error   Error object or error data.
     * @param string         $context Context for logging (e.g., 'checkout', 'recovery').
     * @return void
     */
    public static function handle_api_error($error, $context = '') {
        /**
         * Fires when an API error occurs.
         *
         * @since 0.1.0
         * @param WP_Error|array $error   Error object or error data.
         * @param string         $context Context for the error.
         */
        do_action('imgpro_cdn_api_error', $error, $context);
    }

    /**
     * Recover account details from Managed API
     *
     * Attempts to recover subscription details for the current site.
     *
     * @since 0.1.3
     * @param ImgPro_CDN_Settings $settings_instance Settings instance to update.
     * @return bool True if recovery was successful.
     */
    public static function recover_account($settings_instance) {
        $site_url = get_site_url();

        $response = wp_remote_post(self::get_api_base_url() . '/api/recover', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(['site_url' => $site_url]),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            self::handle_api_error($response, 'recovery');
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Validate response structure
        if (!is_array($body)) {
            self::handle_api_error(['error' => 'Invalid response structure'], 'recovery');
            return false;
        }

        // Validate required fields with proper types
        if (empty($body['api_key']) || !is_string($body['api_key'])) {
            self::handle_api_error(['error' => 'Missing or invalid api_key'], 'recovery');
            return false;
        }
        if (empty($body['email']) || !is_string($body['email'])) {
            self::handle_api_error(['error' => 'Missing or invalid email'], 'recovery');
            return false;
        }
        if (empty($body['tier']) || !is_string($body['tier'])) {
            self::handle_api_error(['error' => 'Missing or invalid tier'], 'recovery');
            return false;
        }

        // Update settings with validated and sanitized data
        $settings = $settings_instance->get_all();
        $settings['setup_mode'] = self::MODE_CLOUD;
        $settings['cloud_api_key'] = sanitize_text_field($body['api_key']);
        $settings['cloud_email'] = sanitize_email($body['email']);

        // Handle tier (support both old and new tier names)
        $tier = $body['tier'];
        if (in_array($tier, [self::TIER_FREE, self::TIER_PRO, self::TIER_ACTIVE, self::TIER_CANCELLED, self::TIER_NONE], true)) {
            $settings['cloud_tier'] = $tier;
        } else {
            $settings['cloud_tier'] = self::TIER_NONE;
        }

        // Update storage stats if provided
        if (isset($body['storage_used'])) {
            $settings['storage_used'] = absint($body['storage_used']);
        }
        if (isset($body['storage_limit'])) {
            $settings['storage_limit'] = absint($body['storage_limit']);
        }
        if (isset($body['images_cached'])) {
            $settings['images_cached'] = absint($body['images_cached']);
        }
        if (isset($body['bandwidth_saved'])) {
            $settings['bandwidth_saved'] = absint($body['bandwidth_saved']);
        }
        $settings['stats_updated_at'] = time();

        // Sync custom domain if present in cloud account
        if (!empty($body['custom_domain'])) {
            $settings['custom_domain'] = self::sanitize_domain($body['custom_domain']);
            $settings['custom_domain_status'] = sanitize_text_field($body['custom_domain_status'] ?? 'pending_dns');
        } else {
            // Clear local custom domain if not in cloud account
            $settings['custom_domain'] = '';
            $settings['custom_domain_status'] = '';
        }

        // Auto-enable if subscription is valid (free or paid)
        $settings['enabled'] = self::is_mode_valid(self::MODE_CLOUD, $settings);

        // Mark onboarding as complete if recovering account
        $settings['onboarding_completed'] = true;

        $result = update_option(self::OPTION_KEY, $settings);
        $settings_instance->clear_cache();

        return $result;
    }

    /**
     * Format bytes to human readable string
     *
     * @since 0.2.0
     * @param int $bytes Bytes to format.
     * @param int $precision Decimal precision.
     * @return string Formatted string (e.g., "1.5 GB").
     */
    public static function format_bytes($bytes, $precision = 1) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Sync subscription status from cloud
     *
     * Checks the cloud API for current subscription status and updates local settings.
     * Uses transient to avoid checking too frequently (once per hour max).
     *
     * @since 0.2.0
     * @param ImgPro_CDN_Settings $settings_instance Settings instance to update.
     * @param bool                $force             Force sync even if recently checked.
     * @return bool|null True if synced successfully, false on error, null if skipped.
     */
    public static function sync_subscription_status($settings_instance, $force = false) {
        // Check if we have a cloud account
        $settings = $settings_instance->get_all();
        $api_key = $settings['cloud_api_key'] ?? '';

        if (empty($api_key)) {
            return null; // No account to sync
        }

        // Check transient to avoid frequent API calls (1 hour cache)
        $transient_key = 'imgpro_cdn_last_sync';
        if (!$force && get_transient($transient_key)) {
            return null; // Recently synced
        }

        // Call the account validation endpoint
        $response = wp_remote_get(
            self::get_api_base_url() . '/api/account/' . $api_key,
            ['timeout' => 10]
        );

        if (is_wp_error($response)) {
            self::handle_api_error($response, 'sync_status');
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (404 === $status_code) {
            // Account not found - might have been deleted
            // Don't auto-disable, just log it
            self::handle_api_error(['error' => 'Account not found in cloud'], 'sync_status');
            set_transient($transient_key, time(), HOUR_IN_SECONDS);
            return false;
        }

        if ($status_code >= 400 || !is_array($body)) {
            self::handle_api_error(['error' => 'Invalid response', 'status' => $status_code], 'sync_status');
            set_transient($transient_key, time(), HOUR_IN_SECONDS);
            return false;
        }

        // Update local tier if different
        $cloud_tier = $body['tier'] ?? '';
        $local_tier = $settings['cloud_tier'] ?? '';

        if (!empty($cloud_tier) && $cloud_tier !== $local_tier) {
            $settings['cloud_tier'] = $cloud_tier;

            // If subscription became invalid, disable CDN
            if (self::is_subscription_inactive($settings)) {
                $settings['enabled'] = false;
            }

            // Update settings
            update_option(self::OPTION_KEY, $settings);
            $settings_instance->clear_cache();
        }

        // Set transient to avoid frequent checks
        set_transient($transient_key, time(), HOUR_IN_SECONDS);

        return true;
    }
}
