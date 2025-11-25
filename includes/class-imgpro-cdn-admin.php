<?php
/**
 * ImgPro Admin Interface
 *
 * @package ImgPro_CDN
 * @version 0.1.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class ImgPro_CDN_Admin {

    /**
     * Settings instance
     *
     * @var ImgPro_CDN_Settings
     */
    private $settings;

    /**
     * Constructor
     *
     * @param ImgPro_CDN_Settings $settings Settings instance
     */
    public function __construct(ImgPro_CDN_Settings $settings) {
        $this->settings = $settings;
    }

    /**
     * Register admin hooks
     */
    public function register_hooks() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'show_notices']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Register AJAX handlers
        add_action('wp_ajax_imgpro_cdn_toggle_enabled', [$this, 'ajax_toggle_enabled']);
        add_action('wp_ajax_imgpro_cdn_checkout', [$this, 'ajax_checkout']);
        add_action('wp_ajax_imgpro_cdn_manage_subscription', [$this, 'ajax_manage_subscription']);
        add_action('wp_ajax_imgpro_cdn_recover_account', [$this, 'ajax_recover_account']);
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our settings page
        if ($hook !== 'settings_page_imgpro-cdn-settings') {
            return;
        }

        // Enqueue admin CSS (if file exists)
        $css_file = dirname(__FILE__) . '/../admin/css/imgpro-cdn-admin.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'imgpro-cdn-admin',
                plugins_url('admin/css/imgpro-cdn-admin.css', dirname(__FILE__)),
                [],
                IMGPRO_CDN_VERSION . '.' . filemtime($css_file)
            );
        }

        // Enqueue admin JS (if file exists)
        $js_file = dirname(__FILE__) . '/../admin/js/imgpro-cdn-admin.js';
        if (file_exists($js_file)) {
            wp_enqueue_script(
                'imgpro-cdn-admin',
                plugins_url('admin/js/imgpro-cdn-admin.js', dirname(__FILE__)),
                ['jquery'],
                IMGPRO_CDN_VERSION . '.' . filemtime($js_file),
                true
            );

            // Localize script
            wp_localize_script('imgpro-cdn-admin', 'imgproCdnAdmin', [
                'nonce' => wp_create_nonce('imgpro_cdn_toggle_enabled'),
                'checkoutNonce' => wp_create_nonce('imgpro_cdn_checkout'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'i18n' => [
                    'activeLabel' => __('Active', 'bandwidth-saver'),
                    'disabledLabel' => __('Disabled', 'bandwidth-saver'),
                    'activeMessage' => '<span class="imgpro-cdn-nowrap imgpro-cdn-hide-mobile">' . __('Images load faster worldwide.', 'bandwidth-saver') . '</span> <span class="imgpro-cdn-nowrap">' . __('Your bandwidth costs are being reduced.', 'bandwidth-saver') . '</span>',
                    'disabledMessage' => __('Enable to cut bandwidth costs and speed up image delivery globally', 'bandwidth-saver'),
                    // Button states
                    'creatingCheckout' => __('Creating checkout session...', 'bandwidth-saver'),
                    'recovering' => __('Recovering...', 'bandwidth-saver'),
                    'openingPortal' => __('Opening portal...', 'bandwidth-saver'),
                    // Error messages
                    'checkoutError' => __('Failed to create checkout session', 'bandwidth-saver'),
                    'recoverError' => __('Failed to recover account', 'bandwidth-saver'),
                    'portalError' => __('Failed to open customer portal', 'bandwidth-saver'),
                    'genericError' => __('An error occurred. Please try again.', 'bandwidth-saver'),
                    'settingsError' => __('Failed to update settings', 'bandwidth-saver'),
                    // Confirm dialogs
                    'recoverConfirm' => __('This will recover your subscription details. Continue?', 'bandwidth-saver'),
                    // Success messages
                    'subscriptionActivated' => __('Subscription activated successfully!', 'bandwidth-saver'),
                    'checkoutCancelled' => __('Checkout was cancelled. You can try again anytime.', 'bandwidth-saver'),
                    // Toggle UI text
                    'cdnActiveHeading' => __('Image CDN is Active', 'bandwidth-saver'),
                    'cdnInactiveHeading' => __('Image CDN is Inactive', 'bandwidth-saver'),
                    'cdnActiveDesc' => __('Images are being optimized and delivered from edge locations worldwide.', 'bandwidth-saver'),
                    'cdnInactiveDesc' => __('Turn on to optimize images and reduce bandwidth costs.', 'bandwidth-saver'),
                ]
            ]);
        }
    }

    /**
     * Add admin menu page
     */
    public function add_menu_page() {
        add_options_page(
            esc_html__('Image CDN', 'bandwidth-saver'),       // Page title
            esc_html__('Image CDN', 'bandwidth-saver'),       // Menu title
            'manage_options',                         // Capability required
            'imgpro-cdn-settings',                        // Menu slug
            [$this, 'render_settings_page']          // Callback function
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'imgpro_cdn_settings_group',
            ImgPro_CDN_Settings::OPTION_KEY,
            [$this, 'sanitize_settings']
        );
    }

    /**
     * Sanitize settings
     *
     * This callback is called by WordPress Settings API when settings are saved.
     * We merge validated input with existing settings to preserve values from
     * other tabs that weren't submitted in this form.
     *
     * @param array $input Posted form data
     * @return array Complete settings array to be saved
     */
    public function sanitize_settings($input) {
        // Get existing settings to preserve fields not in current form
        $existing = $this->settings->get_all();

        // Validate submitted fields
        $validated = $this->settings->validate($input);

        // Merge with existing settings to preserve Cloud/Cloudflare data when switching tabs
        $merged = array_merge($existing, $validated);

        // Handle unchecked checkboxes (HTML doesn't submit unchecked values)
        // Only apply this logic when the form that contains these fields was submitted
        // The toggle form includes a hidden '_has_enabled_field' marker to identify it
        if (isset($input['_has_enabled_field'])) {
            if (!isset($input['enabled'])) {
                $merged['enabled'] = false;
            }
            if (!isset($input['debug_mode'])) {
                $merged['debug_mode'] = false;
            }
        }

        // Auto-disable plugin if the ACTIVE mode is not properly configured
        // Only run this check when:
        // 1. The enabled field was explicitly submitted (user toggled or submitted toggle form), OR
        // 2. The setup_mode is being changed (user switched tabs)
        // This prevents unexpectedly disabling the CDN when users save unrelated settings
        $enabled_field_submitted = isset($input['_has_enabled_field']);
        $mode_is_changing = isset($input['setup_mode']) && ($input['setup_mode'] !== ($existing['setup_mode'] ?? ''));

        if ($enabled_field_submitted || $mode_is_changing) {
            if (!$this->is_mode_valid($merged['setup_mode'] ?? '', $merged)) {
                $merged['enabled'] = false;
            }
        }

        return $merged;
    }

    /**
     * Show admin notices
     *
     * On our settings page, we suppress all default WordPress notices
     * and render our own notices inline via render_inline_notices().
     */
    public function show_notices() {
        // Don't output anything on our settings page
        // All notices are rendered inline via render_inline_notices()
    }

    /**
     * Render inline notices within the settings page
     *
     * Called from render_settings_page() to show notices in the correct position.
     */
    private function render_inline_notices() {
        // Handle payment success - attempt recovery (single attempt, no blocking)
        $payment_status = filter_input(INPUT_GET, 'payment', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ($payment_status === 'success') {
            // Single recovery attempt without blocking
            if ($this->recover_account()) {
                // Success! Redirect to show activation
                delete_transient('imgpro_cdn_pending_payment');
                $clean_url = admin_url('options-general.php?page=imgpro-cdn-settings&tab=cloud&activated=1');
                wp_safe_redirect($clean_url);
                exit;
            } else {
                // Webhook hasn't processed yet - show pending notice
                ?>
                <div class="notice notice-info is-dismissible imgpro-cdn-inline-notice">
                    <p>
                        <strong><?php esc_html_e('Payment received! Your account is being set up.', 'bandwidth-saver'); ?></strong>
                        <?php esc_html_e('Refresh this page in a few seconds to complete activation.', 'bandwidth-saver'); ?>
                    </p>
                </div>
                <?php
            }
        }

        // Show activation success message
        if (filter_input(INPUT_GET, 'activated', FILTER_VALIDATE_BOOLEAN)) {
            ?>
            <div class="notice notice-success is-dismissible imgpro-cdn-inline-notice">
                <p>
                    <strong><?php esc_html_e('Subscription activated successfully!', 'bandwidth-saver'); ?></strong>
                </p>
            </div>
            <?php
        }

        // Show settings saved message
        if (filter_input(INPUT_GET, 'settings-updated', FILTER_VALIDATE_BOOLEAN)) {
            ?>
            <div class="notice notice-success is-dismissible imgpro-cdn-inline-notice">
                <p>
                    <strong><?php esc_html_e('Settings saved successfully!', 'bandwidth-saver'); ?></strong>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if there's a pending payment and attempt recovery
        if (get_transient('imgpro_cdn_pending_payment')) {
            // Attempt recovery (webhook might have completed)
            if ($this->recover_account()) {
                delete_transient('imgpro_cdn_pending_payment');
                // Redirect to show success
                wp_safe_redirect(admin_url('options-general.php?page=imgpro-cdn-settings&tab=cloud&payment=success'));
                exit;
            }
            // Keep transient for next page load if recovery failed
        }

        $settings = $this->settings->get_all();

        // Handle mode switching (when user clicks tabs)
        if (isset($_GET['switch_mode'])) {
            // Verify nonce for CSRF protection
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            if (!wp_verify_nonce($nonce, 'imgpro_switch_mode')) {
                wp_die(esc_html__('Security check failed', 'bandwidth-saver'));
            }

            $new_mode = sanitize_text_field(wp_unslash($_GET['switch_mode']));
            if (in_array($new_mode, ['cloud', 'cloudflare'], true)) {
                $old_mode = $settings['setup_mode'] ?? '';
                $was_enabled = $settings['enabled'] ?? false;
                $new_mode_is_valid = $this->is_mode_valid($new_mode, $settings);

                // Update setup_mode
                $settings['setup_mode'] = $new_mode;

                if ($new_mode_is_valid) {
                    // Switching TO a configured mode
                    // Check if we should restore the previously enabled state
                    if (!empty($settings['previously_enabled'])) {
                        $settings['enabled'] = true;
                        $settings['previously_enabled'] = false;
                    }
                } else {
                    // Switching TO an unconfigured mode
                    // Remember the enabled state if CDN was active
                    if ($was_enabled) {
                        $settings['previously_enabled'] = true;
                    }
                    $settings['enabled'] = false;
                }

                update_option(ImgPro_CDN_Settings::OPTION_KEY, $settings);
                $this->settings->clear_cache(); // Ensure subsequent reads get fresh data
            }
        }

        // Determine current tab from URL or settings
        $current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';

        // If no tab specified, use setup_mode from settings or default to 'cloudflare' (Self-Host)
        // Self-Host is the default to show the free option first, building trust before showing paid option
        if (empty($current_tab)) {
            $current_tab = !empty($settings['setup_mode']) ? $settings['setup_mode'] : 'cloudflare';
        }

        ?>
        <div class="wrap imgpro-cdn-admin">
            <div class="imgpro-cdn-header">
                <div>
                    <h1><?php esc_html_e('Bandwidth Saver: Image CDN', 'bandwidth-saver'); ?></h1>
                    <p class="imgpro-cdn-tagline"><?php esc_html_e('Cut bandwidth costs, boost global speed', 'bandwidth-saver'); ?></p>
                </div>
                <div class="imgpro-cdn-header-meta">
                    <span class="imgpro-cdn-version">v<?php echo esc_html(IMGPRO_CDN_VERSION); ?></span>
                </div>
            </div>

            <?php // WordPress looks for .wp-header-end to position admin notices ?>
            <hr class="wp-header-end">

            <?php
            // Show inline notices (settings saved, payment status, etc.)
            $this->render_inline_notices();

            // Show toggle if configured
            $this->render_main_toggle($settings);

            // Show tabs
            $this->render_tabs($current_tab, $settings);

            // Show account status (if Cloud subscription exists and Cloud tab is active)
            if ($current_tab === 'cloud') {
                $this->render_account_status($settings);
            }
            ?>

            <div class="imgpro-cdn-tab-content">
                <?php
                if ($current_tab === 'cloud') {
                    // Managed tab
                    $this->render_cloud_tab($settings);
                } else {
                    // Self-Host (Cloudflare) tab
                    $this->render_cloudflare_tab($settings);
                }
                ?>
            </div>

            <div class="imgpro-cdn-footer">
                <p>
                    <?php
                    echo wp_kses_post(
                        sprintf(
                            /* translators: 1: ImgPro link, 2: Cloudflare R2 & Workers link */
                            __('Bandwidth Saver: Image CDN by %1$s, powered by %2$s', 'bandwidth-saver'),
                            '<a href="https://img.pro" target="_blank">ImgPro</a>',
                            '<a href="https://www.cloudflare.com/developer-platform/products/r2/" target="_blank">Cloudflare R2</a> &amp; <a href="https://www.cloudflare.com/developer-platform/products/workers/" target="_blank">Workers</a>'
                        )
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render main toggle (above tabs, works for both modes)
     */
    private function render_main_toggle($settings) {
        // Check if EITHER backend is configured (not just active mode)
        $has_cloud = ($settings['cloud_tier'] === 'active');
        $has_cloudflare = !empty($settings['cdn_url']) && !empty($settings['worker_url']);

        if (!$has_cloud && !$has_cloudflare) {
            return; // Don't show toggle if nothing is configured
        }

        // Use current setup_mode or infer from what's configured
        $setup_mode = $settings['setup_mode'] ?? '';
        if (empty($setup_mode)) {
            $setup_mode = $has_cloud ? 'cloud' : 'cloudflare';
        }

        $is_enabled = $settings['enabled'] ?? false;
        ?>
        <form method="post" action="options.php" class="imgpro-cdn-toggle-form">
            <?php settings_fields('imgpro_cdn_settings_group'); ?>
            <input type="hidden" name="imgpro_cdn_settings[setup_mode]" value="<?php echo esc_attr($setup_mode); ?>">
            <?php // Marker to identify this form contains the enabled checkbox ?>
            <input type="hidden" name="imgpro_cdn_settings[_has_enabled_field]" value="1">

            <div class="imgpro-cdn-main-toggle-card <?php echo $is_enabled ? 'is-active' : 'is-inactive'; ?>">
                <div class="imgpro-cdn-toggle-wrapper">
                    <div class="imgpro-cdn-toggle-info">
                        <div class="imgpro-cdn-toggle-status">
                            <span class="dashicons <?php echo $is_enabled ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>"></span>
                            <h3>
                                <?php echo $is_enabled
                                    ? esc_html__('Image CDN is Active', 'bandwidth-saver')
                                    : esc_html__('Image CDN is Inactive', 'bandwidth-saver'); ?>
                            </h3>
                        </div>
                        <p class="imgpro-cdn-toggle-description">
                            <?php echo $is_enabled
                                ? esc_html__('Images are being optimized and delivered from edge locations worldwide.', 'bandwidth-saver')
                                : esc_html__('Turn on to optimize images and reduce bandwidth costs.', 'bandwidth-saver'); ?>
                        </p>
                    </div>

                    <label class="imgpro-cdn-toggle-switch" for="enabled">
                        <input
                            type="checkbox"
                            id="enabled"
                            name="imgpro_cdn_settings[enabled]"
                            value="1"
                            <?php checked($is_enabled, true); ?>
                            aria-describedby="enabled-description"
                            role="switch"
                            aria-checked="<?php echo $is_enabled ? 'true' : 'false'; ?>"
                        >
                        <span class="imgpro-cdn-toggle-slider" aria-hidden="true"></span>
                        <span class="screen-reader-text" id="enabled-description">
                            <?php esc_html_e('Toggle Image CDN on or off', 'bandwidth-saver'); ?>
                        </span>
                    </label>
                </div>
            </div>
        </form>
        <?php
    }

    /**
     * Render account status (Cloud subscription info - shown regardless of active tab)
     */
    private function render_account_status($settings) {
        // Only show if user has Managed subscription
        $has_subscription = ($settings['cloud_tier'] === 'active');
        if (!$has_subscription) {
            return;
        }

        ?>
        <div class="imgpro-cdn-account-card">
            <div class="imgpro-cdn-account-header">
                <div class="imgpro-cdn-account-info">
                    <span class="imgpro-cdn-account-icon dashicons dashicons-cloud"></span>
                    <div>
                        <h3><?php esc_html_e('Cloud Account', 'bandwidth-saver'); ?></h3>
                        <p class="imgpro-cdn-account-plan">
                            <?php esc_html_e('Active Subscription', 'bandwidth-saver'); ?>
                        </p>
                    </div>
                </div>
                <div class="imgpro-cdn-account-actions">
                    <span class="imgpro-cdn-account-email"><?php echo esc_html($settings['cloud_email']); ?></span>
                    <button type="button" class="button" id="imgpro-cdn-manage-subscription">
                        <?php esc_html_e('Manage Subscription', 'bandwidth-saver'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render navigation tabs
     */
    private function render_tabs($current_tab, $settings) {
        $base_url = admin_url('options-general.php?page=imgpro-cdn-settings');

        // Check if both modes are configured
        $has_cloud = ($settings['cloud_tier'] === 'active');
        $has_cloudflare = !empty($settings['cdn_url']) && !empty($settings['worker_url']);

        // Always add switch_mode parameter with nonce when clicking tabs
        $cloud_url = add_query_arg([
            'tab' => 'cloud',
            'switch_mode' => 'cloud',
            '_wpnonce' => wp_create_nonce('imgpro_switch_mode')
        ], $base_url);
        $cloudflare_url = add_query_arg([
            'tab' => 'cloudflare',
            'switch_mode' => 'cloudflare',
            '_wpnonce' => wp_create_nonce('imgpro_switch_mode')
        ], $base_url);

        // Get enabled state for color coding the active tab
        $is_enabled = $settings['enabled'] ?? false;

        ?>
        <nav class="nav-tab-wrapper imgpro-cdn-nav-tabs">
            <a href="<?php echo esc_url($cloudflare_url); ?>"
               class="nav-tab <?php echo $current_tab === 'cloudflare' ? 'nav-tab-active' : ''; ?> <?php echo $current_tab === 'cloudflare' ? ($is_enabled ? 'is-enabled' : 'is-disabled') : ''; ?>"
               data-tab="cloudflare">
                <span class="dashicons dashicons-cloud"></span>
                <?php esc_html_e('Self-Host', 'bandwidth-saver'); ?>
            </a>
            <a href="<?php echo esc_url($cloud_url); ?>"
               class="nav-tab <?php echo $current_tab === 'cloud' ? 'nav-tab-active' : ''; ?> <?php echo $current_tab === 'cloud' ? ($is_enabled ? 'is-enabled' : 'is-disabled') : ''; ?>"
               data-tab="cloud">
                <span class="dashicons dashicons-superhero"></span>
                <?php esc_html_e('Managed', 'bandwidth-saver'); ?>
            </a>
        </nav>
        <?php
    }

    /**
     * Get pricing from Managed API with caching
     *
     * @return array Pricing information with fallback
     */
    private function get_pricing() {
        // Check cache (5 minute transient)
        $cached = get_transient('imgpro_cdn_pricing');
        if ($cached !== false) {
            return $cached;
        }

        // Fetch from API
        $response = wp_remote_get('https://cloud.wp.img.pro/api/pricing', [
            'timeout' => 5,
        ]);

        // Fallback pricing
        $fallback = [
            'amount' => 29,
            'currency' => 'USD',
            'interval' => 'month',
            'formatted' => [
                'amount' => '$29',
                'period' => '/month',
                'full' => '$29/month',
            ],
        ];

        // Parse and validate response
        if (is_wp_error($response)) {
            // Cache fallback for 1 minute on error
            set_transient('imgpro_cdn_pricing', $fallback, MINUTE_IN_SECONDS);
            return $fallback;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Validate pricing response structure
        if (!is_array($body) || !isset($body['amount']) || !isset($body['currency'])) {
            set_transient('imgpro_cdn_pricing', $fallback, MINUTE_IN_SECONDS);
            return $fallback;
        }

        // Cache for 5 minutes
        set_transient('imgpro_cdn_pricing', $body, 5 * MINUTE_IN_SECONDS);

        return $body;
    }

    /**
     * Render Managed tab
     */
    private function render_cloud_tab($settings) {
        $is_configured = !empty($settings['cloud_api_key']);
        $has_active_subscription = ($settings['cloud_tier'] === 'active');
        $pricing = $this->get_pricing();
        ?>
        <form method="post" action="options.php" class="imgpro-cdn-cloud-form">
            <?php settings_fields('imgpro_cdn_settings_group'); ?>
            <input type="hidden" name="imgpro_cdn_settings[setup_mode]" value="<?php echo esc_attr($settings['setup_mode'] ?: 'cloud'); ?>">

            <?php if (!$is_configured || !$has_active_subscription): ?>
                <?php // No Subscription - Conversion-focused CTA ?>
                <div class="imgpro-cdn-subscribe-hero">
                    <div class="imgpro-cdn-subscribe-content">
                        <h2><?php esc_html_e('Skip the Setup. We Handle Everything.', 'bandwidth-saver'); ?></h2>
                        <p class="imgpro-cdn-subscribe-description">
                            <?php esc_html_e('Same Cloudflare CDN, zero configuration. Activate in 30 seconds.', 'bandwidth-saver'); ?>
                        </p>

                        <div class="imgpro-cdn-subscribe-features imgpro-cdn-features-contrast">
                            <div class="imgpro-cdn-feature">
                                <span class="dashicons dashicons-no-alt imgpro-cdn-feature-removed"></span>
                                <div>
                                    <strong><?php esc_html_e('No Cloudflare Account', 'bandwidth-saver'); ?></strong>
                                    <p><?php esc_html_e('We provide the infrastructure', 'bandwidth-saver'); ?></p>
                                </div>
                            </div>
                            <div class="imgpro-cdn-feature">
                                <span class="dashicons dashicons-no-alt imgpro-cdn-feature-removed"></span>
                                <div>
                                    <strong><?php esc_html_e('No Worker Deployment', 'bandwidth-saver'); ?></strong>
                                    <p><?php esc_html_e('Already configured and running', 'bandwidth-saver'); ?></p>
                                </div>
                            </div>
                            <div class="imgpro-cdn-feature">
                                <span class="dashicons dashicons-no-alt imgpro-cdn-feature-removed"></span>
                                <div>
                                    <strong><?php esc_html_e('No DNS Configuration', 'bandwidth-saver'); ?></strong>
                                    <p><?php esc_html_e('Works instantly with any domain', 'bandwidth-saver'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="imgpro-cdn-subscribe-cta">
                            <button type="button" class="button button-primary button-hero" id="imgpro-cdn-subscribe">
                                <?php
                                printf(
                                    /* translators: %s: Price per month (e.g., $2.99/month) */
                                    esc_html__('Activate Now — %s', 'bandwidth-saver'),
                                    esc_html($pricing['formatted']['full'] ?? '$2.99/month')
                                );
                                ?>
                            </button>
                            <p class="imgpro-cdn-subscribe-trust">
                                <span class="dashicons dashicons-lock"></span>
                                <?php esc_html_e('Secure checkout via Stripe • Cancel anytime', 'bandwidth-saver'); ?>
                            </p>
                        </div>

                        <p class="imgpro-cdn-subscribe-recovery">
                            <?php esc_html_e('Already subscribed?', 'bandwidth-saver'); ?>
                            <button type="button" class="button-link" id="imgpro-cdn-recover-account">
                                <?php esc_html_e('Recover account', 'bandwidth-saver'); ?>
                            </button>
                        </p>
                    </div>
                </div>

            <?php else: ?>
                <?php // Active Subscription - Show Advanced Settings ?>
                <?php // Advanced Settings (Collapsible) ?>
                <div class="imgpro-cdn-advanced-section">
                    <button type="button" class="imgpro-cdn-advanced-toggle" aria-expanded="false" aria-controls="imgpro-cdn-advanced-content">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                        <span><?php esc_html_e('Advanced Settings', 'bandwidth-saver'); ?></span>
                    </button>

                    <div class="imgpro-cdn-advanced-content" id="imgpro-cdn-advanced-content" hidden>
                        <?php $this->render_advanced_options($settings); ?>
                    </div>
                </div>

                <div class="imgpro-cdn-form-actions">
                    <?php submit_button(__('Save Settings', 'bandwidth-saver'), 'primary large', 'submit', false); ?>
                </div>
            <?php endif; ?>
        </form>
        <?php
    }

    /**
     * Render Cloudflare Account tab (Self-Host)
     */
    private function render_cloudflare_tab($settings) {
        $is_configured = !empty($settings['cdn_url']) && !empty($settings['worker_url']);
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('imgpro_cdn_settings_group'); ?>
            <input type="hidden" name="imgpro_cdn_settings[setup_mode]" value="<?php echo esc_attr($settings['setup_mode'] ?: 'cloudflare'); ?>">

            <?php if (!$is_configured): ?>
                <?php // Show setup requirements before configuration ?>
                <div class="imgpro-cdn-setup-intro">
                    <h2>
                        <?php esc_html_e('Host on Your Own Cloudflare Account', 'bandwidth-saver'); ?>
                        <span class="imgpro-cdn-badge-free"><?php esc_html_e('Free', 'bandwidth-saver'); ?></span>
                    </h2>
                    <p class="imgpro-cdn-setup-subtitle">
                        <?php esc_html_e('You control the infrastructure and pay Cloudflare directly (usually $0/month).', 'bandwidth-saver'); ?>
                    </p>

                    <div class="imgpro-cdn-setup-steps">
                        <h3><?php esc_html_e('Setup Requirements', 'bandwidth-saver'); ?></h3>
                        <ol class="imgpro-cdn-steps-list">
                            <li>
                                <strong><?php esc_html_e('Cloudflare Account', 'bandwidth-saver'); ?></strong>
                                <span><?php esc_html_e('Create a free account at cloudflare.com', 'bandwidth-saver'); ?></span>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Enable R2 Storage', 'bandwidth-saver'); ?></strong>
                                <span><?php esc_html_e('Activate R2 in your Cloudflare dashboard', 'bandwidth-saver'); ?></span>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Deploy the Worker', 'bandwidth-saver'); ?></strong>
                                <span><?php esc_html_e('Clone and deploy our open-source worker code', 'bandwidth-saver'); ?></span>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Configure Custom Domains', 'bandwidth-saver'); ?></strong>
                                <span><?php esc_html_e('Set up DNS records for your CDN and worker', 'bandwidth-saver'); ?></span>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Enter Domains Below', 'bandwidth-saver'); ?></strong>
                                <span><?php esc_html_e('Add your configured domains to activate', 'bandwidth-saver'); ?></span>
                            </li>
                        </ol>

                        <div class="imgpro-cdn-setup-actions">
                            <a href="https://github.com/img-pro/bandwidth-saver-worker#setup" target="_blank" class="button button-primary">
                                <?php esc_html_e('View Full Setup Guide', 'bandwidth-saver'); ?>
                                <span class="dashicons dashicons-external"></span>
                            </a>
                            <span class="imgpro-cdn-setup-time">
                                <span class="dashicons dashicons-clock"></span>
                                <?php esc_html_e('~20 minutes if familiar with Cloudflare', 'bandwidth-saver'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="imgpro-cdn-setup-alternative">
                        <p>
                            <strong><?php esc_html_e('Want to skip the setup?', 'bandwidth-saver'); ?></strong>
                            <?php esc_html_e('The Managed option handles all infrastructure for you.', 'bandwidth-saver'); ?>
                            <a href="<?php echo esc_url(add_query_arg(['tab' => 'cloud', 'switch_mode' => 'cloud', '_wpnonce' => wp_create_nonce('imgpro_switch_mode')], admin_url('options-general.php?page=imgpro-cdn-settings'))); ?>">
                                <?php esc_html_e('Try Managed instead →', 'bandwidth-saver'); ?>
                            </a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php // Configuration Card ?>
            <div class="imgpro-cdn-config-card">
                <h2><?php echo $is_configured ? esc_html__('Your Cloudflare Domains', 'bandwidth-saver') : esc_html__('Enter Your Domains', 'bandwidth-saver'); ?></h2>

                <div class="imgpro-cdn-config-fields">
                    <div class="imgpro-cdn-field">
                        <label for="cdn_url"><?php esc_html_e('CDN Domain', 'bandwidth-saver'); ?></label>
                        <input
                            type="text"
                            id="cdn_url"
                            name="imgpro_cdn_settings[cdn_url]"
                            value="<?php echo esc_attr($settings['cdn_url']); ?>"
                            placeholder="cdn.yourdomain.com"
                            aria-describedby="cdn-url-description"
                        >
                        <p class="imgpro-cdn-field-description" id="cdn-url-description">
                            <?php esc_html_e('Your R2 bucket\'s public domain', 'bandwidth-saver'); ?>
                        </p>
                    </div>

                    <div class="imgpro-cdn-field">
                        <label for="worker_url"><?php esc_html_e('Worker Domain', 'bandwidth-saver'); ?></label>
                        <input
                            type="text"
                            id="worker_url"
                            name="imgpro_cdn_settings[worker_url]"
                            value="<?php echo esc_attr($settings['worker_url']); ?>"
                            placeholder="worker.yourdomain.com"
                            aria-describedby="worker-url-description"
                        >
                        <p class="imgpro-cdn-field-description" id="worker-url-description">
                            <?php esc_html_e('Your worker\'s custom domain', 'bandwidth-saver'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <?php // Advanced Settings (Collapsible) ?>
            <?php if ($is_configured): ?>
                <div class="imgpro-cdn-advanced-section">
                    <button type="button" class="imgpro-cdn-advanced-toggle" aria-expanded="false" aria-controls="imgpro-cdn-advanced-content">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                        <span><?php esc_html_e('Advanced Settings', 'bandwidth-saver'); ?></span>
                    </button>

                    <div class="imgpro-cdn-advanced-content" id="imgpro-cdn-advanced-content" hidden>
                        <?php $this->render_advanced_options($settings); ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="imgpro-cdn-form-actions">
                <?php submit_button(__('Save Settings', 'bandwidth-saver'), 'primary large', 'submit', false); ?>
            </div>
        </form>
        <?php
    }

    /**
     * Render advanced options (shared between both tabs)
     */
    private function render_advanced_options($settings) {
        ?>
        <div class="imgpro-cdn-advanced-fields">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="allowed_domains"><?php esc_html_e('Allowed Domains', 'bandwidth-saver'); ?></label>
                    </th>
                    <td>
                        <textarea
                            id="allowed_domains"
                            name="imgpro_cdn_settings[allowed_domains]"
                            rows="3"
                            class="large-text"
                            placeholder="example.com&#10;blog.example.com&#10;shop.example.com"
                            aria-describedby="allowed-domains-description"
                        ><?php
                            if (is_array($settings['allowed_domains'])) {
                                echo esc_textarea(implode("\n", $settings['allowed_domains']));
                            }
                        ?></textarea>
                        <p class="description" id="allowed-domains-description">
                            <?php esc_html_e('Enable Image CDN only on specific domains (one per line). Leave empty to process all images.', 'bandwidth-saver'); ?>
                        </p>
                    </td>
                </tr>

                <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                <tr>
                    <th scope="row">
                        <label for="debug_mode"><?php esc_html_e('Debug Mode', 'bandwidth-saver'); ?></label>
                    </th>
                    <td>
                        <label for="debug_mode">
                            <input
                                type="checkbox"
                                id="debug_mode"
                                name="imgpro_cdn_settings[debug_mode]"
                                value="1"
                                <?php checked($settings['debug_mode'], true); ?>
                                aria-describedby="debug-mode-description"
                            >
                            <?php esc_html_e('Enable debug mode', 'bandwidth-saver'); ?>
                        </label>
                        <p class="description" id="debug-mode-description">
                            <?php esc_html_e('Adds debug data to images (visible in browser console).', 'bandwidth-saver'); ?>
                        </p>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php
    }

    /**
     * Check if a given mode has valid configuration
     *
     * Cloud mode requires an active subscription.
     * Cloudflare mode requires both CDN and Worker URLs to be configured.
     *
     * @param string $mode The mode to check ('cloud' or 'cloudflare')
     * @param array $settings The settings array to check against
     * @return bool True if the mode is properly configured
     */
    private function is_mode_valid($mode, $settings) {
        if ($mode === 'cloud') {
            return ($settings['cloud_tier'] ?? '') === 'active';
        } elseif ($mode === 'cloudflare') {
            return !empty($settings['cdn_url']) && !empty($settings['worker_url']);
        }
        return false;
    }

    /**
     * Handle API error with action hook for logging
     *
     * Fires an action hook that developers can use to log errors.
     * This follows WordPress patterns by using hooks instead of direct logging.
     *
     * @param WP_Error|array $error Error object or error data
     * @param string $context Context for logging (e.g., 'checkout', 'recovery')
     * @return void
     */
    private function handle_api_error($error, $context = '') {
        /**
         * Fires when an API error occurs.
         *
         * @since 1.0.0
         *
         * @param WP_Error|array $error Error object or error data.
         * @param string $context Context for the error (e.g., 'checkout', 'recovery').
         */
        do_action('imgpro_cdn_api_error', $error, $context);
    }

    public function ajax_toggle_enabled() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_toggle_enabled')) {
            wp_send_json_error(['message' => __('Security check failed', 'bandwidth-saver')]);
        }

        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'bandwidth-saver')]);
        }

        // Get enabled value and current tab
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] == '1';
        $current_tab = isset($_POST['current_tab']) ? sanitize_text_field(wp_unslash($_POST['current_tab'])) : '';

        // Get current settings
        $current_settings = $this->settings->get_all();

        // Smart enable: if trying to enable on unconfigured tab, switch to configured mode
        if ($enabled && !empty($current_tab)) {
            $current_mode_valid = $this->is_mode_valid($current_tab, $current_settings);

            if (!$current_mode_valid) {
                // Current tab is not configured, check if another mode is
                $other_mode = ($current_tab === 'cloud') ? 'cloudflare' : 'cloud';
                $other_mode_valid = $this->is_mode_valid($other_mode, $current_settings);

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
                        'message' => __('Image CDN enabled. Switching to configured mode.', 'bandwidth-saver'),
                        'redirect' => $redirect_url
                    ]);
                    return;
                }

                // No configured mode available - shouldn't happen since toggle only shows when something is configured
                wp_send_json_error(['message' => __('Please configure a CDN mode first.', 'bandwidth-saver')]);
                return;
            }
        }

        // Check if value is already set to desired state
        if ($current_settings['enabled'] === $enabled) {
            // Value unchanged - still success since setting is in desired state
            $message = $enabled
                ? __('Image CDN enabled. Your images now load from Cloudflare\'s global network.', 'bandwidth-saver')
                : __('Image CDN disabled. Images now load from your server.', 'bandwidth-saver');

            wp_send_json_success(['message' => $message]);
            return;
        }

        // Update only the enabled field
        $current_settings['enabled'] = $enabled;

        // Clear previously_enabled when user manually toggles
        if ($enabled) {
            $current_settings['previously_enabled'] = false;
        }

        // Save settings - update_option returns false if value unchanged OR on error
        // Since we checked for unchanged value above, false here means actual error
        $result = update_option(ImgPro_CDN_Settings::OPTION_KEY, $current_settings);
        $this->settings->clear_cache(); // Ensure subsequent reads get fresh data

        if ($result !== false) {
            $message = $enabled
                ? __('Image CDN enabled. Your images now load from Cloudflare\'s global network.', 'bandwidth-saver')
                : __('Image CDN disabled. Images now load from your server.', 'bandwidth-saver');

            wp_send_json_success(['message' => $message]);
        } else {
            wp_send_json_error(['message' => __('Failed to update settings. Please try again.', 'bandwidth-saver')]);
        }
    }

    /**
     * Generate cryptographically secure API key
     *
     * @return string API key in format: imgpro_[64 hex chars]
     */
    private function generate_api_key() {
        // Generate 32 random bytes (256 bits)
        $random_bytes = random_bytes(32);
        $hex = bin2hex($random_bytes);
        return 'imgpro_' . $hex;
    }

    /**
     * AJAX handler for Stripe checkout
     */
    public function ajax_checkout() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_checkout')) {
            wp_send_json_error(['message' => __('Security check failed', 'bandwidth-saver')]);
        }

        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'bandwidth-saver')]);
        }

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
            $settings['setup_mode'] = 'cloud';
            update_option(ImgPro_CDN_Settings::OPTION_KEY, $settings);
            $this->settings->clear_cache(); // Ensure subsequent reads get fresh data
        }

        // Call Managed billing API
        $response = wp_remote_post('https://cloud.wp.img.pro/api/checkout', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'email' => $email,
                'site_url' => $site_url,
                'api_key' => $api_key,
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $this->handle_api_error($response, 'checkout');
            wp_send_json_error([
                'message' => __('Failed to connect to billing service. Please try again.', 'bandwidth-saver')
            ]);
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Check for existing subscription - attempt to recover it automatically
        if ($status_code === 409 && isset($body['existing'])) {
            if ($this->recover_account()) {
                wp_send_json_success([
                    'message' => __('Existing subscription found and activated!', 'bandwidth-saver'),
                    'recovered' => true
                ]);
            } else {
                // Recovery failed - maybe subscription is cancelled
                wp_send_json_error([
                    'message' => __('This site has an existing subscription but it could not be activated. Please contact support.', 'bandwidth-saver'),
                    'existing' => true
                ]);
            }
            return;
        }

        if (isset($body['url'])) {
            // Set transient flag to check for payment on next page load (expires in 1 hour)
            set_transient('imgpro_cdn_pending_payment', true, HOUR_IN_SECONDS);

            wp_send_json_success(['checkout_url' => $body['url']]);
        } else {
            $this->handle_api_error(['status' => $status_code, 'body' => $body], 'checkout');
            wp_send_json_error([
                'message' => __('Failed to create checkout session. Please try again.', 'bandwidth-saver')
            ]);
        }
    }

    /**
     * Recover account details from Managed
     */
    private function recover_account() {
        $site_url = get_site_url();

        $response = wp_remote_post('https://cloud.wp.img.pro/api/recover', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(['site_url' => $site_url]),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            $this->handle_api_error($response, 'recovery');
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Validate response structure
        if (!is_array($body)) {
            $this->handle_api_error(['error' => 'Invalid response structure'], 'recovery');
            return false;
        }

        // Validate required fields with proper types
        if (empty($body['api_key']) || !is_string($body['api_key'])) {
            $this->handle_api_error(['error' => 'Missing or invalid api_key'], 'recovery');
            return false;
        }
        if (empty($body['email']) || !is_string($body['email'])) {
            $this->handle_api_error(['error' => 'Missing or invalid email'], 'recovery');
            return false;
        }
        if (empty($body['tier']) || !is_string($body['tier'])) {
            $this->handle_api_error(['error' => 'Missing or invalid tier'], 'recovery');
            return false;
        }

        // Update settings with validated and sanitized data
        $settings = $this->settings->get_all();
        $settings['setup_mode'] = 'cloud';
        $settings['cloud_api_key'] = sanitize_text_field($body['api_key']);
        $settings['cloud_email'] = sanitize_email($body['email']);
        $settings['cloud_tier'] = in_array($body['tier'], ['active', 'cancelled', 'none'], true) ? $body['tier'] : 'none';
        // Only auto-enable if subscription is active (not cancelled or none)
        $settings['enabled'] = ($settings['cloud_tier'] === 'active');

        $result = update_option(ImgPro_CDN_Settings::OPTION_KEY, $settings);
        $this->settings->clear_cache(); // Ensure subsequent reads get fresh data

        return $result;
    }

    /**
     * AJAX handler for account recovery
     */
    public function ajax_recover_account() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_checkout')) {
            wp_send_json_error(['message' => __('Security check failed', 'bandwidth-saver')]);
        }

        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'bandwidth-saver')]);
        }

        // Attempt recovery
        if ($this->recover_account()) {
            wp_send_json_success([
                'message' => __('Account recovered successfully!', 'bandwidth-saver')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('No subscription found for this site. Please subscribe first.', 'bandwidth-saver')
            ]);
        }
    }

    /**
     * AJAX handler for managing subscription (redirects to Stripe portal)
     */
    public function ajax_manage_subscription() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_checkout')) {
            wp_send_json_error(['message' => __('Security check failed', 'bandwidth-saver')]);
        }

        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'bandwidth-saver')]);
        }

        // Get API key from settings
        $settings = $this->settings->get_all();
        $api_key = $settings['cloud_api_key'] ?? '';

        if (empty($api_key)) {
            wp_send_json_error([
                'message' => __('No API key found. Please subscribe first.', 'bandwidth-saver')
            ]);
            return;
        }

        // Call billing API to create customer portal session
        $response = wp_remote_post('https://cloud.wp.img.pro/api/portal', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'api_key' => $api_key,
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $this->handle_api_error($response, 'portal');
            wp_send_json_error([
                'message' => __('Failed to connect to billing service. Please try again.', 'bandwidth-saver')
            ]);
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!empty($data['portal_url'])) {
            wp_send_json_success([
                'portal_url' => $data['portal_url']
            ]);
        } else {
            $this->handle_api_error(['status' => wp_remote_retrieve_response_code($response), 'body' => $data], 'portal');
            wp_send_json_error([
                'message' => $data['error'] ?? __('Failed to create portal session', 'bandwidth-saver')
            ]);
        }
    }
}
