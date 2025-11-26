=== Bandwidth Saver: Image CDN ===
Contributors: imgpro
Tags: cdn, images, cloudflare, performance, bandwidth
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Faster images without touching DNS. No Cloudflare account needed. Install, activate, done.

== Description ==

WordPress images often load slowly, especially on shared hosting. Setting up a real CDN usually means editing DNS records, configuring Cloudflare, or dealing with complex caching rules. Most site owners just want faster images without the technical overhead.

**Bandwidth Saver** solves this by delivering your existing WordPress images through Cloudflare's global edge network. No DNS changes. No Cloudflare account needed. No configuration. Just activate and go.

Your images stay on your server. Your Media Library stays the same. If anything goes wrong, WordPress automatically loads the original images. You can disable the plugin anytime and your site instantly goes back to normal.

= How It Works =

1. You upload images to WordPress as usual
2. The plugin rewrites image URLs on your frontend pages
3. When a visitor requests an image, a Cloudflare Worker fetches it from your site and stores it in R2
4. Future requests serve the cached image from Cloudflare's edge

**Your original images stay on your server.** WordPress keeps full control. The plugin only changes how images are delivered to visitors.

The first request to each image may be slightly slower while Cloudflare caches it. Future requests are fast and delivered from the nearest Cloudflare edge.

= What This Plugin Does =

* Rewrites image URLs on your frontend pages (your Media Library URLs stay the same)
* Delivers cached images from Cloudflare's global edge network
* Falls back to your original images if Cloudflare is unavailable
* Works with lazy loading, responsive image sizes, and srcset

The plugin delivers whatever WordPress outputs, including images processed by optimization plugins.

= What This Plugin Does NOT Do =

* Does not move or delete your images
* Does not optimize or compress images
* Does not replace your existing image plugins
* Does not cache HTML, CSS, or JavaScript
* Does not require DNS changes or Cloudflare proxy

Keep using your favorite image optimization plugin. Bandwidth Saver handles the delivery layer only.

= Who This Is For =

This plugin is ideal if you run:

* Blogs and content sites
* Recipe sites
* Photography or portfolio sites
* WooCommerce stores with many product images
* Travel and lifestyle sites
* Image-heavy sites on shared hosting

It is also a good fit if you:

* Cannot or do not want to move your DNS to Cloudflare
* Want faster image loading without configuring caching rules
* Want Cloudflare-level speed without the Cloudflare learning curve

= Who This Is NOT For =

This plugin may not be right if you:

* Already use Cloudflare DNS with caching rules (you may not need this)
* Need to fully offload media files from your server
* Need caching for HTML, CSS, or JavaScript
* Want fine-grained control over Cloudflare features

= Why Try It =

* **No DNS changes required.** Works with any hosting setup.
* **No Cloudflare account needed** for the Managed option.
* **Nothing to configure.** Install, activate, done.
* **Safe to disable.** Your site instantly goes back to normal.
* **No database changes.** Settings are stored cleanly in a single option.
* **Works with any theme or page builder.** Tested with Elementor, Beaver Builder, Divi, Bricks, Gutenberg, and more.
* **Works with image optimization plugins.** ShortPixel, Imagify, Smush, EWWW, and others.
* **Supports all image formats.** JPG, PNG, GIF, WebP, AVIF, SVG.

= Two Ways to Use =

**Managed (Recommended for most users)**
One click setup. We handle the Cloudflare Worker and R2 storage. No Cloudflare account needed.

The Managed plan costs $9.99 per month and includes up to 500 GB of storage and 5 TB of monthly bandwidth. This is more than enough for most small and medium WordPress sites.

**Self-Hosted (Free)**
For technical users who prefer running Cloudflare on their own account. You control the infrastructure and pay Cloudflare directly (usually $0/month on their free tier).

Requires: Cloudflare account, R2 bucket, Worker deployment, custom domain setup.

= Open Source =

The Cloudflare Worker that powers this plugin is [fully open source](https://github.com/img-pro/bandwidth-saver-worker). Inspect the code, fork it, or contribute improvements.

== Installation ==

= Managed Setup (Under 1 Minute) =

1. Install and activate the plugin
2. Go to **Settings > Bandwidth Saver**
3. Click the **Managed** tab
4. Click **Activate Now** and complete checkout
5. Done. Images now load from Cloudflare.

No Cloudflare account. No DNS changes. No configuration.

= Self-Hosted Setup (About 20 Minutes) =

For technical users who prefer running Cloudflare on their own account:

1. Create a free [Cloudflare account](https://cloudflare.com) if you do not have one
2. Deploy the worker from [our GitHub repository](https://github.com/img-pro/bandwidth-saver-worker)
3. Create an R2 bucket and configure a custom domain
4. Enter your CDN and Worker domains in **Settings > Bandwidth Saver > Self-Host**

Detailed guide: [github.com/img-pro/bandwidth-saver-worker](https://github.com/img-pro/bandwidth-saver-worker#setup)

== Frequently Asked Questions ==

= How long does setup take? =

**Managed:** Under a minute. Install, activate, subscribe, done.

**Self-Hosted:** About 20 minutes if you are familiar with Cloudflare.

= How much does it cost? =

**Managed:** $9.99 per month. Includes up to 500 GB of storage and 5 TB of monthly bandwidth.

**Self-Hosted:** Free. You pay Cloudflare directly, usually $0/month on their free tier.

= Will this break my site? =

No. The plugin only rewrites image URLs on your frontend. Your original images stay on your server. If anything goes wrong, disable the plugin and your site instantly goes back to normal.

= Do I need a Cloudflare account? =

**For Managed:** No. We handle everything.

**For Self-Hosted:** Yes, but the free tier is enough for most sites.

= Do I need to change my DNS? =

No. This plugin works without DNS changes. That is the main benefit for users who cannot or do not want to proxy their site through Cloudflare.

= Does this change my Media Library URLs? =

No. Image URLs are rewritten on the frontend only. Your Media Library URLs stay the same.

= What about image optimization (compression, WebP)? =

Bandwidth Saver delivers images. It does not optimize them. Keep using your favorite optimization plugin (ShortPixel, Imagify, Smush, etc.) to compress and convert images. Bandwidth Saver delivers whatever WordPress generates.

= Does it support WebP and AVIF? =

Yes. Whatever format WordPress serves, Bandwidth Saver delivers.

= Does it work with lazy loading and srcset? =

Yes. Works with lazy loading, responsive image sizes, and srcset. The plugin delivers whatever WordPress outputs.

= Are the first requests slower? =

The first request to each image may be slightly slower while Cloudflare caches it. Future requests are fast and delivered from the nearest Cloudflare edge.

= What happens if Cloudflare is down? =

Images fall back to your server automatically. Your site keeps working.

= Can I use this on multisite? =

Yes. Each site in your network needs its own configuration.

= What happens when I deactivate the plugin? =

Images load from your server again. No cleanup needed. Your original files are never modified.

= Will this work with my theme or page builder? =

Yes. Bandwidth Saver works at the URL level and is compatible with virtually any WordPress setup. Tested with major themes, page builders, and optimization plugins.

= Is this an offloading plugin? =

No. Your images stay on your WordPress server. The plugin only rewrites URLs so visitors receive cached copies from Cloudflare.

= Is this a full CDN replacement? =

No. This plugin only handles images. It does not cache HTML, CSS, JavaScript, or other assets.

= What if I exceed the Managed plan limits? =

The Managed plan includes 500 GB of storage and 5 TB of monthly bandwidth as soft limits. If your site grows beyond these limits, we recommend switching to the Self-Hosted option, which gives you full control over your own Cloudflare account with no restrictions.

== Screenshots ==

1. **One-Click Setup** - Turn on Cloudflare image delivery in one click with the Managed option
2. **Simple Status** - Clean and simple status indicators show your image delivery is working
3. **Self-Hosted Option** - Full control for technical users who want to run their own Cloudflare Worker

== Privacy ==

= What the plugin collects =

The plugin itself does not add cookies, tracking scripts, or analytics to your site.

= What Cloudflare logs =

When images are delivered through Cloudflare, standard CDN request metadata is logged by Cloudflare (IP addresses, timestamps, request headers, etc.). This is standard for any CDN service.

**For Managed users:** Images are cached on Cloudflare infrastructure managed by ImgPro. Your site URL and admin email are stored for account management. Review Cloudflare's [privacy policy](https://www.cloudflare.com/privacypolicy/).

**For Self-Hosted users:** Images are stored in your own Cloudflare account. You have full control over your data and logs.

= Recommendation =

If you have strict privacy requirements, consider the Self-Hosted option for complete control, or review Cloudflare's data processing terms before using the Managed service.

== External Services ==

This plugin connects to external services to deliver images:

= Cloudflare R2 and Workers =

* **Purpose:** Stores and serves cached images from 300+ global edge locations
* **Provider:** Cloudflare, Inc.
* **When used:** Every time a visitor loads an image on your site
* **Terms:** [cloudflare.com/terms](https://www.cloudflare.com/terms/)
* **Privacy:** [cloudflare.com/privacypolicy](https://www.cloudflare.com/privacypolicy/)

= ImgPro Cloud API (Managed mode only) =

* **Purpose:** Subscription management and CDN configuration
* **Provider:** ImgPro
* **When used:** During checkout, subscription management, and account recovery
* **Data sent:** Site URL, admin email
* **Data stored:** Subscription status, API key

== Fair Use Policy ==

The Managed plan costs $9.99 per month and includes up to 500 GB of storage and 5 TB of monthly bandwidth. These are soft limits designed to accommodate most small and medium WordPress sites.

**If your usage grows beyond these limits:**

We will reach out to discuss your options. In most cases, we recommend switching to the Self-Hosted option, which gives you full control over your own Cloudflare account with no restrictions from us.

**What may require self-hosting:**

* Sites with very high image traffic
* Sites serving very large files repeatedly
* Patterns that suggest automated or abusive access

We want you to succeed. If your site outgrows the Managed plan, the Self-Hosted option is a natural next step that puts you in full control.

== Content Responsibility ==

You are responsible for the content served through this plugin.

* Do not use this service to distribute illegal, harmful, or abusive content
* Do not use this service to circumvent copyright protections
* Accounts serving prohibited content may be suspended without notice

For high-volume or high-risk use cases, the Self-Hosted option gives you full control over your own Cloudflare account.

== Changelog ==

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
