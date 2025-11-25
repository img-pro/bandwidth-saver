=== Bandwidth Saver: Image CDN ===
Contributors: imgpro
Tags: cdn, images, cloudflare, performance, bandwidth
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Serve images from Cloudflare's global CDN. Zero egress fees, one-click setup, works with any theme.

== Description ==

**Your images are slowing down your site.** Every visitor downloads them from your server, eating bandwidth and making pages load slowly for users far from your hosting location.

**Bandwidth Saver** fixes this by serving your images from Cloudflare's global network of 300+ data centers. Your visitors get images from the server nearest to them — whether they're in Tokyo, London, or New York.

= Why Bandwidth Saver? =

**Zero Egress Fees:** Built on Cloudflare R2, which doesn't charge for data transfer. Most sites pay nothing for image delivery after the initial cache.

**One-Click Setup:** No Cloudflare account needed. No DNS changes. No configuration headaches. Just activate and go.

**Works With Everything:** Any theme, any page builder, any caching plugin. It doesn't fight with your existing setup — it enhances it.

**Bulletproof Fallback:** If the CDN is ever unavailable, images automatically load from your server. Your site never breaks.

= How It Works =

1. You activate the plugin
2. Image URLs are automatically rewritten to point to Cloudflare
3. First visitor triggers caching (images stored in Cloudflare R2)
4. All future visitors get images from the nearest edge server

No changes to your workflow. Upload images to WordPress exactly as before — the plugin handles delivery.

= Works With Everything =

* **Any theme** — Classic, block, or hybrid themes
* **Any page builder** — Gutenberg, Elementor, Beaver Builder, Divi, Bricks, etc.
* **Any image plugin** — ShortPixel, Imagify, Smush, EWWW, etc.
* **Any caching plugin** — WP Rocket, W3 Total Cache, LiteSpeed, WP Super Cache, etc.
* **Any format** — JPG, PNG, GIF, WebP, AVIF, SVG

If your optimization plugin converts images to WebP, Bandwidth Saver delivers those WebP files. If you use lazy loading, it still works. The plugin handles the delivery layer — everything else stays the same.

= Two Ways to Use =

**Managed (Recommended)**
One button, done. We handle the infrastructure. $2.99/month for unlimited images and bandwidth. Perfect for most WordPress sites.

**Self-Hosted (Free)**
Deploy to your own Cloudflare account. Full control, typically $0/month on the free tier. Ideal for developers and agencies.

= Open Source =

The Cloudflare Worker that powers this plugin is [fully open source](https://github.com/img-pro/bandwidth-saver-worker). Inspect the code, fork it, or contribute improvements.

== Installation ==

= Managed Setup (30 seconds) =

1. Install and activate the plugin
2. Go to **Settings → Image CDN**
3. Click the **Managed** tab
4. Click **Activate Now** and complete checkout
5. Done — images now load from Cloudflare worldwide

= Self-Hosted Setup (20 minutes) =

For developers who want full control:

1. Create a free [Cloudflare account](https://cloudflare.com) if you don't have one
2. Deploy the worker from [our GitHub repository](https://github.com/img-pro/bandwidth-saver-worker)
3. Configure your R2 bucket with a custom domain
4. Enter your CDN and Worker domains in **Settings → Image CDN → Self-Host**

Detailed guide: [github.com/img-pro/bandwidth-saver-worker](https://github.com/img-pro/bandwidth-saver-worker#setup)

== Frequently Asked Questions ==

= Will this work with my theme/plugin? =

Yes. Bandwidth Saver works at the URL level, making it compatible with virtually any WordPress setup. We've tested with major themes, page builders, and optimization plugins.

= Do I need a Cloudflare account? =

**For Managed:** No. We handle everything.
**For Self-Hosted:** Yes, but the free tier is sufficient for most sites.

= How much does it cost? =

**Managed:** $2.99/month for unlimited images and bandwidth.
**Self-Hosted:** Typically $0/month. Even high-traffic sites rarely exceed a few dollars on Cloudflare's generous free tier.

= What about image optimization (compression, WebP)? =

Bandwidth Saver focuses on **delivery**, not optimization. Keep using your favorite optimization plugin (ShortPixel, Imagify, Smush, etc.) to compress and convert images. Bandwidth Saver delivers whatever WordPress generates — optimized or not.

= Does it support WebP/AVIF? =

Yes. Whatever image format WordPress serves, Bandwidth Saver delivers. Use any format conversion plugin you like.

= What happens if Cloudflare is down? =

Images automatically fall back to your server. Your site keeps working — just without the CDN speed boost until service resumes.

= Can I use this on multisite? =

Yes. Each site in your network needs its own configuration, but the plugin works on multisite installations.

= What happens when I deactivate? =

Images immediately load from your server again. No broken images, no cleanup needed. Your original files are never modified.

= What data does the plugin collect? =

None from your visitors. We don't track users, don't use cookies, and don't collect analytics. The plugin simply rewrites URLs.

For Managed users: We store your site URL and email for account recovery. That's it.

= How do I test or debug the plugin? =

Developers can use the `imgpro_cdn_api_base_url` filter to point to a staging environment, and hook into `imgpro_cdn_api_error` for error logging.

== Screenshots ==

1. **Managed Setup** — One-click activation, no Cloudflare account needed
2. **Active State** — Plugin enabled, showing CDN status at a glance
3. **Self-Hosted Configuration** — Enter your own Cloudflare domains

== Changelog ==

= 0.1.3 =
*Stability & Developer Experience Release*

* **Clearer error messages** — When payment or subscription issues occur, you now see specific, actionable messages instead of generic errors
* **Faster page processing** — Pages without images skip CDN processing entirely, reducing overhead
* **Better developer tools** — New `imgpro_cdn_api_base_url` filter for testing with staging environments
* **Improved code architecture** — Cleaner separation of concerns makes the plugin easier to maintain and extend
* **Enhanced reliability** — Better handling of edge cases in the checkout and subscription flow

= 0.1.2 =
* Fixed: Plugin no longer disables itself when saving settings
* Fixed: Improved reliability for dynamically loaded images (infinite scroll, AJAX)
* Improved: Better handling of browser-cached images
* Improved: Cloud mode now auto-configures — no manual URL entry needed
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

== Privacy ==

Bandwidth Saver respects your privacy and your visitors' privacy:

* Does not track visitors
* Does not use cookies
* Does not collect analytics
* Does not phone home

**For Managed users:** Images are cached on Cloudflare infrastructure managed by ImgPro. Only publicly accessible images are cached. Your site URL and admin email are stored for account management. See Cloudflare's [privacy policy](https://www.cloudflare.com/privacypolicy/).

**For Self-Hosted users:** Images are stored in your own Cloudflare account. You have complete control over your data.

== External Services ==

This plugin connects to external services to deliver images:

**Cloudflare R2 & Workers**

* Purpose: Stores and serves cached images from 300+ global locations
* Provider: Cloudflare, Inc.
* Terms: [cloudflare.com/terms](https://www.cloudflare.com/terms/)
* Privacy: [cloudflare.com/privacypolicy](https://www.cloudflare.com/privacypolicy/)

**ImgPro Cloud API** (Managed mode only)

* Purpose: Subscription management and CDN configuration
* Provider: ImgPro
* Data sent: Site URL, admin email (for account recovery)
* Data stored: Subscription status only

== Support ==

* **Documentation:** [github.com/img-pro/bandwidth-saver](https://github.com/img-pro/bandwidth-saver)
* **Support Forum:** [wordpress.org/support/plugin/bandwidth-saver](https://wordpress.org/support/plugin/bandwidth-saver/)
* **Worker Setup Guide:** [github.com/img-pro/bandwidth-saver-worker](https://github.com/img-pro/bandwidth-saver-worker)
* **Report Issues:** [github.com/img-pro/bandwidth-saver/issues](https://github.com/img-pro/bandwidth-saver/issues)
