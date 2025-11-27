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
     * Subscription tier: None
     *
     * @since 0.1.2
     * @var string
     */
    const TIER_NONE = 'none';

    /**
     * Subscription tier: Active
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
            if (in_array($tier, [self::TIER_NONE, self::TIER_ACTIVE, self::TIER_CANCELLED], true)) {
                $validated['cloud_tier'] = $tier;
            }
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
     * Cloud mode requires an active subscription.
     * Cloudflare mode requires CDN URL to be configured (single domain architecture).
     *
     * @since 0.1.3
     * @param string $mode     The mode to check ('cloud' or 'cloudflare').
     * @param array  $settings The settings array to check against.
     * @return bool True if the mode is properly configured.
     */
    public static function is_mode_valid($mode, $settings) {
        if (self::MODE_CLOUD === $mode) {
            return self::TIER_ACTIVE === ($settings['cloud_tier'] ?? '');
        } elseif (self::MODE_CLOUDFLARE === $mode) {
            return !empty($settings['cdn_url']);
        }
        return false;
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
        $settings['cloud_tier'] = in_array($body['tier'], [self::TIER_ACTIVE, self::TIER_CANCELLED, self::TIER_NONE], true)
            ? $body['tier']
            : self::TIER_NONE;
        // Only auto-enable if subscription is active
        $settings['enabled'] = (self::TIER_ACTIVE === $settings['cloud_tier']);

        $result = update_option(self::OPTION_KEY, $settings);
        $settings_instance->clear_cache();

        return $result;
    }
}
