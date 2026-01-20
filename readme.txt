=== Free Image CDN – Bandwidth Saver ===
Contributors: imgpro
Tags: image cdn, cdn, speed, core web vitals, performance
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.2.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Free global image CDN for WordPress. Serve images from 300+ edge servers worldwide. Improves Core Web Vitals and PageSpeed scores. One-click setup.

== Description ==

**Free image CDN that makes your WordPress site faster.**

Images are the heaviest part of most WordPress pages. When they load slowly, visitors leave, Core Web Vitals fail, and Google ranks you lower.

This free image CDN plugin fixes that by serving your images from 300+ global edge servers. A visitor in Tokyo loads images from Asia. A visitor in London loads from Europe. Everyone gets faster pages.

**60-second setup.** No DNS changes. No external accounts. No settings to configure. Just activate and flip the switch.

= Why Use an Image CDN? =

* **Faster page load times** — Images load from the nearest server instead of traveling across the world from your host
* **Better Core Web Vitals** — Improve LCP (Largest Contentful Paint) by delivering images faster
* **Higher PageSpeed scores** — Google PageSpeed Insights will show improved performance
* **Lower bounce rates** — Visitors don't wait for slow sites

= How This Image CDN Works =

1. Install the free image CDN plugin from WordPress
2. Flip the switch to activate
3. Images instantly load from 300+ global CDN servers

That's it. Your original images stay exactly where they are on your server. The plugin only changes URLs on your public pages. Deactivate it and everything returns to normal instantly.

= Free Image CDN vs Paid CDN Services =

Most CDN solutions require DNS changes, external account setup, and technical configuration. This image CDN plugin works out of the box:

* **100 GB free per month** — Enough bandwidth for most WordPress sites
* **No credit card required** — Free tier is free forever
* **No DNS changes** — Works immediately after activation
* **No configuration** — Zero settings to configure
* **Can't break your site** — Automatic fallback to your server if anything goes wrong

= Works With Any WordPress Theme or Plugin =

This image CDN is compatible with:

* **Page builders** — Elementor, Divi, Beaver Builder, Gutenberg, Bricks, Oxygen
* **WooCommerce** — Product images, galleries, thumbnails
* **Image formats** — JPG, PNG, GIF, WebP, AVIF, SVG
* **Lazy loading** — Works with native lazy load and plugins
* **Responsive images** — Full srcset support
* **Caching plugins** — WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache

= Who This Image CDN Is For =

* Bloggers with image-heavy posts
* WooCommerce stores with product photos
* Recipe, travel, and photography sites
* Portfolio and agency sites
* Anyone who wants faster WordPress image loading without complexity

= Image CDN Pricing =

**Free** — 100 GB/month, free forever, no credit card
**Lite** ($4.99/mo) — 250 GB/month + custom CDN domain
**Pro** ($14.99/mo) — 2 TB/month + custom CDN domain
**Business** ($49/mo) — 10 TB/month + priority support

All paid plans include custom domains (cdn.yoursite.com) with automatic SSL.

= Self-Hosted Image CDN Option =

For developers who want full control, you can deploy the open-source worker on your own Cloudflare account. Your images, your infrastructure, zero external dependencies.

[Self-hosted CDN setup guide on GitHub](https://github.com/img-pro/bandwidth-saver-worker)

== Installation ==

**60-second setup. No technical knowledge required.**

1. Install and activate the image CDN plugin
2. Go to **Settings → Bandwidth Saver**
3. Toggle the CDN switch on

Done. Your images are now loading faster from the global CDN. No email required for the free tier.

== Frequently Asked Questions ==

= Is this really a free image CDN? =

Yes. 100 GB of bandwidth per month, free forever. No credit card required. No trial period. Most WordPress sites never need to upgrade.

= Will this image CDN improve my Core Web Vitals? =

Yes. The image CDN improves LCP (Largest Contentful Paint) by serving images from servers close to your visitors. Faster image delivery means better Core Web Vitals scores.

= How much will my PageSpeed score improve? =

Results vary by site, but most users see significant improvements in their Google PageSpeed Insights scores after enabling the image CDN. The improvement is most noticeable for visitors far from your hosting server.

= Does this CDN work with WooCommerce? =

Yes. The image CDN works with WooCommerce product images, galleries, thumbnails, and all image-heavy ecommerce content.

= Will this CDN work with my page builder? =

Yes. This image CDN works with Elementor, Divi, Beaver Builder, Gutenberg blocks, Bricks, Oxygen, and any other WordPress page builder.

= Does the image CDN support WebP and AVIF? =

Yes. The CDN serves all image formats including JPG, PNG, GIF, WebP, AVIF, and SVG.

= What if I go over my CDN bandwidth limit? =

Your images will temporarily load directly from your server (the normal way) until your bandwidth resets next month. Nothing breaks — your site just loads images without the CDN temporarily.

= Can I use this image CDN with caching plugins? =

Yes. This image CDN works perfectly with WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, and other WordPress caching plugins.

= Is this image CDN safe? Will it break my site? =

The image CDN cannot break your site. Your original images stay on your server completely untouched. The plugin only changes URLs on your public pages. If the CDN ever has issues, your site automatically falls back to loading images directly. Deactivate the plugin and everything returns to normal instantly.

= Does this replace image optimization plugins? =

No. This is an image *delivery* CDN, not an optimization tool. It makes images load faster by serving them from nearby servers. For making images *smaller*, use a compression plugin like ShortPixel, Imagify, or Smush. They work great alongside this image CDN.

= Do I need to change my DNS for this CDN? =

No. Unlike other CDN services, this image CDN works immediately without any DNS changes. Everything happens from your WordPress admin.

= Can I use my own domain for CDN URLs? =

Yes. All paid plans support custom domains (cdn.yoursite.com) with automatic SSL.

= What happens if the image CDN goes down? =

Your site automatically serves images directly from your server. Visitors won't notice anything — images just load the normal way until the CDN is back.

== Screenshots ==

1. Speed up your images in 60 seconds with the free image CDN
2. Track your CDN bandwidth and performance
3. Generous free tier, simple upgrades
4. Multi-site support and custom CDN domains
5. Self-host option for full control

== Privacy ==

= What Data Is Collected? =

The image CDN plugin does not add cookies, tracking pixels, or analytics to your site.

= Managed Mode =

* Your site URL is used to configure CDN routing
* Email is optional — only collected if you upgrade or request account recovery
* Custom domain settings are sent if configured

Images are cached and served through a global edge network powered by Cloudflare.

= Self-Hosted Mode =

No data is sent to us. Images are cached in your own Cloudflare account.

== External Services ==

This image CDN plugin connects to external services:

**Cloudflare (R2 Storage and Workers)**

* Purpose: Image caching and global edge CDN delivery
* [Terms of Service](https://www.cloudflare.com/terms/)
* [Privacy Policy](https://www.cloudflare.com/privacypolicy/)

**Bandwidth Saver API** (Managed mode only)

* Purpose: Account management, usage tracking
* Data sent: Site URL, email (if provided), custom domain (if configured)

Self-hosted users connect only to their own Cloudflare account.

== Changelog ==

= 0.2.5 =
* Improved: Simplified Self-Host setup instructions (3 steps instead of 4)
* Improved: Cleaner UI with single time estimate for setup process

= 0.2.4 =
* New: Frictionless activation — no email required for free image CDN tier
* Improved: Toggle on directly from dashboard, account created automatically
* Improved: Cleaner first-run experience with fewer steps
* Fixed: Account card displays correctly when email is not set

= 0.2.3 =
* Improved: Clearer messaging about image CDN speed and Core Web Vitals benefits
* Improved: Simplified onboarding copy
* Improved: Updated screenshot captions
* Fixed: PHPCS warnings for Stripe redirect handler

= 0.2.2 =
* Improved: Settings page loads faster with batched API requests
* Improved: Source URLs and usage stats are pre-loaded for the CDN dashboard
* Improved: Better WordPress coding standards compliance
* Fixed: Cleaner transient cleanup on uninstall

= 0.2.1 =
* New: Usage analytics dashboard with CDN bandwidth charts
* New: Source URLs management for multiple origin domains
* New: Projected bandwidth usage
* Improved: Image CDN works with infinite scroll and "load more"
* Improved: Faster settings page with smarter caching
* Fixed: Double-click prevention on all buttons
* Fixed: Custom CDN domain feature now available on Lite plans

= 0.2.0 =
* New: Updated pricing with bandwidth as primary metric
* New: All paid plans include custom CDN domain support
* New: Free tier upgraded to 100 GB CDN bandwidth/month
* Security: API keys encrypted at rest
* Security: Rate limiting on admin actions
* Security: Stricter validation of CDN domains

= 0.1.9 =
* Fixed: Image CDN activates reliably after payment or recovery
* Fixed: CDN properly disables when subscription becomes inactive

= 0.1.8 =
* Fixed: Payment success now correctly enables CDN toggle

= 0.1.7 =
* Improved: Redesigned CDN toggle with better visual feedback
* Fixed: Direct upgrade properly saves new tier limits

= 0.1.6 =
* New: Custom CDN domain support (cdn.yoursite.com)
* Fixed: Fallback uses correct URL for srcset images

= 0.1.5 =
* Improved: Simplified image CDN setup
* Improved: Faster image fallback with inline error handling
* Fixed: Images no longer flash on load

= 0.1.0 =
* New: Managed option for one-click image CDN setup
* New: Redesigned admin interface

= 0.0.1 =
* Initial release of the free image CDN plugin

== Upgrade Notice ==

= 0.2.4 =
Frictionless activation: Just flip the switch, no email required. The easiest free image CDN setup ever.

= 0.2.3 =
Clearer messaging and improved onboarding experience. Recommended for all users.

= 0.2.2 =
Performance improvements: Settings page now loads faster. Recommended for all users.

= 0.2.0 =
New pricing with more generous limits. Free image CDN tier now includes 100 GB/month. Security improvements. Recommended for all users.

== Support ==

* [Support Forum](https://wordpress.org/support/plugin/bandwidth-saver/)
* [Self-Host Guide](https://github.com/img-pro/bandwidth-saver-worker)
