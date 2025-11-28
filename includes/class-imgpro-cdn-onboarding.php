<?php
/**
 * ImgPro CDN Onboarding Wizard
 *
 * Handles the 4-step onboarding experience for new users.
 *
 * @package ImgPro_CDN
 * @since   0.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Onboarding wizard class
 *
 * @since 0.2.0
 */
class ImgPro_CDN_Onboarding {

    /**
     * Settings instance
     *
     * @since 0.2.0
     * @var ImgPro_CDN_Settings
     */
    private $settings;

    /**
     * Constructor
     *
     * @since 0.2.0
     * @param ImgPro_CDN_Settings $settings Settings instance.
     */
    public function __construct(ImgPro_CDN_Settings $settings) {
        $this->settings = $settings;
    }

    /**
     * Check if onboarding should be shown
     *
     * Show onboarding if:
     * - Not completed AND
     * - No existing subscription (free or paid) AND
     * - Not using self-hosted mode
     *
     * @since 0.2.0
     * @return bool
     */
    public function should_show_onboarding() {
        $all_settings = $this->settings->get_all();

        // Already completed onboarding
        if (!empty($all_settings['onboarding_completed'])) {
            return false;
        }

        // Has a subscription (free or paid)
        $tier = $all_settings['cloud_tier'] ?? '';
        if (in_array($tier, [ImgPro_CDN_Settings::TIER_FREE, ImgPro_CDN_Settings::TIER_PRO, ImgPro_CDN_Settings::TIER_ACTIVE], true)) {
            return false;
        }

        // Using self-hosted mode with valid configuration
        if (ImgPro_CDN_Settings::MODE_CLOUDFLARE === ($all_settings['setup_mode'] ?? '') && !empty($all_settings['cdn_url'])) {
            return false;
        }

        return true;
    }

    /**
     * Get current onboarding step
     *
     * @since 0.2.0
     * @return int Step number (1-4).
     */
    public function get_current_step() {
        return (int) $this->settings->get('onboarding_step', 1);
    }

    /**
     * Render the onboarding wizard
     *
     * @since 0.2.0
     * @return void
     */
    public function render() {
        $step = $this->get_current_step();
        ?>
        <div class="imgpro-onboarding-wrapper">
            <div class="imgpro-onboarding" data-step="<?php echo esc_attr($step); ?>">
                <?php
                switch ($step) {
                    case 1:
                        $this->render_step_welcome();
                        break;
                    case 2:
                        $this->render_step_connect();
                        break;
                    case 3:
                        $this->render_step_activate();
                        break;
                    case 4:
                        $this->render_step_success();
                        break;
                    default:
                        $this->render_step_welcome();
                }
                ?>
                <?php $this->render_progress_dots($step); ?>
            </div>
            <?php $this->render_skip_link(); ?>
        </div>
        <?php
    }

    /**
     * Step 1: Welcome - Show value proposition
     *
     * @since 0.2.0
     * @return void
     */
    private function render_step_welcome() {
        ?>
        <div class="imgpro-onboarding-content imgpro-onboarding-step-1">
            <div class="imgpro-onboarding-icon">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="32" cy="32" r="28" stroke="currentColor" stroke-width="2" fill="none" opacity="0.2"/>
                    <circle cx="32" cy="32" r="20" stroke="currentColor" stroke-width="2" fill="none" opacity="0.4"/>
                    <circle cx="32" cy="32" r="12" fill="currentColor"/>
                    <path d="M32 8 L32 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M32 62 L32 56" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M8 32 L2 32" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M62 32 L56 32" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>

            <h1><?php esc_html_e('Your images are about to get faster', 'bandwidth-saver'); ?></h1>

            <p class="imgpro-onboarding-description">
                <?php esc_html_e('Deliver WordPress images through Cloudflare\'s global edge network. No DNS changes, no configuration needed.', 'bandwidth-saver'); ?>
            </p>

            <ul class="imgpro-onboarding-benefits">
                <li>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M16.667 5L7.5 14.167 3.333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span><strong><?php esc_html_e('1 GB free', 'bandwidth-saver'); ?></strong> <?php esc_html_e('storage (enough for ~1,500 images)', 'bandwidth-saver'); ?></span>
                </li>
                <li>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M16.667 5L7.5 14.167 3.333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span><?php esc_html_e('Unlimited bandwidth, forever', 'bandwidth-saver'); ?></span>
                </li>
                <li>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M16.667 5L7.5 14.167 3.333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span><?php esc_html_e('300+ edge locations worldwide', 'bandwidth-saver'); ?></span>
                </li>
                <li>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M16.667 5L7.5 14.167 3.333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span><?php esc_html_e('Automatic fallback if CDN is ever down', 'bandwidth-saver'); ?></span>
                </li>
            </ul>

            <div class="imgpro-onboarding-actions">
                <button type="button" class="imgpro-btn imgpro-btn-primary imgpro-btn-lg" id="imgpro-onboarding-start">
                    <?php esc_html_e('Get Started Free', 'bandwidth-saver'); ?>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4.167 10h11.666M10 4.167L15.833 10 10 15.833" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>

            <p class="imgpro-onboarding-hint">
                <?php esc_html_e('Need more storage? Pro includes 100 GB for $9.99/mo', 'bandwidth-saver'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Step 2: Connect - Create free account
     *
     * @since 0.2.0
     * @return void
     */
    private function render_step_connect() {
        $admin_email = get_option('admin_email');
        $site_url = get_site_url();
        ?>
        <div class="imgpro-onboarding-content imgpro-onboarding-step-2">
            <div class="imgpro-onboarding-step-header">
                <span class="imgpro-step-badge"><?php esc_html_e('Step 2 of 4', 'bandwidth-saver'); ?></span>
            </div>

            <h1><?php esc_html_e('Create your free account', 'bandwidth-saver'); ?></h1>

            <p class="imgpro-onboarding-description">
                <?php esc_html_e('We just need your email to set up your CDN. No credit card required.', 'bandwidth-saver'); ?>
            </p>

            <form id="imgpro-onboarding-connect-form" class="imgpro-onboarding-form">
                <div class="imgpro-form-group">
                    <label for="imgpro-email"><?php esc_html_e('Email', 'bandwidth-saver'); ?></label>
                    <input
                        type="email"
                        id="imgpro-email"
                        name="email"
                        value="<?php echo esc_attr($admin_email); ?>"
                        required
                        autocomplete="email"
                    >
                </div>

                <div class="imgpro-form-group">
                    <label for="imgpro-site-url"><?php esc_html_e('Site URL', 'bandwidth-saver'); ?></label>
                    <input
                        type="text"
                        id="imgpro-site-url"
                        value="<?php echo esc_attr($site_url); ?>"
                        readonly
                        disabled
                        class="imgpro-input-readonly"
                    >
                    <span class="imgpro-input-hint"><?php esc_html_e('Auto-detected from WordPress', 'bandwidth-saver'); ?></span>
                </div>

                <div class="imgpro-form-group imgpro-form-checkbox">
                    <label class="imgpro-checkbox-label">
                        <input type="checkbox" name="marketing_opt_in" value="1">
                        <span class="imgpro-checkbox-text"><?php esc_html_e('Send me tips to speed up my site', 'bandwidth-saver'); ?></span>
                    </label>
                </div>

                <div class="imgpro-onboarding-actions">
                    <button type="submit" class="imgpro-btn imgpro-btn-primary imgpro-btn-lg imgpro-btn-full">
                        <span class="imgpro-btn-text"><?php esc_html_e('Create Free Account', 'bandwidth-saver'); ?></span>
                        <span class="imgpro-btn-loading">
                            <svg class="imgpro-spinner" width="20" height="20" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="50" stroke-linecap="round"/></svg>
                            <?php esc_html_e('Creating...', 'bandwidth-saver'); ?>
                        </span>
                        <svg class="imgpro-btn-icon" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4.167 10h11.666M10 4.167L15.833 10 10 15.833" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>

                <p class="imgpro-onboarding-terms">
                    <?php
                    printf(
                        wp_kses(
                            /* translators: %s: Terms of Service link */
                            __('By continuing, you agree to our <a href="%s" target="_blank">Terms of Service</a>', 'bandwidth-saver'),
                            ['a' => ['href' => [], 'target' => []]]
                        ),
                        'https://wordpress.org/plugins/bandwidth-saver/'
                    );
                    ?>
                </p>
            </form>

            <div class="imgpro-onboarding-recovery">
                <span><?php esc_html_e('Already have an account?', 'bandwidth-saver'); ?></span>
                <button type="button" class="imgpro-btn-link" id="imgpro-onboarding-recover">
                    <?php esc_html_e('Recover it', 'bandwidth-saver'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Step 3: Activate - Enable the CDN
     *
     * @since 0.2.0
     * @return void
     */
    private function render_step_activate() {
        ?>
        <div class="imgpro-onboarding-content imgpro-onboarding-step-3">
            <div class="imgpro-onboarding-step-header">
                <span class="imgpro-step-badge"><?php esc_html_e('Step 3 of 4', 'bandwidth-saver'); ?></span>
            </div>

            <h1><?php esc_html_e('Ready to activate', 'bandwidth-saver'); ?></h1>

            <p class="imgpro-onboarding-description">
                <?php esc_html_e('Your account is set up. Flip the switch to start serving images from Cloudflare.', 'bandwidth-saver'); ?>
            </p>

            <div class="imgpro-activate-card" id="imgpro-activate-card">
                <div class="imgpro-activate-info">
                    <div class="imgpro-activate-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div class="imgpro-activate-text">
                        <strong><?php esc_html_e('Image CDN', 'bandwidth-saver'); ?></strong>
                        <span><?php esc_html_e('Serve images from Cloudflare\'s global network', 'bandwidth-saver'); ?></span>
                    </div>
                </div>
                <label class="imgpro-toggle" for="imgpro-activate-toggle">
                    <input type="checkbox" id="imgpro-activate-toggle">
                    <span class="imgpro-toggle-slider"></span>
                    <span class="screen-reader-text"><?php esc_html_e('Enable Image CDN', 'bandwidth-saver'); ?></span>
                </label>
            </div>

            <div class="imgpro-activate-details">
                <h3><?php esc_html_e('What happens when you activate:', 'bandwidth-saver'); ?></h3>
                <ul>
                    <li>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="3" fill="currentColor"/></svg>
                        <?php esc_html_e('Image URLs are rewritten on your frontend', 'bandwidth-saver'); ?>
                    </li>
                    <li>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="3" fill="currentColor"/></svg>
                        <?php esc_html_e('First visitor request caches each image', 'bandwidth-saver'); ?>
                    </li>
                    <li>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="3" fill="currentColor"/></svg>
                        <?php esc_html_e('Subsequent requests load from nearest edge', 'bandwidth-saver'); ?>
                    </li>
                    <li>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="3" fill="currentColor"/></svg>
                        <?php esc_html_e('Your original images stay on your server', 'bandwidth-saver'); ?>
                    </li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Step 4: Success - Show confirmation and stats
     *
     * @since 0.2.0
     * @return void
     */
    private function render_step_success() {
        $site_url = get_site_url();
        $all_settings = $this->settings->get_all();
        $storage_limit = ImgPro_CDN_Settings::get_storage_limit($all_settings);
        ?>
        <div class="imgpro-onboarding-content imgpro-onboarding-step-4">
            <div class="imgpro-onboarding-icon imgpro-onboarding-icon-success">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none">
                    <circle cx="32" cy="32" r="28" stroke="currentColor" stroke-width="2" fill="none"/>
                    <path d="M20 32l8 8 16-16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>

            <h1><?php esc_html_e('You\'re all set!', 'bandwidth-saver'); ?></h1>

            <p class="imgpro-onboarding-description">
                <?php esc_html_e('Your images are now being delivered from Cloudflare\'s edge network.', 'bandwidth-saver'); ?>
            </p>

            <div class="imgpro-success-stats" id="imgpro-success-stats">
                <div class="imgpro-stat">
                    <span class="imgpro-stat-value imgpro-stat-active">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" fill="currentColor"/></svg>
                        <?php esc_html_e('Active', 'bandwidth-saver'); ?>
                    </span>
                    <span class="imgpro-stat-label"><?php esc_html_e('CDN Status', 'bandwidth-saver'); ?></span>
                </div>
                <div class="imgpro-stat">
                    <span class="imgpro-stat-value" id="imgpro-stat-images">0</span>
                    <span class="imgpro-stat-label"><?php esc_html_e('Images Cached', 'bandwidth-saver'); ?></span>
                </div>
                <div class="imgpro-stat">
                    <span class="imgpro-stat-value" id="imgpro-stat-storage">0 / <?php echo esc_html(ImgPro_CDN_Settings::format_bytes($storage_limit, 0)); ?></span>
                    <span class="imgpro-stat-label"><?php esc_html_e('Storage Used', 'bandwidth-saver'); ?></span>
                </div>
            </div>

            <div class="imgpro-success-tip">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 14a6 6 0 110-12 6 6 0 010 12zm-1-9h2v5H9V7zm0 6h2v2H9v-2z" fill="currentColor"/></svg>
                <span><?php esc_html_e('Visit your site to start caching images', 'bandwidth-saver'); ?></span>
            </div>

            <div class="imgpro-onboarding-actions imgpro-onboarding-actions-split">
                <a href="<?php echo esc_url($site_url); ?>" target="_blank" class="imgpro-btn imgpro-btn-secondary imgpro-btn-lg">
                    <?php esc_html_e('View Site', 'bandwidth-saver'); ?>
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M12 8.667V12a1.333 1.333 0 01-1.333 1.333H4A1.333 1.333 0 012.667 12V5.333A1.333 1.333 0 014 4h3.333M10 2h4v4M6.667 9.333L14 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
                <button type="button" class="imgpro-btn imgpro-btn-primary imgpro-btn-lg" id="imgpro-onboarding-complete">
                    <?php esc_html_e('Go to Dashboard', 'bandwidth-saver'); ?>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4.167 10h11.666M10 4.167L15.833 10 10 15.833" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render progress dots
     *
     * @since 0.2.0
     * @param int $current_step Current step number.
     * @return void
     */
    private function render_progress_dots($current_step) {
        ?>
        <div class="imgpro-progress-dots" role="progressbar" aria-valuenow="<?php echo esc_attr($current_step); ?>" aria-valuemin="1" aria-valuemax="4">
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <?php
                /* translators: %d: step number */
                $step_label = sprintf(esc_attr__('Step %d', 'bandwidth-saver'), $i);
                ?>
                <span class="imgpro-progress-dot <?php echo $i < $current_step ? 'completed' : ''; ?> <?php echo $i === $current_step ? 'active' : ''; ?>" aria-label="<?php echo esc_attr($step_label); ?>"></span>
            <?php endfor; ?>
        </div>
        <?php
    }

    /**
     * Render skip link for users who want self-hosted
     *
     * @since 0.2.0
     * @return void
     */
    private function render_skip_link() {
        $self_host_url = add_query_arg([
            'tab' => ImgPro_CDN_Settings::MODE_CLOUDFLARE,
            'skip_onboarding' => '1',
            '_wpnonce' => wp_create_nonce('imgpro_skip_onboarding')
        ], admin_url('options-general.php?page=imgpro-cdn-settings'));
        ?>
        <div class="imgpro-onboarding-skip">
            <span><?php esc_html_e('Prefer to use your own Cloudflare account?', 'bandwidth-saver'); ?></span>
            <a href="<?php echo esc_url($self_host_url); ?>"><?php esc_html_e('Self-host instead', 'bandwidth-saver'); ?></a>
        </div>
        <?php
    }
}
