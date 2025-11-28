# Bandwidth Saver: Image CDN

[![WordPress Plugin Version](https://img.shields.io/badge/version-0.1.5-blue.svg)](https://wordpress.org/plugins/bandwidth-saver/)
[![Requires WordPress Version](https://img.shields.io/badge/wordpress-6.2%2B-blue.svg)](https://wordpress.org/download/)
[![Requires PHP Version](https://img.shields.io/badge/php-7.4%2B-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-red.svg)](LICENSE)

**Faster images without touching DNS. Rewrites image URLs to load through Cloudflare. No configuration needed.**

## Overview

WordPress images often load slowly, especially on shared hosting. Large sites with thousands of images can grind to a halt without a CDN. But setting up Cloudflare usually means editing DNS records, configuring caching rules, and navigating technical steps that many site owners prefer to avoid.

**Bandwidth Saver** solves this by delivering your existing WordPress images through Cloudflare's global edge network. No DNS changes. No Cloudflare account needed. No configuration. Just activate and go.

Safe to try on any site. Does not touch your database or existing files. You can disable it at any time and your site instantly returns to normal. If Cloudflare ever has an issue, WordPress automatically loads your original images.

## How It Works

1. You upload images to WordPress as usual
2. The plugin rewrites image URLs on your frontend pages
3. When a visitor requests an image, a Cloudflare Worker fetches it from your site and stores it in R2
4. Future requests serve the cached image from Cloudflare's edge

**Your original images stay on your server.** WordPress keeps full control. The plugin only changes how images are delivered to visitors.

Image URLs are rewritten on the frontend only. Your Media Library URLs stay exactly the same.

The first request to each image may be slightly slower while Cloudflare caches it. Future requests are extremely fast and come from the nearest Cloudflare edge.

## What This Plugin Does

- Rewrites image URLs on your frontend pages (your Media Library URLs stay exactly the same)
- Delivers cached images from Cloudflare's global edge network
- Falls back to your original images automatically if Cloudflare is unavailable
- Works with lazy loading, responsive image sizes, and srcset
- Works with any theme or page builder without modifying templates or file structures
- Designed to handle large, image-heavy websites with ease

The plugin simply delivers whatever WordPress outputs, including images processed by optimization plugins.

## What This Plugin Does NOT Do

- Does not move or delete your images
- Does not optimize or compress images
- Does not replace your existing image plugins
- Does not cache HTML, CSS, or JavaScript
- Does not require DNS changes or Cloudflare proxy
- Does not touch your database

## Two Ways to Use

### Managed (Recommended for most users)

One click setup. We handle the Cloudflare Worker and R2 storage. No Cloudflare account needed.

The free tier includes 10 GB storage and 50 GB bandwidth. The Pro plan costs $14.99 per month and includes 120 GB of storage with 2 TB bandwidth. This comfortably supports everything from small blogs to large, image-heavy WordPress installations.

### Self-Hosted (Free)

For technical users who prefer running Cloudflare on their own account. You control the infrastructure and pay Cloudflare directly (usually $0/month on their free tier).

Requires: Cloudflare account, Worker deployment, custom domain setup.

## Requirements

- WordPress 6.2 or higher
- PHP 7.4 or higher

**For Self-Hosted only:**
- Cloudflare account (free tier works)
- Cloudflare Worker deployed ([see worker repo](https://github.com/img-pro/bandwidth-saver-worker))
- Custom domain pointing to your Worker

## Installation

### Managed Setup (Under 1 Minute)

1. Install and activate the plugin
2. Go to **Settings > Bandwidth Saver**
3. Click the **Managed** tab
4. Click **Activate Now** and complete checkout
5. Done. Images now load from Cloudflare.

### Self-Hosted Setup (About 15 Minutes)

For technical users who prefer running Cloudflare on their own account:

1. Create a free [Cloudflare account](https://cloudflare.com) if you do not have one
2. Deploy the worker from [our GitHub repository](https://github.com/img-pro/bandwidth-saver-worker)
3. Add a custom domain to your Worker (e.g., cdn.yoursite.com)
4. Enter your CDN domain in **Settings > Bandwidth Saver > Self-Host**

Detailed guide: [github.com/img-pro/bandwidth-saver-worker](https://github.com/img-pro/bandwidth-saver-worker#setup)

## Compatibility

**Works With:**
- WordPress 6.2+
- PHP 7.4, 8.0, 8.1, 8.2, 8.3
- Multisite installations
- All page builders (Gutenberg, Elementor, Beaver Builder, Divi, Bricks, etc.)
- All image optimization plugins (ShortPixel, Imagify, Smush, EWWW, etc.)
- WooCommerce product images
- Jetpack (REST API compatible)
- All image formats (JPG, PNG, GIF, WebP, AVIF, SVG)
- Lazy loading and srcset

## Privacy

**What the plugin collects:**
The plugin itself does not add cookies, tracking scripts, or analytics to your site.

**What Cloudflare logs:**
When images are delivered through Cloudflare, standard CDN request metadata is logged by Cloudflare (IP addresses, timestamps, request headers, etc.). This is standard for any CDN service.

**For Managed users:** Images are cached on Cloudflare infrastructure managed by ImgPro. Your site URL and admin email are stored for account management. Review [Cloudflare's privacy policy](https://www.cloudflare.com/privacypolicy/).

**For Self-Hosted users:** Images are stored in your own Cloudflare account. You have full control over your data and logs.

## Content Responsibility

You are responsible for the images you upload. Illegal or abusive content may lead to account review. High-volume or high-risk sites should use the Self-Hosted option for full control.

## Security

- All input sanitized
- All output escaped
- Nonce verification on AJAX
- Capability checks (`manage_options`)
- No SQL injection vulnerabilities
- No XSS vulnerabilities
- No CSRF vulnerabilities

## File Structure

```
bandwidth-saver/
├── imgpro-cdn.php                      # Main plugin file
├── readme.txt                          # WordPress.org readme
├── LICENSE                             # GPL v2 license
├── uninstall.php                       # Clean uninstall
├── includes/
│   ├── class-imgpro-cdn-core.php      # Core functionality
│   ├── class-imgpro-cdn-settings.php  # Settings management
│   ├── class-imgpro-cdn-rewriter.php  # URL rewriting
│   ├── class-imgpro-cdn-admin.php     # Admin interface
│   └── class-imgpro-cdn-admin-ajax.php # AJAX handlers
├── admin/
│   ├── css/
│   │   └── imgpro-cdn-admin.css       # Admin styles
│   └── js/
│       └── imgpro-cdn-admin.js        # Admin JavaScript
└── assets/
    ├── css/
    │   └── imgpro-cdn-frontend.css    # Frontend styles
    └── js/
        └── imgpro-cdn.js              # Frontend JavaScript
```

## Support

- **WordPress.org Support Forum:** [wordpress.org/support/plugin/bandwidth-saver](https://wordpress.org/support/plugin/bandwidth-saver/)
- **GitHub Issues:** [github.com/img-pro/bandwidth-saver/issues](https://github.com/img-pro/bandwidth-saver/issues)
- **Worker Setup Guide:** [github.com/img-pro/bandwidth-saver-worker](https://github.com/img-pro/bandwidth-saver-worker)

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

**Coding Standards:**
- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- All strings must be translatable
- All input sanitized, all output escaped
- Document all functions with PHPDoc

## License

This plugin is licensed under GPL v2 or later.

```
Bandwidth Saver: Image CDN by ImgPro
Copyright (C) 2025 ImgPro

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

## Related Projects

- **Cloudflare Worker:** [bandwidth-saver-worker](https://github.com/img-pro/bandwidth-saver-worker)

## Credits

Built for the WordPress community.

**Powered by:**
- Cloudflare R2 (object storage)
- Cloudflare Workers (edge compute)
- Cloudflare CDN (global delivery)

---

**Made by [ImgPro](https://img.pro)**
