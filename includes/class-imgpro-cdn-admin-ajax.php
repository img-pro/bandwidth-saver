<?php
/**
 * ImgPro CDN Admin AJAX Handlers
 *
 * @package ImgPro_CDN
 * @since   0.1.2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX handlers for admin operations
 *
 * Handles all AJAX operations including toggle, checkout,
 * subscription management, free registration, and account recovery.
 *
 * @since 0.1.2
 */
class ImgPro_CDN_Admin_Ajax {

    /**
     * Settings instance
     *
     * @since 0.1.2
     * @var ImgPro_CDN_Settings
     */
    private $settings;

    /**
     * API client instance
     *
     * @since 0.1.7
     * @var ImgPro_CDN_API
     */
    private $api;

    /**
     * Constructor
     *
     * @since 0.1.2
     * @param ImgPro_CDN_Settings $settings Settings instance.
     */
    public function __construct(ImgPro_CDN_Settings $settings) {
        $this->settings = $settings;
        $this->api = ImgPro_CDN_API::instance();
    }

    /**
     * Register AJAX hooks
     *
     * @since 0.1.2
     * @return void
     */
    public function register_hooks() {
        add_action('wp_ajax_imgpro_cdn_toggle_enabled', [$this, 'ajax_toggle_enabled']);
        add_action('wp_ajax_imgpro_cdn_checkout', [$this, 'ajax_checkout']);
        add_action('wp_ajax_imgpro_cdn_manage_subscription', [$this, 'ajax_manage_subscription']);
        add_action('wp_ajax_imgpro_cdn_recover_account', [$this, 'ajax_recover_account']);
        add_action('wp_ajax_imgpro_cdn_add_custom_domain', [$this, 'ajax_add_custom_domain']);
        add_action('wp_ajax_imgpro_cdn_check_custom_domain', [$this, 'ajax_check_custom_domain']);
        add_action('wp_ajax_imgpro_cdn_remove_custom_domain', [$this, 'ajax_remove_custom_domain']);
        add_action('wp_ajax_imgpro_cdn_remove_cdn_domain', [$this, 'ajax_remove_cdn_domain']);

        // New endpoints for free tier
        add_action('wp_ajax_imgpro_cdn_free_register', [$this, 'ajax_free_register']);
        add_action('wp_ajax_imgpro_cdn_update_onboarding_step', [$this, 'ajax_update_onboarding_step']);
        add_action('wp_ajax_imgpro_cdn_complete_onboarding', [$this, 'ajax_complete_onboarding']);
        add_action('wp_ajax_imgpro_cdn_sync_stats', [$this, 'ajax_sync_stats']);
    }

    /**
     * Generate cryptographically secure API key
     *
     * @since 0.1.2
     * @return string API key in format: imgpro_[64 hex chars].
     */
    private function generate_api_key() {
        $random_bytes = random_bytes(32);
        $hex = bin2hex($random_bytes);
        return 'imgpro_' . $hex;
    }

    /**
     * AJAX handler for toggling CDN enabled state
     *
     * @since 0.1.2
     * @return void
     */
    public function ajax_toggle_enabled() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_toggle_enabled')) {
            wp_send_json_error(['message' => __('Security check failed', 'bandwidth-saver')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'bandwidth-saver')]);
        }

        $enabled = isset($_POST['enabled']) && '1' === $_POST['enabled'];
        $mode = isset($_POST['mode']) ? sanitize_text_field(wp_unslash($_POST['mode'])) : '';

        // Validate mode
        if (!in_array($mode, [ImgPro_CDN_Settings::MODE_CLOUD, ImgPro_CDN_Settings::MODE_CLOUDFLARE], true)) {
            wp_send_json_error(['message' => __('Invalid mode specified', 'bandwidth-saver')]);
            return;
        }

        $current_settings = $this->settings->get_all();

        // Check if the mode is properly configured before allowing enable
        if ($enabled && !ImgPro_CDN_Settings::is_mode_valid($mode, $current_settings)) {
            wp_send_json_error(['message' => __('Please complete setup first before enabling.', 'bandwidth-saver')]);
            return;
        }

        // Determine the field key for this mode
        $enabled_key = ImgPro_CDN_Settings::MODE_CLOUD === $mode ? 'cloud_enabled' : 'cloudflare_enabled';

        // Check if already in desired state
        $current_enabled = ImgPro_CDN_Settings::is_mode_enabled($mode, $current_settings);
        if ($current_enabled === $enabled) {
            $message = $enabled
                ? __('Image CDN is active. Images are loading from Cloudflare.', 'bandwidth-saver')
                : __('Image CDN is disabled. Images are loading from your server.', 'bandwidth-saver');

            wp_send_json_success(['message' => $message]);
            return;
        }

        // Update the mode-specific enabled state
        $current_settings[$enabled_key] = $enabled;

        // Also update setup_mode if enabling this mode
        if ($enabled) {
            $current_settings['setup_mode'] = $mode;
        }

        $result = update_option(ImgPro_CDN_Settings::OPTION_KEY, $current_settings);
        $this->settings->clear_cache();

        if (false !== $result) {
            $message = $enabled
                ? __('Image CDN enabled. Images now load from Cloudflare.', 'bandwidth-saver')
                : __('Image CDN disabled. Images now load from your server.', 'bandwidth-saver');

            wp_send_json_success(['message' => $message]);
        } else {
            wp_send_json_error(['message' => __('Could not save settings. Please try again.', 'bandwidth-saver')]);
        }
    }

    /**
     * AJAX handler for free tier registration
     *
     * @since 0.1.7
     * @return void
     */
    public function ajax_free_register() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_onboarding') && !wp_verify_nonce($nonce, 'imgpro_cdn_checkout')) {
            wp_send_json_error(['message' => __('Security check failed', 'bandwidth-saver')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'bandwidth-saver')]);
        }

        // Get email from request or use admin email
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : get_option('admin_email');
        $site_url = get_site_url();
        $marketing_opt_in = isset($_POST['marketing_opt_in']) && '1' === $_POST['marketing_opt_in'];

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(['message' => __('Please enter a valid email address.', 'bandwidth-saver')]);
            return;
        }

        // Call API to register
        $result = $this->api->create_site($email, $site_url, $marketing_opt_in);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();

            // Handle existing account - try to recover
            if ('conflict' === $error_code) {
                $recovery = $this->api->find_site($site_url);
                if (!is_wp_error($recovery)) {
                    $this->save_site_to_settings($recovery, $email, $marketing_opt_in);
                    wp_send_json_success([
                        'message' => __('Found your existing account. It has been activated.', 'bandwidth-saver'),
                        'recovered' => true,
                        'next_step' => 3
                    ]);
                    return;
                }

                wp_send_json_error([
                    'message' => __('This email is already registered. Try recovering your account.', 'bandwidth-saver'),
                    'existing' => true,
                    'code' => 'account_exists'
                ]);
                return;
            }

            // Connection error - fall back to local registration
            if ('connection_error' === $error_code) {
                $api_key = $this->generate_api_key();
                $this->settings->update([
                    'cloud_api_key' => $api_key,
                    'cloud_email' => $email,
                    'cloud_tier' => ImgPro_CDN_Settings::TIER_FREE,
                    'setup_mode' => ImgPro_CDN_Settings::MODE_CLOUD,
                    'onboarding_step' => 3,
                    'marketing_opt_in' => $marketing_opt_in,
                    'storage_limit' => ImgPro_CDN_Settings::FREE_STORAGE_LIMIT,
                    'bandwidth_limit' => ImgPro_CDN_Settings::FREE_BANDWIDTH_LIMIT,
                ]);

                wp_send_json_success([
                    'message' => __('Account created! Ready to activate your CDN.', 'bandwidth-saver'),
                    'next_step' => 3
                ]);
                return;
            }

            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code' => $error_code
            ]);
            return;
        }

        // Success - save site data
        $this->save_site_to_settings($result, $email, $marketing_opt_in);

        wp_send_json_success([
            'message' => __('Account created! Ready to activate your CDN.', 'bandwidth-saver'),
            'next_step' => 3
        ]);
    }

    /**
     * Save site data from API response to local settings
     *
     * @since 0.1.7
     * @param array  $site           Site data from API.
     * @param string $email          User email.
     * @param bool   $marketing_opt_in Marketing consent.
     * @return void
     */
    private function save_site_to_settings($site, $email, $marketing_opt_in = false) {
        $tier_id = $this->api->get_tier_id($site);
        $usage = $this->api->get_usage($site);

        $this->settings->update([
            'cloud_api_key' => $site['api_key'] ?? '',
            'cloud_email' => $email,
            'cloud_tier' => $tier_id,
            'setup_mode' => ImgPro_CDN_Settings::MODE_CLOUD,
            'onboarding_step' => 3,
            'marketing_opt_in' => $marketing_opt_in,
            'storage_used' => $usage['storage_used'],
            'storage_limit' => $usage['storage_limit'] ?: ImgPro_CDN_Settings::FREE_STORAGE_LIMIT,
            'bandwidth_used' => $usage['bandwidth_used'],
            'bandwidth_limit' => $usage['bandwidth_limit'] ?: ImgPro_CDN_Settings::FREE_BANDWIDTH_LIMIT,
            'images_cached' => $usage['images_cached'],
            'stats_updated_at' => time(),
        ]);
    }

    /**
     * AJAX handler for updating onboarding step
     *
     * @since 0.1.7
     * @return void
     */
    public function ajax_update_onboarding_step() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_onboarding')) {
            wp_send_json_error(['message' => __('Security check failed', 'bandwidth-saver')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'bandwidth-saver')]);
        }

        $step = isset($_POST['step']) ? absint($_POST['step']) : 1;
        $step = max(1, min(4, $step)); // Clamp to 1-4

        $this->settings->update(['onboarding_step' => $step]);

        wp_send_json_success(['step' => $step]);
    }

    /**
     * AJAX handler for completing onboarding
     *
     * @since 0.1.7
     * @return void
     */
    public function ajax_complete_onboarding() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_onboarding')) {
            wp_send_json_error(['message' => __('Security check failed', 'bandwidth-saver')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'bandwidth-saver')]);
        }

        $this->settings->update([
            'onboarding_completed' => true,
            'onboarding_step' => 4,
            'enabled' => true,
        ]);

        wp_send_json_success([
            'message' => __('Setup complete!', 'bandwidth-saver'),
            'redirect' => admin_url('options-general.php?page=imgpro-cdn-settings&tab=cloud&activated=1')
        ]);
    }

    /**
     * AJAX handler for syncing stats from API
     *
     * Uses the API client to fetch fresh site data.
     *
     * @since 0.1.7
     * @return void
     */
    public function ajax_sync_stats() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_toggle_enabled') && !wp_verify_nonce($nonce, 'imgpro_cdn_onboarding')) {
            wp_send_json_error(['message' => __('Security check failed', 'bandwidth-saver')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'bandwidth-saver')]);
        }

        $settings = $this->settings->get_all();
        $api_key = $settings['cloud_api_key'] ?? '';

        if (empty($api_key)) {
            wp_send_json_error(['message' => __('No subscription found.', 'bandwidth-saver')]);
            return;
        }

        // Force fetch fresh site data
        $site = $this->api->get_site($api_key, true);

        if (is_wp_error($site)) {
            wp_send_json_error([
                'message' => __('Could not sync stats. Please try again.', 'bandwidth-saver'),
                'code' => 'sync_error'
            ]);
            return;
        }

        // Update local settings with fresh data
        $this->update_settings_from_site($site);

        // Get updated settings for response
        $updated_settings = $this->settings->get_all();
        $storage_limit = ImgPro_CDN_Settings::get_storage_limit($updated_settings);
        $bandwidth_limit = ImgPro_CDN_Settings::get_bandwidth_limit($updated_settings);

        wp_send_json_success([
            'storage_used' => $updated_settings['storage_used'] ?? 0,
            'storage_limit' => $storage_limit,
            'storage_percentage' => ImgPro_CDN_Settings::get_storage_percentage($updated_settings),
            'bandwidth_used' => $updated_settings['bandwidth_used'] ?? 0,
            'bandwidth_limit' => $bandwidth_limit,
            'bandwidth_percentage' => ImgPro_CDN_Settings::get_bandwidth_percentage($updated_settings),
            'images_cached' => $updated_settings['images_cached'] ?? 0,
            'formatted' => [
                'storage_used' => ImgPro_CDN_Settings::format_bytes($updated_settings['storage_used'] ?? 0),
                'storage_limit' => ImgPro_CDN_Settings::format_bytes($storage_limit, 0),
                'bandwidth_used' => ImgPro_CDN_Settings::format_bytes($updated_settings['bandwidth_used'] ?? 0),
                'bandwidth_limit' => ImgPro_CDN_Settings::format_bytes($bandwidth_limit, 0),
            ]
        ]);
    }

    /**
     * Update local settings from site API response
     *
     * @since 0.1.7
     * @param array $site Site data from API.
     * @return void
     */
    private function update_settings_from_site($site) {
        $tier_id = $this->api->get_tier_id($site);
        $usage = $this->api->get_usage($site);
        $domain = $this->api->get_custom_domain($site);

        $update_data = [
            'cloud_tier' => $tier_id,
            'storage_used' => $usage['storage_used'],
            'bandwidth_used' => $usage['bandwidth_used'],
            'images_cached' => $usage['images_cached'],
            'stats_updated_at' => time(),
        ];

        // Update storage/bandwidth limits if provided
        if ($usage['storage_limit'] > 0) {
            $update_data['storage_limit'] = $usage['storage_limit'];
        }
        if ($usage['bandwidth_limit'] > 0) {
            $update_data['bandwidth_limit'] = $usage['bandwidth_limit'];
        }

        // Update custom domain if present
        if ($domain) {
            $update_data['custom_domain'] = $domain['domain'];
            $update_data['custom_domain_status'] = $domain['status'];
        }

        // Disable if subscription is inactive
        if (ImgPro_CDN_Settings::is_subscription_inactive(['cloud_tier' => $tier_id])) {
            $update_data['enabled'] = false;
        }

        $this->settings->update($update_data);
    }

    /**
     * AJAX handler for Stripe checkout (paid tier upgrade)
     *
     * @since 0.1.2
     * @return void
     */
    public function ajax_checkout() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_checkout')) {
            wp_send_json_error(['message' => __('Security check failed', 'bandwidth-saver')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'bandwidth-saver')]);
        }

        // Get requested tier (default to 'pro' for backwards compatibility)
        $tier_id = isset($_POST['tier_id']) ? sanitize_text_field(wp_unslash($_POST['tier_id'])) : 'pro';

        // Validate tier_id is a paid tier
        $valid_tiers = ['lite', 'pro', 'business'];
        if (!in_array($tier_id, $valid_tiers, true)) {
            wp_send_json_error([
                'message' => __('Invalid plan selected. Please try again.', 'bandwidth-saver'),
                'code' => 'invalid_tier'
            ]);
            return;
        }

        $settings = $this->settings->get_all();
        $api_key = $settings['cloud_api_key'] ?? '';
        $email = get_option('admin_email');
        $site_url = get_site_url();

        // Ensure we have an API key (create or recover site if needed)
        if (empty($api_key)) {
            $site = $this->api->create_site($email, $site_url);

            if (is_wp_error($site)) {
                $error_code = $site->get_error_code();

                // Account already exists - try to recover it
                if ('conflict' === $error_code) {
                    $site = $this->api->find_site($site_url);
                    if (is_wp_error($site)) {
                        wp_send_json_error([
                            'message' => __('Account exists but could not be recovered. Please try again.', 'bandwidth-saver'),
                            'code' => 'recovery_failed'
                        ]);
                        return;
                    }
                } else {
                    wp_send_json_error([
                        'message' => __('Could not create account. Please try again.', 'bandwidth-saver'),
                        'code' => $error_code
                    ]);
                    return;
                }
            }

            $api_key = $site['api_key'] ?? '';
            $this->save_site_to_settings($site, $email);
        }

        // Create checkout session with selected tier
        $result = $this->api->create_checkout($api_key, $tier_id);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();

            if ('connection_error' === $error_code) {
                wp_send_json_error([
                    'message' => __('Could not connect to billing service. Please try again in a moment.', 'bandwidth-saver'),
                    'code' => 'connection_error'
                ]);
                return;
            }

            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code' => $error_code
            ]);
            return;
        }

        // Check if already subscribed - sync the subscription
        if (!empty($result['already_subscribed']) && !empty($result['site'])) {
            $site = $result['site'];
            $this->save_site_to_settings($site, $site['email'] ?? get_option('admin_email'));

            wp_send_json_success([
                'recovered' => true,
                'message' => __('Your subscription has been restored.', 'bandwidth-saver'),
            ]);
            return;
        }

        // Check if subscription was upgraded directly (no checkout needed)
        if (!empty($result['upgraded'])) {
            // Sync site data from API to get new tier limits
            $site_data = $this->api->get_site($api_key);
            if (!is_wp_error($site_data)) {
                $this->save_site_to_settings($site_data, $site_data['email'] ?? get_option('admin_email'));
            }

            wp_send_json_success([
                'upgraded' => true,
                'message' => sprintf(
                    /* translators: %s: tier name */
                    __('Successfully upgraded to %s!', 'bandwidth-saver'),
                    ucfirst($result['tier_id'] ?? 'new plan')
                ),
            ]);
            return;
        }

        if (!empty($result['url'])) {
            set_transient('imgpro_cdn_pending_payment', true, HOUR_IN_SECONDS);
            wp_send_json_success(['checkout_url' => $result['url']]);
        } else {
            wp_send_json_error([
                'message' => __('Could not create checkout. Please try again.', 'bandwidth-saver'),
                'code' => 'invalid_response'
            ]);
        }
    }

    /**
     * AJAX handler for account recovery
     *
     * @since 0.1.2
     * @return void
     */
    public function ajax_recover_account() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_checkout') && !wp_verify_nonce($nonce, 'imgpro_cdn_onboarding')) {
            wp_send_json_error(['message' => __('Security check failed', 'bandwidth-saver')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'bandwidth-saver')]);
        }

        $site_url = get_site_url();
        $site = $this->api->find_site($site_url);

        if (is_wp_error($site)) {
            wp_send_json_error([
                'message' => __('No subscription found for this site. If you subscribed recently, please wait a moment and try again.', 'bandwidth-saver')
            ]);
            return;
        }

        // Save recovered site data
        $email = $site['email'] ?? get_option('admin_email');
        $this->save_site_to_settings($site, $email);

        // Enable CDN if valid subscription
        $tier_id = $this->api->get_tier_id($site);
        if (in_array($tier_id, [ImgPro_CDN_Settings::TIER_FREE, ImgPro_CDN_Settings::TIER_LITE, ImgPro_CDN_Settings::TIER_PRO, ImgPro_CDN_Settings::TIER_BUSINESS, ImgPro_CDN_Settings::TIER_ACTIVE], true)) {
            $this->settings->update([
                'enabled' => true,
                'onboarding_completed' => true,
            ]);
        }

        wp_send_json_success([
            'message' => __('Account recovered. Your subscription is now active.', 'bandwidth-saver')
        ]);
    }

    /**
     * AJAX handler for managing subscription
     *
     * @since 0.1.2
     * @return void
     */
    public function ajax_manage_subscription() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_checkout')) {
            wp_send_json_error(['message' => __('Security check failed', 'bandwidth-saver')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'bandwidth-saver')]);
        }

        $settings = $this->settings->get_all();
        $api_key = $settings['cloud_api_key'] ?? '';

        if (empty($api_key)) {
            wp_send_json_error([
                'message' => __('No subscription found. Please subscribe first or recover your account.', 'bandwidth-saver')
            ]);
            return;
        }

        $result = $this->api->create_portal($api_key);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();

            if ('connection_error' === $error_code) {
                wp_send_json_error([
                    'message' => __('Could not connect to billing service. Please try again in a moment.', 'bandwidth-saver'),
                    'code' => 'connection_error'
                ]);
                return;
            }

            if ('unauthorized' === $error_code) {
                wp_send_json_error([
                    'message' => __('Could not verify your subscription. Try recovering your account.', 'bandwidth-saver'),
                    'code' => 'auth_error'
                ]);
                return;
            }

            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code' => $error_code
            ]);
            return;
        }

        if (!empty($result['portal_url'])) {
            wp_send_json_success(['portal_url' => $result['portal_url']]);
        } else {
            wp_send_json_error([
                'message' => __('Could not open subscription portal. Please try again.', 'bandwidth-saver'),
                'code' => 'invalid_response'
            ]);
        }
    }

    /**
     * AJAX handler for adding a custom domain
     *
     * @since 0.1.6
     * @return void
     */
    public function ajax_add_custom_domain() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_custom_domain')) {
            wp_send_json_error(['message' => __('Security check failed', 'bandwidth-saver')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'bandwidth-saver')]);
        }

        $raw_domain = isset($_POST['domain']) ? sanitize_text_field(wp_unslash($_POST['domain'])) : '';
        $domain = ImgPro_CDN_Settings::sanitize_domain($raw_domain);
        if (empty($domain)) {
            wp_send_json_error(['message' => __('Please enter a valid domain (e.g., cdn.example.com)', 'bandwidth-saver')]);
            return;
        }

        $settings = $this->settings->get_all();
        $api_key = $settings['cloud_api_key'] ?? '';

        if (empty($api_key)) {
            wp_send_json_error([
                'message' => __('No subscription found. Please subscribe first.', 'bandwidth-saver')
            ]);
            return;
        }

        $result = $this->api->set_domain($api_key, $domain);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();

            if ('connection_error' === $error_code) {
                wp_send_json_error([
                    'message' => __('Could not connect to service. Please try again.', 'bandwidth-saver'),
                    'code' => 'connection_error'
                ]);
                return;
            }

            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code' => $error_code
            ]);
            return;
        }

        $this->settings->update([
            'custom_domain' => $domain,
            'custom_domain_status' => 'pending_dns',
        ]);

        wp_send_json_success([
            'message' => __('Domain added. Please configure your DNS.', 'bandwidth-saver'),
            'domain' => $domain,
            'status' => 'pending_dns',
            'dns_record' => $result['dns_record'] ?? [
                'type' => 'CNAME',
                'hostname' => $domain,
                'target' => ImgPro_CDN_Settings::CUSTOM_DOMAIN_TARGET
            ]
        ]);
    }

    /**
     * AJAX handler for checking custom domain status
     *
     * @since 0.1.6
     * @return void
     */
    public function ajax_check_custom_domain() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_custom_domain')) {
            wp_send_json_error(['message' => __('Security check failed', 'bandwidth-saver')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'bandwidth-saver')]);
        }

        $settings = $this->settings->get_all();
        $api_key = $settings['cloud_api_key'] ?? '';

        if (empty($api_key)) {
            wp_send_json_error([
                'message' => __('No subscription found.', 'bandwidth-saver')
            ]);
            return;
        }

        $result = $this->api->get_domain($api_key);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => __('Could not check status. Please try again.', 'bandwidth-saver'),
                'code' => $result->get_error_code()
            ]);
            return;
        }

        // Update local status if changed
        if (!empty($result['status']) && $result['status'] !== $settings['custom_domain_status']) {
            $this->settings->update([
                'custom_domain_status' => $result['status'],
            ]);
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX handler for removing custom domain
     *
     * @since 0.1.6
     * @return void
     */
    public function ajax_remove_custom_domain() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_custom_domain')) {
            wp_send_json_error(['message' => __('Security check failed', 'bandwidth-saver')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'bandwidth-saver')]);
        }

        $settings = $this->settings->get_all();
        $api_key = $settings['cloud_api_key'] ?? '';

        if (empty($api_key)) {
            wp_send_json_error([
                'message' => __('No subscription found.', 'bandwidth-saver')
            ]);
            return;
        }

        $result = $this->api->remove_domain($api_key);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => __('Could not remove domain. Please try again.', 'bandwidth-saver'),
                'code' => $result->get_error_code()
            ]);
            return;
        }

        $this->settings->update([
            'custom_domain' => '',
            'custom_domain_status' => '',
        ]);

        wp_send_json_success([
            'message' => __('Custom domain removed.', 'bandwidth-saver')
        ]);
    }

    /**
     * AJAX handler for removing CDN domain (self-hosted)
     *
     * @since 0.1.7
     * @return void
     */
    public function ajax_remove_cdn_domain() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_toggle_enabled')) {
            wp_send_json_error(['message' => __('Security check failed', 'bandwidth-saver')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'bandwidth-saver')]);
        }

        // Clear the CDN URL and disable the CDN
        $this->settings->update([
            'cdn_url' => '',
            'cloudflare_enabled' => false,
        ]);

        wp_send_json_success([
            'message' => __('CDN domain removed. The Image CDN has been disabled.', 'bandwidth-saver')
        ]);
    }
}
