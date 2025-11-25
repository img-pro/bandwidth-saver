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

        // Cloudflare mode settings
        'cdn_url'         => '',
        'worker_url'      => '',

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

        // Auto-configure Cloud mode URLs
        if (self::MODE_CLOUD === $settings['setup_mode']) {
            if ('cdn_url' === $key) {
                return 'wp.img.pro';
            }
            if ('worker_url' === $key) {
                return 'fetch.wp.img.pro';
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

        // CDN URL (domain only)
        if (isset($settings['cdn_url'])) {
            $validated['cdn_url'] = $this->sanitize_domain($settings['cdn_url']);
        }

        // Worker URL (domain only)
        if (isset($settings['worker_url'])) {
            $validated['worker_url'] = $this->sanitize_domain($settings['worker_url']);
        }

        // Allowed domains (array)
        if (isset($settings['allowed_domains'])) {
            if (is_string($settings['allowed_domains'])) {
                $domains = array_map('trim', explode("\n", $settings['allowed_domains']));
            } else {
                $domains = (array) $settings['allowed_domains'];
            }

            $validated['allowed_domains'] = array_map(
                [$this, 'sanitize_domain'],
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
    private function sanitize_domain($domain) {
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
}
