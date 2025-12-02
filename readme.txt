=== Bandwidth Saver: Image CDN ===
Contributors: imgpro
Tags: cdn, images, cloudflare, performance, speed
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Deliver WordPress images from a global edge network — no DNS changes, no Cloudflare account, no configuration. Activate and go.

== Description ==

Images slow down WordPress sites. Setting up a CDN usually means DNS changes, caching rules, or a Cloudflare account — steps most site owners skip.

**Bandwidth Saver** delivers your existing WordPress images through a global edge network with zero configuration. Activate the plugin and images start loading from the nearest edge location automatically.

**Safe to try on any site.** No files are moved or deleted. Deactivate and your site returns to normal instantly. If the CDN ever has an issue, your original images load automatically.

= How It Works =

1. You upload images to WordPress normally
2. The plugin rewrites image URLs on your frontend pages
3. When a visitor requests an image, the CDN fetches and caches it
4. Future requests serve the cached copy from the nearest edge

Your Media Library URLs stay unchanged. WordPress remains the system of record.

= What This Plugin Does =

* Rewrites image URLs on frontend pages only
* Delivers cached images from Cloudflare's global edge network
* Falls back to original images automatically if CDN is unavailable
* Works with lazy loading, srcset, and responsive images
* Compatible with virtually all themes, page builders, and image optimization plugins

= What This Plugin Does NOT Do =

* Does not move, delete, or modify your original images
* Does not optimize or compress images
* Does not cache HTML, CSS, JavaScript, videos, or PDFs
* Does not require DNS changes
* Does not modify your database

This is a delivery optimization, not an image optimization plugin. It works alongside ShortPixel, Imagify, Smush, or any other optimizer.

= Who This Is For =

**Great for:**

* Blogs, magazines, and content-heavy sites
* WooCommerce stores with many product images
* Recipe, travel, photography, and portfolio sites
* Sites with global audiences
* High-traffic sites needing reliable delivery

**Ideal if you:**

* Cannot or prefer not to change DNS to Cloudflare
* Want CDN speed without technical configuration
* Want faster images without touching hosting settings

= Who This Is NOT For =

* Sites already using Cloudflare DNS with optimized caching
* Sites needing full media offloading to external storage
* Sites needing full-page CDN caching (HTML, CSS, JS)

= Two Ways to Use =

**Managed (Recommended)**

One-click activation. We operate the global edge infrastructure. No Cloudflare account required.

* Free: 100 GB bandwidth/month — free forever
* Lite ($4.99/mo): 250 GB bandwidth/month, custom domain
* Pro ($14.99/mo): 2 TB bandwidth/month, custom domain
* Business ($49/mo): 10 TB bandwidth/month, custom domain, priority support

Bandwidth resets monthly.

All paid plans support custom domains (cdn.yoursite.com) with automatic SSL.

**Self-Hosted (Free)**

For technical users who want full control. Deploy our open-source Cloudflare Worker on your own account and pay Cloudflare directly (often $0/month on their free tier).

Requires: Cloudflare account, Worker deployment, custom domain.

The Worker is fully open source: [github.com/img-pro/bandwidth-saver-worker](https://github.com/img-pro/bandwidth-saver-worker)

== Installation ==

= Managed Setup (Under 1 Minute) =

1. Install and activate the plugin
2. Go to **Settings → Bandwidth Saver**
3. Enter your email to create a free account
4. Enable the CDN toggle
5. Done — images now load from the global edge

No DNS changes. No Cloudflare account. No configuration.

= Self-Hosted Setup (About 15 Minutes) =

1. Create a free Cloudflare account
2. Deploy the Worker from GitHub
3. Add a custom domain to your Worker (e.g., cdn.yoursite.com)
4. Enter your CDN domain in **Settings → Bandwidth Saver → Self-Host**

Full guide: [github.com/img-pro/bandwidth-saver-worker#setup](https://github.com/img-pro/bandwidth-saver-worker#setup)

== Frequently Asked Questions ==

= How long does setup take? =

Managed: Under a minute.
Self-Hosted: About 15 minutes if familiar with Cloudflare.

= How much does it cost? =

Managed: Free tier available (100 GB bandwidth/month). Paid plans start at $4.99/month.
Self-Hosted: Free. You pay Cloudflare directly (often $0 on their free tier).

= Will this break my site? =

No. Your original images stay on your server untouched. The plugin only rewrites URLs on your frontend. Disable it and your site returns to normal instantly.

= Do I need a Cloudflare account? =

Managed: No.
Self-Hosted: Yes.

= Do I need to change my DNS? =

No. This is one of the main benefits — you get CDN speed without DNS changes.

= Can I use my own domain for image URLs? =

Yes. All paid plans (Lite, Pro, Business) support custom domains (e.g., cdn.yoursite.com) with automatic SSL provisioning.

= Does this change my Media Library URLs? =

No. Your Media Library URLs stay exactly the same. Only frontend output is rewritten.

= Does it work with WebP and AVIF? =

Yes. The plugin supports JPG, PNG, GIF, WebP, AVIF, and SVG formats.

= Does it work with lazy loading and srcset? =

Yes. Fully compatible.

= Are first requests slower? =

The first request for each image is served from your origin while the CDN caches it. After that, delivery is very fast from the nearest edge.

= What happens if the CDN is down? =

Your site automatically falls back to serving original images from your server. Visitors won't notice any difference.

= What happens if I deactivate the plugin? =

Everything returns to normal immediately. Your images load from your server as before. Cached copies in the CDN are cleaned up automatically over time.

= Is this an offloading plugin? =

No. Your images stay on your WordPress server. This optimizes delivery, not storage.

= Can this handle large sites? =

Yes. The infrastructure is built on Cloudflare's global network. Business plan supports 10 TB bandwidth/month. Self-hosted mode supports unlimited growth.

== Screenshots ==

1. Get started in under a minute
2. Your images are now loading from the edge
3. Upgrade when you need more
4. Use your own domain (paid plans)
5. Or self-host on your Cloudflare account

== Privacy ==

= What the plugin collects =

The plugin does not add cookies, tracking pixels, or analytics to your site.

= Managed mode =

When using Managed mode, the following data is sent to the ImgPro Cloud API:

* Your site URL (to configure CDN routing)
* Admin email (for account creation and service communications)
* Custom domain settings (if configured)

Images are cached and served through Cloudflare infrastructure operated by ImgPro.

= Self-hosted mode =

No data is sent to ImgPro. Images are cached in your own Cloudflare account.

= Cloudflare logging =

Cloudflare logs standard CDN metadata (IP addresses, timestamps, request headers). This is normal for any CDN service. See Cloudflare's privacy policy for details.

== External Services ==

This plugin connects to external services to deliver images:

**Cloudflare R2 and Workers**
Purpose: Image caching and global edge delivery
Terms: https://www.cloudflare.com/terms/
Privacy: https://www.cloudflare.com/privacypolicy/

**ImgPro Cloud API** (Managed mode only)
Purpose: Account management, subscription handling, usage tracking, custom domain provisioning
Data sent: Site URL, admin email, custom domain (if configured)
Data stored: Subscription status, API key, usage metrics, custom domain settings

Self-hosted users connect only to their own Cloudflare account.

== Fair Use Policy ==

Managed mode includes bandwidth limits per plan tier. Bandwidth resets monthly.

If bandwidth usage consistently exceeds plan limits, we will contact you to discuss options. The Self-Hosted option is recommended for sites needing unlimited growth.

== Terms of Service ==

We reserve the right to refuse, suspend, or terminate service at our discretion. Circumstances that may result in service action include:

* Illegal content (including copyright infringement)
* Abusive usage patterns (infrastructure attacks, proxy abuse)
* Violation of Cloudflare's terms of service
* Non-payment or payment fraud
* Activity that degrades service for other users

You are responsible for the images served through your account.

== Changelog ==

= 0.2.0 =
* New: Updated pricing model — bandwidth is now the primary metric
* New: All paid plans now include custom domain support
* New: Free tier upgraded to 100 GB bandwidth/month
* Security: API keys are now encrypted at rest in the database
* Security: Added rate limiting to prevent brute-force attacks on admin actions
* Security: Stricter validation of CDN domains (blocks IPs, localhost, reserved domains)
* Security: Protection against IDN homograph attacks on custom domains
* Security: HTTPS enforcement for all API communications
* Security: Granular permission system with dedicated capability
* Improved: Usage stats now update in real-time after plan changes
* Improved: Simplified pricing display — bandwidth is the only limit shown
* Fixed: Plan limits now display correctly immediately after upgrade

= 0.1.9 =
* Fixed: CDN now activates reliably after payment or account recovery
* Fixed: CDN properly disables when subscription becomes inactive
* Improved: Better error messages when requests time out

= 0.1.8 =
* Improved: Updated messaging to focus on global edge network
* Fixed: Payment success now correctly enables CDN toggle
* Fixed: Debug mode checkbox vertical alignment

= 0.1.7 =
* Improved: Redesigned CDN toggle card with accent color active state
* Improved: Better visual feedback for SSL certificate issuance status
* Fixed: Direct upgrade now properly saves new tier limits
* Fixed: Removing self-hosted CDN domain now properly disables CDN

= 0.1.6 =
* New: Custom domain support for Managed mode (e.g., cdn.yoursite.com)
* Fixed: Fallback now uses the actual failed URL (currentSrc) instead of src attribute
* Fixed: Proper fallback for srcset images when CDN fails

= 0.1.5 =
* Improved: Simplified self-hosted setup to single CDN domain
* Improved: Faster image fallback with inline error handling
* Improved: Images no longer flash on load
* Fixed: Better compatibility with strict CSP policies

= 0.1.4 =
* Improved: Updated copy and documentation for clarity
* Improved: Better audience targeting and positioning

= 0.1.3 =
* Improved: Clearer error messages for payment and subscription issues
* Improved: Faster page processing — pages without images skip CDN processing
* Improved: New imgpro_cdn_api_base_url filter for staging environments
* Improved: Better handling of edge cases in checkout flow

= 0.1.2 =
* Fixed: Plugin no longer disables itself when saving settings
* Fixed: Improved reliability for dynamically loaded images
* Improved: Cloud mode now auto-configures without manual URL entry
* Security: Enhanced protection and CSP compatibility

= 0.1.0 =
* New: Managed option for one-click setup
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

= 0.2.0 =
New pricing model with more generous limits. Free tier now includes 100 GB bandwidth/month. All paid plans include custom domain. Security hardening with encrypted API keys and rate limiting. Recommended for all users.

= 0.1.9 =
Critical fix for CDN activation. Prevents CDN from silently failing to enable after payment or recovery.

= 0.1.8 =
Fixed payment flow to automatically enable CDN. Recommended for all users.

= 0.1.7 =
Improved toggle UI and fixed tier upgrade sync. Recommended for all users.

= 0.1.6 =
Adds custom domain support for Managed mode. Recommended for all users.

= 0.1.5 =
Simplified architecture and fixed image flashing. Self-hosted users now only need one CDN domain.

== Support ==

* Documentation: [github.com/img-pro/bandwidth-saver](https://github.com/img-pro/bandwidth-saver)
* Support Forum: [wordpress.org/support/plugin/bandwidth-saver](https://wordpress.org/support/plugin/bandwidth-saver/)
* Worker Setup Guide: [github.com/img-pro/bandwidth-saver-worker](https://github.com/img-pro/bandwidth-saver-worker)
* Report Issues: [github.com/img-pro/bandwidth-saver/issues](https://github.com/img-pro/bandwidth-saver/issues)
