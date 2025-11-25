=== Bandwidth Saver: Image CDN ===
Contributors: imgpro
Tags: cdn, images, cloudflare, performance, speed
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Speed up your WordPress images with Cloudflare's global CDN. One-click setup, works with any theme or plugin.

== Description ==

**Your images are slowing down your site.** Every visitor downloads them from your server, eating bandwidth and making pages load slowly for users far from your hosting location.

**Bandwidth Saver** fixes this by serving your images from Cloudflare's global network of 300+ data centers. Your visitors get images from the server nearest to them - whether they're in Tokyo, London, or New York.

= Why Bandwidth Saver? =

**Simple:** One-click setup. No Cloudflare account needed. No configuration headaches.

**Compatible:** Works with your existing WordPress setup - any theme, any page builder, any optimization plugin. It doesn't fight with your tools, it works alongside them.

**Affordable:** Most WordPress sites pay $0/month. Cloudflare R2's zero egress fees mean delivery is essentially free after the initial cache.

**Reliable:** Images are cached globally and served directly from Cloudflare's edge. If the CDN is temporarily unavailable, images automatically fall back to your original server.

= How It Works =

1. You activate the plugin
2. Image URLs are automatically rewritten to point to Cloudflare
3. First visitor triggers caching (images stored in Cloudflare R2)
4. All future visitors get images from the nearest Cloudflare edge server

No changes to your workflow. WordPress handles your images exactly as before - the plugin just makes delivery faster.

= Works With Everything =

* **Any theme** - Classic, block, or hybrid
* **Any page builder** - Gutenberg, Elementor, Beaver Builder, Divi, etc.
* **Any image plugin** - ShortPixel, Imagify, Smush, EWWW, etc.
* **Any caching plugin** - WP Rocket, W3 Total Cache, LiteSpeed, etc.
* **Any format** - JPG, PNG, GIF, WebP, AVIF, SVG

If your optimization plugin converts images to WebP, Bandwidth Saver delivers those WebP files. If you use lazy loading, it still works. The plugin handles the delivery layer - everything else stays the same.

= Two Ways to Get Started =

**Managed (Recommended)**
Click one button and you're done. We handle the infrastructure. Perfect for most sites.

**Self-Hosted**
Deploy to your own Cloudflare account for complete control. Free tier works great. Ideal for developers and agencies managing multiple sites.

== Installation ==

= Managed Setup (Recommended) =

1. Install and activate the plugin
2. Go to **Settings → Image CDN**
3. Click **Get Started** on the Managed tab
4. Complete the quick checkout
5. Done! Images now load from Cloudflare's global network

= Self-Hosted Setup =

For developers who want full control:

1. Create a free Cloudflare account (if you don't have one)
2. Deploy the worker from [our GitHub repository](https://github.com/img-pro/bandwidth-saver-worker)
3. Configure your R2 bucket with a custom domain
4. Enter your CDN and Worker domains in **Settings → Image CDN**

Detailed setup guide: [github.com/img-pro/bandwidth-saver-worker](https://github.com/img-pro/bandwidth-saver-worker#setup)

== Frequently Asked Questions ==

= Will this work with my theme/plugin? =

Yes. Bandwidth Saver works at the URL level, so it's compatible with virtually any WordPress setup. We've tested with major themes, page builders, and optimization plugins.

= Do I need a Cloudflare account? =

**For Managed:** No. We handle everything.
**For Self-Hosted:** Yes, but the free tier is sufficient for most sites.

= How much does it cost? =

**Managed:** $2.99/month for unlimited images and bandwidth.

**Self-Hosted:** Typically $0/month on Cloudflare's free tier. Even high-traffic sites rarely exceed a few dollars.

= What about image optimization? =

Bandwidth Saver focuses on **delivery**, not optimization. Keep using your favorite optimization plugin (ShortPixel, Imagify, etc.) to compress and convert images. Bandwidth Saver will deliver whatever WordPress generates - optimized or not.

= Does it support WebP/AVIF? =

Yes. Whatever image format WordPress serves, Bandwidth Saver delivers. Use any format conversion plugin you like.

= What happens if Cloudflare is down? =

Images automatically fall back to loading from your server. Your site keeps working - just without the CDN speed boost until service resumes.

= Can I use this on a multisite? =

Yes. Each site in your network needs its own configuration, but the plugin works on multisite installations.

= What happens when I deactivate the plugin? =

Your images immediately load from your server again. No broken images, no cleanup needed. Your original files are never modified.

= What data does the plugin collect? =

None. We don't track visitors, don't use cookies, and don't collect analytics. The plugin simply rewrites URLs - that's it.

== Screenshots ==

1. **Managed Setup** - One-click activation with the Managed service
2. **Active State** - Plugin enabled, showing CDN status
3. **Self-Hosted Configuration** - Enter your own Cloudflare domains

== Changelog ==

= 0.1.2 =
* Fixed: Plugin no longer disables itself when saving Cloud or Cloudflare settings
* Fixed: Improved reliability for dynamically loaded images (infinite scroll, AJAX)
* Improved: Better handling of browser-cached images
* Improved: Cloud mode now auto-configures - no manual URL entry needed
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

= 0.1.2 =
Fixes settings save bug and improves reliability. Recommended for all users.

= 0.1.0 =
Major update with one-click Managed setup and redesigned interface. Recommended for all users.

== Privacy ==

Bandwidth Saver:

* Does not collect visitor data
* Does not use cookies
* Does not track anything
* Does not send data to plugin authors

**For Managed users:** Images are cached on Cloudflare infrastructure managed by ImgPro. Only publicly accessible images are cached. See Cloudflare's [privacy policy](https://www.cloudflare.com/privacypolicy/).

**For Self-Hosted users:** Images are stored in your own Cloudflare account. You have full control over your data.

== External Services ==

This plugin connects to external services to deliver images:

**Cloudflare R2 & Workers**

* Purpose: Stores and serves cached images globally
* Provider: Cloudflare, Inc.
* Terms: [cloudflare.com/terms](https://www.cloudflare.com/terms/)
* Privacy: [cloudflare.com/privacypolicy](https://www.cloudflare.com/privacypolicy/)

**ImgPro Cloud API** (Managed mode only)

* Purpose: Subscription management and CDN routing
* Provider: ImgPro
* Data sent: Site URL, admin email (for account recovery)
* Data stored: Subscription status only

== Support ==

* **Documentation:** [github.com/img-pro/bandwidth-saver](https://github.com/img-pro/bandwidth-saver)
* **Support Forum:** [wordpress.org/support/plugin/bandwidth-saver](https://wordpress.org/support/plugin/bandwidth-saver/)
* **Worker Setup Guide:** [github.com/img-pro/bandwidth-saver-worker](https://github.com/img-pro/bandwidth-saver-worker)
