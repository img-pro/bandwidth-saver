/**
 * ImgPro Admin JavaScript
 * @version 0.1.1
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Handle "Use ImgPro Cloud" button
        $('#imgpro-cdn-use-cloud').on('click', function() {
            const $button = $(this);
            const originalText = $button.text();

            // Disable button and show loading state
            $button.prop('disabled', true).text('Setting up...');

            // AJAX request to save ImgPro Cloud domains
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'imgpro_cdn_use_cloud',
                    nonce: imgproCdnAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload page to show configured state
                        window.location.reload();
                    } else {
                        $button.prop('disabled', false).text(originalText);
                        alert(response.data.message || 'Failed to configure ImgPro Cloud');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text(originalText);
                    alert('An error occurred. Please try again.');
                }
            });
        });

        // Handle main toggle switch
        $('#enabled').on('change', function() {
            const $toggle = $(this);
            const $card = $('.imgpro-cdn-toggle-card');
            const isEnabled = $toggle.is(':checked');

            // Add loading state
            $card.addClass('imgpro-cdn-loading');

            // AJAX request to update setting
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'imgpro_cdn_toggle_enabled',
                    enabled: isEnabled ? 1 : 0,
                    nonce: imgproCdnAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        updateToggleUI($card, isEnabled);

                        // Show success notice
                        showNotice('success', response.data.message);
                    } else {
                        // Revert toggle
                        $toggle.prop('checked', !isEnabled);
                        showNotice('error', response.data.message || 'Failed to update settings');
                    }
                },
                error: function() {
                    // Revert toggle
                    $toggle.prop('checked', !isEnabled);
                    showNotice('error', 'An error occurred. Please try again.');
                },
                complete: function() {
                    $card.removeClass('imgpro-cdn-loading');
                }
            });
        });

        // Update toggle card UI
        function updateToggleUI($card, isEnabled) {
            const $icon = $card.find('.imgpro-cdn-toggle-icon .dashicons');
            const $content = $card.find('.imgpro-cdn-toggle-content');
            const $checkbox = $('#enabled');

            if (isEnabled) {
                // Update card background
                $card.removeClass('imgpro-cdn-toggle-disabled').addClass('imgpro-cdn-toggle-active');

                // Update icon
                $icon.removeClass('dashicons-warning').addClass('dashicons-yes-alt');

                // Update text
                $content.find('h2').text(imgproCdnAdmin.i18n.activeLabel);
                $content.find('p').html(imgproCdnAdmin.i18n.activeMessage);

                // Update ARIA attribute for screen readers
                $checkbox.attr('aria-checked', 'true');
            } else {
                // Update card background
                $card.removeClass('imgpro-cdn-toggle-active').addClass('imgpro-cdn-toggle-disabled');

                // Update icon
                $icon.removeClass('dashicons-yes-alt').addClass('dashicons-warning');

                // Update text
                $content.find('h2').text(imgproCdnAdmin.i18n.disabledLabel);
                $content.find('p').text(imgproCdnAdmin.i18n.disabledMessage);

                // Update ARIA attribute for screen readers
                $checkbox.attr('aria-checked', 'false');
            }
        }

        // Show admin notice
        function showNotice(type, message) {
            // Remove any existing notices first
            $('.imgpro-cdn-toggle-notice').remove();

            const $notice = $('<div class="notice notice-' + type + ' is-dismissible imgpro-cdn-toggle-notice"><p>' + message + '</p></div>');
            $('.imgpro-cdn-toggle-card').after($notice);

            // Auto dismiss after 3 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }

    });

})(jQuery);
