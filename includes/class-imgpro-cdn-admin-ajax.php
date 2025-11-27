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
 * subscription management, and account recovery.
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
    }

    /**
     * Verify AJAX request security
     *
     * Checks nonce and user permissions. Sends JSON error and exits if invalid.
     *
     * @since 0.1.6
     * @param string $nonce_action The nonce action to verify against.
     * @return void Exits with JSON error if verification fails.
     */
    private function verify_ajax_request($nonce_action) {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, $nonce_action)) {
            wp_send_json_error(['message' => __('Security check failed', 'bandwidth-saver')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'bandwidth-saver')]);
        }
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
        $this->verify_ajax_request('imgpro_cdn_toggle_enabled');

        // Get enabled value and current tab
        $enabled = isset($_POST['enabled']) && '1' === $_POST['enabled'];
        $current_tab = isset($_POST['current_tab']) ? sanitize_text_field(wp_unslash($_POST['current_tab'])) : '';

        // Get current settings
        $current_settings = $this->settings->get_all();

        // Smart enable: if trying to enable on unconfigured tab, switch to configured mode
        if ($enabled && !empty($current_tab)) {
            $current_mode_valid = ImgPro_CDN_Settings::is_mode_valid($current_tab, $current_settings);

            if (!$current_mode_valid) {
                // Current tab is not configured, check if another mode is
                $other_mode = (ImgPro_CDN_Settings::MODE_CLOUD === $current_tab) ? ImgPro_CDN_Settings::MODE_CLOUDFLARE : ImgPro_CDN_Settings::MODE_CLOUD;
                $other_mode_valid = ImgPro_CDN_Settings::is_mode_valid($other_mode, $current_settings);

                if ($other_mode_valid) {
                    // Switch to the configured mode, enable, and redirect
                    $current_settings['setup_mode'] = $other_mode;
                    $current_settings['enabled'] = true;
                    $current_settings['previously_enabled'] = false;

                    update_option(ImgPro_CDN_Settings::OPTION_KEY, $current_settings);
                    $this->settings->clear_cache();

                    // Build redirect URL with nonce
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

                // No configured mode available
                wp_send_json_error(['message' => __('Please complete setup first. Choose Managed or Self-Host above.', 'bandwidth-saver')]);
                return;
            }
        }

        // Check if value is already set to desired state
        if ($current_settings['enabled'] === $enabled) {
            $message = $enabled
                ? __('Image CDN is active. Images are loading from Cloudflare.', 'bandwidth-saver')
                : __('Image CDN is disabled. Images are loading from your server.', 'bandwidth-saver');

            wp_send_json_success(['message' => $message]);
            return;
        }

        // Update only the enabled field
        $current_settings['enabled'] = $enabled;

        // Clear previously_enabled when user manually toggles
        if ($enabled) {
            $current_settings['previously_enabled'] = false;
        }

        // Save settings
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
     * AJAX handler for Stripe checkout
     *
     * @since 0.1.2
     * @return void
     */
    public function ajax_checkout() {
        $this->verify_ajax_request('imgpro_cdn_checkout');

        // Get admin email and site URL
        $email = get_option('admin_email');
        $site_url = get_site_url();

        // Check if API key already exists, otherwise generate new one
        $settings = $this->settings->get_all();
        $api_key = $settings['cloud_api_key'] ?? '';

        if (empty($api_key)) {
            // Generate new API key
            $api_key = $this->generate_api_key();

            // Save API key immediately (before checkout)
            $settings['cloud_api_key'] = $api_key;
            $settings['setup_mode'] = ImgPro_CDN_Settings::MODE_CLOUD;
            update_option(ImgPro_CDN_Settings::OPTION_KEY, $settings);
            $this->settings->clear_cache();
        }

        // Call Managed billing API
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

        // Handle specific HTTP status codes
        if (409 === $status_code && isset($body['existing'])) {
            // Existing subscription - attempt to recover it automatically
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
            // Client error - log and show appropriate message
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
            // Server error - log and show generic message
            ImgPro_CDN_Settings::handle_api_error(['status' => $status_code, 'body' => $body], 'checkout');
            wp_send_json_error([
                'message' => __('Billing service is temporarily unavailable. Please try again in a few minutes.', 'bandwidth-saver'),
                'code' => 'server_error'
            ]);
            return;
        }

        if (isset($body['url'])) {
            // Set transient flag to check for payment on next page load
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
        $this->verify_ajax_request('imgpro_cdn_checkout');

        // Attempt recovery
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
     * AJAX handler for managing subscription (redirects to Stripe portal)
     *
     * @since 0.1.2
     * @return void
     */
    public function ajax_manage_subscription() {
        $this->verify_ajax_request('imgpro_cdn_checkout');

        // Get API key from settings
        $settings = $this->settings->get_all();
        $api_key = $settings['cloud_api_key'] ?? '';

        if (empty($api_key)) {
            wp_send_json_error([
                'message' => __('No subscription found. Please subscribe first or recover your account.', 'bandwidth-saver')
            ]);
            return;
        }

        // Call billing API to create customer portal session
        $response = wp_remote_post(ImgPro_CDN_Settings::get_api_base_url() . '/api/portal', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'api_key' => $api_key,
            ]),
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

        // Handle authentication errors (invalid/expired API key)
        if (401 === $status_code || 403 === $status_code) {
            ImgPro_CDN_Settings::handle_api_error(['status' => $status_code, 'body' => $data], 'portal');
            wp_send_json_error([
                'message' => __('Could not verify your subscription. Try recovering your account.', 'bandwidth-saver'),
                'code' => 'auth_error'
            ]);
            return;
        }

        // Handle other client errors (400, 404, 422, etc.)
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

        // Handle server errors
        if ($status_code >= 500) {
            ImgPro_CDN_Settings::handle_api_error(['status' => $status_code, 'body' => $data], 'portal');
            wp_send_json_error([
                'message' => __('Billing service is temporarily unavailable. Please try again in a few minutes.', 'bandwidth-saver'),
                'code' => 'server_error'
            ]);
            return;
        }

        // Success - check for portal URL
        if (!empty($data['portal_url'])) {
            wp_send_json_success([
                'portal_url' => $data['portal_url']
            ]);
        } else {
            // Unexpected response format (success status but no portal_url)
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
        $this->verify_ajax_request('imgpro_cdn_custom_domain');

        // Get and sanitize domain from request
        $raw_domain = isset($_POST['domain']) ? sanitize_text_field(wp_unslash($_POST['domain'])) : '';
        $domain = ImgPro_CDN_Settings::sanitize_domain($raw_domain);
        if (empty($domain)) {
            wp_send_json_error(['message' => __('Please enter a valid domain (e.g., cdn.example.com)', 'bandwidth-saver')]);
            return;
        }

        // Get API key from settings
        $settings = $this->settings->get_all();
        $api_key = $settings['cloud_api_key'] ?? '';

        if (empty($api_key)) {
            wp_send_json_error([
                'message' => __('No subscription found. Please subscribe first.', 'bandwidth-saver')
            ]);
            return;
        }

        // Call billing API to add custom domain
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

        // Update local settings
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
        $this->verify_ajax_request('imgpro_cdn_custom_domain');

        // Get API key from settings
        $settings = $this->settings->get_all();
        $api_key = $settings['cloud_api_key'] ?? '';

        if (empty($api_key)) {
            wp_send_json_error([
                'message' => __('No subscription found.', 'bandwidth-saver')
            ]);
            return;
        }

        // Call billing API to check status
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

        // Update local settings if status changed
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
        $this->verify_ajax_request('imgpro_cdn_custom_domain');

        // Get API key from settings
        $settings = $this->settings->get_all();
        $api_key = $settings['cloud_api_key'] ?? '';

        if (empty($api_key)) {
            wp_send_json_error([
                'message' => __('No subscription found.', 'bandwidth-saver')
            ]);
            return;
        }

        // Call billing API to remove domain
        $response = wp_remote_request(ImgPro_CDN_Settings::get_api_base_url() . '/api/custom-domain', [
            'method' => 'DELETE',
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'api_key' => $api_key,
            ]),
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

        // Clear local settings
        $this->settings->update([
            'custom_domain' => '',
            'custom_domain_status' => '',
        ]);

        wp_send_json_success([
            'message' => __('Custom domain removed.', 'bandwidth-saver')
        ]);
    }
}
