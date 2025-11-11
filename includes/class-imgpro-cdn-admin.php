<?php
/**
 * ImgPro Admin Interface
 *
 * @package ImgPro_CDN
 * @version 1.0
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
        add_action('wp_ajax_imgpro_cdn_use_cloud', [$this, 'ajax_use_cloud']);
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
                'i18n' => [
                    'activeLabel' => __('Active', 'imgpro-cdn'),
                    'disabledLabel' => __('Disabled', 'imgpro-cdn'),
                    'activeMessage' => '<span class="imgpro-cdn-nowrap imgpro-cdn-hide-mobile">' . __('Images load faster worldwide.', 'imgpro-cdn') . '</span> <span class="imgpro-cdn-nowrap">' . __('Your bandwidth costs are being reduced.', 'imgpro-cdn') . '</span>',
                    'disabledMessage' => __('Enable to cut bandwidth costs and speed up image delivery globally', 'imgpro-cdn'),
                ]
            ]);
        }
    }

    /**
     * Add admin menu page
     */
    public function add_menu_page() {
        add_options_page(
            esc_html__('Image CDN', 'imgpro-cdn'),       // Page title
            esc_html__('Image CDN', 'imgpro-cdn'),       // Menu title
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
        // Validate submitted fields
        $validated = $this->settings->validate($input);

        // Handle unchecked checkboxes (HTML doesn't submit unchecked values)
        if (!isset($input['enabled'])) {
            $validated['enabled'] = false;
        }
        if (!isset($input['debug_mode'])) {
            $validated['debug_mode'] = false;
        }

        return $validated;
    }

    /**
     * Show admin notices
     */
    public function show_notices() {
        // Verify we're on the correct admin page
        $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ($page !== 'imgpro-cdn-settings') {
            return;
        }

        // Show success message after settings save
        if (filter_input(INPUT_GET, 'settings-updated', FILTER_VALIDATE_BOOLEAN)) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php esc_html_e('Settings saved successfully!', 'imgpro-cdn'); ?></strong>
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

        $settings = $this->settings->get_all();
        ?>
        <div class="wrap imgpro-cdn-admin">
            <div class="imgpro-cdn-header">
                <div>
                    <h1><?php esc_html_e('Bandwidth Saver by ImgPro', 'imgpro-cdn'); ?></h1>
                    <p class="imgpro-cdn-tagline"><?php esc_html_e('Cut bandwidth costs, boost global speed', 'imgpro-cdn'); ?></p>
                </div>
                <div class="imgpro-cdn-header-meta">
                    <span class="imgpro-cdn-version">v<?php echo esc_html(IMGPRO_CDN_VERSION); ?></span>
                </div>
            </div>

            <div class="imgpro-cdn-tab-content">
                <?php $this->render_settings_tab($settings); ?>
            </div>

            <div class="imgpro-cdn-footer">
                <p>
                    <?php
                    echo wp_kses_post(
                        sprintf(
                            /* translators: 1: ImgPro link, 2: Cloudflare R2 & Workers link */
                            __('Image CDN - Bandwidth Saver by %1$s, powered by %2$s', 'imgpro-cdn'),
                            '<a href="https://img.pro" target="_blank">ImgPro</a>',
                            '<a href="https://www.cloudflare.com/products/r2/" target="_blank">Cloudflare R2 &amp; Workers</a>'
                        )
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings tab
     */
    private function render_settings_tab($settings) {
        // Check if configured (has valid CDN and Worker domains)
        $is_configured = !empty($settings['cdn_url']) && !empty($settings['worker_url']);
        ?>


        <?php if (!$is_configured): ?>
            <?php // Empty State for Unconfigured Plugin ?>
            <div class="imgpro-cdn-card imgpro-cdn-empty-state">
                <h2><?php esc_html_e('Welcome to Image CDN', 'imgpro-cdn'); ?></h2>
                <p class="imgpro-cdn-empty-state-description">
                    <?php esc_html_e('Choose how you want to deliver your images globally:', 'imgpro-cdn'); ?>
                </p>

                <div class="imgpro-cdn-setup-options">
                    <div class="imgpro-cdn-setup-option imgpro-cdn-setup-option-cloud">
                        <div class="imgpro-cdn-setup-option-header">
                            <span class="dashicons dashicons-cloud"></span>
                            <h3><?php esc_html_e('ImgPro Cloud', 'imgpro-cdn'); ?></h3>
                            <span class="imgpro-cdn-badge imgpro-cdn-badge-recommended"><?php esc_html_e('Recommended', 'imgpro-cdn'); ?></span>
                        </div>
                        <p><?php esc_html_e('Start instantly with our managed service. No Cloudflare account required.', 'imgpro-cdn'); ?></p>
                        <button type="button" class="button button-primary button-hero imgpro-cdn-use-cloud" id="imgpro-cdn-use-cloud">
                            <?php esc_html_e('Use ImgPro Cloud', 'imgpro-cdn'); ?>
                        </button>
                        <p class="imgpro-cdn-setup-note">
                            <span class="dashicons dashicons-info"></span>
                            <?php esc_html_e('One click setup, free trial', 'imgpro-cdn'); ?>
                        </p>
                    </div>

                    <div class="imgpro-cdn-setup-option">
                        <div class="imgpro-cdn-setup-option-header">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <h3><?php esc_html_e('Cloudflare Account', 'imgpro-cdn'); ?></h3>
                        </div>
                        <p><?php esc_html_e('Deploy the worker to your own Cloudflare account for full control.', 'imgpro-cdn'); ?></p>
                        <a href="https://github.com/img-pro/wp-image-cdn-worker" target="_blank" class="button button-secondary button-hero">
                            <?php esc_html_e('View Setup Guide', 'imgpro-cdn'); ?>
                            <span class="dashicons dashicons-external"></span>
                        </a>
                        <p class="imgpro-cdn-setup-note">
                            <span class="dashicons dashicons-info"></span>
                            <?php esc_html_e('15 minute setup, complete control over your infrastructure', 'imgpro-cdn'); ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields('imgpro_cdn_settings_group'); ?>

            <?php if ($is_configured): ?>
                <?php // Big Toggle Switch (only when configured) ?>
                <div class="imgpro-cdn-card imgpro-cdn-toggle-card <?php echo $settings['enabled'] ? 'imgpro-cdn-toggle-active' : 'imgpro-cdn-toggle-disabled'; ?>">
                    <div class="imgpro-cdn-main-toggle">
                        <div class="imgpro-cdn-main-toggle-status">
                            <div class="imgpro-cdn-toggle-icon">
                                <?php if ($settings['enabled']): ?>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning"></span>
                                <?php endif; ?>
                            </div>
                            <div class="imgpro-cdn-toggle-content">
                                <?php if ($settings['enabled']): ?>
                                    <h2><?php esc_html_e('Active', 'imgpro-cdn'); ?></h2>
                                    <p><span class="imgpro-cdn-nowrap imgpro-cdn-hide-mobile"><?php esc_html_e('Images load faster worldwide.', 'imgpro-cdn'); ?></span> <span class="imgpro-cdn-nowrap"><?php esc_html_e('Your bandwidth costs are being reduced.', 'imgpro-cdn'); ?></span></p>
                                <?php else: ?>
                                    <h2><?php esc_html_e('Disabled', 'imgpro-cdn'); ?></h2>
                                    <p><?php esc_html_e('Enable to cut bandwidth costs and speed up image delivery globally', 'imgpro-cdn'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <label class="imgpro-cdn-main-toggle-switch" for="enabled">
                            <input
                                type="checkbox"
                                id="enabled"
                                name="imgpro_cdn_settings[enabled]"
                                value="1"
                                <?php checked($settings['enabled'], true); ?>
                                aria-describedby="enabled-description"
                                role="switch"
                                aria-checked="<?php echo $settings['enabled'] ? 'true' : 'false'; ?>"
                            >
                            <span class="imgpro-cdn-main-toggle-slider" aria-hidden="true"></span>
                            <span class="screen-reader-text" id="enabled-description">
                                <?php esc_html_e('Toggle Image CDN on or off. When enabled, images are delivered through Cloudflare\'s global network.', 'imgpro-cdn'); ?>
                            </span>
                        </label>
                    </div>
                </div>
            <?php endif; ?>

            <?php
            // Check if using ImgPro Cloud
            $using_imgpro_cloud = ($settings['cdn_url'] === 'wp.img.pro' && $settings['worker_url'] === 'fetch.wp.img.pro');
            if ($using_imgpro_cloud):
            ?>
                <div class="imgpro-cdn-card imgpro-cdn-cloud-notice">
                    <div class="imgpro-cdn-cloud-notice-icon">
                        <span class="dashicons dashicons-cloud"></span>
                    </div>
                    <div class="imgpro-cdn-cloud-notice-content">
                        <h4><?php esc_html_e('Using ImgPro Cloud', 'imgpro-cdn'); ?></h4>
                        <p>
                            <?php esc_html_e('You\'re using our managed service. Your images are being delivered through our shared infrastructure.', 'imgpro-cdn'); ?>
                        </p>
                        <p>
                            <?php
                            echo wp_kses_post(
                                sprintf(
                                    __('Want to use your own Cloudflare account? %s to deploy the worker yourself.', 'imgpro-cdn'),
                                    '<a href="https://github.com/img-pro/wp-image-cdn-worker" target="_blank">' . __('View setup guide', 'imgpro-cdn') . ' <span class="dashicons dashicons-external"></span></a>'
                                )
                            );
                            ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php // Image CDN Settings ?>
            <div class="imgpro-cdn-card imgpro-cdn-settings-card">
                <div class="imgpro-cdn-card-header">
                    <h2><?php esc_html_e('Image CDN', 'imgpro-cdn'); ?></h2>
                    <p class="imgpro-cdn-card-description"><?php esc_html_e('Configure your domains to start delivering images globally', 'imgpro-cdn'); ?></p>
                </div>

                <div class="imgpro-cdn-settings-content">
                        <div class="imgpro-cdn-settings-section">
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row">
                                        <label for="cdn_url"><?php esc_html_e('CDN Domain', 'imgpro-cdn'); ?></label>
                                    </th>
                                    <td>
                                        <input
                                            type="text"
                                            id="cdn_url"
                                            name="imgpro_cdn_settings[cdn_url]"
                                            value="<?php echo esc_attr($settings['cdn_url']); ?>"
                                            class="regular-text"
                                            placeholder="cdn.yourdomain.com"
                                            required
                                            aria-required="true"
                                            aria-describedby="cdn-url-description"
                                        >
                                        <p class="description" id="cdn-url-description"><?php esc_html_e('Your R2 bucket\'s public domain. Cached images are delivered from 300+ global locations.', 'imgpro-cdn'); ?></p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="worker_url"><?php esc_html_e('Worker Domain', 'imgpro-cdn'); ?></label>
                                    </th>
                                    <td>
                                        <input
                                            type="text"
                                            id="worker_url"
                                            name="imgpro_cdn_settings[worker_url]"
                                            value="<?php echo esc_attr($settings['worker_url']); ?>"
                                            class="regular-text"
                                            placeholder="worker.yourdomain.com"
                                            required
                                            aria-required="true"
                                            aria-describedby="worker-url-description"
                                        >
                                        <p class="description" id="worker-url-description"><?php esc_html_e('Your Cloudflare Worker domain. Processes new images and cache misses.', 'imgpro-cdn'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="imgpro-cdn-settings-section">
                            <h3 class="imgpro-cdn-section-title"><?php esc_html_e('Advanced Options', 'imgpro-cdn'); ?></h3>
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row">
                                        <label for="allowed_domains"><?php esc_html_e('Allowed Domains', 'imgpro-cdn'); ?></label>
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
                                        <p class="description" id="allowed-domains-description"><?php esc_html_e('Enable Image CDN in limited domains (one per line). Leave empty to process all images.', 'imgpro-cdn'); ?></p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="excluded_paths"><?php esc_html_e('Excluded Paths', 'imgpro-cdn'); ?></label>
                                    </th>
                                    <td>
                                        <textarea
                                            id="excluded_paths"
                                            name="imgpro_cdn_settings[excluded_paths]"
                                            rows="3"
                                            class="large-text"
                                            placeholder="/cart&#10;/checkout&#10;/my-account"
                                            aria-describedby="excluded-paths-description"
                                        ><?php
                                            if (is_array($settings['excluded_paths'])) {
                                                echo esc_textarea(implode("\n", $settings['excluded_paths']));
                                            }
                                        ?></textarea>
                                        <p class="description" id="excluded-paths-description"><?php esc_html_e('Skip Image CDN for specific paths like checkout or cart pages (one per line).', 'imgpro-cdn'); ?></p>
                                    </td>
                                </tr>

                                <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                                <tr>
                                    <th scope="row">
                                        <label for="debug_mode"><?php esc_html_e('Debug Mode', 'imgpro-cdn'); ?></label>
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
                                            <?php esc_html_e('Enable debug mode', 'imgpro-cdn'); ?>
                                        </label>
                                        <p class="description" id="debug-mode-description">
                                            <?php esc_html_e('Adds debug data to images (visible in browser console and Inspect Element).', 'imgpro-cdn'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>

                        <div class="imgpro-cdn-form-actions">
                            <?php submit_button(__('Save Settings', 'imgpro-cdn'), 'primary large', 'submit', false); ?>
                        </div>
                </div>
            </div>
        </form>
        <?php
    }

    public function ajax_toggle_enabled() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_toggle_enabled')) {
            wp_send_json_error(['message' => __('Security check failed', 'imgpro-cdn')]);
        }

        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'imgpro-cdn')]);
        }

        // Get enabled value
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] == '1';

        // Get current settings
        $current_settings = $this->settings->get_all();

        // Update only the enabled field
        $current_settings['enabled'] = $enabled;

        // Save settings
        $result = update_option(ImgPro_CDN_Settings::OPTION_KEY, $current_settings);

        if ($result !== false) {
            $message = $enabled
                ? __('Image CDN enabled. Your images now load from Cloudflare\'s global network.', 'imgpro-cdn')
                : __('Image CDN disabled. Images now load from your server.', 'imgpro-cdn');

            wp_send_json_success(['message' => $message]);
        } else {
            wp_send_json_error(['message' => __('Failed to update settings. Please try again.', 'imgpro-cdn')]);
        }
    }

    public function ajax_use_cloud() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'imgpro_cdn_toggle_enabled')) {
            wp_send_json_error(['message' => __('Security check failed', 'imgpro-cdn')]);
        }

        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'imgpro-cdn')]);
        }

        // Get current settings
        $current_settings = $this->settings->get_all();

        // Set ImgPro Cloud domains
        $current_settings['cdn_url'] = 'wp.img.pro';
        $current_settings['worker_url'] = 'fetch.wp.img.pro';
        $current_settings['enabled'] = true;

        // Save settings
        $result = update_option(ImgPro_CDN_Settings::OPTION_KEY, $current_settings);

        if ($result !== false) {
            wp_send_json_success([
                'message' => __('ImgPro Cloud configured successfully!', 'imgpro-cdn')
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to configure ImgPro Cloud. Please try again.', 'imgpro-cdn')]);
        }
    }
}
