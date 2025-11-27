/**
 * ImgPro CDN Admin JavaScript
 *
 * Handles admin interface interactions including toggle switches,
 * subscription management, and AJAX operations.
 *
 * @package ImgPro_CDN
 * @since   0.1.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Handle Subscribe button (Stripe checkout)
        $('#imgpro-cdn-subscribe').on('click', function() {
            const $button = $(this);
            const originalText = $button.text();

            // Disable button and show loading state
            $button.prop('disabled', true).text(imgproCdnAdmin.i18n.creatingCheckout);

            // AJAX request to create Stripe checkout session
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
                            // Redirect to Stripe checkout
                            window.location.href = response.data.checkout_url;
                        } else if (response.data.recovered) {
                            // Existing subscription was recovered - reload page
                            window.location.reload();
                        }
                    } else {
                        $button.prop('disabled', false).text(originalText);
                        alert(response.data.message || imgproCdnAdmin.i18n.checkoutError);
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text(originalText);
                    alert(imgproCdnAdmin.i18n.genericError);
                }
            });
        });

        // Handle Recover Account button
        $('#imgpro-cdn-recover-account').on('click', function() {
            const $button = $(this);
            const originalText = $button.text();

            if (!confirm(imgproCdnAdmin.i18n.recoverConfirm)) {
                return;
            }

            // Disable button and show loading state
            $button.prop('disabled', true).text(imgproCdnAdmin.i18n.recovering);

            // AJAX request to recover account
            $.ajax({
                url: imgproCdnAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'imgpro_cdn_recover_account',
                    nonce: imgproCdnAdmin.checkoutNonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload page to show active subscription
                        showNotice('success', response.data.message);
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        $button.prop('disabled', false).text(originalText);
                        alert(response.data.message || imgproCdnAdmin.i18n.recoverError);
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text(originalText);
                    alert(imgproCdnAdmin.i18n.genericError);
                }
            });
        });

        // Handle Manage Subscription button
        $('#imgpro-cdn-manage-subscription').on('click', function() {
            const $button = $(this);
            const originalText = $button.text();

            // Disable button and show loading state
            $button.prop('disabled', true).text(imgproCdnAdmin.i18n.openingPortal);

            // AJAX request to create customer portal session
            $.ajax({
                url: imgproCdnAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'imgpro_cdn_manage_subscription',
                    nonce: imgproCdnAdmin.checkoutNonce
                },
                success: function(response) {
                    if (response.success && response.data.portal_url) {
                        // Redirect to Stripe customer portal
                        window.location.href = response.data.portal_url;
                    } else {
                        $button.prop('disabled', false).text(originalText);
                        alert(response.data.message || imgproCdnAdmin.i18n.portalError);
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text(originalText);
                    alert(imgproCdnAdmin.i18n.genericError);
                }
            });
        });

        // Handle main toggle switch
        $('#enabled').on('change', function() {
            const $toggle = $(this);
            const $card = $('.imgpro-cdn-main-toggle-card');
            const isEnabled = $toggle.is(':checked');

            // Get current tab from URL or active tab
            const urlParams = new URLSearchParams(window.location.search);
            let currentTab = urlParams.get('tab') || '';
            if (!currentTab) {
                // Fallback to active tab's data attribute
                const $activeTab = $('.imgpro-cdn-nav-tabs .nav-tab-active');
                currentTab = $activeTab.data('tab') || '';
            }

            // Add loading state
            $card.addClass('imgpro-cdn-loading');

            // AJAX request to update setting
            $.ajax({
                url: imgproCdnAdmin.ajaxUrl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'imgpro_cdn_toggle_enabled',
                    enabled: isEnabled ? 1 : 0,
                    current_tab: currentTab,
                    nonce: imgproCdnAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Check if we need to redirect (smart enable switched modes)
                        if (response.data.redirect) {
                            showNotice('success', response.data.message);
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 500);
                            return;
                        }

                        // Update UI
                        updateToggleUI($card, isEnabled);

                        // Show success notice
                        showNotice('success', response.data.message);
                    } else {
                        // Revert toggle
                        $toggle.prop('checked', !isEnabled);
                        showNotice('error', response.data.message || imgproCdnAdmin.i18n.settingsError);
                    }
                },
                error: function() {
                    // Revert toggle
                    $toggle.prop('checked', !isEnabled);
                    showNotice('error', imgproCdnAdmin.i18n.genericError);
                },
                complete: function() {
                    $card.removeClass('imgpro-cdn-loading');
                }
            });
        });

        // Update toggle card UI
        function updateToggleUI($card, isEnabled) {
            const $icon = $card.find('.imgpro-cdn-toggle-status .dashicons');
            const $heading = $card.find('.imgpro-cdn-toggle-status h3');
            const $description = $card.find('.imgpro-cdn-toggle-description');
            const $checkbox = $('#enabled');
            const $activeTab = $('.imgpro-cdn-nav-tabs .nav-tab-active');

            if (isEnabled) {
                // Update card state classes
                $card.removeClass('is-inactive').addClass('is-active');

                // Update icon
                $icon.removeClass('dashicons-marker').addClass('dashicons-yes-alt');

                // Update text
                $heading.text(imgproCdnAdmin.i18n.cdnActiveHeading);
                $description.text(imgproCdnAdmin.i18n.cdnActiveDesc);

                // Update ARIA attribute for screen readers
                $checkbox.attr('aria-checked', 'true');

                // Update active tab color to green
                $activeTab.removeClass('is-disabled').addClass('is-enabled');
            } else {
                // Update card state classes
                $card.removeClass('is-active').addClass('is-inactive');

                // Update icon
                $icon.removeClass('dashicons-yes-alt').addClass('dashicons-marker');

                // Update text
                $heading.text(imgproCdnAdmin.i18n.cdnInactiveHeading);
                $description.text(imgproCdnAdmin.i18n.cdnInactiveDesc);

                // Update ARIA attribute for screen readers
                $checkbox.attr('aria-checked', 'false');

                // Update active tab color to amber
                $activeTab.removeClass('is-enabled').addClass('is-disabled');
            }
        }

        // Show admin notice
        function showNotice(type, message) {
            // Remove any existing notices first
            $('.imgpro-cdn-toggle-notice').remove();

            // Build notice element safely to prevent XSS
            const $notice = $('<div>', {
                'class': 'notice notice-' + type + ' is-dismissible imgpro-cdn-toggle-notice'
            }).append(
                $('<p>').text(message)
            );

            // Insert after the toggle form, not inside it
            $('.imgpro-cdn-toggle-form').after($notice);

            // Auto dismiss after 3 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }

        // Handle payment success/cancel query params
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('payment') === 'success') {
            showNotice('success', imgproCdnAdmin.i18n.subscriptionActivated);
            // Clean up URL
            window.history.replaceState({}, document.title, window.location.pathname + '?page=imgpro-cdn-settings&tab=cloud');
        } else if (urlParams.get('payment') === 'cancelled') {
            showNotice('warning', imgproCdnAdmin.i18n.checkoutCancelled);
            // Clean up URL
            window.history.replaceState({}, document.title, window.location.pathname + '?page=imgpro-cdn-settings&tab=cloud');
        }

        // Handle Advanced Settings collapse/expand
        $('.imgpro-cdn-advanced-toggle').on('click', function() {
            const $button = $(this);
            const contentId = $button.attr('aria-controls');
            const $content = $('#' + contentId);
            const isExpanded = $button.attr('aria-expanded') === 'true';

            if (isExpanded) {
                // Collapse
                $button.attr('aria-expanded', 'false');
                $content.attr('hidden', '');
                $content.slideUp(200);
            } else {
                // Expand
                $button.attr('aria-expanded', 'true');
                $content.removeAttr('hidden');
                $content.slideDown(200);
            }
        });

        // ===== Custom Domain Management =====

        // Handle Add Custom Domain button
        $('#imgpro-cdn-add-domain').on('click', function() {
            const $button = $(this);
            const $input = $('#imgpro-cdn-custom-domain-input');
            const $section = $('#imgpro-cdn-custom-domain-section');
            const domain = $input.val().trim();

            if (!domain) {
                $input.focus();
                return;
            }

            const originalText = $button.text();

            // Disable UI and show loading state
            $button.prop('disabled', true).text(imgproCdnAdmin.i18n.addingDomain);
            $input.prop('disabled', true);
            $section.addClass('imgpro-cdn-loading');

            // AJAX request to add domain
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
                        // Reload page to show DNS instructions
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        $button.prop('disabled', false).text(originalText);
                        $input.prop('disabled', false);
                        $section.removeClass('imgpro-cdn-loading');
                        alert(response.data.message || imgproCdnAdmin.i18n.genericError);
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text(originalText);
                    $input.prop('disabled', false);
                    $section.removeClass('imgpro-cdn-loading');
                    alert(imgproCdnAdmin.i18n.genericError);
                }
            });
        });

        // Handle Check Domain Status button (both in pending notice and in advanced settings)
        $('#imgpro-cdn-check-domain-pending, #imgpro-cdn-check-domain').on('click', function() {
            const $button = $(this);
            const $notice = $('#imgpro-cdn-pending-notice');
            const originalText = $button.text();

            // Disable UI and show loading state
            $button.prop('disabled', true).text(imgproCdnAdmin.i18n.checkingStatus);

            // AJAX request to check status
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
                            // Reload to update UI
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        } else {
                            // Status hasn't changed
                            $button.prop('disabled', false).text(originalText);
                            // Update status display if changed
                            const $statusDiv = $('#imgpro-cdn-custom-domain-status');
                            if ($statusDiv.length && $statusDiv.data('status') !== response.data.status) {
                                window.location.reload();
                            }
                        }
                    } else {
                        $button.prop('disabled', false).text(originalText);
                        alert(response.data.message || imgproCdnAdmin.i18n.genericError);
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text(originalText);
                    alert(imgproCdnAdmin.i18n.genericError);
                }
            });
        });

        // Handle Remove Domain button
        $('#imgpro-cdn-remove-domain').on('click', function() {
            const $button = $(this);
            const $section = $('#imgpro-cdn-custom-domain-section');

            if (!confirm(imgproCdnAdmin.i18n.confirmRemoveDomain)) {
                return;
            }

            const originalText = $button.text();

            // Disable UI and show loading state
            $button.prop('disabled', true).text(imgproCdnAdmin.i18n.removingDomain);
            $section.addClass('imgpro-cdn-loading');

            // AJAX request to remove domain
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
                        // Reload page to show add form
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        $button.prop('disabled', false).text(originalText);
                        $section.removeClass('imgpro-cdn-loading');
                        alert(response.data.message || imgproCdnAdmin.i18n.genericError);
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text(originalText);
                    $section.removeClass('imgpro-cdn-loading');
                    alert(imgproCdnAdmin.i18n.genericError);
                }
            });
        });

        // Allow Enter key to submit custom domain
        $('#imgpro-cdn-custom-domain-input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#imgpro-cdn-add-domain').click();
            }
        });

    });

})(jQuery);
