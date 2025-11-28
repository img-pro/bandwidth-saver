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
     * Subscription tier: Lite (paid)
     *
     * @since 0.2.0
     * @var string
     */
    const TIER_LITE = 'lite';

    /**
     * Subscription tier: Pro (paid)
     *
     * @since 0.2.0
     * @var string
     */
    const TIER_PRO = 'pro';

    /**
     * Subscription tier: Business (paid)
     *
     * @since 0.2.0
     * @var string
     */
    const TIER_BUSINESS = 'business';

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
     * Free tier storage limit in bytes (10 GB)
     *
     * @since 0.2.0
     * @var int
     */
    const FREE_STORAGE_LIMIT = 10737418240;

    /**
     * Pro tier storage limit in bytes (120 GB)
     *
     * @since 0.2.0
     * @var int
     */
    const PRO_STORAGE_LIMIT = 128849018880;

    /**
     * Free tier bandwidth limit in bytes (50 GB)
     *
     * @since 0.2.0
     * @var int
     */
    const FREE_BANDWIDTH_LIMIT = 53687091200;

    /**
     * Pro tier bandwidth limit in bytes (2 TB)
     *
     * @since 0.2.0
     * @var int
     */
    const PRO_BANDWIDTH_LIMIT = 2199023255552;

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
        'bandwidth_used'     => 0,
        'bandwidth_limit'    => 0,
        'images_cached'      => 0,
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
            if (in_array($tier, [self::TIER_NONE, self::TIER_FREE, self::TIER_LITE, self::TIER_PRO, self::TIER_BUSINESS, self::TIER_ACTIVE, self::TIER_CANCELLED, self::TIER_PAST_DUE, self::TIER_SUSPENDED], true)) {
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
        if (isset($settings['bandwidth_used'])) {
            $validated['bandwidth_used'] = absint($settings['bandwidth_used']);
        }
        if (isset($settings['bandwidth_limit'])) {
            $validated['bandwidth_limit'] = absint($settings['bandwidth_limit']);
        }
        if (isset($settings['images_cached'])) {
            $validated['images_cached'] = absint($settings['images_cached']);
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
            // Valid tiers: free, lite, pro, business, active (legacy), past_due (grace period)
            return in_array($tier, [self::TIER_FREE, self::TIER_LITE, self::TIER_PRO, self::TIER_BUSINESS, self::TIER_ACTIVE, self::TIER_PAST_DUE], true);
        } elseif (self::MODE_CLOUDFLARE === $mode) {
            return !empty($settings['cdn_url']);
        }
        return false;
    }

    /**
     * Check if user has any paid subscription (lite, pro, or business)
     *
     * @since 0.2.0
     * @param array $settings The settings array to check against.
     * @return bool True if user has a paid subscription.
     */
    public static function is_paid($settings) {
        $tier = $settings['cloud_tier'] ?? '';
        // past_due still counts as paid (grace period)
        return in_array($tier, [self::TIER_LITE, self::TIER_PRO, self::TIER_BUSINESS, self::TIER_ACTIVE, self::TIER_PAST_DUE], true);
    }

    /**
     * Check if user has a paid subscription (alias for is_paid for backwards compatibility)
     *
     * @since 0.2.0
     * @param array $settings The settings array to check against.
     * @return bool True if user has a paid subscription.
     */
    public static function is_pro($settings) {
        return self::is_paid($settings);
    }

    /**
     * Check if tier has custom domain feature
     *
     * @since 0.2.0
     * @param array $settings The settings array to check against.
     * @return bool True if custom domain is available.
     */
    public static function has_custom_domain($settings) {
        $tier = $settings['cloud_tier'] ?? '';
        // Custom domain available on Pro and Business (not Lite)
        return in_array($tier, [self::TIER_PRO, self::TIER_BUSINESS, self::TIER_ACTIVE, self::TIER_PAST_DUE], true);
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
     * Get bandwidth limit for current tier
     *
     * @since 0.2.0
     * @param array $settings The settings array to check against.
     * @return int Bandwidth limit in bytes.
     */
    public static function get_bandwidth_limit($settings) {
        if (self::is_pro($settings)) {
            return self::PRO_BANDWIDTH_LIMIT;
        }
        if (self::is_free($settings)) {
            return self::FREE_BANDWIDTH_LIMIT;
        }
        return 0;
    }

    /**
     * Get bandwidth usage percentage
     *
     * @since 0.2.0
     * @param array $settings The settings array to check against.
     * @return float Percentage of bandwidth used (0-100).
     */
    public static function get_bandwidth_percentage($settings) {
        $limit = self::get_bandwidth_limit($settings);
        if ($limit <= 0) {
            return 0;
        }
        $used = $settings['bandwidth_used'] ?? 0;
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
}
