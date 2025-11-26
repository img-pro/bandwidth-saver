<?php
/**
 * ImgPro Admin Interface
 *
 * @package ImgPro_CDN
 * @since   0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin interface class
 *
 * Handles settings page rendering, subscription management,
 * and admin UI. AJAX operations are handled by ImgPro_CDN_Admin_Ajax.
 *
 * @since 0.1.0
 */
class ImgPro_CDN_Admin {

    /**
     * Settings instance
     *
     * @since 0.1.0
     * @var ImgPro_CDN_Settings
     */
    private $settings;

    /**
     * Constructor
     *
     * @since 0.1.0
     * @param ImgPro_CDN_Settings $settings Settings instance.
     */
    public function __construct(ImgPro_CDN_Settings $settings) {
        $this->settings = $settings;
    }

    /**
     * Register admin hooks
     *
     * @since 0.1.0
     * @return void
     */
    public function register_hooks() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'show_notices']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Enqueue admin assets
     *
     * @since 0.1.0
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our settings page
        if ('settings_page_imgpro-cdn-settings' !== $hook) {
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
                    'activeMessage' => sprintf(
                        /* translators: 1: opening span tag, 2: closing span tag, 3: opening span tag, 4: closing span tag */
                        __('%1$sImages are loading from Cloudflare.%2$s %3$sYour server handles less traffic.%4$s', 'bandwidth-saver'),
                        '<span class="imgpro-cdn-nowrap imgpro-cdn-hide-mobile">',
                        '</span>',
                        '<span class="imgpro-cdn-nowrap">',
                        '</span>'
                    ),
                    'disabledMessage' => __('Enable to serve images from Cloudflare instead of your server', 'bandwidth-saver'),
                    // Button states
                    'creatingCheckout' => __('Creating checkout...', 'bandwidth-saver'),
                    'recovering' => __('Recovering...', 'bandwidth-saver'),
                    'openingPortal' => __('Opening portal...', 'bandwidth-saver'),
                    // Error messages
                    'checkoutError' => __('Could not create checkout. Please try again.', 'bandwidth-saver'),
                    'recoverError' => __('Could not recover account. Please try again.', 'bandwidth-saver'),
                    'portalError' => __('Could not open subscription portal. Please try again.', 'bandwidth-saver'),
                    'genericError' => __('Something went wrong. Please try again.', 'bandwidth-saver'),
                    'settingsError' => __('Could not save settings. Please try again.', 'bandwidth-saver'),
                    // Confirm dialogs
                    'recoverConfirm' => __('This will look up your existing subscription. Continue?', 'bandwidth-saver'),
                    // Success messages
                    'subscriptionActivated' => __('Subscription activated. Images now load from Cloudflare.', 'bandwidth-saver'),
                    'checkoutCancelled' => __('Checkout cancelled. You can try again anytime.', 'bandwidth-saver'),
                    // Toggle UI text
                    'cdnActiveHeading' => __('Image CDN is Active', 'bandwidth-saver'),
                    'cdnInactiveHeading' => __('Image CDN is Inactive', 'bandwidth-saver'),
                    'cdnActiveDesc' => __('Images are being delivered from Cloudflare edge locations worldwide.', 'bandwidth-saver'),
                    'cdnInactiveDesc' => __('Enable to serve images from Cloudflare instead of your server.', 'bandwidth-saver'),
                ]
            ]);
        }
    }

    /**
     * Add admin menu page
     *
     * @since 0.1.0
     * @return void
     */
    public function add_menu_page() {
        add_options_page(
            esc_html__('Bandwidth Saver', 'bandwidth-saver'),       // Page title
            esc_html__('Bandwidth Saver', 'bandwidth-saver'),       // Menu title
            'manage_options',                         // Capability required
            'imgpro-cdn-settings',                        // Menu slug
            [$this, 'render_settings_page']          // Callback function
        );
    }

    /**
     * Register settings
     *
     * @since 0.1.0
     * @return void
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
     * @since 0.1.0
     * @param array $input Posted form data.
     * @return array Complete settings array to be saved.
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
            if (!ImgPro_CDN_Settings::is_mode_valid($merged['setup_mode'] ?? '', $merged)) {
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
     *
     * @since 0.1.0
     * @return void
     */
    public function show_notices() {
        // Don't output anything on our settings page
        // All notices are rendered inline via render_inline_notices()
    }

    /**
     * Render inline notices within the settings page
     *
     * Called from render_settings_page() to show notices in the correct position.
     *
     * @since 0.1.0
     * @return void
     */
    private function render_inline_notices() {
        // Handle payment success - attempt recovery (single attempt, no blocking)
        $payment_status = filter_input(INPUT_GET, 'payment', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ('success' === $payment_status) {
            // Single recovery attempt without blocking
            if (ImgPro_CDN_Settings::recover_account($this->settings)) {
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
                        <strong><?php esc_html_e('Payment received. Setting up your account...', 'bandwidth-saver'); ?></strong>
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
                    <strong><?php esc_html_e('Subscription activated. Images now load from Cloudflare.', 'bandwidth-saver'); ?></strong>
                </p>
            </div>
            <?php
        }

        // Show settings saved message
        if (filter_input(INPUT_GET, 'settings-updated', FILTER_VALIDATE_BOOLEAN)) {
            ?>
            <div class="notice notice-success is-dismissible imgpro-cdn-inline-notice">
                <p>
                    <strong><?php esc_html_e('Settings saved.', 'bandwidth-saver'); ?></strong>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Render settings page
     *
     * @since 0.1.0
     * @return void
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if there's a pending payment and attempt recovery
        if (get_transient('imgpro_cdn_pending_payment')) {
            // Attempt recovery (webhook might have completed)
            if (ImgPro_CDN_Settings::recover_account($this->settings)) {
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
            if (in_array($new_mode, [ImgPro_CDN_Settings::MODE_CLOUD, ImgPro_CDN_Settings::MODE_CLOUDFLARE], true)) {
                $old_mode = $settings['setup_mode'] ?? '';
                $was_enabled = $settings['enabled'] ?? false;
                $new_mode_is_valid = ImgPro_CDN_Settings::is_mode_valid($new_mode, $settings);

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

        // If no tab specified, use setup_mode from settings or default to cloudflare (Self-Host)
        // Self-Host is the default to show the free option first, building trust before showing paid option
        if (empty($current_tab)) {
            $current_tab = !empty($settings['setup_mode']) ? $settings['setup_mode'] : ImgPro_CDN_Settings::MODE_CLOUDFLARE;
        }

        ?>
        <div class="wrap imgpro-cdn-admin">
            <div class="imgpro-cdn-header">
                <div>
                    <h1><?php esc_html_e('Bandwidth Saver', 'bandwidth-saver'); ?></h1>
                    <p class="imgpro-cdn-tagline"><?php esc_html_e('Faster images without touching DNS', 'bandwidth-saver'); ?></p>
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
            if (ImgPro_CDN_Settings::MODE_CLOUD === $current_tab) {
                $this->render_account_status($settings);
            }
            ?>

            <div class="imgpro-cdn-tab-content">
                <?php
                if (ImgPro_CDN_Settings::MODE_CLOUD === $current_tab) {
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
                            __('Bandwidth Saver by %1$s, powered by %2$s', 'bandwidth-saver'),
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
     *
     * @since 0.1.0
     * @param array $settings Plugin settings.
     * @return void
     */
    private function render_main_toggle($settings) {
        // Check if EITHER backend is configured (not just active mode)
        $has_cloud = (ImgPro_CDN_Settings::TIER_ACTIVE === $settings['cloud_tier']);
        $has_cloudflare = !empty($settings['cdn_url']) && !empty($settings['worker_url']);

        if (!$has_cloud && !$has_cloudflare) {
            return; // Don't show toggle if nothing is configured
        }

        // Use current setup_mode or infer from what's configured
        $setup_mode = $settings['setup_mode'] ?? '';
        if (empty($setup_mode)) {
            $setup_mode = $has_cloud ? ImgPro_CDN_Settings::MODE_CLOUD : ImgPro_CDN_Settings::MODE_CLOUDFLARE;
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
                                ? esc_html__('Images are being delivered from Cloudflare edge locations worldwide.', 'bandwidth-saver')
                                : esc_html__('Enable to serve images from Cloudflare instead of your server.', 'bandwidth-saver'); ?>
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
     *
     * @since 0.1.0
     * @param array $settings Plugin settings.
     * @return void
     */
    private function render_account_status($settings) {
        // Only show if user has Managed subscription
        $has_subscription = (ImgPro_CDN_Settings::TIER_ACTIVE === $settings['cloud_tier']);
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
     *
     * @since 0.1.0
     * @param string $current_tab Current active tab.
     * @param array  $settings    Plugin settings.
     * @return void
     */
    private function render_tabs($current_tab, $settings) {
        $base_url = admin_url('options-general.php?page=imgpro-cdn-settings');

        // Check if both modes are configured
        $has_cloud = (ImgPro_CDN_Settings::TIER_ACTIVE === $settings['cloud_tier']);
        $has_cloudflare = !empty($settings['cdn_url']) && !empty($settings['worker_url']);

        // Always add switch_mode parameter with nonce when clicking tabs
        $cloud_url = add_query_arg([
            'tab' => ImgPro_CDN_Settings::MODE_CLOUD,
            'switch_mode' => ImgPro_CDN_Settings::MODE_CLOUD,
            '_wpnonce' => wp_create_nonce('imgpro_switch_mode')
        ], $base_url);
        $cloudflare_url = add_query_arg([
            'tab' => ImgPro_CDN_Settings::MODE_CLOUDFLARE,
            'switch_mode' => ImgPro_CDN_Settings::MODE_CLOUDFLARE,
            '_wpnonce' => wp_create_nonce('imgpro_switch_mode')
        ], $base_url);

        // Get enabled state for color coding the active tab
        $is_enabled = $settings['enabled'] ?? false;

        ?>
        <nav class="nav-tab-wrapper imgpro-cdn-nav-tabs">
            <a href="<?php echo esc_url($cloudflare_url); ?>"
               class="nav-tab <?php echo ImgPro_CDN_Settings::MODE_CLOUDFLARE === $current_tab ? 'nav-tab-active' : ''; ?> <?php echo ImgPro_CDN_Settings::MODE_CLOUDFLARE === $current_tab ? ($is_enabled ? 'is-enabled' : 'is-disabled') : ''; ?>"
               data-tab="<?php echo esc_attr(ImgPro_CDN_Settings::MODE_CLOUDFLARE); ?>">
                <span class="dashicons dashicons-cloud"></span>
                <?php esc_html_e('Self-Host', 'bandwidth-saver'); ?>
            </a>
            <a href="<?php echo esc_url($cloud_url); ?>"
               class="nav-tab <?php echo ImgPro_CDN_Settings::MODE_CLOUD === $current_tab ? 'nav-tab-active' : ''; ?> <?php echo ImgPro_CDN_Settings::MODE_CLOUD === $current_tab ? ($is_enabled ? 'is-enabled' : 'is-disabled') : ''; ?>"
               data-tab="<?php echo esc_attr(ImgPro_CDN_Settings::MODE_CLOUD); ?>">
                <span class="dashicons dashicons-superhero"></span>
                <?php esc_html_e('Managed', 'bandwidth-saver'); ?>
            </a>
        </nav>
        <?php
    }

    /**
     * Get pricing from Managed API with caching
     *
     * @since 0.1.0
     * @return array Pricing information with fallback.
     */
    private function get_pricing() {
        // Check cache (1 hour transient - pricing rarely changes)
        $cached = get_transient('imgpro_cdn_pricing');
        if (false !== $cached) {
            return $cached;
        }

        // Fetch from API
        $response = wp_remote_get(ImgPro_CDN_Settings::get_api_base_url() . '/api/pricing', [
            'timeout' => 5,
        ]);

        // Fallback pricing
        $fallback = [
            'amount' => 999,
            'currency' => 'USD',
            'interval' => 'month',
            'formatted' => [
                'amount' => '$9.99',
                'period' => '/month',
                'full' => '$9.99/month',
            ],
        ];

        // Parse and validate response
        if (is_wp_error($response)) {
            // Cache fallback for 5 minutes on error
            set_transient('imgpro_cdn_pricing', $fallback, 5 * MINUTE_IN_SECONDS);
            return $fallback;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Validate pricing response structure
        if (!is_array($body) || !isset($body['amount']) || !isset($body['currency'])) {
            set_transient('imgpro_cdn_pricing', $fallback, 5 * MINUTE_IN_SECONDS);
            return $fallback;
        }

        // Cache for 1 hour - pricing rarely changes
        set_transient('imgpro_cdn_pricing', $body, HOUR_IN_SECONDS);

        return $body;
    }

    /**
     * Render Managed tab
     *
     * @since 0.1.0
     * @param array $settings Plugin settings.
     * @return void
     */
    private function render_cloud_tab($settings) {
        $is_configured = !empty($settings['cloud_api_key']);
        $has_active_subscription = (ImgPro_CDN_Settings::TIER_ACTIVE === $settings['cloud_tier']);
        $pricing = $this->get_pricing();
        ?>
        <form method="post" action="options.php" class="imgpro-cdn-cloud-form">
            <?php settings_fields('imgpro_cdn_settings_group'); ?>
            <input type="hidden" name="imgpro_cdn_settings[setup_mode]" value="<?php echo esc_attr($settings['setup_mode'] ?: ImgPro_CDN_Settings::MODE_CLOUD); ?>">

            <?php if (!$is_configured || !$has_active_subscription): ?>
                <?php // No Subscription - Conversion-focused CTA ?>
                <div class="imgpro-cdn-subscribe-hero">
                    <div class="imgpro-cdn-subscribe-content">
                        <h2><?php esc_html_e('Skip the Setup. We Handle Everything.', 'bandwidth-saver'); ?></h2>
                        <p class="imgpro-cdn-subscribe-description">
                            <?php esc_html_e('Images load from Cloudflare with zero configuration. Takes less than a minute.', 'bandwidth-saver'); ?>
                        </p>

                        <div class="imgpro-cdn-subscribe-features imgpro-cdn-features-contrast">
                            <div class="imgpro-cdn-feature">
                                <span class="dashicons dashicons-no-alt imgpro-cdn-feature-removed"></span>
                                <div>
                                    <strong><?php esc_html_e('No Cloudflare Account', 'bandwidth-saver'); ?></strong>
                                    <p><?php esc_html_e('We handle the infrastructure', 'bandwidth-saver'); ?></p>
                                </div>
                            </div>
                            <div class="imgpro-cdn-feature">
                                <span class="dashicons dashicons-no-alt imgpro-cdn-feature-removed"></span>
                                <div>
                                    <strong><?php esc_html_e('No DNS Changes', 'bandwidth-saver'); ?></strong>
                                    <p><?php esc_html_e('Works with any hosting setup', 'bandwidth-saver'); ?></p>
                                </div>
                            </div>
                            <div class="imgpro-cdn-feature">
                                <span class="dashicons dashicons-no-alt imgpro-cdn-feature-removed"></span>
                                <div>
                                    <strong><?php esc_html_e('No Configuration', 'bandwidth-saver'); ?></strong>
                                    <p><?php esc_html_e('Just activate and go', 'bandwidth-saver'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="imgpro-cdn-subscribe-cta">
                            <button type="button" class="button button-primary button-hero" id="imgpro-cdn-subscribe">
                                <?php
                                printf(
                                    /* translators: %s: Price per month (e.g., $9.99/month) */
                                    esc_html__('Activate Now — %s', 'bandwidth-saver'),
                                    esc_html($pricing['formatted']['full'] ?? '$9.99/month')
                                );
                                ?>
                            </button>
                            <p class="imgpro-cdn-subscribe-includes">
                                <?php esc_html_e('Includes 500 GB storage and 5 TB monthly bandwidth', 'bandwidth-saver'); ?>
                            </p>
                            <p class="imgpro-cdn-subscribe-trust">
                                <span class="dashicons dashicons-lock"></span>
                                <?php esc_html_e('Secure checkout via Stripe. Cancel anytime.', 'bandwidth-saver'); ?>
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
     *
     * @since 0.1.0
     * @param array $settings Plugin settings.
     * @return void
     */
    private function render_cloudflare_tab($settings) {
        $is_configured = !empty($settings['cdn_url']) && !empty($settings['worker_url']);
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('imgpro_cdn_settings_group'); ?>
            <input type="hidden" name="imgpro_cdn_settings[setup_mode]" value="<?php echo esc_attr($settings['setup_mode'] ?: ImgPro_CDN_Settings::MODE_CLOUDFLARE); ?>">

            <?php if (!$is_configured): ?>
                <?php // Show setup requirements before configuration ?>
                <div class="imgpro-cdn-setup-intro">
                    <h2>
                        <?php esc_html_e('Use Your Own Cloudflare Account', 'bandwidth-saver'); ?>
                        <span class="imgpro-cdn-badge-free"><?php esc_html_e('Free', 'bandwidth-saver'); ?></span>
                    </h2>
                    <p class="imgpro-cdn-setup-subtitle">
                        <?php esc_html_e('For technical users who prefer running Cloudflare on their own account. You pay Cloudflare directly (usually $0/month on the free tier).', 'bandwidth-saver'); ?>
                    </p>

                    <div class="imgpro-cdn-setup-steps">
                        <h3><?php esc_html_e('What You Will Need', 'bandwidth-saver'); ?></h3>
                        <ol class="imgpro-cdn-steps-list">
                            <li>
                                <strong><?php esc_html_e('Cloudflare Account', 'bandwidth-saver'); ?></strong>
                                <span><?php esc_html_e('Free account at cloudflare.com', 'bandwidth-saver'); ?></span>
                            </li>
                            <li>
                                <strong><?php esc_html_e('R2 Storage Bucket', 'bandwidth-saver'); ?></strong>
                                <span><?php esc_html_e('Enable R2 in your Cloudflare dashboard', 'bandwidth-saver'); ?></span>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Deploy the Worker', 'bandwidth-saver'); ?></strong>
                                <span><?php esc_html_e('Our open-source Worker code on GitHub', 'bandwidth-saver'); ?></span>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Custom Domains', 'bandwidth-saver'); ?></strong>
                                <span><?php esc_html_e('Point subdomains to your R2 bucket and Worker', 'bandwidth-saver'); ?></span>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Enter Domains Below', 'bandwidth-saver'); ?></strong>
                                <span><?php esc_html_e('Add your domains here to activate', 'bandwidth-saver'); ?></span>
                            </li>
                        </ol>

                        <div class="imgpro-cdn-setup-actions">
                            <a href="https://github.com/img-pro/bandwidth-saver-worker#setup" target="_blank" class="button button-primary">
                                <?php esc_html_e('View Setup Guide', 'bandwidth-saver'); ?>
                                <span class="dashicons dashicons-external"></span>
                            </a>
                            <span class="imgpro-cdn-setup-time">
                                <span class="dashicons dashicons-clock"></span>
                                <?php esc_html_e('About 20 minutes if familiar with Cloudflare', 'bandwidth-saver'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="imgpro-cdn-setup-alternative">
                        <p>
                            <strong><?php esc_html_e('Prefer a simpler setup?', 'bandwidth-saver'); ?></strong>
                            <?php esc_html_e('The Managed option works in under a minute with no Cloudflare account needed.', 'bandwidth-saver'); ?>
                            <a href="<?php echo esc_url(add_query_arg(['tab' => ImgPro_CDN_Settings::MODE_CLOUD, 'switch_mode' => ImgPro_CDN_Settings::MODE_CLOUD, '_wpnonce' => wp_create_nonce('imgpro_switch_mode')], admin_url('options-general.php?page=imgpro-cdn-settings'))); ?>">
                                <?php esc_html_e('Try Managed instead', 'bandwidth-saver'); ?>
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
                            <?php esc_html_e('The custom domain pointing to your R2 bucket', 'bandwidth-saver'); ?>
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
                            <?php esc_html_e('The custom domain pointing to your Cloudflare Worker', 'bandwidth-saver'); ?>
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
     *
     * @since 0.1.0
     * @param array $settings Plugin settings.
     * @return void
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
                            <?php esc_html_e('Only rewrite image URLs from these domains (one per line). Leave empty to rewrite all images.', 'bandwidth-saver'); ?>
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
                            <?php esc_html_e('Adds debug information to image elements (visible in browser developer tools).', 'bandwidth-saver'); ?>
                        </p>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php
    }
}
