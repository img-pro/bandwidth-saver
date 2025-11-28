/**
 * ImgPro CDN Admin JavaScript
 *
 * Handles admin interface interactions including onboarding wizard,
 * toggle switches, subscription management, and AJAX operations.
 *
 * @package ImgPro_CDN
 * @since   0.1.0
 */

(function($) {
    'use strict';

    // ===== Utility Functions =====

    /**
     * Show admin notice
     */
    function showNotice(type, message) {
        $('.imgpro-notice-dynamic').remove();

        const $notice = $('<div>', {
            'class': 'imgpro-notice imgpro-notice-' + type + ' imgpro-notice-dynamic'
        }).append(
            $('<p>').text(message)
        );

        // Insert at top of admin wrapper
        $('.imgpro-admin').prepend($notice);

        // Auto dismiss after 4 seconds
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 4000);
    }

    // ===== Onboarding Wizard =====

    /**
     * Initialize onboarding wizard
     */
    function initOnboarding() {
        const $wizard = $('.imgpro-onboarding');
        if (!$wizard.length) return;

        // Step 1: Get Started
        $('#imgpro-onboarding-start').on('click', function() {
            updateOnboardingStep(2);
        });

        // Step 2: Connect Form
        $('#imgpro-onboarding-connect-form').on('submit', function(e) {
            e.preventDefault();
            handleFreeRegistration($(this));
        });

        // Step 2: Recover Account
        $('#imgpro-onboarding-recover').on('click', function() {
            handleRecoverAccount($(this));
        });

        // Step 3: Activate Toggle
        $('#imgpro-activate-toggle').on('change', function() {
            if ($(this).is(':checked')) {
                handleActivateCDN();
            }
        });

        // Step 4: Complete
        $('#imgpro-onboarding-complete').on('click', function() {
            completeOnboarding();
        });

        // Note: Upgrade link in onboarding now uses .imgpro-open-plan-selector class
        // which is handled by initPlanSelector()
    }

    /**
     * Update onboarding step (client-side transition)
     */
    function updateOnboardingStep(step) {
        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgpro_cdn_update_onboarding_step',
                step: step,
                nonce: imgproCdnAdmin.onboardingNonce
            },
            success: function(response) {
                if (response.success) {
                    // Reload to show new step
                    window.location.reload();
                }
            }
        });
    }

    /**
     * Handle free registration form submission
     */
    function handleFreeRegistration($form) {
        const $button = $form.find('button[type="submit"]');
        const email = $('#imgpro-email').val();
        const marketingOptIn = $form.find('input[name="marketing_opt_in"]').is(':checked') ? '1' : '0';

        // Add loading state
        $button.addClass('is-loading').prop('disabled', true);

        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgpro_cdn_free_register',
                email: email,
                marketing_opt_in: marketingOptIn,
                nonce: imgproCdnAdmin.onboardingNonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.next_step) {
                        updateOnboardingStep(response.data.next_step);
                    } else {
                        window.location.reload();
                    }
                } else {
                    $button.removeClass('is-loading').prop('disabled', false);
                    showNotice('error', response.data.message || imgproCdnAdmin.i18n.registrationError);
                }
            },
            error: function() {
                $button.removeClass('is-loading').prop('disabled', false);
                showNotice('error', imgproCdnAdmin.i18n.genericError);
            }
        });
    }

    /**
     * Handle CDN activation from onboarding
     */
    function handleActivateCDN() {
        const $card = $('#imgpro-activate-card');
        $card.addClass('is-loading');

        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgpro_cdn_toggle_enabled',
                enabled: 1,
                current_tab: 'cloud',
                nonce: imgproCdnAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $card.removeClass('is-loading').addClass('is-active');
                    // Move to success step
                    setTimeout(function() {
                        updateOnboardingStep(4);
                    }, 500);
                } else {
                    $card.removeClass('is-loading');
                    $('#imgpro-activate-toggle').prop('checked', false);
                    showNotice('error', response.data.message || imgproCdnAdmin.i18n.settingsError);
                }
            },
            error: function() {
                $card.removeClass('is-loading');
                $('#imgpro-activate-toggle').prop('checked', false);
                showNotice('error', imgproCdnAdmin.i18n.genericError);
            }
        });
    }

    /**
     * Complete onboarding
     */
    function completeOnboarding() {
        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgpro_cdn_complete_onboarding',
                nonce: imgproCdnAdmin.onboardingNonce
            },
            success: function(response) {
                if (response.success && response.data.redirect) {
                    window.location.href = response.data.redirect;
                } else {
                    window.location.reload();
                }
            },
            error: function() {
                window.location.reload();
            }
        });
    }

    // ===== Dashboard & Settings =====

    /**
     * Initialize dashboard functionality
     */
    function initDashboard() {
        // Main toggle handler
        $('#imgpro-cdn-enabled').on('change', function() {
            handleToggle($(this));
        });

        // Free signup button
        $('#imgpro-free-signup').on('click', function() {
            handleFreeSignup($(this));
        });

        // Recover account
        $('#imgpro-recover-account').on('click', function() {
            handleRecoverAccount($(this));
        });

        // Manage subscription
        $('#imgpro-manage-subscription').on('click', function() {
            handleManageSubscription($(this));
        });

        // Subscription alert buttons (cancelled/past_due states)
        $('#imgpro-resubscribe').on('click', function() {
            // Resubscribe - redirect to checkout
            handleCheckout($(this));
        });

        $('#imgpro-update-payment, #imgpro-manage-subscription-alert').on('click', function() {
            // Update payment / manage - open Stripe portal
            handleManageSubscription($(this));
        });

        // Direct upgrade to next tier (from account card) - show confirmation modal
        $(document).on('click', '.imgpro-direct-upgrade', function(e) {
            e.preventDefault();
            const tierId = $(this).data('tier');
            if (tierId) {
                showUpgradeConfirmModal(tierId, $(this));
            }
        });

        // Upgrade confirmation modal handlers
        initUpgradeConfirmModal();

        // Advanced settings accordion
        initDetailsAccordion();

        // Custom domain handlers
        initCustomDomain();

        // Self-hosted CDN domain handlers
        initCdnDomain();

        // Sync stats on page load (if dashboard visible)
        if ($('#imgpro-stats-grid').length && imgproCdnAdmin.tier !== 'none') {
            // Sync stats every 5 minutes while page is open
            syncStats();
            setInterval(syncStats, 5 * 60 * 1000);
        }
    }

    /**
     * Handle main toggle
     */
    function handleToggle($toggle) {
        const $card = $('#imgpro-toggle-card');
        const isEnabled = $toggle.is(':checked');

        // Get current tab
        const urlParams = new URLSearchParams(window.location.search);
        let currentTab = urlParams.get('tab') || '';
        if (!currentTab) {
            const $activeTab = $('.imgpro-tab.is-active');
            currentTab = $activeTab.length ? ($activeTab.attr('href').includes('cloudflare') ? 'cloudflare' : 'cloud') : '';
        }

        $card.addClass('is-loading');

        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgpro_cdn_toggle_enabled',
                enabled: isEnabled ? 1 : 0,
                current_tab: currentTab,
                nonce: imgproCdnAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.redirect) {
                        showNotice('success', response.data.message);
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 500);
                        return;
                    }

                    updateToggleUI(isEnabled);
                    showNotice('success', response.data.message);
                } else {
                    // Revert toggle
                    $toggle.prop('checked', !isEnabled);
                    showNotice('error', response.data.message || imgproCdnAdmin.i18n.settingsError);
                }
            },
            error: function() {
                $toggle.prop('checked', !isEnabled);
                showNotice('error', imgproCdnAdmin.i18n.genericError);
            },
            complete: function() {
                $card.removeClass('is-loading');
            }
        });
    }

    /**
     * Update toggle UI
     */
    function updateToggleUI(isEnabled) {
        const $card = $('#imgpro-toggle-card');
        const $heading = $('#imgpro-toggle-heading');
        const $description = $('#imgpro-toggle-description');
        const $statusBadge = $('#imgpro-status-badge');
        const $statusText = $statusBadge.find('.imgpro-status-text');

        if (isEnabled) {
            $card.removeClass('is-inactive').addClass('is-active');
            $heading.text(imgproCdnAdmin.i18n.cdnActiveHeading);
            $description.text(imgproCdnAdmin.i18n.cdnActiveDesc);
            $statusBadge.removeClass('imgpro-status-inactive').addClass('imgpro-status-active');
            $statusText.text(imgproCdnAdmin.i18n.activeLabel);
        } else {
            $card.removeClass('is-active').addClass('is-inactive');
            $heading.text(imgproCdnAdmin.i18n.cdnInactiveHeading);
            $description.text(imgproCdnAdmin.i18n.cdnInactiveDesc);
            $statusBadge.removeClass('imgpro-status-active').addClass('imgpro-status-inactive');
            $statusText.text(imgproCdnAdmin.i18n.inactiveLabel);
        }
    }

    /**
     * Handle free signup
     */
    function handleFreeSignup($button) {
        const originalText = $button.text();
        $button.prop('disabled', true).text(imgproCdnAdmin.i18n.creatingAccount);

        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgpro_cdn_free_register',
                nonce: imgproCdnAdmin.checkoutNonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', imgproCdnAdmin.i18n.accountCreated);
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    $button.prop('disabled', false).text(originalText);
                    showNotice('error', response.data.message || imgproCdnAdmin.i18n.registrationError);
                }
            },
            error: function() {
                $button.prop('disabled', false).text(originalText);
                showNotice('error', imgproCdnAdmin.i18n.genericError);
            }
        });
    }

    /**
     * Handle checkout (Pro upgrade)
     */
    function handleCheckout($button) {
        const originalText = $button.text();
        $button.prop('disabled', true).text(imgproCdnAdmin.i18n.creatingCheckout);

        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgpro_cdn_checkout',
                nonce: imgproCdnAdmin.checkoutNonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.checkout_url) {
                        window.location.href = response.data.checkout_url;
                    } else if (response.data.recovered) {
                        window.location.reload();
                    }
                } else {
                    $button.prop('disabled', false).text(originalText);
                    showNotice('error', response.data.message || imgproCdnAdmin.i18n.checkoutError);
                }
            },
            error: function() {
                $button.prop('disabled', false).text(originalText);
                showNotice('error', imgproCdnAdmin.i18n.genericError);
            }
        });
    }

    /**
     * Handle recover account
     */
    function handleRecoverAccount($button) {
        const originalText = $button.text();

        if (!confirm(imgproCdnAdmin.i18n.recoverConfirm)) {
            return;
        }

        $button.prop('disabled', true).text(imgproCdnAdmin.i18n.recovering);

        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgpro_cdn_recover_account',
                nonce: imgproCdnAdmin.onboardingNonce || imgproCdnAdmin.checkoutNonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    $button.prop('disabled', false).text(originalText);
                    showNotice('error', response.data.message || imgproCdnAdmin.i18n.recoverError);
                }
            },
            error: function() {
                $button.prop('disabled', false).text(originalText);
                showNotice('error', imgproCdnAdmin.i18n.genericError);
            }
        });
    }

    /**
     * Handle manage subscription
     */
    function handleManageSubscription($button) {
        const originalText = $button.text();
        $button.prop('disabled', true).text(imgproCdnAdmin.i18n.openingPortal);

        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgpro_cdn_manage_subscription',
                nonce: imgproCdnAdmin.checkoutNonce
            },
            success: function(response) {
                if (response.success && response.data.portal_url) {
                    window.location.href = response.data.portal_url;
                } else {
                    $button.prop('disabled', false).text(originalText);
                    showNotice('error', response.data.message || imgproCdnAdmin.i18n.portalError);
                }
            },
            error: function() {
                $button.prop('disabled', false).text(originalText);
                showNotice('error', imgproCdnAdmin.i18n.genericError);
            }
        });
    }

    /**
     * Sync stats from API
     */
    function syncStats() {
        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgpro_cdn_sync_stats',
                nonce: imgproCdnAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data.formatted) {
                    // Update stats display
                    const $storageVal = $('#imgpro-stat-storage');
                    if ($storageVal.length) {
                        $storageVal.html(response.data.formatted.storage_used +
                            '<span class="imgpro-stat-limit">/ ' + response.data.formatted.storage_limit + '</span>');
                    }

                    $('#imgpro-stat-images').text(response.data.images_cached.toLocaleString());
                    $('#imgpro-stat-bandwidth').text(response.data.formatted.bandwidth_saved);

                    // Update progress bar
                    const $progressFill = $('.imgpro-progress-fill');
                    if ($progressFill.length) {
                        const percentage = Math.min(100, response.data.storage_percentage);
                        $progressFill.css('width', percentage + '%');

                        $progressFill.removeClass('is-warning is-critical');
                        if (percentage >= 90) {
                            $progressFill.addClass('is-critical');
                        } else if (percentage >= 70) {
                            $progressFill.addClass('is-warning');
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize details/accordion elements
     */
    function initDetailsAccordion() {
        // Details elements handle their own open/close, but we can enhance
        $('.imgpro-details summary').on('click', function() {
            const $details = $(this).parent();
            const $icon = $(this).find('svg');

            // Rotate icon on toggle
            if ($details.attr('open') !== undefined) {
                $icon.css('transform', 'rotate(0deg)');
            } else {
                $icon.css('transform', 'rotate(90deg)');
            }
        });
    }

    // ===== Custom Domain =====

    /**
     * Initialize custom domain handlers
     */
    function initCustomDomain() {
        // Add domain
        $('#imgpro-add-domain').on('click', function() {
            handleAddDomain($(this));
        });

        // Enter key on domain input
        $('#imgpro-custom-domain-input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#imgpro-add-domain').click();
            }
        });

        // Check domain status
        $('#imgpro-check-domain-pending').on('click', function() {
            handleCheckDomainStatus($(this));
        });

        // Remove domain
        $('#imgpro-remove-domain').on('click', function() {
            handleRemoveDomain($(this));
        });
    }

    /**
     * Handle add custom domain
     */
    function handleAddDomain($button) {
        const $input = $('#imgpro-custom-domain-input');
        const $section = $('#imgpro-custom-domain-section');
        const domain = $input.val().trim();

        if (!domain) {
            $input.focus();
            return;
        }

        const originalText = $button.text();
        $button.prop('disabled', true).text(imgproCdnAdmin.i18n.addingDomain);
        $input.prop('disabled', true);
        $section.addClass('is-loading');

        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgpro_cdn_add_custom_domain',
                domain: domain,
                nonce: imgproCdnAdmin.customDomainNonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', imgproCdnAdmin.i18n.domainAdded);
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    $button.prop('disabled', false).text(originalText);
                    $input.prop('disabled', false);
                    $section.removeClass('is-loading');
                    showNotice('error', response.data.message || imgproCdnAdmin.i18n.genericError);
                }
            },
            error: function() {
                $button.prop('disabled', false).text(originalText);
                $input.prop('disabled', false);
                $section.removeClass('is-loading');
                showNotice('error', imgproCdnAdmin.i18n.genericError);
            }
        });
    }

    /**
     * Handle check domain status
     */
    function handleCheckDomainStatus($button) {
        const originalText = $button.text();
        $button.prop('disabled', true).text(imgproCdnAdmin.i18n.checkingStatus);

        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgpro_cdn_check_custom_domain',
                nonce: imgproCdnAdmin.customDomainNonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.status === 'active') {
                        showNotice('success', imgproCdnAdmin.i18n.domainActive);
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        $button.prop('disabled', false).text(originalText);
                        // Status unchanged
                        const $statusDiv = $('#imgpro-custom-domain-status');
                        if ($statusDiv.length && $statusDiv.data('status') !== response.data.status) {
                            window.location.reload();
                        }
                    }
                } else {
                    $button.prop('disabled', false).text(originalText);
                    showNotice('error', response.data.message || imgproCdnAdmin.i18n.genericError);
                }
            },
            error: function() {
                $button.prop('disabled', false).text(originalText);
                showNotice('error', imgproCdnAdmin.i18n.genericError);
            }
        });
    }

    /**
     * Handle remove custom domain
     */
    function handleRemoveDomain($button) {
        if (!confirm(imgproCdnAdmin.i18n.confirmRemoveDomain)) {
            return;
        }

        const $section = $('#imgpro-custom-domain-section');
        const originalText = $button.text();

        $button.prop('disabled', true).text(imgproCdnAdmin.i18n.removingDomain);
        $section.addClass('is-loading');

        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgpro_cdn_remove_custom_domain',
                nonce: imgproCdnAdmin.customDomainNonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', imgproCdnAdmin.i18n.domainRemoved);
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    $button.prop('disabled', false).text(originalText);
                    $section.removeClass('is-loading');
                    showNotice('error', response.data.message || imgproCdnAdmin.i18n.genericError);
                }
            },
            error: function() {
                $button.prop('disabled', false).text(originalText);
                $section.removeClass('is-loading');
                showNotice('error', imgproCdnAdmin.i18n.genericError);
            }
        });
    }

    // ===== Self-Hosted CDN Domain =====

    /**
     * Initialize self-hosted CDN domain handlers
     */
    function initCdnDomain() {
        // Remove CDN domain
        $('#imgpro-remove-cdn-domain').on('click', function() {
            handleRemoveCdnDomain($(this));
        });
    }

    /**
     * Handle remove CDN domain (self-hosted)
     */
    function handleRemoveCdnDomain($button) {
        if (!confirm(imgproCdnAdmin.i18n.confirmRemoveCdnDomain || 'Remove this CDN domain? The Image CDN will be disabled.')) {
            return;
        }

        const $section = $('#imgpro-cdn-domain-section');
        $section.addClass('is-loading');
        $button.prop('disabled', true);

        // Use AJAX to clear the CDN URL
        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgpro_cdn_remove_cdn_domain',
                nonce: imgproCdnAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message || 'CDN domain removed.');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    $section.removeClass('is-loading');
                    $button.prop('disabled', false);
                    showNotice('error', response.data.message || imgproCdnAdmin.i18n.genericError);
                }
            },
            error: function() {
                $section.removeClass('is-loading');
                $button.prop('disabled', false);
                showNotice('error', imgproCdnAdmin.i18n.genericError);
            }
        });
    }

    // ===== URL Handling =====

    /**
     * Handle payment success/cancel from URL
     */
    function handlePaymentStatus() {
        const urlParams = new URLSearchParams(window.location.search);

        if (urlParams.get('payment') === 'success') {
            showNotice('success', imgproCdnAdmin.i18n.subscriptionActivated);
            // Clean up URL
            const cleanUrl = imgproCdnAdmin.settingsUrl + '&tab=cloud';
            window.history.replaceState({}, document.title, cleanUrl);
        } else if (urlParams.get('payment') === 'cancelled') {
            showNotice('info', imgproCdnAdmin.i18n.checkoutCancelled);
            const cleanUrl = imgproCdnAdmin.settingsUrl + '&tab=cloud';
            window.history.replaceState({}, document.title, cleanUrl);
        }

        if (urlParams.get('activated')) {
            // URL already shows activated, no additional notice needed
            const cleanUrl = imgproCdnAdmin.settingsUrl + '&tab=cloud';
            window.history.replaceState({}, document.title, cleanUrl);
        }
    }

    // ===== Plan Selector =====

    /**
     * Selected tier for plan selector
     */
    let selectedTierId = null;

    /**
     * Initialize plan selector modal and interactions
     */
    function initPlanSelector() {
        const $modal = $('#imgpro-plan-modal');
        const $selector = $('.imgpro-plan-selector');

        if (!$selector.length) return;

        // Open plan selector modal
        $(document).on('click', '.imgpro-open-plan-selector', function(e) {
            e.preventDefault();
            openPlanModal();
        });

        // Close modal on X button
        $(document).on('click', '.imgpro-plan-selector__close', function(e) {
            e.preventDefault();
            closePlanModal();
        });

        // Close modal on backdrop click
        $(document).on('click', '.imgpro-plan-modal__backdrop', function() {
            closePlanModal();
        });

        // Close modal on Escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $modal.is(':visible')) {
                closePlanModal();
            }
        });

        // Select plan card
        $(document).on('click', '.imgpro-plan-card__select', function(e) {
            e.preventDefault();
            const $card = $(this).closest('.imgpro-plan-card');
            selectPlanCard($card);
        });

        // Also allow clicking the entire card (except current plan)
        $(document).on('click', '.imgpro-plan-card:not(.imgpro-plan-card--current)', function(e) {
            // Ignore if clicking the button (handled above)
            if ($(e.target).closest('.imgpro-plan-card__select').length) return;
            selectPlanCard($(this));
        });

        // Checkout button
        $(document).on('click', '#imgpro-plan-checkout', function(e) {
            e.preventDefault();
            if (selectedTierId) {
                handlePlanCheckout($(this), selectedTierId);
            }
        });

        // Pre-select first available card if none is current
        initDefaultSelection();
    }

    /**
     * Initialize default plan selection
     */
    function initDefaultSelection() {
        const $selector = $('.imgpro-plan-selector');
        if (!$selector.length) return;

        const currentTier = $selector.data('current-tier') || '';

        // If user is on free tier or no tier, pre-select Pro (highlighted)
        if (!currentTier || currentTier === 'free') {
            const $highlighted = $selector.find('.imgpro-plan-card--highlight');
            if ($highlighted.length) {
                selectPlanCard($highlighted, false);
            } else {
                // Fall back to first non-current card
                const $firstAvailable = $selector.find('.imgpro-plan-card:not(.imgpro-plan-card--current)').first();
                if ($firstAvailable.length) {
                    selectPlanCard($firstAvailable, false);
                }
            }
        }
    }

    /**
     * Open the plan modal
     */
    function openPlanModal() {
        const $modal = $('#imgpro-plan-modal');
        if (!$modal.length) return;

        $modal.fadeIn(200);
        $('body').addClass('imgpro-modal-open');

        // Re-initialize default selection when opening
        initDefaultSelection();
    }

    /**
     * Close the plan modal
     */
    function closePlanModal() {
        const $modal = $('#imgpro-plan-modal');
        $modal.fadeOut(200);
        $('body').removeClass('imgpro-modal-open');
    }

    // ===== Upgrade Confirmation Modal =====

    let pendingUpgradeTierId = null;
    let pendingUpgradeButton = null;

    /**
     * Initialize upgrade confirmation modal handlers
     */
    function initUpgradeConfirmModal() {
        const $modal = $('#imgpro-upgrade-confirm-modal');
        if (!$modal.length) return;

        // Cancel button
        $('#imgpro-upgrade-cancel').on('click', function() {
            closeUpgradeConfirmModal();
        });

        // Confirm button
        $('#imgpro-upgrade-confirm').on('click', function() {
            if (pendingUpgradeTierId) {
                const $btn = $(this);
                $btn.addClass('is-loading').prop('disabled', true);
                $('#imgpro-upgrade-cancel').prop('disabled', true);
                handlePlanCheckout($btn, pendingUpgradeTierId);
            }
        });

        // Close on backdrop click
        $modal.find('.imgpro-confirm-modal__backdrop').on('click', function() {
            closeUpgradeConfirmModal();
        });

        // Close on Escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $modal.is(':visible')) {
                closeUpgradeConfirmModal();
            }
        });
    }

    /**
     * Show upgrade confirmation modal
     */
    function showUpgradeConfirmModal(tierId, $triggerButton) {
        const $modal = $('#imgpro-upgrade-confirm-modal');
        if (!$modal.length) return;

        // Store pending upgrade info
        pendingUpgradeTierId = tierId;
        pendingUpgradeButton = $triggerButton;

        // Get tier data from localized script
        const tiers = imgproCdnAdmin.tiers || {};
        const currentTierId = imgproCdnAdmin.tier;
        const currentTier = tiers[currentTierId];
        const newTier = tiers[tierId];

        // Update new plan info
        const tierName = newTier?.name || tierId.charAt(0).toUpperCase() + tierId.slice(1);
        const tierPrice = newTier?.price?.formatted || '';
        const tierPeriod = newTier?.price?.period || '/mo';

        $('#imgpro-confirm-tier-name').text(tierName);
        $('#imgpro-confirm-tier-price-amount').text(tierPrice);
        $('#imgpro-confirm-tier-price-period').text(tierPeriod);
        $('#imgpro-confirm-metrics').html(buildMetricsHtml(newTier));
        $('#imgpro-confirm-checklist').html(buildChecklistHtml(newTier));

        // Populate current plan reference (compact inline format)
        const currentName = currentTier?.name || currentTierId?.charAt(0).toUpperCase() + currentTierId?.slice(1) || 'Current';
        const currentLimits = buildLimitsString(currentTier);
        $('#imgpro-confirm-current-name').text(currentName);
        $('#imgpro-confirm-current-limits').text(currentLimits ? '· ' + currentLimits : '');

        // Reset button states
        const $confirmBtn = $('#imgpro-upgrade-confirm');
        $confirmBtn.removeClass('is-loading').prop('disabled', false);
        $('#imgpro-upgrade-cancel').prop('disabled', false);

        // Show modal
        $modal.fadeIn(200);
        $('body').addClass('imgpro-modal-open');
    }

    /**
     * Build HTML for metrics (storage + bandwidth)
     */
    function buildMetricsHtml(tier) {
        if (!tier) return '';

        let html = '';

        if (tier.limits?.storage?.formatted) {
            html += '<div class="imgpro-confirm-modal__metric">' +
                        '<div class="imgpro-confirm-modal__metric-value">' + tier.limits.storage.formatted + '</div>' +
                        '<div class="imgpro-confirm-modal__metric-label">Storage</div>' +
                    '</div>';
        }

        if (tier.limits?.bandwidth?.formatted) {
            html += '<div class="imgpro-confirm-modal__metric">' +
                        '<div class="imgpro-confirm-modal__metric-value">' + tier.limits.bandwidth.formatted + '</div>' +
                        '<div class="imgpro-confirm-modal__metric-label">Bandwidth/mo</div>' +
                    '</div>';
        }

        return html;
    }

    /**
     * Build HTML for checklist (custom domain, priority support)
     */
    function buildChecklistHtml(tier) {
        if (!tier) return '';

        const items = [];

        if (tier.features?.custom_domain) {
            items.push(
                '<li>' +
                    '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13.333 4L6 11.333 2.667 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
                    'Custom domain' +
                '</li>'
            );
        }

        if (tier.features?.priority_support) {
            items.push(
                '<li>' +
                    '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13.333 4L6 11.333 2.667 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
                    'Priority support' +
                '</li>'
            );
        }

        return items.join('');
    }

    /**
     * Build compact limits string for current plan reference
     */
    function buildLimitsString(tier) {
        if (!tier) return '';

        const parts = [];

        if (tier.limits?.storage?.formatted) {
            parts.push(tier.limits.storage.formatted);
        }

        if (tier.limits?.bandwidth?.formatted) {
            parts.push(tier.limits.bandwidth.formatted + '/mo');
        }

        return parts.join(', ');
    }

    /**
     * Close upgrade confirmation modal
     */
    function closeUpgradeConfirmModal() {
        const $modal = $('#imgpro-upgrade-confirm-modal');
        $modal.fadeOut(200);
        $('body').removeClass('imgpro-modal-open');

        // Clear pending upgrade
        pendingUpgradeTierId = null;
        pendingUpgradeButton = null;
    }

    /**
     * Select a plan card
     */
    function selectPlanCard($card, enableCheckout = true) {
        const $selector = $card.closest('.imgpro-plan-selector');

        // Remove selection from all cards and reset their buttons
        $selector.find('.imgpro-plan-card').each(function() {
            const $thisCard = $(this);
            $thisCard.removeClass('is-selected');

            // Reset button to default state (secondary, "Select")
            const $btn = $thisCard.find('.imgpro-plan-card__select');
            if ($btn.length && !$thisCard.hasClass('imgpro-plan-card--current')) {
                $btn.removeClass('imgpro-btn-primary').addClass('imgpro-btn-secondary');
                // Only change text for upgrade buttons, not downgrade
                if (!$thisCard.hasClass('imgpro-plan-card--downgrade')) {
                    $btn.text(imgproCdnAdmin.i18n?.select || 'Select');
                }
            }
        });

        // Add selection to clicked card
        $card.addClass('is-selected');

        // Update the selected card's button to active state
        const $selectedBtn = $card.find('.imgpro-plan-card__select');
        if ($selectedBtn.length && !$card.hasClass('imgpro-plan-card--downgrade')) {
            $selectedBtn.removeClass('imgpro-btn-secondary').addClass('imgpro-btn-primary');
            $selectedBtn.text(imgproCdnAdmin.i18n?.selected || 'Selected');
        }

        // Update selected tier
        selectedTierId = $card.data('tier-id');
        const tierName = $card.data('tier-name');
        const tierPrice = $card.data('tier-price');

        // Update footer selection display
        $('#imgpro-selected-plan-name').text(tierName);
        $('#imgpro-selected-plan-price').text(tierPrice);

        // Enable/disable checkout button
        const $checkoutBtn = $('#imgpro-plan-checkout');
        if (enableCheckout && selectedTierId) {
            $checkoutBtn.prop('disabled', false);
        }
    }

    /**
     * Handle checkout for selected plan
     */
    function handlePlanCheckout($button, tierId) {
        // Add loading state
        $button.addClass('is-loading').prop('disabled', true);

        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgpro_cdn_checkout',
                tier_id: tierId,
                nonce: imgproCdnAdmin.checkoutNonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.checkout_url) {
                        window.location.href = response.data.checkout_url;
                    } else if (response.data.upgraded) {
                        // Subscription upgraded directly - show success and reload
                        closePlanModal();
                        closeUpgradeConfirmModal();
                        showNotice('success', response.data.message || 'Subscription upgraded!');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else if (response.data.recovered) {
                        window.location.reload();
                    }
                } else {
                    $button.removeClass('is-loading').prop('disabled', false);
                    closePlanModal();
                    closeUpgradeConfirmModal();
                    showNotice('error', response.data.message || imgproCdnAdmin.i18n.checkoutError);
                }
            },
            error: function() {
                $button.removeClass('is-loading').prop('disabled', false);
                closePlanModal();
                closeUpgradeConfirmModal();
                showNotice('error', imgproCdnAdmin.i18n.genericError);
            }
        });
    }

    // ===== Initialize =====

    $(document).ready(function() {
        // Check for onboarding wizard
        if ($('.imgpro-onboarding').length) {
            initOnboarding();
        } else {
            // Dashboard & settings
            initDashboard();
            handlePaymentStatus();
        }

        // Plan selector (available on both onboarding and dashboard)
        initPlanSelector();
    });

})(jQuery);
