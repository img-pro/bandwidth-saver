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
     * Onboarding instance
     *
     * @since 0.2.0
     * @var ImgPro_CDN_Onboarding
     */
    private $onboarding;

    /**
     * API client instance
     *
     * @since 0.2.0
     * @var ImgPro_CDN_API
     */
    private $api;

    /**
     * Plan selector instance
     *
     * @since 0.2.0
     * @var ImgPro_CDN_Plan_Selector
     */
    private $plan_selector;

    /**
     * Constructor
     *
     * @since 0.1.0
     * @param ImgPro_CDN_Settings $settings Settings instance.
     */
    public function __construct(ImgPro_CDN_Settings $settings) {
        $this->settings = $settings;
        $this->onboarding = new ImgPro_CDN_Onboarding($settings);
        $this->api = ImgPro_CDN_API::instance();
        $this->plan_selector = new ImgPro_CDN_Plan_Selector($settings);
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
        add_action('admin_init', [$this, 'handle_skip_onboarding']);
        add_action('admin_init', [$this, 'handle_payment_return']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Handle skip onboarding request
     *
     * @since 0.2.0
     * @return void
     */
    public function handle_skip_onboarding() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET['skip_onboarding']) || !isset($_GET['_wpnonce'])) {
            return;
        }

        // Verify nonce
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
        if (!wp_verify_nonce($nonce, 'imgpro_skip_onboarding')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        // Mark onboarding as completed (skipped)
        $this->settings->update([
            'onboarding_completed' => true,
            'setup_mode' => ImgPro_CDN_Settings::MODE_CLOUDFLARE,
        ]);

        // Redirect to clean URL
        wp_safe_redirect(admin_url('options-general.php?page=imgpro-cdn-settings&tab=cloudflare'));
        exit;
    }

    /**
     * Handle payment return from Stripe checkout
     *
     * @since 0.1.6
     * @return void
     */
    public function handle_payment_return() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET['page']) || 'imgpro-cdn-settings' !== $_GET['page']) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $payment_status = isset($_GET['payment']) ? sanitize_text_field(wp_unslash($_GET['payment'])) : '';
        if ('success' !== $payment_status) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        // Try to recover/sync account from cloud
        $site_url = get_site_url();
        $site = $this->api->find_site($site_url);

        if (!is_wp_error($site)) {
            // Save site data to settings
            $this->save_site_to_settings($site);

            // Enable if subscription is valid
            $tier_id = $this->api->get_tier_id($site);
            if (in_array($tier_id, [ImgPro_CDN_Settings::TIER_FREE, ImgPro_CDN_Settings::TIER_LITE, ImgPro_CDN_Settings::TIER_PRO, ImgPro_CDN_Settings::TIER_BUSINESS, ImgPro_CDN_Settings::TIER_ACTIVE], true)) {
                $this->settings->update([
                    'enabled' => true,
                    'onboarding_completed' => true,
                ]);
            }

            delete_transient('imgpro_cdn_pending_payment');
            wp_safe_redirect(admin_url('options-general.php?page=imgpro-cdn-settings&tab=cloud&activated=1'));
            exit;
        }

        // If recovery fails, set a transient to show a notice and try again later
        set_transient('imgpro_cdn_payment_pending_recovery', true, 60);
    }

    /**
     * Save site data from API response to local settings
     *
     * @since 0.2.0
     * @param array $site Site data from API.
     * @return void
     */
    private function save_site_to_settings($site) {
        $tier_id = $this->api->get_tier_id($site);
        $usage = $this->api->get_usage($site);
        $domain = $this->api->get_custom_domain($site);

        $update_data = [
            'cloud_api_key' => $site['api_key'] ?? '',
            'cloud_email' => $site['email'] ?? '',
            'cloud_tier' => $tier_id,
            'setup_mode' => ImgPro_CDN_Settings::MODE_CLOUD,
            'storage_used' => $usage['storage_used'],
            'bandwidth_used' => $usage['bandwidth_used'],
            'images_cached' => $usage['images_cached'],
            'stats_updated_at' => time(),
        ];

        if ($usage['storage_limit'] > 0) {
            $update_data['storage_limit'] = $usage['storage_limit'];
        }
        if ($usage['bandwidth_limit'] > 0) {
            $update_data['bandwidth_limit'] = $usage['bandwidth_limit'];
        }

        if ($domain) {
            $update_data['custom_domain'] = $domain['domain'];
            $update_data['custom_domain_status'] = $domain['status'];
        }

        $this->settings->update($update_data);
    }

    /**
     * Sync site data from cloud API
     *
     * Uses cached data if available and fresh, otherwise fetches from API.
     * Updates local settings with any changes from the cloud.
     *
     * @since 0.2.0
     * @return void
     */
    private function sync_site_data() {
        $settings = $this->settings->get_all();
        $api_key = $settings['cloud_api_key'] ?? '';

        if (empty($api_key)) {
            return; // No account to sync
        }

        // API client handles caching internally
        $site = $this->api->get_site($api_key);

        if (is_wp_error($site)) {
            return; // Silently fail - cached data will be used
        }

        // Update local settings if data has changed
        $tier_id = $this->api->get_tier_id($site);
        $usage = $this->api->get_usage($site);

        $settings_changed = false;
        $update_data = [];

        // Update tier if changed
        if ($tier_id !== ($settings['cloud_tier'] ?? '')) {
            $update_data['cloud_tier'] = $tier_id;
            $settings_changed = true;

            // Disable if subscription is inactive
            if (ImgPro_CDN_Settings::is_subscription_inactive(['cloud_tier' => $tier_id])) {
                $update_data['enabled'] = false;
            }
        }

        // Update usage stats
        if ($usage['storage_used'] !== ($settings['storage_used'] ?? 0)) {
            $update_data['storage_used'] = $usage['storage_used'];
            $settings_changed = true;
        }
        if ($usage['bandwidth_used'] !== ($settings['bandwidth_used'] ?? 0)) {
            $update_data['bandwidth_used'] = $usage['bandwidth_used'];
            $settings_changed = true;
        }
        if ($usage['images_cached'] !== ($settings['images_cached'] ?? 0)) {
            $update_data['images_cached'] = $usage['images_cached'];
            $settings_changed = true;
        }

        // Update custom domain if present
        $domain = $this->api->get_custom_domain($site);
        if ($domain && $domain['status'] !== ($settings['custom_domain_status'] ?? '')) {
            $update_data['custom_domain'] = $domain['domain'];
            $update_data['custom_domain_status'] = $domain['status'];
            $settings_changed = true;
        }

        if ($settings_changed) {
            $update_data['stats_updated_at'] = time();
            $this->settings->update($update_data);
        }
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

        // Enqueue admin CSS
        $css_file = dirname(__FILE__) . '/../admin/css/imgpro-cdn-admin.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'imgpro-cdn-admin',
                plugins_url('admin/css/imgpro-cdn-admin.css', dirname(__FILE__)),
                [],
                IMGPRO_CDN_VERSION . '.' . filemtime($css_file)
            );
        }

        // Enqueue admin JS
        $js_file = dirname(__FILE__) . '/../admin/js/imgpro-cdn-admin.js';
        if (file_exists($js_file)) {
            wp_enqueue_script(
                'imgpro-cdn-admin',
                plugins_url('admin/js/imgpro-cdn-admin.js', dirname(__FILE__)),
                ['jquery'],
                IMGPRO_CDN_VERSION . '.' . filemtime($js_file),
                true
            );

            $all_settings = $this->settings->get_all();
            $pricing = $this->get_pricing();
            $tiers = $this->api->get_tiers();

            // Index tiers by ID for easy JavaScript lookup
            $tiers_by_id = [];
            foreach ($tiers as $tier) {
                $tiers_by_id[$tier['id']] = $tier;
            }

            // Localize script
            wp_localize_script('imgpro-cdn-admin', 'imgproCdnAdmin', [
                'nonce' => wp_create_nonce('imgpro_cdn_toggle_enabled'),
                'checkoutNonce' => wp_create_nonce('imgpro_cdn_checkout'),
                'customDomainNonce' => wp_create_nonce('imgpro_cdn_custom_domain'),
                'onboardingNonce' => wp_create_nonce('imgpro_cdn_onboarding'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'settingsUrl' => admin_url('options-general.php?page=imgpro-cdn-settings'),
                'tier' => $all_settings['cloud_tier'] ?? ImgPro_CDN_Settings::TIER_NONE,
                'storageLimit' => ImgPro_CDN_Settings::get_storage_limit($all_settings),
                'pricing' => $pricing,
                'tiers' => $tiers_by_id,
                'i18n' => [
                    'activeLabel' => __('CDN Active', 'bandwidth-saver'),
                    'inactiveLabel' => __('CDN Inactive', 'bandwidth-saver'),
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
                    'creatingAccount' => __('Creating account...', 'bandwidth-saver'),
                    'recovering' => __('Recovering...', 'bandwidth-saver'),
                    'openingPortal' => __('Opening portal...', 'bandwidth-saver'),
                    'activating' => __('Activating...', 'bandwidth-saver'),
                    // Error messages
                    'checkoutError' => __('Could not create checkout. Please try again.', 'bandwidth-saver'),
                    'registrationError' => __('Could not create account. Please try again.', 'bandwidth-saver'),
                    'recoverError' => __('Could not recover account. Please try again.', 'bandwidth-saver'),
                    'portalError' => __('Could not open subscription portal. Please try again.', 'bandwidth-saver'),
                    'genericError' => __('Something went wrong. Please try again.', 'bandwidth-saver'),
                    'settingsError' => __('Could not save settings. Please try again.', 'bandwidth-saver'),
                    // Confirm dialogs
                    'recoverConfirm' => __('This will look up your existing subscription. Continue?', 'bandwidth-saver'),
                    // Success messages
                    'subscriptionActivated' => __('Subscription activated. Images now load from Cloudflare.', 'bandwidth-saver'),
                    'accountCreated' => __('Account created! Let\'s activate your CDN.', 'bandwidth-saver'),
                    'checkoutCancelled' => __('Checkout cancelled. You can try again anytime.', 'bandwidth-saver'),
                    // Toggle UI text
                    'cdnActiveHeading' => __('Image CDN is Active', 'bandwidth-saver'),
                    'cdnInactiveHeading' => __('Image CDN is Inactive', 'bandwidth-saver'),
                    'cdnActiveDesc' => __('Images are being delivered from Cloudflare edge locations worldwide.', 'bandwidth-saver'),
                    'cdnInactiveDesc' => __('Enable to serve images from Cloudflare instead of your server.', 'bandwidth-saver'),
                    // Custom domain
                    'addingDomain' => __('Adding domain...', 'bandwidth-saver'),
                    'checkingStatus' => __('Checking...', 'bandwidth-saver'),
                    'removingDomain' => __('Removing...', 'bandwidth-saver'),
                    'domainAdded' => __('Domain added. Configure your DNS to complete setup.', 'bandwidth-saver'),
                    'domainRemoved' => __('Custom domain removed.', 'bandwidth-saver'),
                    'domainActive' => __('Custom domain is active.', 'bandwidth-saver'),
                    'confirmRemoveDomain' => __('Remove this custom domain? Images will be served from the default domain.', 'bandwidth-saver'),
                    'confirmRemoveCdnDomain' => __('Remove this CDN domain? The Image CDN will be disabled.', 'bandwidth-saver'),
                    // Upgrade prompts
                    'upgradeTitle' => __('Need more capacity?', 'bandwidth-saver'),
                    'upgradeSubtitle' => __('Upgrade to Pro for 120 GB storage + 2 TB bandwidth', 'bandwidth-saver'),
                    // Plan selector
                    'select' => __('Select', 'bandwidth-saver'),
                    'selected' => __('Selected', 'bandwidth-saver'),
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
            esc_html__('Bandwidth Saver', 'bandwidth-saver'),
            esc_html__('Bandwidth Saver', 'bandwidth-saver'),
            'manage_options',
            'imgpro-cdn-settings',
            [$this, 'render_settings_page']
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
     * @since 0.1.0
     * @param array $input Posted form data.
     * @return array Complete settings array to be saved.
     */
    public function sanitize_settings($input) {
        $existing = $this->settings->get_all();
        $validated = $this->settings->validate($input);
        $merged = array_merge($existing, $validated);

        // Handle unchecked checkboxes
        if (isset($input['_has_enabled_field'])) {
            if (!isset($input['enabled'])) {
                $merged['enabled'] = false;
            }
            if (!isset($input['debug_mode'])) {
                $merged['debug_mode'] = false;
            }
        }

        // Auto-disable if mode not valid
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
     * Render inline notices
     *
     * @since 0.1.0
     * @return void
     */
    private function render_inline_notices() {
        // Show waiting notice if recovery is pending (webhook hasn't processed yet)
        if (get_transient('imgpro_cdn_payment_pending_recovery')) {
            delete_transient('imgpro_cdn_payment_pending_recovery');
            ?>
            <div class="imgpro-notice imgpro-notice-info">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/><path d="M10 6v4m0 4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <div>
                    <strong><?php esc_html_e('Payment received. Setting up your account...', 'bandwidth-saver'); ?></strong>
                    <p><?php esc_html_e('Refresh this page in a few seconds to complete activation.', 'bandwidth-saver'); ?></p>
                </div>
            </div>
            <?php
        }

        if (filter_input(INPUT_GET, 'activated', FILTER_VALIDATE_BOOLEAN)) {
            ?>
            <div class="imgpro-notice imgpro-notice-success">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/><path d="M6 10l3 3 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <p><strong><?php esc_html_e('Subscription activated. Images now load from Cloudflare.', 'bandwidth-saver'); ?></strong></p>
            </div>
            <?php
        }

        if (filter_input(INPUT_GET, 'settings-updated', FILTER_VALIDATE_BOOLEAN)) {
            ?>
            <div class="imgpro-notice imgpro-notice-success">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/><path d="M6 10l3 3 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <p><strong><?php esc_html_e('Settings saved.', 'bandwidth-saver'); ?></strong></p>
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

        // Sync subscription status from cloud (cached, once per hour)
        $this->sync_site_data();

        $settings = $this->settings->get_all();

        // Check if should show onboarding
        if ($this->onboarding->should_show_onboarding()) {
            $this->render_onboarding_page();
            return;
        }

        // Handle mode switching
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['switch_mode'])) {
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            if (!wp_verify_nonce($nonce, 'imgpro_switch_mode')) {
                wp_die(esc_html__('Security check failed', 'bandwidth-saver'));
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $new_mode = isset($_GET['switch_mode']) ? sanitize_text_field(wp_unslash($_GET['switch_mode'])) : '';
            if (in_array($new_mode, [ImgPro_CDN_Settings::MODE_CLOUD, ImgPro_CDN_Settings::MODE_CLOUDFLARE], true)) {
                $old_mode = $settings['setup_mode'] ?? '';
                $was_enabled = $settings['enabled'] ?? false;
                $new_mode_is_valid = ImgPro_CDN_Settings::is_mode_valid($new_mode, $settings);

                $settings['setup_mode'] = $new_mode;

                if ($new_mode_is_valid) {
                    if (!empty($settings['previously_enabled'])) {
                        $settings['enabled'] = true;
                        $settings['previously_enabled'] = false;
                    }
                } else {
                    if ($was_enabled) {
                        $settings['previously_enabled'] = true;
                    }
                    $settings['enabled'] = false;
                }

                update_option(ImgPro_CDN_Settings::OPTION_KEY, $settings);
                $this->settings->clear_cache();
            }
        }

        // Determine current tab
        $current_tab = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';

        if (empty($current_tab)) {
            $current_tab = !empty($settings['setup_mode']) ? $settings['setup_mode'] : ImgPro_CDN_Settings::MODE_CLOUD;
        }

        ?>
        <div class="wrap imgpro-admin">
            <?php $this->render_header($settings, $current_tab); ?>

            <hr class="wp-header-end">

            <?php $this->render_inline_notices(); ?>

            <?php $this->render_tabs($current_tab, $settings); ?>

            <div class="imgpro-tab-content">
                <?php
                if (ImgPro_CDN_Settings::MODE_CLOUD === $current_tab) {
                    $this->render_cloud_tab($settings);
                } else {
                    $this->render_cloudflare_tab($settings);
                }
                ?>
            </div>

            <?php $this->render_footer(); ?>

            <?php // Plan selector modal (for upgrade CTAs) ?>
            <?php $this->plan_selector->render_modal_wrapper(); ?>
        </div>
        <?php
    }

    /**
     * Render onboarding page wrapper
     *
     * @since 0.2.0
     * @return void
     */
    private function render_onboarding_page() {
        ?>
        <div class="wrap imgpro-admin imgpro-admin-onboarding">
            <?php $this->onboarding->render(); ?>
        </div>
        <?php
    }

    /**
     * Render page header
     *
     * Status badge reflects the current tab's mode-specific enabled state.
     *
     * @since 0.2.0
     * @param array  $settings    Plugin settings.
     * @param string $current_tab Current tab/mode being viewed.
     * @return void
     */
    private function render_header($settings, $current_tab = '') {
        $mode = $current_tab ?: ($settings['setup_mode'] ?? '');
        $is_mode_configured = ImgPro_CDN_Settings::is_mode_valid($mode, $settings);
        $is_mode_enabled = ImgPro_CDN_Settings::is_mode_enabled($mode, $settings);
        $is_active = $is_mode_configured && $is_mode_enabled;
        ?>
        <div class="imgpro-header">
            <div class="imgpro-header-brand">
                <div>
                    <h1><?php esc_html_e('Bandwidth Saver', 'bandwidth-saver'); ?></h1>
                    <p class="imgpro-tagline"><?php esc_html_e('Image CDN powered by Cloudflare', 'bandwidth-saver'); ?></p>
                </div>
            </div>
            <div class="imgpro-header-meta">
                <?php if ($is_mode_configured): ?>
                    <span class="imgpro-status-badge <?php echo $is_active ? 'imgpro-status-active' : 'imgpro-status-inactive'; ?>" id="imgpro-status-badge" data-mode="<?php echo esc_attr($mode); ?>">
                        <span class="imgpro-status-dot"></span>
                        <span class="imgpro-status-text"><?php echo $is_active ? esc_html__('CDN Active', 'bandwidth-saver') : esc_html__('CDN Inactive', 'bandwidth-saver'); ?></span>
                    </span>
                <?php endif; ?>
                <span class="imgpro-version">v<?php echo esc_html(IMGPRO_CDN_VERSION); ?></span>
            </div>
        </div>
        <?php
    }

    /**
     * Render CDN toggle card
     *
     * Each mode has its own independent enabled state.
     *
     * @since 0.2.0
     * @param array  $settings   Plugin settings.
     * @param string $setup_mode Current setup mode.
     * @return void
     */
    private function render_toggle_card($settings, $setup_mode = '') {
        $mode = $setup_mode ?: ($settings['setup_mode'] ?? '');
        $is_enabled = ImgPro_CDN_Settings::is_mode_enabled($mode, $settings);

        // Determine field name based on mode
        $field_name = ImgPro_CDN_Settings::MODE_CLOUD === $mode
            ? 'imgpro_cdn_settings[cloud_enabled]'
            : 'imgpro_cdn_settings[cloudflare_enabled]';
        ?>
        <div class="imgpro-toggle-card <?php echo $is_enabled ? 'is-active' : 'is-inactive'; ?>" id="imgpro-toggle-card" data-mode="<?php echo esc_attr($mode); ?>">
            <form method="post" action="options.php" class="imgpro-toggle-form">
                <?php settings_fields('imgpro_cdn_settings_group'); ?>
                <input type="hidden" name="imgpro_cdn_settings[setup_mode]" value="<?php echo esc_attr($mode); ?>">

                <div class="imgpro-toggle-content">
                    <div class="imgpro-toggle-info">
                        <div class="imgpro-toggle-icon">
                            <?php if ($is_enabled): ?>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M22 11.08V12a10 10 0 11-5.93-9.14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 4L12 14.01l-3-3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <?php else: ?>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 8v4m0 4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h2 id="imgpro-toggle-heading">
                                <?php echo $is_enabled
                                    ? esc_html__('Image CDN is Active', 'bandwidth-saver')
                                    : esc_html__('Image CDN is Inactive', 'bandwidth-saver'); ?>
                            </h2>
                            <p id="imgpro-toggle-description">
                                <?php echo $is_enabled
                                    ? esc_html__('Images are being delivered from Cloudflare edge locations worldwide.', 'bandwidth-saver')
                                    : esc_html__('Enable to serve images from Cloudflare instead of your server.', 'bandwidth-saver'); ?>
                            </p>
                        </div>
                    </div>

                    <label class="imgpro-toggle" for="imgpro-cdn-enabled">
                        <input
                            type="checkbox"
                            id="imgpro-cdn-enabled"
                            name="<?php echo esc_attr($field_name); ?>"
                            value="1"
                            <?php checked($is_enabled, true); ?>
                            role="switch"
                            aria-checked="<?php echo $is_enabled ? 'true' : 'false'; ?>"
                        >
                        <span class="imgpro-toggle-slider"></span>
                        <span class="screen-reader-text"><?php esc_html_e('Toggle Image CDN', 'bandwidth-saver'); ?></span>
                    </label>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render subscription alerts (cancelled, past_due)
     *
     * @since 0.2.0
     * @param array $settings Plugin settings.
     * @return void
     */
    private function render_subscription_alerts($settings) {
        $is_cancelled = ImgPro_CDN_Settings::is_subscription_inactive($settings);
        $is_past_due = ImgPro_CDN_Settings::is_past_due($settings);

        if ($is_cancelled) {
            $this->render_subscription_alert('cancelled', $settings);
        } elseif ($is_past_due) {
            $this->render_subscription_alert('past_due', $settings);
        }
    }

    /**
     * Render stats grid
     *
     * @since 0.2.0
     * @param array $settings Plugin settings.
     * @return void
     */
    private function render_stats_grid($settings) {
        $storage_used = $settings['storage_used'] ?? 0;
        $storage_limit = ImgPro_CDN_Settings::get_storage_limit($settings);
        $storage_percentage = ImgPro_CDN_Settings::get_storage_percentage($settings);
        $bandwidth_used = $settings['bandwidth_used'] ?? 0;
        $bandwidth_limit = ImgPro_CDN_Settings::get_bandwidth_limit($settings);
        $bandwidth_percentage = ImgPro_CDN_Settings::get_bandwidth_percentage($settings);
        $images_cached = $settings['images_cached'] ?? 0;
        ?>
        <div class="imgpro-stats-grid" id="imgpro-stats-grid">
            <div class="imgpro-stat-card">
                <div class="imgpro-stat-header">
                    <span class="imgpro-stat-label"><?php esc_html_e('Storage Used', 'bandwidth-saver'); ?></span>
                </div>
                <div class="imgpro-stat-value" id="imgpro-stat-storage">
                    <?php echo esc_html(ImgPro_CDN_Settings::format_bytes($storage_used)); ?>
                    <span class="imgpro-stat-limit">/ <?php echo esc_html(ImgPro_CDN_Settings::format_bytes($storage_limit, 0)); ?></span>
                </div>
                <div class="imgpro-progress-bar">
                    <div class="imgpro-progress-fill <?php echo $storage_percentage >= 90 ? 'is-critical' : ($storage_percentage >= 70 ? 'is-warning' : ''); ?>" style="width: <?php echo esc_attr(min(100, $storage_percentage)); ?>%"></div>
                </div>
            </div>

            <div class="imgpro-stat-card">
                <div class="imgpro-stat-header">
                    <span class="imgpro-stat-label"><?php esc_html_e('Bandwidth Used', 'bandwidth-saver'); ?></span>
                </div>
                <div class="imgpro-stat-value" id="imgpro-stat-bandwidth">
                    <?php echo esc_html(ImgPro_CDN_Settings::format_bytes($bandwidth_used)); ?>
                    <span class="imgpro-stat-limit">/ <?php echo esc_html(ImgPro_CDN_Settings::format_bytes($bandwidth_limit, 0)); ?></span>
                </div>
                <div class="imgpro-progress-bar">
                    <div class="imgpro-progress-fill <?php echo $bandwidth_percentage >= 90 ? 'is-critical' : ($bandwidth_percentage >= 70 ? 'is-warning' : ''); ?>" style="width: <?php echo esc_attr(min(100, $bandwidth_percentage)); ?>%"></div>
                </div>
            </div>

            <div class="imgpro-stat-card">
                <div class="imgpro-stat-header">
                    <span class="imgpro-stat-label"><?php esc_html_e('Images Cached', 'bandwidth-saver'); ?></span>
                </div>
                <div class="imgpro-stat-value" id="imgpro-stat-images">
                    <?php echo esc_html(number_format($images_cached)); ?>
                </div>
                <p class="imgpro-stat-hint"><?php esc_html_e('Across all edge locations', 'bandwidth-saver'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render subscription alert banner
     *
     * @since 0.2.0
     * @param string $type     Alert type ('cancelled', 'past_due', 'suspended').
     * @param array  $settings Plugin settings.
     * @return void
     */
    private function render_subscription_alert($type, $settings) {
        $pricing = $this->get_pricing();

        if ('cancelled' === $type) {
            $icon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M15 9l-6 6M9 9l6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
            $title = __('Your subscription has ended', 'bandwidth-saver');
            $message = __('Your Pro subscription has been cancelled. CDN functionality is disabled until you resubscribe.', 'bandwidth-saver');
            $button_text = __('Resubscribe', 'bandwidth-saver');
            $button_id = 'imgpro-resubscribe';
            $alert_class = 'is-error';
        } elseif ('past_due' === $type) {
            $icon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="12" y1="9" x2="12" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="12" y1="17" x2="12.01" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
            $title = __('Payment failed', 'bandwidth-saver');
            $message = __('We couldn\'t process your last payment. Please update your payment method to avoid service interruption.', 'bandwidth-saver');
            $button_text = __('Update Payment', 'bandwidth-saver');
            $button_id = 'imgpro-update-payment';
            $alert_class = 'is-warning';
        } else {
            $icon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 8v4m0 4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
            $title = __('Subscription suspended', 'bandwidth-saver');
            $message = __('Your subscription has been suspended. Please contact support or update your payment method.', 'bandwidth-saver');
            $button_text = __('Manage Subscription', 'bandwidth-saver');
            $button_id = 'imgpro-manage-subscription-alert';
            $alert_class = 'is-error';
        }
        ?>
        <div class="imgpro-subscription-alert <?php echo esc_attr($alert_class); ?>">
            <div class="imgpro-subscription-alert-icon">
                <?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <div class="imgpro-subscription-alert-content">
                <strong><?php echo esc_html($title); ?></strong>
                <p><?php echo esc_html($message); ?></p>
            </div>
            <button type="button" class="imgpro-btn imgpro-btn-primary" id="<?php echo esc_attr($button_id); ?>">
                <?php echo esc_html($button_text); ?>
            </button>
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

        ?>
        <nav class="imgpro-tabs" role="tablist">
            <a href="<?php echo esc_url($cloud_url); ?>"
               class="imgpro-tab <?php echo ImgPro_CDN_Settings::MODE_CLOUD === $current_tab ? 'is-active' : ''; ?>"
               role="tab"
               aria-selected="<?php echo ImgPro_CDN_Settings::MODE_CLOUD === $current_tab ? 'true' : 'false'; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                <?php esc_html_e('Managed', 'bandwidth-saver'); ?>
            </a>
            <a href="<?php echo esc_url($cloudflare_url); ?>"
               class="imgpro-tab <?php echo ImgPro_CDN_Settings::MODE_CLOUDFLARE === $current_tab ? 'is-active' : ''; ?>"
               role="tab"
               aria-selected="<?php echo ImgPro_CDN_Settings::MODE_CLOUDFLARE === $current_tab ? 'true' : 'false'; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                <?php esc_html_e('Self-Host', 'bandwidth-saver'); ?>
            </a>
        </nav>
        <?php
    }

    /**
     * Get pricing from API
     *
     * Uses the API client which handles caching internally.
     *
     * @since 0.1.0
     * @return array
     */
    private function get_pricing() {
        return $this->api->get_pricing();
    }

    /**
     * Render Cloud tab
     *
     * @since 0.1.0
     * @param array $settings Plugin settings.
     * @return void
     */
    private function render_cloud_tab($settings) {
        $is_configured = !empty($settings['cloud_api_key']);
        $tier = $settings['cloud_tier'] ?? ImgPro_CDN_Settings::TIER_NONE;
        $has_subscription = in_array($tier, [ImgPro_CDN_Settings::TIER_FREE, ImgPro_CDN_Settings::TIER_LITE, ImgPro_CDN_Settings::TIER_PRO, ImgPro_CDN_Settings::TIER_BUSINESS, ImgPro_CDN_Settings::TIER_ACTIVE], true);
        $pricing = $this->get_pricing();
        ?>
        <div class="imgpro-tab-panel" role="tabpanel">
            <?php if (!$has_subscription): ?>
                <?php $this->render_cloud_signup($pricing); ?>
            <?php else: ?>
                <?php $this->render_cloud_settings($settings); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Cloud signup CTA
     *
     * @since 0.2.0
     * @param array $pricing Pricing information.
     * @return void
     */
    private function render_cloud_signup($pricing) {
        ?>
        <div class="imgpro-cta-card">
            <div class="imgpro-cta-content">
                <h2><?php esc_html_e('Skip the Setup. We Handle Everything.', 'bandwidth-saver'); ?></h2>
                <p><?php esc_html_e('Images load from Cloudflare with zero configuration. Takes less than a minute.', 'bandwidth-saver'); ?></p>

                <ul class="imgpro-feature-list">
                    <li>
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M16.667 5L7.5 14.167 3.333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span><strong><?php esc_html_e('10 GB storage + 50 GB bandwidth free', 'bandwidth-saver'); ?></strong></span>
                    </li>
                    <li>
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M16.667 5L7.5 14.167 3.333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span>No Cloudflare account needed</span>
                    </li>
                    <li>
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M16.667 5L7.5 14.167 3.333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span>No DNS changes required</span>
                    </li>
                    <li>
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M16.667 5L7.5 14.167 3.333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span>300+ edge locations worldwide</span>
                    </li>
                </ul>

                <div class="imgpro-cta-actions">
                    <button type="button" class="imgpro-btn imgpro-btn-primary imgpro-btn-lg" id="imgpro-free-signup">
                        <?php esc_html_e('Start Free', 'bandwidth-saver'); ?>
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4.167 10h11.666M10 4.167L15.833 10 10 15.833" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>

                    <span class="imgpro-cta-divider"><?php esc_html_e('or', 'bandwidth-saver'); ?></span>

                    <button type="button" class="imgpro-btn imgpro-btn-secondary imgpro-open-plan-selector">
                        <?php esc_html_e('See paid plans', 'bandwidth-saver'); ?>
                    </button>
                </div>

                <p class="imgpro-cta-note">
                    <?php esc_html_e('Start with 10 GB free. Upgrade anytime for more storage and bandwidth.', 'bandwidth-saver'); ?>
                </p>

                <p class="imgpro-cta-recovery">
                    <?php esc_html_e('Already have an account?', 'bandwidth-saver'); ?>
                    <button type="button" class="imgpro-btn-link" id="imgpro-recover-account">
                        <?php esc_html_e('Recover it', 'bandwidth-saver'); ?>
                    </button>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render Cloud settings (for active users)
     *
     * Layout hierarchy:
     * 1. CDN Toggle
     * 2. Account Card
     * 3. Stats Grid
     * 4. Custom Domain
     * 5. Advanced Settings
     *
     * @since 0.2.0
     * @param array $settings Plugin settings.
     * @return void
     */
    private function render_cloud_settings($settings) {
        $email = $settings['cloud_email'] ?? '';
        $custom_domain = $settings['custom_domain'] ?? '';
        $domain_status = $settings['custom_domain_status'] ?? '';
        $has_custom_domain = !empty($custom_domain);
        $needs_attention = $has_custom_domain && 'active' !== $domain_status;
        ?>
        <div class="imgpro-cloud-dashboard">
            <?php // Subscription Alerts ?>
            <?php $this->render_subscription_alerts($settings); ?>

            <?php // 1. CDN Toggle ?>
            <?php $this->render_toggle_card($settings, ImgPro_CDN_Settings::MODE_CLOUD); ?>

            <?php // 2. Account Card ?>
            <?php $this->render_account_card($settings, $email); ?>

            <?php // 3. Stats Grid ?>
            <?php $this->render_stats_grid($settings); ?>

            <?php // 4. Custom Domain Section ?>
            <?php $this->render_custom_domain_section($settings); ?>

            <?php // Custom Domain Pending Notice (if DNS needs attention) ?>
            <?php if ($needs_attention): ?>
                <?php $this->render_custom_domain_pending($settings); ?>
            <?php endif; ?>

            <?php // 5. Advanced Settings ?>
            <details class="imgpro-details">
                <summary class="imgpro-details-summary">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span><?php esc_html_e('Advanced Settings', 'bandwidth-saver'); ?></span>
                </summary>

                <div class="imgpro-details-content">
                    <form method="post" action="options.php" class="imgpro-settings-form">
                        <?php settings_fields('imgpro_cdn_settings_group'); ?>
                        <input type="hidden" name="imgpro_cdn_settings[setup_mode]" value="<?php echo esc_attr(ImgPro_CDN_Settings::MODE_CLOUD); ?>">

                        <?php $this->render_advanced_options($settings); ?>

                        <div class="imgpro-form-actions">
                            <button type="submit" class="imgpro-btn imgpro-btn-primary"><?php esc_html_e('Save Settings', 'bandwidth-saver'); ?></button>
                        </div>
                    </form>
                </div>
            </details>
        </div>
        <?php
    }

    /**
     * Render account card (unified for all tiers)
     *
     * @since 0.1.6
     * @param array  $settings Plugin settings.
     * @param string $email    User email.
     * @return void
     */
    private function render_account_card($settings, $email) {
        $tier = $settings['cloud_tier'] ?? '';
        $is_free = ImgPro_CDN_Settings::is_free($settings);
        $is_business = $tier === ImgPro_CDN_Settings::TIER_BUSINESS;

        // Get tier display name
        $tier_names = [
            'free' => __('Free', 'bandwidth-saver'),
            'lite' => __('Lite', 'bandwidth-saver'),
            'pro' => __('Pro', 'bandwidth-saver'),
            'business' => __('Business', 'bandwidth-saver'),
        ];
        $tier_name = $tier_names[$tier] ?? ucfirst($tier);

        // Get limits
        $storage_limit = $settings['storage_limit'] ?? 0;
        $bandwidth_limit = $settings['bandwidth_limit'] ?? 0;

        // Format limits for display
        $storage_formatted = $storage_limit > 0 ? ImgPro_CDN_Settings::format_bytes($storage_limit, 0) : '10 GB';
        $bandwidth_formatted = $bandwidth_limit > 0 ? ImgPro_CDN_Settings::format_bytes($bandwidth_limit, 0) : '50 GB';

        // Check for custom domain feature (available on Pro+)
        $has_custom_domain_feature = in_array($tier, ['pro', 'business'], true);
        $has_priority_support = $tier === 'business';

        // Determine next tier for direct upgrade
        $next_tier_map = [
            'lite' => 'pro',
            'pro' => 'business',
        ];
        $next_tier = $next_tier_map[$tier] ?? null;
        $next_tier_name = $next_tier ? ($tier_names[$next_tier] ?? ucfirst($next_tier)) : null;

        if ($is_free): ?>
            <div class="imgpro-account-card imgpro-account-card--free">
                <div class="imgpro-account-card__main">
                    <div class="imgpro-account-card__content">
                        <strong class="imgpro-account-card__headline"><?php esc_html_e('Need more capacity?', 'bandwidth-saver'); ?></strong>
                        <span class="imgpro-account-card__description"><?php esc_html_e('Upgrade for more storage, bandwidth, and features like custom domains.', 'bandwidth-saver'); ?></span>
                    </div>
                    <button type="button" class="imgpro-btn imgpro-btn-primary imgpro-open-plan-selector">
                        <?php esc_html_e('See upgrade options', 'bandwidth-saver'); ?>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3.333 8h9.334M8 3.333L12.667 8 8 12.667" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>
                <div class="imgpro-account-card__footer">
                    <span><?php echo esc_html($email); ?></span>
                    <span class="imgpro-separator">·</span>
                    <span><?php esc_html_e('Free Plan', 'bandwidth-saver'); ?></span>
                </div>
            </div>
        <?php else: ?>
            <div class="imgpro-account-card imgpro-account-card--paid">
                <div class="imgpro-account-card__main">
                    <div class="imgpro-account-card__content">
                        <div class="imgpro-account-card__plan">
                            <span class="imgpro-account-card__tier"><?php echo esc_html($tier_name); ?></span>
                            <span class="imgpro-account-card__plan-label"><?php esc_html_e('Plan', 'bandwidth-saver'); ?></span>
                        </div>
                        <div class="imgpro-account-card__limits">
                            <span class="imgpro-account-card__limit"><?php echo esc_html($storage_formatted); ?> <?php esc_html_e('storage', 'bandwidth-saver'); ?></span>
                            <span class="imgpro-account-card__separator">·</span>
                            <span class="imgpro-account-card__limit"><?php echo esc_html($bandwidth_formatted); ?> <?php esc_html_e('bandwidth', 'bandwidth-saver'); ?></span>
                            <?php if ($has_custom_domain_feature): ?>
                                <span class="imgpro-account-card__separator">·</span>
                                <span class="imgpro-account-card__limit"><?php esc_html_e('Custom domains', 'bandwidth-saver'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="imgpro-account-card__actions">
                        <?php if ($next_tier): ?>
                            <button type="button" class="imgpro-btn imgpro-btn-primary imgpro-direct-upgrade" data-tier="<?php echo esc_attr($next_tier); ?>">
                                <?php
                                /* translators: %s: tier name (e.g., Pro, Business) */
                                printf(esc_html__('Upgrade to %s', 'bandwidth-saver'), esc_html($next_tier_name));
                                ?>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3.333 8h9.334M8 3.333L12.667 8 8 12.667" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                        <?php else: ?>
                            <button type="button" class="imgpro-btn imgpro-btn-primary" id="imgpro-manage-subscription">
                                <?php esc_html_e('Manage Subscription', 'bandwidth-saver'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="imgpro-account-card__footer">
                    <span><?php echo esc_html($email); ?></span>
                    <?php if (!$is_business): ?>
                        <span class="imgpro-separator">·</span>
                        <button type="button" class="imgpro-btn-link" id="imgpro-manage-subscription">
                            <?php esc_html_e('Manage Subscription', 'bandwidth-saver'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif;
    }

    /**
     * Render Cloudflare (Self-Host) tab
     *
     * Layout hierarchy (when configured):
     * 1. CDN Toggle
     * 2. CDN Domain Card
     * 3. Advanced Settings
     *
     * @since 0.1.0
     * @param array $settings Plugin settings.
     * @return void
     */
    private function render_cloudflare_tab($settings) {
        $is_configured = !empty($settings['cdn_url']);
        ?>
        <div class="imgpro-tab-panel" role="tabpanel">
            <?php if (!$is_configured): ?>
                <?php // Setup instructions for unconfigured state ?>
                <div class="imgpro-card imgpro-setup-card">
                    <h2><?php esc_html_e('Use Your Own Cloudflare Account', 'bandwidth-saver'); ?></h2>
                    <p><?php esc_html_e('For technical users who prefer running Cloudflare on their own account. You pay Cloudflare directly (usually $0/month on the free tier).', 'bandwidth-saver'); ?></p>

                    <ol class="imgpro-steps-list">
                        <li>
                            <strong><?php esc_html_e('Create a Cloudflare Account', 'bandwidth-saver'); ?></strong>
                            <span><?php esc_html_e('Free at cloudflare.com', 'bandwidth-saver'); ?></span>
                        </li>
                        <li>
                            <strong><?php esc_html_e('Deploy the Worker', 'bandwidth-saver'); ?></strong>
                            <span><?php esc_html_e('One-click deploy from our GitHub repository', 'bandwidth-saver'); ?></span>
                        </li>
                        <li>
                            <strong><?php esc_html_e('Add a Custom Domain', 'bandwidth-saver'); ?></strong>
                            <span><?php esc_html_e('Point a subdomain to your Worker', 'bandwidth-saver'); ?></span>
                        </li>
                        <li>
                            <strong><?php esc_html_e('Enter Domain Below', 'bandwidth-saver'); ?></strong>
                            <span><?php esc_html_e('Add your CDN domain here to activate', 'bandwidth-saver'); ?></span>
                        </li>
                    </ol>

                    <div class="imgpro-setup-actions">
                        <a href="https://github.com/img-pro/bandwidth-saver-worker#setup" target="_blank" class="imgpro-btn imgpro-btn-primary">
                            <?php esc_html_e('View Setup Guide', 'bandwidth-saver'); ?>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M12 8.667V12a1.333 1.333 0 01-1.333 1.333H4A1.333 1.333 0 012.667 12V5.333A1.333 1.333 0 014 4h3.333M10 2h4v4M6.667 9.333L14 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                        <span class="imgpro-setup-time">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M8 5v3l2 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                            <?php esc_html_e('~15 minutes', 'bandwidth-saver'); ?>
                        </span>
                    </div>
                </div>

                <?php // CDN Domain input form (unconfigured) ?>
                <form method="post" action="options.php" class="imgpro-settings-form">
                    <?php settings_fields('imgpro_cdn_settings_group'); ?>
                    <input type="hidden" name="imgpro_cdn_settings[setup_mode]" value="<?php echo esc_attr(ImgPro_CDN_Settings::MODE_CLOUDFLARE); ?>">

                    <div class="imgpro-custom-domain-card" id="imgpro-cdn-domain-section">
                        <div class="imgpro-custom-domain-header">
                            <h4><?php esc_html_e('CDN Domain', 'bandwidth-saver'); ?></h4>
                            <p><?php esc_html_e('The domain pointing to your Cloudflare Worker.', 'bandwidth-saver'); ?></p>
                        </div>
                        <div class="imgpro-custom-domain-form">
                            <div class="imgpro-custom-domain-input-group">
                                <input
                                    type="text"
                                    id="cdn_url"
                                    name="imgpro_cdn_settings[cdn_url]"
                                    value=""
                                    placeholder="cdn.yourdomain.com"
                                    class="imgpro-input"
                                >
                                <button type="submit" class="imgpro-btn imgpro-btn-primary">
                                    <?php esc_html_e('Add Domain', 'bandwidth-saver'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="imgpro-alt-option">
                    <p>
                        <strong><?php esc_html_e('Prefer a simpler setup?', 'bandwidth-saver'); ?></strong>
                        <?php esc_html_e('The Managed option works in under a minute with no configuration.', 'bandwidth-saver'); ?>
                        <a href="<?php echo esc_url(add_query_arg(['tab' => ImgPro_CDN_Settings::MODE_CLOUD, 'switch_mode' => ImgPro_CDN_Settings::MODE_CLOUD, '_wpnonce' => wp_create_nonce('imgpro_switch_mode')], admin_url('options-general.php?page=imgpro-cdn-settings'))); ?>">
                            <?php esc_html_e('Try Managed instead', 'bandwidth-saver'); ?>
                        </a>
                    </p>
                </div>

            <?php else: ?>
                <?php // Configured state: Toggle → Domain → Advanced ?>
                <div class="imgpro-selfhost-dashboard">
                    <?php // 1. CDN Toggle ?>
                    <?php $this->render_toggle_card($settings, ImgPro_CDN_Settings::MODE_CLOUDFLARE); ?>

                    <?php // 2. CDN Domain Card ?>
                    <div class="imgpro-custom-domain-card" id="imgpro-cdn-domain-section">
                        <div class="imgpro-custom-domain-header">
                            <h4><?php esc_html_e('CDN Domain', 'bandwidth-saver'); ?></h4>
                            <p><?php esc_html_e('The domain pointing to your Cloudflare Worker.', 'bandwidth-saver'); ?></p>
                        </div>
                        <div class="imgpro-custom-domain-configured">
                            <div class="imgpro-custom-domain-info">
                                <code class="imgpro-custom-domain-value"><?php echo esc_html($settings['cdn_url']); ?></code>
                                <span class="imgpro-domain-badge imgpro-domain-badge-active">
                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M10 3L4.5 8.5 2 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    <?php esc_html_e('Active', 'bandwidth-saver'); ?>
                                </span>
                            </div>
                            <button type="button" class="imgpro-btn imgpro-btn-sm imgpro-btn-ghost imgpro-btn-danger" id="imgpro-remove-cdn-domain">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M11 3L3 11M3 3l8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                <?php esc_html_e('Remove', 'bandwidth-saver'); ?>
                            </button>
                        </div>
                    </div>

                    <?php // 3. Advanced Settings ?>
                    <details class="imgpro-details">
                        <summary class="imgpro-details-summary">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span><?php esc_html_e('Advanced Settings', 'bandwidth-saver'); ?></span>
                        </summary>
                        <div class="imgpro-details-content">
                            <form method="post" action="options.php" class="imgpro-settings-form">
                                <?php settings_fields('imgpro_cdn_settings_group'); ?>
                                <input type="hidden" name="imgpro_cdn_settings[setup_mode]" value="<?php echo esc_attr(ImgPro_CDN_Settings::MODE_CLOUDFLARE); ?>">
                                <input type="hidden" name="imgpro_cdn_settings[cdn_url]" value="<?php echo esc_attr($settings['cdn_url']); ?>">

                                <?php $this->render_advanced_options($settings); ?>

                                <div class="imgpro-form-actions">
                                    <button type="submit" class="imgpro-btn imgpro-btn-primary"><?php esc_html_e('Save Settings', 'bandwidth-saver'); ?></button>
                                </div>
                            </form>
                        </div>
                    </details>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Parse custom domain
     *
     * @since 0.1.6
     * @param string $domain Full domain name.
     * @return array
     */
    private function parse_custom_domain($domain) {
        $parts = explode('.', $domain);
        $num_parts = count($parts);

        $two_part_tlds = ['co.uk', 'com.au', 'co.nz', 'com.br', 'co.jp', 'com.mx', 'org.uk', 'net.au'];
        $last_two = $num_parts >= 2 ? $parts[$num_parts - 2] . '.' . $parts[$num_parts - 1] : '';

        if (in_array($last_two, $two_part_tlds, true)) {
            if ($num_parts < 3) {
                return ['subdomain' => '', 'root' => $domain, 'is_root_domain' => true];
            }
            if ($num_parts === 3) {
                return ['subdomain' => '', 'root' => $domain, 'is_root_domain' => true];
            }
            return [
                'subdomain' => implode('.', array_slice($parts, 0, -3)),
                'root' => implode('.', array_slice($parts, -3)),
                'is_root_domain' => false,
            ];
        }

        if ($num_parts < 2) {
            return ['subdomain' => '', 'root' => $domain, 'is_root_domain' => true];
        }
        if ($num_parts === 2) {
            return ['subdomain' => '', 'root' => $domain, 'is_root_domain' => true];
        }

        return [
            'subdomain' => implode('.', array_slice($parts, 0, -2)),
            'root' => implode('.', array_slice($parts, -2)),
            'is_root_domain' => false,
        ];
    }

    /**
     * Render custom domain pending notice
     *
     * @since 0.1.6
     * @param array $settings Plugin settings.
     * @return void
     */
    private function render_custom_domain_pending($settings) {
        $custom_domain = $settings['custom_domain'] ?? '';
        $domain_status = $settings['custom_domain_status'] ?? '';
        $parsed = $this->parse_custom_domain($custom_domain);

        if ('pending_ssl' === $domain_status) {
            $status_message = __('DNS verified. SSL certificate is being issued...', 'bandwidth-saver');
            $show_dns = false;
            $icon_class = 'is-pending-ssl';
        } elseif ('error' === $domain_status) {
            $status_message = __('Verification failed. Please check your DNS settings.', 'bandwidth-saver');
            $show_dns = true;
            $icon_class = 'is-error';
        } else {
            $status_message = __('Configure your DNS to activate this domain.', 'bandwidth-saver');
            $show_dns = true;
            $icon_class = 'is-pending';
        }
        ?>
        <div class="imgpro-domain-pending-card <?php echo esc_attr($icon_class); ?>" id="imgpro-pending-notice">
            <div class="imgpro-domain-pending-header">
                <div class="imgpro-domain-pending-icon">
                    <?php if ('pending_ssl' === $domain_status): ?>
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2v4m0 8v4M4.93 4.93l2.83 2.83m4.48 4.48l2.83 2.83M2 10h4m8 0h4M4.93 15.07l2.83-2.83m4.48-4.48l2.83-2.83" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <?php elseif ('error' === $domain_status): ?>
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/><path d="M12.5 7.5l-5 5m0-5l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/><path d="M10 6v4m0 4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <?php endif; ?>
                </div>
                <div class="imgpro-domain-pending-info">
                    <strong><?php echo esc_html($custom_domain); ?></strong>
                    <span><?php echo esc_html($status_message); ?></span>
                </div>
                <button type="button" class="imgpro-btn imgpro-btn-sm imgpro-btn-secondary" id="imgpro-check-domain-pending">
                    <?php esc_html_e('Check Status', 'bandwidth-saver'); ?>
                </button>
            </div>

            <?php if ($show_dns): ?>
                <div class="imgpro-domain-pending-dns">
                    <span class="imgpro-domain-pending-dns-label"><?php esc_html_e('Add this DNS record:', 'bandwidth-saver'); ?></span>
                    <div class="imgpro-domain-pending-dns-record">
                        <div class="imgpro-dns-item">
                            <span class="imgpro-dns-item-label"><?php esc_html_e('Type', 'bandwidth-saver'); ?></span>
                            <code>CNAME</code>
                        </div>
                        <div class="imgpro-dns-item">
                            <span class="imgpro-dns-item-label"><?php esc_html_e('Name', 'bandwidth-saver'); ?></span>
                            <code><?php echo esc_html($parsed['is_root_domain'] ? '@' : $parsed['subdomain']); ?></code>
                        </div>
                        <div class="imgpro-dns-item">
                            <span class="imgpro-dns-item-label"><?php esc_html_e('Target', 'bandwidth-saver'); ?></span>
                            <code><?php echo esc_html(ImgPro_CDN_Settings::CUSTOM_DOMAIN_TARGET); ?></code>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render custom domain section
     *
     * @since 0.1.6
     * @param array $settings Plugin settings.
     * @return void
     */
    private function render_custom_domain_section($settings) {
        $custom_domain = $settings['custom_domain'] ?? '';
        $domain_status = $settings['custom_domain_status'] ?? '';
        $has_custom_domain = !empty($custom_domain);
        $can_use_custom_domain = ImgPro_CDN_Settings::has_custom_domain($settings);
        ?>
        <div class="imgpro-custom-domain-card" id="imgpro-custom-domain-section">
            <div class="imgpro-custom-domain-header">
                <h4><?php esc_html_e('Custom Domain', 'bandwidth-saver'); ?></h4>
                <p><?php esc_html_e('Serve images from your own branded domain.', 'bandwidth-saver'); ?></p>
            </div>

            <?php if (!$has_custom_domain): ?>
                <div class="imgpro-custom-domain-form" id="imgpro-custom-domain-form">
                    <div class="imgpro-custom-domain-input-group">
                        <input
                            type="text"
                            id="imgpro-custom-domain-input"
                            placeholder="cdn.yourdomain.com"
                            class="imgpro-input"
                            <?php echo !$can_use_custom_domain ? 'disabled' : ''; ?>
                        >
                        <button type="button" class="imgpro-btn imgpro-btn-primary" id="imgpro-add-domain" <?php echo !$can_use_custom_domain ? 'disabled' : ''; ?>>
                            <?php esc_html_e('Add Domain', 'bandwidth-saver'); ?>
                        </button>
                    </div>
                    <?php if (!$can_use_custom_domain): ?>
                        <p class="imgpro-custom-domain-upgrade-hint">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1l1.5 3 3.5.5-2.5 2.5.5 3.5L7 9l-3 1.5.5-3.5L2 4.5l3.5-.5L7 1z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <?php esc_html_e('Custom domains are available on Pro and Business plans.', 'bandwidth-saver'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="imgpro-custom-domain-configured" id="imgpro-custom-domain-status" data-status="<?php echo esc_attr($domain_status); ?>">
                    <div class="imgpro-custom-domain-info">
                        <code class="imgpro-custom-domain-value"><?php echo esc_html($custom_domain); ?></code>
                        <?php if ('active' === $domain_status): ?>
                            <span class="imgpro-domain-badge imgpro-domain-badge-active">
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M10 3L4.5 8.5 2 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <?php esc_html_e('Active', 'bandwidth-saver'); ?>
                            </span>
                        <?php else: ?>
                            <span class="imgpro-domain-badge imgpro-domain-badge-pending">
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5"/></svg>
                                <?php esc_html_e('Pending', 'bandwidth-saver'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="imgpro-btn imgpro-btn-sm imgpro-btn-ghost imgpro-btn-danger" id="imgpro-remove-domain">
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M11 3L3 11M3 3l8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        <?php esc_html_e('Remove', 'bandwidth-saver'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render advanced options
     *
     * @since 0.1.0
     * @param array $settings Plugin settings.
     * @return void
     */
    private function render_advanced_options($settings) {
        ?>
        <div class="imgpro-settings-section">
            <div class="imgpro-form-group">
                <label for="allowed_domains"><?php esc_html_e('Allowed Domains', 'bandwidth-saver'); ?></label>
                <textarea
                    id="allowed_domains"
                    name="imgpro_cdn_settings[allowed_domains]"
                    rows="3"
                    class="imgpro-textarea"
                    placeholder="example.com&#10;blog.example.com"
                ><?php
                    if (is_array($settings['allowed_domains'])) {
                        echo esc_textarea(implode("\n", $settings['allowed_domains']));
                    }
                ?></textarea>
                <p class="imgpro-input-hint">
                    <?php esc_html_e('Only rewrite images from these domains (one per line). Leave empty to rewrite all.', 'bandwidth-saver'); ?>
                </p>
            </div>

            <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                <div class="imgpro-form-group imgpro-form-checkbox">
                    <label class="imgpro-checkbox-label">
                        <input
                            type="checkbox"
                            id="debug_mode"
                            name="imgpro_cdn_settings[debug_mode]"
                            value="1"
                            <?php checked($settings['debug_mode'], true); ?>
                        >
                        <span><?php esc_html_e('Enable debug mode', 'bandwidth-saver'); ?></span>
                    </label>
                    <p class="imgpro-input-hint"><?php esc_html_e('Adds debug info to image elements (visible in dev tools).', 'bandwidth-saver'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render page footer
     *
     * @since 0.2.0
     * @return void
     */
    private function render_footer() {
        ?>
        <div class="imgpro-footer">
            <p>
                <?php
                echo wp_kses_post(
                    sprintf(
                        /* translators: 1: ImgPro link, 2: Cloudflare link */
                        __('Bandwidth Saver by %1$s, powered by %2$s', 'bandwidth-saver'),
                        '<a href="https://img.pro" target="_blank">ImgPro</a>',
                        '<a href="https://cloudflare.com" target="_blank">Cloudflare</a>'
                    )
                );
                ?>
            </p>
        </div>
        <?php
    }
}
