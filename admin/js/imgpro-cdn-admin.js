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

    // ===== Constants =====

    var AJAX_TIMEOUT = 30000; // 30 seconds for API-dependent operations

    // ===== Utility Functions =====

    /**
     * Escape HTML special characters to prevent XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

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

    /**
     * Update a progress bar element with percentage and warning/critical states
     * @param {string} selector - jQuery selector for the progress bar element
     * @param {number} percentage - The percentage to display (0-100)
     */
    function updateProgressBar(selector, percentage) {
        const $bar = $(selector);
        if (!$bar.length) return;

        const pct = Math.min(100, percentage);
        $bar.css('width', pct + '%');
        $bar.removeClass('is-warning is-critical');

        if (pct >= 90) {
            $bar.addClass('is-critical');
        } else if (pct >= 70) {
            $bar.addClass('is-warning');
        }
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
            timeout: AJAX_TIMEOUT,
            data: {
                action: 'imgpro_cdn_update_onboarding_step',
                step: step,
                nonce: imgproCdnAdmin.nonces.onboarding
            },
            success: function(response) {
                if (response.success) {
                    // Reload to show new step
                    window.location.reload();
                }
            },
            error: function(xhr, status) {
                var message = status === 'timeout' ? imgproCdnAdmin.i18n.timeoutError : imgproCdnAdmin.i18n.genericError;
                showNotice('error', message);
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
            timeout: AJAX_TIMEOUT,
            data: {
                action: 'imgpro_cdn_free_register',
                email: email,
                marketing_opt_in: marketingOptIn,
                nonce: imgproCdnAdmin.nonces.free_register
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
                    // Account exists - show verification modal (email already sent)
                    if (response.data.show_recovery) {
                        showRecoveryVerificationModal(response.data.email_hint);
                    } else {
                        showNotice('error', response.data.message || imgproCdnAdmin.i18n.registrationError);
                    }
                }
            },
            error: function(xhr, status) {
                $button.removeClass('is-loading').prop('disabled', false);
                var message = status === 'timeout' ? imgproCdnAdmin.i18n.timeoutError : imgproCdnAdmin.i18n.genericError;
                showNotice('error', message || imgproCdnAdmin.i18n.genericError);
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
            timeout: AJAX_TIMEOUT,
            data: {
                action: 'imgpro_cdn_toggle_enabled',
                enabled: 1,
                mode: 'cloud',
                nonce: imgproCdnAdmin.nonces.toggle_enabled
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
            error: function(xhr, status) {
                $card.removeClass('is-loading');
                $('#imgpro-activate-toggle').prop('checked', false);
                var message = status === 'timeout' ? imgproCdnAdmin.i18n.timeoutError : imgproCdnAdmin.i18n.genericError;
                showNotice('error', message);
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
            timeout: AJAX_TIMEOUT,
            data: {
                action: 'imgpro_cdn_complete_onboarding',
                nonce: imgproCdnAdmin.nonces.onboarding
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
            setInterval(syncStats, 5 * 60 * 1000); // 5 minutes
        }
    }

    // Track if stats sync is in progress to prevent race conditions
    var statsSyncInProgress = false;

    /**
     * Handle main toggle
     *
     * Each mode (cloud/cloudflare) has its own independent enabled state.
     */
    function handleToggle($toggle) {
        const $card = $('#imgpro-toggle-card');
        const isEnabled = $toggle.is(':checked');

        // Get mode from the toggle card's data attribute
        const mode = $card.data('mode') || '';
        if (!mode) {
            showNotice('error', 'Unable to determine mode');
            $toggle.prop('checked', !isEnabled);
            return;
        }

        $card.addClass('is-loading');

        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            timeout: AJAX_TIMEOUT,
            data: {
                action: 'imgpro_cdn_toggle_enabled',
                enabled: isEnabled ? 1 : 0,
                mode: mode,
                nonce: imgproCdnAdmin.nonces.toggle_enabled
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
            error: function(xhr, status) {
                $toggle.prop('checked', !isEnabled);
                var message = status === 'timeout' ? imgproCdnAdmin.i18n.timeoutError : imgproCdnAdmin.i18n.genericError;
                showNotice('error', message);
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
            timeout: AJAX_TIMEOUT,
            data: {
                action: 'imgpro_cdn_free_register',
                nonce: imgproCdnAdmin.nonces.free_register
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', imgproCdnAdmin.i18n.accountCreated);
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    $button.prop('disabled', false).text(originalText);
                    // Account exists - show verification modal (email already sent)
                    if (response.data.show_recovery) {
                        showRecoveryVerificationModal(response.data.email_hint);
                    } else {
                        showNotice('error', response.data.message || imgproCdnAdmin.i18n.registrationError);
                    }
                }
            },
            error: function(xhr, status) {
                $button.prop('disabled', false).text(originalText);
                var message = status === 'timeout' ? imgproCdnAdmin.i18n.timeoutError : imgproCdnAdmin.i18n.genericError;
                showNotice('error', message);
            }
        });
    }

    /**
     * Handle checkout (Pro upgrade)
     */
    function handleCheckout($button, tierId) {
        const originalText = $button.text();
        // Get tier from parameter, button data attribute, or default to 'pro'
        const tier = tierId || $button.data('tier') || 'pro';
        $button.prop('disabled', true).text(imgproCdnAdmin.i18n.creatingCheckout);

        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            timeout: AJAX_TIMEOUT,
            data: {
                action: 'imgpro_cdn_checkout',
                tier_id: tier,
                nonce: imgproCdnAdmin.nonces.checkout
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.checkout_url) {
                        window.location.href = response.data.checkout_url;
                    } else if (response.data.recovered || response.data.upgraded) {
                        // Show success message if provided, then reload to reflect changes
                        if (response.data.message) {
                            showNotice('success', response.data.message);
                        }
                        // Brief delay to show the message before reload
                        setTimeout(function() {
                            window.location.reload();
                        }, response.data.message ? 1500 : 0);
                    }
                } else {
                    $button.prop('disabled', false).text(originalText);
                    // Account exists - show verification modal (email already sent)
                    if (response.data.show_recovery) {
                        showRecoveryVerificationModal(response.data.email_hint, tier);
                    } else {
                        showNotice('error', response.data.message || imgproCdnAdmin.i18n.checkoutError);
                    }
                }
            },
            error: function(xhr, status) {
                $button.prop('disabled', false).text(originalText);
                var message = status === 'timeout' ? imgproCdnAdmin.i18n.timeoutError : imgproCdnAdmin.i18n.genericError;
                showNotice('error', message);
            }
        });
    }

    /**
     * Handle recover account - Step 1: Request verification code
     * @param {jQuery} $button - The button element
     * @param {boolean} skipConfirm - Skip confirmation dialog (for auto-triggered recovery)
     */
    function handleRecoverAccount($button, skipConfirm) {
        const originalText = $button.text();

        if (!skipConfirm && !confirm(imgproCdnAdmin.i18n.recoverConfirm)) {
            return;
        }

        $button.prop('disabled', true).text(imgproCdnAdmin.i18n.recovering);

        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            timeout: AJAX_TIMEOUT,
            data: {
                action: 'imgpro_cdn_request_recovery',
                nonce: imgproCdnAdmin.nonces.recovery
            },
            success: function(response) {
                $button.prop('disabled', false).text(originalText);

                if (response.success && response.data.step === 'verify') {
                    // Show verification code modal
                    showRecoveryVerificationModal(response.data.email_hint);
                } else if (response.success) {
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data.message || imgproCdnAdmin.i18n.recoverError);
                }
            },
            error: function(xhr, status) {
                $button.prop('disabled', false).text(originalText);
                var message = status === 'timeout' ? imgproCdnAdmin.i18n.timeoutError : imgproCdnAdmin.i18n.genericError;
                showNotice('error', message);
            }
        });
    }

    /**
     * Show recovery verification code modal
     * @param {string} emailHint - Masked email hint (e.g., "i•••••@domain.com")
     * @param {string|null} pendingTierId - Tier ID to checkout after verification (null = just recover)
     */
    function showRecoveryVerificationModal(emailHint, pendingTierId) {
        // Remove existing modal if any
        $('#imgpro-recovery-modal').remove();

        const descText = imgproCdnAdmin.i18n.accountFoundDesc || 'We found an existing account for this site. To restore access, enter the verification code sent to:';
        const emailDisplay = escapeHtml(emailHint || imgproCdnAdmin.i18n.yourEmail || 'your registered email');

        const modalHtml = `
            <div id="imgpro-recovery-modal" class="imgpro-modal-overlay">
                <div class="imgpro-modal">
                    <button type="button" class="imgpro-modal-close">&times;</button>
                    <div class="imgpro-modal-header">
                        <h2>${imgproCdnAdmin.i18n.accountFound || 'Welcome Back'}</h2>
                    </div>
                    <div class="imgpro-modal-body">
                        <p>${descText} <strong>${emailDisplay}</strong></p>
                        <div class="imgpro-verification-input">
                            <input type="text"
                                   id="imgpro-recovery-code"
                                   maxlength="6"
                                   pattern="[0-9]*"
                                   inputmode="numeric"
                                   placeholder="000000"
                                   autocomplete="off"
                                   data-1p-ignore="true"
                                   data-lpignore="true">
                        </div>
                        <p class="imgpro-hint">${imgproCdnAdmin.i18n.codeExpires}</p>
                    </div>
                    <div class="imgpro-modal-footer">
                        <button type="button" class="imgpro-btn imgpro-btn-secondary" id="imgpro-recovery-cancel">
                            ${imgproCdnAdmin.i18n.cancel}
                        </button>
                        <button type="button" class="imgpro-btn imgpro-btn-primary" id="imgpro-recovery-verify">
                            ${imgproCdnAdmin.i18n.verify}
                        </button>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);

        const $modal = $('#imgpro-recovery-modal');
        const $input = $('#imgpro-recovery-code');

        // Focus input
        setTimeout(function() {
            $input.focus();
        }, 100);

        // Handle close
        $modal.find('.imgpro-modal-close, #imgpro-recovery-cancel').on('click', function() {
            $modal.remove();
        });

        // Handle verify
        $('#imgpro-recovery-verify').on('click', function() {
            handleRecoveryVerification($input.val(), pendingTierId);
        });

        // Handle enter key
        $input.on('keypress', function(e) {
            if (e.which === 13) {
                handleRecoveryVerification($input.val(), pendingTierId);
            }
        });

        // Only allow numbers
        $input.on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }

    /**
     * Handle recovery verification - Step 2: Verify code
     * @param {string} code - 6-digit verification code
     * @param {string|null} pendingTierId - Tier to checkout after verification
     */
    function handleRecoveryVerification(code, pendingTierId) {
        const $modal = $('#imgpro-recovery-modal');
        const $button = $('#imgpro-recovery-verify');
        const $input = $('#imgpro-recovery-code');

        if (!code || code.length !== 6) {
            $input.addClass('error').focus();
            showNotice('error', imgproCdnAdmin.i18n.invalidCode);
            return;
        }

        $button.prop('disabled', true).text(imgproCdnAdmin.i18n.verifying);
        $input.prop('disabled', true);

        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            timeout: AJAX_TIMEOUT,
            data: {
                action: 'imgpro_cdn_verify_recovery',
                code: code,
                pending_tier_id: pendingTierId || '',
                nonce: imgproCdnAdmin.nonces.recovery
            },
            success: function(response) {
                if (response.success) {
                    $modal.remove();

                    // If upgrade confirmation needed, reload page and show modal
                    if (response.data.show_upgrade && response.data.pending_tier_id) {
                        // Reload page with parameter to trigger upgrade modal
                        const url = new URL(window.location.href);
                        url.searchParams.set('show_upgrade', response.data.pending_tier_id);
                        url.searchParams.delete('payment_status'); // Clean up any old params
                        window.location.href = url.toString();
                        return;
                    }

                    // If there's a checkout URL, go there (upgrade needed)
                    if (response.data.checkout_url) {
                        window.location.href = response.data.checkout_url;
                        return;
                    }

                    // Otherwise just reload (already on same/higher plan)
                    showNotice('success', response.data.message);
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    $button.prop('disabled', false).text(imgproCdnAdmin.i18n.verify);
                    $input.prop('disabled', false).addClass('error').val('').focus();
                    showNotice('error', response.data.message || imgproCdnAdmin.i18n.verificationFailed);
                }
            },
            error: function(xhr, status) {
                $button.prop('disabled', false).text(imgproCdnAdmin.i18n.verify);
                $input.prop('disabled', false);
                var message = status === 'timeout' ? imgproCdnAdmin.i18n.timeoutError : imgproCdnAdmin.i18n.genericError;
                showNotice('error', message);
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
            timeout: AJAX_TIMEOUT,
            data: {
                action: 'imgpro_cdn_manage_subscription',
                nonce: imgproCdnAdmin.nonces.manage_subscription
            },
            success: function(response) {
                if (response.success && response.data.portal_url) {
                    window.location.href = response.data.portal_url;
                } else {
                    $button.prop('disabled', false).text(originalText);
                    showNotice('error', response.data.message || imgproCdnAdmin.i18n.portalError);
                }
            },
            error: function(xhr, status) {
                $button.prop('disabled', false).text(originalText);
                var message = status === 'timeout' ? imgproCdnAdmin.i18n.timeoutError : imgproCdnAdmin.i18n.genericError;
                showNotice('error', message);
            }
        });
    }

    /**
     * Sync stats from API
     * Includes debouncing to prevent race conditions from concurrent calls
     */
    function syncStats() {
        // Prevent concurrent requests
        if (statsSyncInProgress) {
            return;
        }
        statsSyncInProgress = true;

        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            timeout: AJAX_TIMEOUT,
            data: {
                action: 'imgpro_cdn_sync_stats',
                nonce: imgproCdnAdmin.nonces.sync_stats
            },
            success: function(response) {
                if (response.success && response.data.formatted) {
                    // Update bandwidth stat with limit (primary metric)
                    const $bandwidthVal = $('#imgpro-stat-bandwidth');
                    if ($bandwidthVal.length) {
                        $bandwidthVal.html(escapeHtml(response.data.formatted.bandwidth_used || '0 B') +
                            '<span class="imgpro-stat-limit">/ ' + escapeHtml(response.data.formatted.bandwidth_limit) + '</span>');
                    }

                    // Update cache stat with limit (secondary metric)
                    const $cacheVal = $('#imgpro-stat-cache');
                    if ($cacheVal.length) {
                        $cacheVal.html(escapeHtml(response.data.formatted.cache_used) +
                            '<span class="imgpro-stat-limit">/ ' + escapeHtml(response.data.formatted.cache_limit) + '</span>');
                    }

                    $('#imgpro-stat-images').text((response.data.images_cached || 0).toLocaleString());

                    // Update bandwidth progress bar
                    updateProgressBar('#imgpro-progress-bandwidth', response.data.bandwidth_percentage || 0);

                    // Update cache progress bar
                    updateProgressBar('#imgpro-progress-cache', response.data.cache_percentage || 0);
                }
            },
            complete: function() {
                statsSyncInProgress = false;
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
            timeout: AJAX_TIMEOUT,
            data: {
                action: 'imgpro_cdn_add_custom_domain',
                domain: domain,
                nonce: imgproCdnAdmin.nonces.custom_domain
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
            error: function(xhr, status) {
                $button.prop('disabled', false).text(originalText);
                $input.prop('disabled', false);
                $section.removeClass('is-loading');
                var message = status === 'timeout' ? imgproCdnAdmin.i18n.timeoutError : imgproCdnAdmin.i18n.genericError;
                showNotice('error', message);
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
            timeout: AJAX_TIMEOUT,
            data: {
                action: 'imgpro_cdn_check_custom_domain',
                nonce: imgproCdnAdmin.nonces.custom_domain
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
            error: function(xhr, status) {
                $button.prop('disabled', false).text(originalText);
                var message = status === 'timeout' ? imgproCdnAdmin.i18n.timeoutError : imgproCdnAdmin.i18n.genericError;
                showNotice('error', message);
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
            timeout: AJAX_TIMEOUT,
            data: {
                action: 'imgpro_cdn_remove_custom_domain',
                nonce: imgproCdnAdmin.nonces.custom_domain
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
            error: function(xhr, status) {
                $button.prop('disabled', false).text(originalText);
                $section.removeClass('is-loading');
                var message = status === 'timeout' ? imgproCdnAdmin.i18n.timeoutError : imgproCdnAdmin.i18n.genericError;
                showNotice('error', message);
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
        if (!confirm(imgproCdnAdmin.i18n.confirmRemoveCdnDomain)) {
            return;
        }

        const $section = $('#imgpro-cdn-domain-section');
        $section.addClass('is-loading');
        $button.prop('disabled', true);

        $.ajax({
            url: imgproCdnAdmin.ajaxUrl,
            type: 'POST',
            timeout: AJAX_TIMEOUT,
            data: {
                action: 'imgpro_cdn_remove_cdn_domain',
                nonce: imgproCdnAdmin.nonces.remove_cdn_domain
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message || imgproCdnAdmin.i18n.cdnDomainRemoved);
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    $section.removeClass('is-loading');
                    $button.prop('disabled', false);
                    showNotice('error', response.data.message || imgproCdnAdmin.i18n.genericError);
                }
            },
            error: function(xhr, status) {
                $section.removeClass('is-loading');
                $button.prop('disabled', false);
                var message = status === 'timeout' ? imgproCdnAdmin.i18n.timeoutError : imgproCdnAdmin.i18n.genericError;
                showNotice('error', message);
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
    }

    /**
     * Initialize default plan selection
     */
    function initDefaultSelection() {
        // Only target the modal selector to avoid conflicts
        const $modal = $('#imgpro-plan-modal');
        const $selector = $modal.find('.imgpro-plan-selector');
        if (!$selector.length) return;

        const currentTier = $selector.data('current-tier') || '';

        // If user is on free tier or no tier, pre-select Pro
        if (!currentTier || currentTier === 'free') {
            // Try highlighted card first, then Pro by ID, then first available
            let $cardToSelect = $selector.find('.imgpro-plan-card--highlight').first();

            if (!$cardToSelect.length) {
                $cardToSelect = $selector.find('.imgpro-plan-card[data-tier-id="pro"]').first();
            }

            if (!$cardToSelect.length || $cardToSelect.hasClass('imgpro-plan-card--current')) {
                $cardToSelect = $selector.find('.imgpro-plan-card:not(.imgpro-plan-card--current)').first();
            }

            if ($cardToSelect.length) {
                selectPlanCard($cardToSelect, true);
            }
        }
    }

    /**
     * Open the plan modal
     */
    function openPlanModal() {
        const $modal = $('#imgpro-plan-modal');
        if (!$modal.length) return;

        $modal.fadeIn(200, function() {
            // Pre-select Pro tier when modal opens
            const $proCard = $modal.find('.imgpro-plan-card[data-tier-id="pro"]');
            if ($proCard.length && !$proCard.hasClass('imgpro-plan-card--current')) {
                selectPlanCard($proCard, true);
            }
        });
        $('body').addClass('imgpro-modal-open');
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
        $('#imgpro-confirm-btn-tier').text(tierName);

        // Build multiplier hero
        const multiplier = calculateBandwidthMultiplier(currentTier, newTier);
        const newBandwidth = newTier?.limits?.bandwidth?.formatted || '';
        const currentBandwidth = currentTier?.limits?.bandwidth?.formatted || '100 GB';

        $('#imgpro-confirm-multiplier').text(multiplier + ' more bandwidth');
        $('#imgpro-confirm-comparison').text(newBandwidth + '/mo (vs ' + currentBandwidth + ')');

        // Build checklist
        $('#imgpro-confirm-checklist').html(buildChecklistHtml(newTier));

        // Reset button states
        const $confirmBtn = $('#imgpro-upgrade-confirm');
        $confirmBtn.removeClass('is-loading').prop('disabled', false);
        $('#imgpro-upgrade-cancel').prop('disabled', false);

        // Show modal
        $modal.fadeIn(200);
        $('body').addClass('imgpro-modal-open');
    }

    /**
     * Calculate bandwidth multiplier between tiers
     */
    function calculateBandwidthMultiplier(currentTier, newTier) {
        const currentBytes = currentTier?.limits?.bandwidth?.bytes || 107374182400; // 100 GB default
        const newBytes = newTier?.limits?.bandwidth?.bytes || currentBytes;

        if (newTier?.limits?.bandwidth?.unlimited) {
            return 'Unlimited';
        }

        const multiplier = Math.round(newBytes / currentBytes);
        return multiplier + 'x';
    }

    /**
     * Build HTML for checklist (custom domain, priority support)
     */
    function buildChecklistHtml(tier) {
        if (!tier) return '';

        const checkIcon = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13.333 4L6 11.333 2.667 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        const items = [];

        if (tier.features?.custom_domain) {
            items.push('<li>' + checkIcon + 'Custom CDN domain</li>');
        }

        if (tier.features?.priority_support) {
            items.push('<li>' + checkIcon + 'Priority support</li>');
        }

        return items.join('');
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
        const $modal = $('#imgpro-plan-modal');

        // Reset all cards in the modal
        $modal.find('.imgpro-plan-card').each(function() {
            $(this).removeClass('is-selected');
            const $btn = $(this).find('.imgpro-plan-card__select');
            if ($btn.length && !$(this).hasClass('imgpro-plan-card--current') && !$(this).hasClass('imgpro-plan-card--downgrade')) {
                $btn.removeClass('imgpro-btn-primary').addClass('imgpro-btn-secondary');
                $btn.text('Select');
            }
        });

        // Select this card
        $card.addClass('is-selected');

        // Update this card's button
        const $btn = $card.find('.imgpro-plan-card__select');
        if ($btn.length && !$card.hasClass('imgpro-plan-card--downgrade')) {
            $btn.removeClass('imgpro-btn-secondary').addClass('imgpro-btn-primary');
            $btn.text('Selected');
        }

        // Update state
        selectedTierId = $card.data('tier-id');

        // Update footer
        $modal.find('#imgpro-selected-plan-name').text($card.data('tier-name'));
        $modal.find('#imgpro-selected-plan-price').text($card.data('tier-price'));

        // Enable checkout button
        if (enableCheckout && selectedTierId) {
            $modal.find('#imgpro-plan-checkout').prop('disabled', false);
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
            timeout: AJAX_TIMEOUT,
            data: {
                action: 'imgpro_cdn_checkout',
                tier_id: tierId,
                nonce: imgproCdnAdmin.nonces.checkout
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.checkout_url) {
                        window.location.href = response.data.checkout_url;
                    } else if (response.data.upgraded) {
                        // Subscription upgraded directly - show success and reload
                        closePlanModal();
                        closeUpgradeConfirmModal();
                        showNotice('success', response.data.message || imgproCdnAdmin.i18n.subscriptionUpgraded);
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
                    // Account exists - show verification modal with pending tier
                    if (response.data.show_recovery) {
                        showRecoveryVerificationModal(response.data.email_hint, tierId);
                    } else {
                        showNotice('error', response.data.message || imgproCdnAdmin.i18n.checkoutError);
                    }
                }
            },
            error: function(xhr, status) {
                $button.removeClass('is-loading').prop('disabled', false);
                closePlanModal();
                closeUpgradeConfirmModal();
                var message = status === 'timeout' ? imgproCdnAdmin.i18n.timeoutError : imgproCdnAdmin.i18n.genericError;
                showNotice('error', message);
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
            handleShowUpgrade();
        }

        // Plan selector (available on both onboarding and dashboard)
        initPlanSelector();
    });

    /**
     * Handle show_upgrade URL parameter (after account recovery)
     */
    function handleShowUpgrade() {
        const urlParams = new URLSearchParams(window.location.search);
        const upgradeTier = urlParams.get('show_upgrade');

        if (upgradeTier) {
            // Clean up URL
            const url = new URL(window.location.href);
            url.searchParams.delete('show_upgrade');
            window.history.replaceState({}, '', url.toString());

            // Show success notice and upgrade modal
            showNotice('success', imgproCdnAdmin.i18n.accountRecovered || 'Account recovered!');
            setTimeout(function() {
                showUpgradeConfirmModal(upgradeTier, null);
            }, 300);
        }
    }

})(jQuery);
