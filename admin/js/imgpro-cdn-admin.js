/**
 * ImgPro Admin JavaScript
 * @version 2.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Handle main toggle switch
        $('#enabled').on('change', function() {
            const $toggle = $(this);
            const $card = $toggle.closest('.imgpro-cdn-toggle-card');
            const isEnabled = $toggle.is(':checked');

            // Add loading state
            $card.addClass('imgpro-loading');

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
                        updateToggleUI(isEnabled);

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
                    $card.removeClass('imgpro-loading');
                }
            });
        });

        // Update toggle card UI
        function updateToggleUI(isEnabled) {
            const $status = $('.imgpro-cdn-main-toggle-status');

            if (isEnabled) {
                $status.find('.dashicons').removeClass('dashicons-warning').addClass('dashicons-yes-alt');
                $status.find('h2').text(imgproCdnAdmin.i18n.active);
                $status.find('p').text(imgproCdnAdmin.i18n.activeMessage);
            } else {
                $status.find('.dashicons').removeClass('dashicons-yes-alt').addClass('dashicons-warning');
                $status.find('h2').text(imgproCdnAdmin.i18n.disabled);
                $status.find('p').text(imgproCdnAdmin.i18n.disabledMessage);
            }
        }

        // Show admin notice
        function showNotice(type, message) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.imgpro-cdn-admin').prepend($notice);

            // Auto dismiss after 3 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }

    });

})(jQuery);
