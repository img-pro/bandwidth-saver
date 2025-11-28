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
     * Constructor
     *
     * @since 0.1.2
     * @param ImgPro_CDN_Settings $settings Settings instance.
     */
    public function __construct(ImgPro_CDN_Settings $settings) {
        $this->settings = $settings;
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
        $current_tab = isset($_POST['current_tab']) ? sanitize_text_field(wp_unslash($_POST['current_tab'])) : '';

        $current_settings = $this->settings->get_all();

        // Smart enable: if trying to enable on unconfigured tab, switch to configured mode
        if ($enabled && !empty($current_tab)) {
            $current_mode_valid = ImgPro_CDN_Settings::is_mode_valid($current_tab, $current_settings);

            if (!$current_mode_valid) {
                $other_mode = (ImgPro_CDN_Settings::MODE_CLOUD === $current_tab)
                    ? ImgPro_CDN_Settings::MODE_CLOUDFLARE
                    : ImgPro_CDN_Settings::MODE_CLOUD;
                $other_mode_valid = ImgPro_CDN_Settings::is_mode_valid($other_mode, $current_settings);

                if ($other_mode_valid) {
                    $current_settings['setup_mode'] = $other_mode;
                    $current_settings['enabled'] = true;
                    $current_settings['previously_enabled'] = false;

                    update_option(ImgPro_CDN_Settings::OPTION_KEY, $current_settings);
                    $this->settings->clear_cache();

                    $redirect_url = add_query_arg([
                        'tab' => $other_mode,
                        'switch_mode' => $other_mode,
                        '_wpnonce' => wp_create_nonce('imgpro_switch_mode')
                    ], admin_url('options-general.php?page=imgpro-cdn-settings'));

                    wp_send_json_success([
                        'message' => __('Image CDN enabled. Images now load from Cloudflare.', 'bandwidth-saver'),
                        'redirect' => $redirect_url
                    ]);
                    return;
                }

                wp_send_json_error(['message' => __('Please complete setup first. Choose Managed or Self-Host above.', 'bandwidth-saver')]);
                return;
            }
        }

        if ($current_settings['enabled'] === $enabled) {
            $message = $enabled
                ? __('Image CDN is active. Images are loading from Cloudflare.', 'bandwidth-saver')
                : __('Image CDN is disabled. Images are loading from your server.', 'bandwidth-saver');

            wp_send_json_success(['message' => $message]);
            return;
        }

        $current_settings['enabled'] = $enabled;

        if ($enabled) {
            $current_settings['previously_enabled'] = false;
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
     * @since 0.2.0
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

        // Check if API key already exists
        $settings = $this->settings->get_all();
        $api_key = $settings['cloud_api_key'] ?? '';

        if (empty($api_key)) {
            $api_key = $this->generate_api_key();
        }

        // Try to call API to register free account
        $api_url = ImgPro_CDN_Settings::get_api_base_url();
        $api_available = !empty($api_url) && strpos($api_url, 'localhost') === false;

        if ($api_available) {
            $response = wp_remote_post($api_url . '/api/register', [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode([
                    'email' => $email,
                    'site_url' => $site_url,
                    'api_key' => $api_key,
                    'tier' => 'free',
                    'marketing_opt_in' => $marketing_opt_in,
                ]),
                'timeout' => 15,
            ]);

            // Only process API response if we got one (not a connection error)
            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                $body = json_decode(wp_remote_retrieve_body($response), true);

                // Handle existing account - try to recover
                if (409 === $status_code && isset($body['existing'])) {
                    if (ImgPro_CDN_Settings::recover_account($this->settings)) {
                        wp_send_json_success([
                            'message' => __('Found your existing account. It has been activated.', 'bandwidth-saver'),
                            'recovered' => true,
                            'next_step' => 3
                        ]);
                    } else {
                        wp_send_json_error([
                            'message' => __('This email is already registered. Try recovering your account.', 'bandwidth-saver'),
                            'existing' => true,
                            'code' => 'account_exists'
                        ]);
                    }
                    return;
                }

                // If API explicitly rejected, show error
                if ($status_code >= 400 && $status_code < 500 && isset($body['error'])) {
                    wp_send_json_error([
                        'message' => $body['error'],
                        'code' => 'api_error'
                    ]);
                    return;
                }

                // API registration successful (status 201)
                if ($status_code >= 200 && $status_code < 300 && isset($body['success'])) {
                    // Update local settings with API response
                    $this->settings->update([
                        'cloud_api_key' => $api_key,
                        'cloud_email' => $email,
                        'cloud_tier' => $body['tier'] ?? ImgPro_CDN_Settings::TIER_FREE,
                        'setup_mode' => ImgPro_CDN_Settings::MODE_CLOUD,
                        'onboarding_step' => 3,
                        'marketing_opt_in' => $marketing_opt_in,
                        'storage_limit' => $body['storage_limit'] ?? ImgPro_CDN_Settings::FREE_STORAGE_LIMIT,
                    ]);

                    wp_send_json_success([
                        'message' => __('Account created! Ready to activate your CDN.', 'bandwidth-saver'),
                        'next_step' => 3
                    ]);
                    return;
                }
            }
            // If API unavailable or returned 500, fall through to local registration
        }

        // Local registration fallback (works offline or when API unavailable)
        $this->settings->update([
            'cloud_api_key' => $api_key,
            'cloud_email' => $email,
            'cloud_tier' => ImgPro_CDN_Settings::TIER_FREE,
            'setup_mode' => ImgPro_CDN_Settings::MODE_CLOUD,
            'onboarding_step' => 3,
            'marketing_opt_in' => $marketing_opt_in,
            'storage_limit' => ImgPro_CDN_Settings::FREE_STORAGE_LIMIT,
        ]);

        wp_send_json_success([
            'message' => __('Account created! Ready to activate your CDN.', 'bandwidth-saver'),
            'next_step' => 3
        ]);
    }

    /**
     * AJAX handler for updating onboarding step
     *
     * @since 0.2.0
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
     * @since 0.2.0
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
        ]);

        wp_send_json_success([
            'message' => __('Setup complete!', 'bandwidth-saver'),
            'redirect' => admin_url('options-general.php?page=imgpro-cdn-settings&tab=cloud')
        ]);
    }

    /**
     * AJAX handler for syncing stats from API
     *
     * @since 0.2.0
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

        // Call API to get stats
        $response = wp_remote_get(
            add_query_arg('api_key', $api_key, ImgPro_CDN_Settings::get_api_base_url() . '/api/stats'),
            ['timeout' => 10]
        );

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => __('Could not sync stats. Please try again.', 'bandwidth-saver'),
                'code' => 'connection_error'
            ]);
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code >= 400 || !is_array($data)) {
            wp_send_json_error([
                'message' => __('Could not retrieve stats.', 'bandwidth-saver'),
                'code' => 'api_error'
            ]);
            return;
        }

        // Update local settings with stats
        $update = ['stats_updated_at' => time()];

        if (isset($data['storage_used'])) {
            $update['storage_used'] = absint($data['storage_used']);
        }
        if (isset($data['storage_limit'])) {
            $update['storage_limit'] = absint($data['storage_limit']);
        }
        if (isset($data['images_cached'])) {
            $update['images_cached'] = absint($data['images_cached']);
        }
        if (isset($data['bandwidth_saved'])) {
            $update['bandwidth_saved'] = absint($data['bandwidth_saved']);
        }

        $this->settings->update($update);

        // Get updated settings for response
        $updated_settings = $this->settings->get_all();
        $storage_limit = ImgPro_CDN_Settings::get_storage_limit($updated_settings);

        wp_send_json_success([
            'storage_used' => $update['storage_used'] ?? 0,
            'storage_limit' => $storage_limit,
            'storage_percentage' => ImgPro_CDN_Settings::get_storage_percentage($updated_settings),
            'images_cached' => $update['images_cached'] ?? 0,
            'bandwidth_saved' => $update['bandwidth_saved'] ?? 0,
            'formatted' => [
                'storage_used' => ImgPro_CDN_Settings::format_bytes($update['storage_used'] ?? 0),
                'storage_limit' => ImgPro_CDN_Settings::format_bytes($storage_limit, 0),
                'bandwidth_saved' => ImgPro_CDN_Settings::format_bytes($update['bandwidth_saved'] ?? 0),
            ]
        ]);
    }

    /**
     * AJAX handler for Stripe checkout (Pro upgrade)
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

        $email = get_option('admin_email');
        $site_url = get_site_url();

        $settings = $this->settings->get_all();
        $api_key = $settings['cloud_api_key'] ?? '';

        if (empty($api_key)) {
            $api_key = $this->generate_api_key();

            $settings['cloud_api_key'] = $api_key;
            $settings['setup_mode'] = ImgPro_CDN_Settings::MODE_CLOUD;
            update_option(ImgPro_CDN_Settings::OPTION_KEY, $settings);
            $this->settings->clear_cache();
        }

        $response = wp_remote_post(ImgPro_CDN_Settings::get_api_base_url() . '/api/checkout', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'email' => $email,
                'site_url' => $site_url,
                'api_key' => $api_key,
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            ImgPro_CDN_Settings::handle_api_error($response, 'checkout');
            wp_send_json_error([
                'message' => __('Could not connect to billing service. Please try again in a moment.', 'bandwidth-saver'),
                'code' => 'connection_error'
            ]);
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (409 === $status_code && isset($body['existing'])) {
            if (ImgPro_CDN_Settings::recover_account($this->settings)) {
                wp_send_json_success([
                    'message' => __('Found your existing subscription. It has been activated.', 'bandwidth-saver'),
                    'recovered' => true
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('This site has an existing subscription but we could not activate it. Please contact support.', 'bandwidth-saver'),
                    'existing' => true,
                    'code' => 'recovery_failed'
                ]);
            }
            return;
        }

        if ($status_code >= 400 && $status_code < 500) {
            ImgPro_CDN_Settings::handle_api_error(['status' => $status_code, 'body' => $body], 'checkout');
            $error_message = isset($body['error']) && is_string($body['error'])
                ? $body['error']
                : __('Something went wrong. Please try again or contact support.', 'bandwidth-saver');
            wp_send_json_error([
                'message' => $error_message,
                'code' => 'client_error'
            ]);
            return;
        }

        if ($status_code >= 500) {
            ImgPro_CDN_Settings::handle_api_error(['status' => $status_code, 'body' => $body], 'checkout');
            wp_send_json_error([
                'message' => __('Billing service is temporarily unavailable. Please try again in a few minutes.', 'bandwidth-saver'),
                'code' => 'server_error'
            ]);
            return;
        }

        if (isset($body['url'])) {
            set_transient('imgpro_cdn_pending_payment', true, HOUR_IN_SECONDS);
            wp_send_json_success(['checkout_url' => $body['url']]);
        } else {
            ImgPro_CDN_Settings::handle_api_error(['status' => $status_code, 'body' => $body], 'checkout');
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

        if (ImgPro_CDN_Settings::recover_account($this->settings)) {
            wp_send_json_success([
                'message' => __('Account recovered. Your subscription is now active.', 'bandwidth-saver')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('No subscription found for this site. If you subscribed recently, please wait a moment and try again.', 'bandwidth-saver')
            ]);
        }
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

        $response = wp_remote_post(ImgPro_CDN_Settings::get_api_base_url() . '/api/portal', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(['api_key' => $api_key]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            ImgPro_CDN_Settings::handle_api_error($response, 'portal');
            wp_send_json_error([
                'message' => __('Could not connect to billing service. Please try again in a moment.', 'bandwidth-saver'),
                'code' => 'connection_error'
            ]);
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (401 === $status_code || 403 === $status_code) {
            ImgPro_CDN_Settings::handle_api_error(['status' => $status_code, 'body' => $data], 'portal');
            wp_send_json_error([
                'message' => __('Could not verify your subscription. Try recovering your account.', 'bandwidth-saver'),
                'code' => 'auth_error'
            ]);
            return;
        }

        if ($status_code >= 400 && $status_code < 500) {
            ImgPro_CDN_Settings::handle_api_error(['status' => $status_code, 'body' => $data], 'portal');
            $error_message = isset($data['error']) && is_string($data['error'])
                ? $data['error']
                : __('Could not open subscription portal. Please try again or contact support.', 'bandwidth-saver');
            wp_send_json_error([
                'message' => $error_message,
                'code' => 'client_error'
            ]);
            return;
        }

        if ($status_code >= 500) {
            ImgPro_CDN_Settings::handle_api_error(['status' => $status_code, 'body' => $data], 'portal');
            wp_send_json_error([
                'message' => __('Billing service is temporarily unavailable. Please try again in a few minutes.', 'bandwidth-saver'),
                'code' => 'server_error'
            ]);
            return;
        }

        if (!empty($data['portal_url'])) {
            wp_send_json_success(['portal_url' => $data['portal_url']]);
        } else {
            ImgPro_CDN_Settings::handle_api_error(['status' => $status_code, 'body' => $data], 'portal');
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

        $response = wp_remote_post(ImgPro_CDN_Settings::get_api_base_url() . '/api/custom-domain', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'api_key' => $api_key,
                'domain' => $domain,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            ImgPro_CDN_Settings::handle_api_error($response, 'custom_domain_add');
            wp_send_json_error([
                'message' => __('Could not connect to service. Please try again.', 'bandwidth-saver'),
                'code' => 'connection_error'
            ]);
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code >= 400) {
            $error_message = isset($data['error']) ? $data['error'] : __('Could not add domain. Please try again.', 'bandwidth-saver');
            wp_send_json_error([
                'message' => $error_message,
                'code' => 'api_error'
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
            'dns_record' => $data['dns_record'] ?? [
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

        $response = wp_remote_get(
            add_query_arg('api_key', $api_key, ImgPro_CDN_Settings::get_api_base_url() . '/api/custom-domain/status'),
            ['timeout' => 15]
        );

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => __('Could not check status. Please try again.', 'bandwidth-saver'),
                'code' => 'connection_error'
            ]);
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code >= 400) {
            wp_send_json_error([
                'message' => __('Could not check status.', 'bandwidth-saver'),
                'code' => 'api_error'
            ]);
            return;
        }

        if (!empty($data['status']) && $data['status'] !== $settings['custom_domain_status']) {
            $this->settings->update([
                'custom_domain_status' => $data['status'],
            ]);
        }

        wp_send_json_success($data);
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

        $response = wp_remote_request(ImgPro_CDN_Settings::get_api_base_url() . '/api/custom-domain', [
            'method' => 'DELETE',
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(['api_key' => $api_key]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => __('Could not connect to service. Please try again.', 'bandwidth-saver'),
                'code' => 'connection_error'
            ]);
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 400) {
            wp_send_json_error([
                'message' => __('Could not remove domain. Please try again.', 'bandwidth-saver'),
                'code' => 'api_error'
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
            'enabled' => false,
        ]);

        wp_send_json_success([
            'message' => __('CDN domain removed. The Image CDN has been disabled.', 'bandwidth-saver')
        ]);
    }
}
