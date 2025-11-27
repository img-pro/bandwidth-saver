<?php
/**
 * ImgPro CDN Uninstall
 *
 * Removes all plugin data when uninstalled
 *
 * @package ImgPro_CDN
 */

// Exit if accessed directly or not in uninstall context
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Fires before ImgPro CDN plugin data is deleted
 *
 * Allows other code to perform cleanup before uninstall
 */
do_action('imgpro_cdn_before_uninstall');

// Delete plugin options
delete_option('imgpro_cdn_settings');
delete_option('imgpro_cdn_version');

// Delete transients
delete_transient('imgpro_cdn_pricing');
delete_transient('imgpro_cdn_pending_payment');

// For multisite installations
if (is_multisite()) {
    // Get all sites with pagination for better performance on large networks
    $imgpro_page = 1;
    $imgpro_per_page = 100;

    while (true) {
        $imgpro_sites = get_sites([
            'number' => $imgpro_per_page,
            'offset' => ($imgpro_page - 1) * $imgpro_per_page,
        ]);

        if (empty($imgpro_sites)) {
            break;
        }

        foreach ($imgpro_sites as $imgpro_site) {
            switch_to_blog($imgpro_site->blog_id);

            // Delete options for this site
            delete_option('imgpro_cdn_settings');
            delete_option('imgpro_cdn_version');

            // Delete transients for this site
            delete_transient('imgpro_cdn_pricing');
            delete_transient('imgpro_cdn_pending_payment');

            restore_current_blog();
        }

        $imgpro_page++;
    }
}

/**
 * Fires after ImgPro CDN plugin data is deleted
 *
 * Allows other code to perform final cleanup after uninstall
 */
do_action('imgpro_cdn_after_uninstall');
