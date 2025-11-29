<?php
/**
 * ImgPro CDN Plan Selector Component
 *
 * Unified plan selection UI used throughout the plugin.
 *
 * @package ImgPro_CDN
 * @since   0.1.7
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plan Selector class
 *
 * @since 0.1.7
 */
class ImgPro_CDN_Plan_Selector {

    /**
     * API client instance
     *
     * @var ImgPro_CDN_API
     */
    private $api;

    /**
     * Settings instance
     *
     * @var ImgPro_CDN_Settings
     */
    private $settings;

    /**
     * Constructor
     *
     * @param ImgPro_CDN_Settings $settings Settings instance.
     */
    public function __construct(ImgPro_CDN_Settings $settings) {
        $this->settings = $settings;
        $this->api = ImgPro_CDN_API::instance();
    }

    /**
     * Render the plan selector
     *
     * @param string $context   Context: 'modal', 'inline', 'onboarding'.
     * @param string $current_tier Current tier ID (or empty for none).
     * @return void
     */
    public function render($context = 'modal', $current_tier = '') {
        $tiers = $this->api->get_tiers();
        $all_settings = $this->settings->get_all();

        if (empty($current_tier)) {
            $current_tier = $all_settings['cloud_tier'] ?? '';
        }

        // Filter to only paid tiers for upgrade context
        $paid_tiers = array_filter($tiers, function($tier) {
            return $tier['price']['cents'] > 0;
        });

        $wrapper_class = 'imgpro-plan-selector';
        if ('modal' === $context) {
            $wrapper_class .= ' imgpro-plan-selector--modal';
        } elseif ('onboarding' === $context) {
            $wrapper_class .= ' imgpro-plan-selector--onboarding';
        }
        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>" data-current-tier="<?php echo esc_attr($current_tier); ?>">
            <?php if ('modal' === $context): ?>
            <div class="imgpro-plan-selector__header">
                <h2><?php esc_html_e('Choose your plan', 'bandwidth-saver'); ?></h2>
                <button type="button" class="imgpro-plan-selector__close" aria-label="<?php esc_attr_e('Close', 'bandwidth-saver'); ?>">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <?php endif; ?>

            <div class="imgpro-plan-selector__grid">
                <?php foreach ($paid_tiers as $tier): ?>
                    <?php $this->render_tier_card($tier, $current_tier); ?>
                <?php endforeach; ?>
            </div>

            <div class="imgpro-plan-selector__footer">
                <div class="imgpro-plan-selector__selected">
                    <span class="imgpro-plan-selector__selected-label"><?php esc_html_e('Selected:', 'bandwidth-saver'); ?></span>
                    <span class="imgpro-plan-selector__selected-plan" id="imgpro-selected-plan-name">
                        <?php esc_html_e('Pro', 'bandwidth-saver'); ?>
                    </span>
                    <span class="imgpro-plan-selector__selected-price" id="imgpro-selected-plan-price">
                        <?php esc_html_e('$14.99/mo', 'bandwidth-saver'); ?>
                    </span>
                </div>
                <button type="button" class="imgpro-btn imgpro-btn-primary imgpro-btn-lg" id="imgpro-plan-checkout" disabled>
                    <span class="imgpro-btn-text"><?php esc_html_e('Continue to Checkout', 'bandwidth-saver'); ?></span>
                    <span class="imgpro-btn-loading">
                        <svg class="imgpro-spinner" width="20" height="20" viewBox="0 0 20 20">
                            <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="50" stroke-linecap="round"/>
                        </svg>
                        <?php esc_html_e('Redirecting...', 'bandwidth-saver'); ?>
                    </span>
                    <svg class="imgpro-btn-icon" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M4.167 10h11.666M10 4.167L15.833 10 10 15.833" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>

            <?php if ('free' === $current_tier || empty($current_tier)): ?>
            <p class="imgpro-plan-selector__hint">
                <?php esc_html_e('All plans include a 7-day money-back guarantee.', 'bandwidth-saver'); ?>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render a single tier card
     *
     * @param array  $tier         Tier data.
     * @param string $current_tier Current tier ID.
     * @return void
     */
    private function render_tier_card($tier, $current_tier) {
        $is_current = ($tier['id'] === $current_tier);
        $is_highlighted = !empty($tier['highlight']);
        $is_downgrade = $this->is_downgrade($tier['id'], $current_tier);

        $card_classes = ['imgpro-plan-card'];
        if ($is_current) {
            $card_classes[] = 'imgpro-plan-card--current';
        }
        if ($is_highlighted && !$is_current) {
            $card_classes[] = 'imgpro-plan-card--highlight';
        }
        if ($is_downgrade) {
            $card_classes[] = 'imgpro-plan-card--downgrade';
        }

        $price_display = $tier['price']['formatted'];
        $period = $tier['price']['period'] ?? '';
        ?>
        <div class="<?php echo esc_attr(implode(' ', $card_classes)); ?>"
             data-tier-id="<?php echo esc_attr($tier['id']); ?>"
             data-tier-name="<?php echo esc_attr($tier['name']); ?>"
             data-tier-price="<?php echo esc_attr($price_display . $period); ?>">

            <?php if ($is_highlighted && !$is_current): ?>
                <div class="imgpro-plan-card__badge"><?php esc_html_e('Popular', 'bandwidth-saver'); ?></div>
            <?php elseif ($is_current): ?>
                <div class="imgpro-plan-card__badge imgpro-plan-card__badge--current"><?php esc_html_e('Current', 'bandwidth-saver'); ?></div>
            <?php endif; ?>

            <div class="imgpro-plan-card__header">
                <h3 class="imgpro-plan-card__name"><?php echo esc_html($tier['name']); ?></h3>
                <?php if (!empty($tier['description'])): ?>
                    <p class="imgpro-plan-card__description"><?php echo esc_html($tier['description']); ?></p>
                <?php endif; ?>
            </div>

            <div class="imgpro-plan-card__price">
                <span class="imgpro-plan-card__amount"><?php echo esc_html($price_display); ?></span>
                <?php if ($period): ?>
                    <span class="imgpro-plan-card__period"><?php echo esc_html($period); ?></span>
                <?php endif; ?>
            </div>

            <ul class="imgpro-plan-card__features">
                <li class="imgpro-plan-card__feature">
                    <svg class="imgpro-plan-card__feature-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M13.333 4L6 11.333 2.667 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>
                        <strong><?php echo esc_html($tier['limits']['storage']['formatted']); ?></strong>
                        <?php esc_html_e('storage', 'bandwidth-saver'); ?>
                    </span>
                </li>
                <li class="imgpro-plan-card__feature">
                    <svg class="imgpro-plan-card__feature-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M13.333 4L6 11.333 2.667 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>
                        <strong><?php echo esc_html($tier['limits']['bandwidth']['formatted']); ?></strong>
                        <?php if (empty($tier['limits']['bandwidth']['unlimited'])): ?>
                            <?php esc_html_e('bandwidth/mo', 'bandwidth-saver'); ?>
                        <?php else: ?>
                            <?php esc_html_e('bandwidth', 'bandwidth-saver'); ?>
                        <?php endif; ?>
                    </span>
                </li>
                <?php if (!empty($tier['features']['custom_domain'])): ?>
                <li class="imgpro-plan-card__feature">
                    <svg class="imgpro-plan-card__feature-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M13.333 4L6 11.333 2.667 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Custom domain', 'bandwidth-saver'); ?></span>
                </li>
                <?php else: ?>
                <li class="imgpro-plan-card__feature imgpro-plan-card__feature--disabled">
                    <svg class="imgpro-plan-card__feature-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Custom domain', 'bandwidth-saver'); ?></span>
                </li>
                <?php endif; ?>
                <?php if (!empty($tier['features']['priority_support'])): ?>
                <li class="imgpro-plan-card__feature">
                    <svg class="imgpro-plan-card__feature-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M13.333 4L6 11.333 2.667 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Priority support', 'bandwidth-saver'); ?></span>
                </li>
                <?php endif; ?>
            </ul>

            <div class="imgpro-plan-card__action">
                <?php if ($is_current): ?>
                    <span class="imgpro-plan-card__current-label">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M13.333 4L6 11.333 2.667 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <?php esc_html_e('Current plan', 'bandwidth-saver'); ?>
                    </span>
                <?php elseif ($is_downgrade): ?>
                    <button type="button" class="imgpro-btn imgpro-btn-secondary imgpro-plan-card__select" data-tier="<?php echo esc_attr($tier['id']); ?>">
                        <?php esc_html_e('Downgrade', 'bandwidth-saver'); ?>
                    </button>
                <?php else: ?>
                    <button type="button" class="imgpro-btn <?php echo $is_highlighted ? 'imgpro-btn-primary' : 'imgpro-btn-secondary'; ?> imgpro-plan-card__select" data-tier="<?php echo esc_attr($tier['id']); ?>">
                        <?php esc_html_e('Select', 'bandwidth-saver'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Check if selecting a tier would be a downgrade
     *
     * @param string $target_tier  Target tier ID.
     * @param string $current_tier Current tier ID.
     * @return bool
     */
    private function is_downgrade($target_tier, $current_tier) {
        $tier_order = ['free' => 0, 'lite' => 1, 'pro' => 2, 'business' => 3];

        $target_order = $tier_order[$target_tier] ?? 0;
        $current_order = $tier_order[$current_tier] ?? 0;

        return $target_order < $current_order;
    }

    /**
     * Render the modal overlay wrapper
     *
     * Call this once in the admin page, then use JavaScript to show/hide.
     *
     * @return void
     */
    public function render_modal_wrapper() {
        ?>
        <div class="imgpro-plan-modal" id="imgpro-plan-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="imgpro-plan-modal-title">
            <div class="imgpro-plan-modal__backdrop"></div>
            <div class="imgpro-plan-modal__content">
                <?php $this->render('modal'); ?>
            </div>
        </div>
        <?php
        $this->render_upgrade_confirm_modal();
    }

    /**
     * Render the upgrade confirmation modal
     *
     * @return void
     */
    private function render_upgrade_confirm_modal() {
        ?>
        <div class="imgpro-confirm-modal" id="imgpro-upgrade-confirm-modal" style="display: none;" role="dialog" aria-modal="true">
            <div class="imgpro-confirm-modal__backdrop"></div>
            <div class="imgpro-confirm-modal__content">
                <!-- Header -->
                <div class="imgpro-confirm-modal__header">
                    <div class="imgpro-confirm-modal__badge"><?php esc_html_e('Upgrade', 'bandwidth-saver'); ?></div>
                    <h2 class="imgpro-confirm-modal__title" id="imgpro-confirm-tier-name"></h2>
                    <div class="imgpro-confirm-modal__price">
                        <span class="imgpro-confirm-modal__price-amount" id="imgpro-confirm-tier-price-amount"></span>
                        <span class="imgpro-confirm-modal__price-period" id="imgpro-confirm-tier-price-period"></span>
                    </div>
                </div>

                <!-- Metrics (storage + bandwidth) -->
                <div class="imgpro-confirm-modal__metrics" id="imgpro-confirm-metrics"></div>

                <!-- Current plan reference -->
                <div class="imgpro-confirm-modal__current">
                    <?php esc_html_e('Currently on', 'bandwidth-saver'); ?>
                    <strong id="imgpro-confirm-current-name"></strong>
                    <span id="imgpro-confirm-current-limits"></span>
                </div>

                <!-- Extra features (custom domain, priority support) -->
                <ul class="imgpro-confirm-modal__checklist" id="imgpro-confirm-checklist"></ul>

                <!-- Footer -->
                <div class="imgpro-confirm-modal__footer">
                    <p class="imgpro-confirm-modal__note">
                        <?php esc_html_e('Billed immediately, prorated for this period.', 'bandwidth-saver'); ?>
                    </p>
                    <div class="imgpro-confirm-modal__actions">
                        <button type="button" class="imgpro-btn imgpro-btn-ghost" id="imgpro-upgrade-cancel">
                            <?php esc_html_e('Cancel', 'bandwidth-saver'); ?>
                        </button>
                        <button type="button" class="imgpro-btn imgpro-btn-primary" id="imgpro-upgrade-confirm">
                            <span class="imgpro-btn-text"><?php esc_html_e('Confirm Upgrade', 'bandwidth-saver'); ?></span>
                            <span class="imgpro-btn-loading">
                                <svg class="imgpro-spinner" width="16" height="16" viewBox="0 0 20 20">
                                    <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="50" stroke-linecap="round"/>
                                </svg>
                                <?php esc_html_e('Upgrading...', 'bandwidth-saver'); ?>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render a compact upgrade CTA that opens the plan selector
     *
     * @param string $context Context for styling: 'card', 'inline', 'alert'.
     * @return void
     */
    public function render_upgrade_cta($context = 'card') {
        $all_settings = $this->settings->get_all();
        $current_tier = $all_settings['cloud_tier'] ?? 'free';

        // Don't show upgrade CTA if already on highest tier
        if ('business' === $current_tier) {
            return;
        }

        $cta_class = 'imgpro-upgrade-cta';
        if ($context) {
            $cta_class .= ' imgpro-upgrade-cta--' . $context;
        }
        ?>
        <div class="<?php echo esc_attr($cta_class); ?>">
            <?php if ('card' === $context): ?>
                <div class="imgpro-upgrade-cta__icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
                    </svg>
                </div>
                <div class="imgpro-upgrade-cta__content">
                    <h4><?php esc_html_e('Need more capacity?', 'bandwidth-saver'); ?></h4>
                    <p><?php esc_html_e('Upgrade for more storage, bandwidth, and features.', 'bandwidth-saver'); ?></p>
                </div>
            <?php endif; ?>
            <button type="button" class="imgpro-btn imgpro-btn-primary imgpro-open-plan-selector">
                <?php esc_html_e('See upgrade options', 'bandwidth-saver'); ?>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M3.333 8h9.334M8 3.333L12.667 8 8 12.667" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        <?php
    }
}
