<?php
/**
 * ImgPro CDN Core
 *
 * @package ImgPro_CDN
 * @version 0.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class ImgPro_CDN_Core {

    /**
     * Plugin instance
     *
     * @var ImgPro_CDN_Core
     */
    private static $instance = null;

    /**
     * Settings instance
     *
     * @var ImgPro_CDN_Settings
     */
    private $settings;

    /**
     * Rewriter instance
     *
     * @var ImgPro_CDN_Rewriter
     */
    private $rewriter;

    /**
     * Admin instance
     *
     * @var ImgPro_CDN_Admin
     */
    private $admin;

    /**
     * Get plugin instance
     *
     * @return ImgPro_CDN_Core
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize plugin
     */
    private function init() {
        // Initialize settings
        $this->settings = new ImgPro_CDN_Settings();

        // Initialize rewriter
        $this->rewriter = new ImgPro_CDN_Rewriter($this->settings);
        $this->rewriter->init();

        // Initialize admin (only in admin area)
        if (is_admin()) {
            $this->admin = new ImgPro_CDN_Admin($this->settings);
            $this->admin->register_hooks();
        }

        // Register core hooks
        $this->register_hooks();
    }

    /**
     * Register plugin hooks
     */
    private function register_hooks() {
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . IMGPRO_CDN_PLUGIN_BASENAME, [$this, 'add_action_links']);

        // Handle plugin upgrades
        add_action('admin_init', [$this, 'check_version']);

        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue if CDN is enabled
        if (!$this->settings->get('enabled')) {
            return;
        }

        // Enqueue frontend CSS (only if file exists)
        $css_file = IMGPRO_CDN_PLUGIN_DIR . 'assets/css/imgpro-cdn-frontend.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'imgpro-cdn-frontend',
                IMGPRO_CDN_PLUGIN_URL . 'assets/css/imgpro-cdn-frontend.css',
                [],
                IMGPRO_CDN_VERSION,
                'all'
            );
        }

        // Enqueue frontend JavaScript (error handler + lazy loader)
        $js_file = IMGPRO_CDN_PLUGIN_DIR . 'assets/js/imgpro-cdn.js';
        if (file_exists($js_file)) {
            wp_enqueue_script(
                'imgpro-cdn',
                IMGPRO_CDN_PLUGIN_URL . 'assets/js/imgpro-cdn.js',
                [],
                IMGPRO_CDN_VERSION,
                false // Load in header
            );

            // Output inline stub BEFORE main script to provide immediate fallback
            // This ensures images can recover even if main script is slow/blocked
            $debug_enabled = $this->settings->get('debug_mode') && defined('WP_DEBUG') && WP_DEBUG;
            wp_add_inline_script(
                'imgpro-cdn',
                'window.imgproCdnConfig={debug:' . ($debug_enabled ? '1' : '0') . '};' .
                'window.ImgProCDN=window.ImgProCDN||{' .
                    'handleError:function(img){' .
                        'if(!img.dataset.fallback){' .
                            'try{' .
                                'var u=new URL(img.currentSrc||img.src);' .
                                'var p=u.pathname.substring(1).split("/");' .
                                'if(p.length>=2){' .
                                    'img.dataset.fallback="1";' .
                                    'img.classList.remove("imgpro-loaded");' .
                                    'img.removeAttribute("srcset");' .
                                    'img.removeAttribute("sizes");' .
                                    'img.src=u.protocol+"//"+p[0]+"/"+p.slice(1).join("/")+(u.search||"")+(u.hash||"");' .
                                '}' .
                            '}catch(e){}' .
                        '}' .
                    '}' .
                '};',
                'before'
            );
        }
    }

    /**
     * Add action links to plugins page
     *
     * @param array $links Existing plugin action links
     * @return array Modified plugin action links
     */
    public function add_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=imgpro-cdn-settings')),
            esc_html__('Settings', 'bandwidth-saver')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Check plugin version and run upgrades if needed
     */
    public function check_version() {
        $current_version = get_option('imgpro_cdn_version');

        if ($current_version !== IMGPRO_CDN_VERSION) {
            $this->upgrade($current_version);
            update_option('imgpro_cdn_version', IMGPRO_CDN_VERSION, false);
        }
    }

    /**
     * Run upgrade routines
     *
     * @param string|false $old_version Previous version number or false if new install
     */
    private function upgrade($old_version) {
        // Future upgrade routines can be added here
        // Example:
        // if (version_compare($old_version, '1.0.0', '<')) {
        //     $this->upgrade_to_1_0_0();
        // }

        /**
         * Fires after ImgPro CDN upgrade routines have completed
         *
         * @param string|false $old_version Previous version number or false if new install
         * @param string $new_version New version number
         */
        do_action('imgpro_cdn_upgraded', $old_version, IMGPRO_CDN_VERSION);
    }

    /**
     * Plugin activation
     */
    public static function activate() {
        // Check capabilities (shouldn't be needed but defense in depth)
        if (!current_user_can('activate_plugins')) {
            return;
        }

        // Store plugin version (autoload=false for performance)
        update_option('imgpro_cdn_version', IMGPRO_CDN_VERSION, false);

        /**
         * Fires after ImgPro CDN activation
         */
        do_action('imgpro_cdn_activated');
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Check capabilities
        if (!current_user_can('activate_plugins')) {
            return;
        }

        /**
         * Fires after ImgPro CDN deactivation
         */
        do_action('imgpro_cdn_deactivated');
    }

    /**
     * Get settings instance
     *
     * @return ImgPro_CDN_Settings Settings instance
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Get rewriter instance
     *
     * @return ImgPro_CDN_Rewriter Rewriter instance
     */
    public function get_rewriter() {
        return $this->rewriter;
    }

    /**
     * Get admin instance
     *
     * @return ImgPro_CDN_Admin|null Admin instance or null if not in admin area
     */
    public function get_admin() {
        return $this->admin;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     *
     * @throws Exception When attempting to unserialize singleton
     */
    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }
}
