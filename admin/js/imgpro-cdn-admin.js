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

        // Upgrade to Pro link (from onboarding)
        $('#imgpro-onboarding-upgrade').on('click', function(e) {
            e.preventDefault();
            handleCheckout($(this));
        });
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

        // Pro signup / upgrade buttons
        $('#imgpro-pro-signup, #imgpro-upgrade-cta, #imgpro-upgrade-btn').on('click', function() {
            handleCheckout($(this));
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
    });

})(jQuery);
