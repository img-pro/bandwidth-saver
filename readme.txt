=== Bandwidth Saver: Image CDN ===
Contributors: imgpro
Tags: cdn, images, cloudflare, performance, bandwidth
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Cloudflare speed for your WordPress images without DNS changes or account setup. Install, activate, done.

== Description ==

WordPress images often load slowly, especially on shared hosting. Large sites with thousands of images can feel sluggish without a CDN. But setting up Cloudflare normally requires changing DNS, configuring caching rules, and navigating technical steps that many site owners avoid.

**Bandwidth Saver** delivers your existing WordPress images through Cloudflare's global edge network without requiring DNS changes or a Cloudflare account in Managed mode. There is nothing to configure. Just activate the plugin and your images begin loading from Cloudflare.

Safe to try on any site. The plugin does not modify your Media Library, files, or database. If you disable it, your site immediately returns to normal. If Cloudflare ever has an issue, WordPress automatically loads your original images.

= How It Works =

1. You upload images to WordPress normally  
2. The plugin rewrites image URLs on your frontend pages  
3. When an image is requested for the first time, a Cloudflare Worker fetches it from your server and stores it in R2  
4. Future requests serve the cached copy from Cloudflare's edge

Your original images stay on your server. WordPress remains the system of record. The plugin only changes how images are delivered to visitors.

Image URLs are rewritten on the frontend only. Your Media Library URLs stay exactly the same.

The first request to each image comes from your server while Cloudflare caches it. After that, visitors receive the image from the nearest Cloudflare edge location.

= What This Plugin Does =

* Rewrites image URLs on your frontend pages (Media Library URLs stay unchanged)
* Delivers cached images from Cloudflare's global edge network
* Falls back to your original images automatically if Cloudflare is unavailable
* Works with lazy loading, responsive image sizes, and srcset
* Compatible with virtually all themes and page builders
* Designed to handle large, image-heavy websites reliably

The plugin delivers whatever WordPress outputs, including images processed by optimization plugins.

= What This Plugin Does NOT Do =

* Does not move or delete your images
* Does not optimize or compress images
* Does not replace your existing image plugins
* Does not cache HTML, CSS, or JavaScript
* Does not require DNS changes or Cloudflare proxy
* Does not modify your database

Only images are delivered through Cloudflare. Videos, PDFs, and other non-image files are not cached or rewritten.

= Who This Is For =

Bandwidth Saver is for WordPress users who want faster image delivery without DNS changes, regardless of site size.

Great for:

* Blogs and content sites  
* Recipe sites  
* Photography and portfolio sites  
* WooCommerce stores with many product images  
* Online magazines  
* Travel and lifestyle sites  
* Large content libraries  
* High traffic environments

It is a good fit if you:

* Cannot or do not want to move your DNS to Cloudflare  
* Want Cloudflare-level speed without configuration  
* Want faster image delivery without touching hosting settings  

= Who This Is NOT For =

This plugin may not be the right fit if you:

* Already use Cloudflare DNS with proper caching rules  
* Need to fully offload media files to external storage  
* Need caching for HTML, CSS, or JavaScript  
* Want advanced or custom Cloudflare configurations  

= Why Try It =

* Safe to try on any site  
* No DNS changes required  
* No Cloudflare account needed in Managed mode  
* Nothing to configure  
* Disable and your site returns to normal instantly  
* Works with virtually all themes and page builders  
* Compatible with image optimization plugins  
* Supports JPG, PNG, GIF, WebP, AVIF, and SVG  
* Handles large websites and high traffic effortlessly  

= Two Ways to Use =

**Managed (Recommended for most users)**  
One click setup. We operate the Cloudflare Worker and R2 storage. No Cloudflare account required for Managed mode. Images are cached and delivered through Cloudflare infrastructure operated by ImgPro.

The Managed plan costs $9.99 per month and includes up to 500 GB of cached image storage and 5 TB of monthly bandwidth as soft limits. Storage refers only to the total weight of cached images in R2. It does not include HTML, CSS, JavaScript, PDFs, videos, or other file types. Storage is cumulative, not monthly traffic.

This comfortably supports everything from small blogs to large, image-heavy WordPress installations.

**Self-Hosted (Free)**  
For technical users who prefer running Cloudflare on their own account. You control all infrastructure and pay Cloudflare directly (often $0 per month on the free tier).

Requires: Cloudflare account, Worker deployment, custom domain setup.

= Open Source =

The Cloudflare Worker is fully open source:  
https://github.com/img-pro/bandwidth-saver-worker

== Installation ==

= Managed Setup (Under 1 Minute) =

1. Install and activate the plugin  
2. Go to **Settings > Bandwidth Saver**  
3. Select **Managed**  
4. Click **Activate Now** and complete checkout  
5. Done. Images now load from Cloudflare  

No DNS changes. No Cloudflare account. No configuration.

= Self-Hosted Setup (About 15 Minutes) =

1. Create a free Cloudflare account
2. Deploy the worker from GitHub
3. Add a custom domain to your Worker (e.g., cdn.yoursite.com)
4. Enter your CDN domain in **Settings > Bandwidth Saver > Self-Host**

Full guide:  
https://github.com/img-pro/bandwidth-saver-worker#setup

== Frequently Asked Questions ==

= How long does setup take? =

Managed: Under a minute.
Self-Hosted: About 15 minutes if familiar with Cloudflare.

= How much does it cost? =

Managed: $9.99 per month for up to 500 GB storage and 5 TB monthly bandwidth.  
Self-Hosted: Free. You pay Cloudflare directly.

= How much faster will my images load? =

Most sites see significantly faster delivery after the first request is cached. Cloudflare serves images from the closest edge location, reducing load times to a few milliseconds. Actual performance varies by hosting and visitor location.

= Will this break my site? =

No. Your original images stay on your server. The plugin only rewrites URLs on your frontend. If you disable the plugin, your site returns to normal instantly.

= Do I need a Cloudflare account? =

Managed: No.  
Self-Hosted: Yes.

= Do I need to change DNS? =

No. This plugin works without DNS changes. That is one of its main benefits.

= Does this change my Media Library URLs? =

No. Media Library URLs stay the same. Only frontend output is rewritten.

= Does this handle WebP and AVIF? =

Yes. The plugin delivers whatever format WordPress generates.

= Does it work with lazy loading and srcset? =

Yes. Fully compatible.

= Are first requests slower? =

Yes. The first request for each image comes from your server while Cloudflare caches it. After that, delivery is very fast.

= What happens if Cloudflare is down? =

Your site falls back to your original images automatically.

= What happens if I deactivate the plugin? =

Everything returns to normal immediately. Cached copies in Cloudflare are cleaned up automatically over time.

= Is this an offloading plugin? =

No. Images stay on your WordPress server.

= Does this replace a full CDN? =

No. It only handles images.

= Can this handle large sites? =

Yes. Managed mode supports up to 500 GB cached image storage and 5 TB monthly bandwidth. Larger installations can use Self-Hosted mode for full control.

== Screenshots ==

1. One click enablement  
2. Clear status indicators  
3. Works with virtually all themes and builders  

== Privacy ==

= What the plugin collects =

The plugin does not add cookies, tracking, or analytics.

= Cloudflare logs =

Cloudflare logs standard CDN metadata (IP, timestamps, headers). This is normal for any CDN.

For Managed mode, images are cached on Cloudflare infrastructure operated by ImgPro. Review Cloudflare’s privacy policy for details.

Self-Hosted users store data in their own Cloudflare account.

== External Services ==

Cloudflare R2 and Workers:  
Purpose: Image storage and edge delivery  
Terms: https://www.cloudflare.com/terms/  
Privacy: https://www.cloudflare.com/privacypolicy/

ImgPro Cloud API (Managed mode):  
Purpose: Subscription and configuration  
Data: Site URL, admin email  
Stored: Subscription status, API key  

== Fair Use Policy ==

Managed mode includes up to 500 GB of cached image storage and 5 TB monthly bandwidth as soft limits. Storage refers to total cached image weight in R2 and does not include non-image assets. Storage is cumulative, not monthly traffic.

If your usage regularly exceeds the limits, we will contact you. In many cases, the Self-Hosted option is recommended for full control and unlimited growth.

== Content Responsibility ==

You are responsible for the images you upload. Illegal or abusive content may lead to account review. High risk or high volume sites should use the Self-Hosted option.

== Changelog ==

= 0.1.5 =
* Improved: Simplified self-hosted setup to single CDN domain
* Improved: Faster image fallback with inline error handling
* Improved: Images no longer flash on load
* Fixed: Better compatibility with strict CSP policies

= 0.1.4 =
* Improved: Updated copy and documentation for clarity
* Improved: Better audience targeting and positioning

= 0.1.3 =
*Stability and Developer Experience Release*

* **Clearer error messages** - Specific, actionable messages for payment and subscription issues
* **Faster page processing** - Pages without images skip CDN processing entirely
* **Better developer tools** - New `imgpro_cdn_api_base_url` filter for staging environments
* **Improved code architecture** - Cleaner separation of concerns
* **Enhanced reliability** - Better handling of edge cases in checkout and subscription flow

= 0.1.2 =
* Fixed: Plugin no longer disables itself when saving settings
* Fixed: Improved reliability for dynamically loaded images (infinite scroll, AJAX)
* Improved: Better handling of browser-cached images
* Improved: Cloud mode now auto-configures without manual URL entry
* Security: Enhanced protection and CSP compatibility
* Developer: Added hooks for error logging and debugging

= 0.1.0 =
* New: Managed option for one-click setup (no Cloudflare account needed)
* New: Completely redesigned admin interface
* New: Full accessibility support (ARIA labels, keyboard navigation)
* Improved: Mobile-responsive settings page
* Improved: Performance optimization for image-heavy pages

= 0.0.8 =
* Fixed: Critical JavaScript issue preventing images from displaying

= 0.0.6 =
* Fixed: Jetpack compatibility (connections, backups, Block Editor)
* Fixed: REST API timing issues

= 0.0.1 =
* Initial release

== Upgrade Notice ==

= 0.1.5 =
Simplified architecture and fixed image flashing. Self-hosted users now only need one CDN domain.

= 0.1.4 =
Documentation and copy improvements. Recommended for all users.

= 0.1.3 =
Stability improvements with clearer error messages and faster page processing. Recommended for all users.

= 0.1.2 =
Fixes settings save bug and improves reliability. Recommended for all users.

= 0.1.0 =
Major update with one-click Managed setup and redesigned interface.

== Support ==

* **Documentation:** [github.com/img-pro/bandwidth-saver](https://github.com/img-pro/bandwidth-saver)
* **Support Forum:** [wordpress.org/support/plugin/bandwidth-saver](https://wordpress.org/support/plugin/bandwidth-saver/)
* **Worker Setup Guide:** [github.com/img-pro/bandwidth-saver-worker](https://github.com/img-pro/bandwidth-saver-worker)
* **Report Issues:** [github.com/img-pro/bandwidth-saver/issues](https://github.com/img-pro/bandwidth-saver/issues)
