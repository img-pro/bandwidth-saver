# Image CDN – Bandwidth Saver by ImgPro

[![WordPress Plugin Version](https://img.shields.io/badge/version-0.0.8-blue.svg)](https://wordpress.org/plugins/imgpro-cdn/)
[![Requires WordPress Version](https://img.shields.io/badge/wordpress-6.2%2B-blue.svg)](https://wordpress.org/download/)
[![Requires PHP Version](https://img.shields.io/badge/php-7.4%2B-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-red.svg)](LICENSE)

**Deliver images from Cloudflare's global network. Save bandwidth costs with free-tier friendly R2 storage and zero egress fees.**

## Overview

Image CDN is a bandwidth-saving WordPress plugin that delivers your images through Cloudflare's global edge network. Unlike complex image optimization services, Image CDN focuses on one thing: making your existing WordPress images load faster worldwide while cutting bandwidth costs.

**No transformations. No complexity. Just fast, affordable delivery.**

## Features

- ✅ **Free Tier Compatible** - Most sites pay $0/month
- ✅ **Ultra Simple** - Minimal configuration needed
- ✅ **Works with WordPress** - No fighting against WP image handling
- ✅ **Works with ANY Plugin** - Use your favorite optimization plugins
- ✅ **Global Edge Delivery** - Fast worldwide from 300+ locations
- ✅ **Zero Egress Fees** - Cloudflare R2 advantage
- ✅ **Automatic Fallback** - Origin fallback if CDN fails
- ✅ **Translation Ready** - i18n support included

## How It Works

1. **WordPress generates images** (as it normally does)
2. **Image CDN rewrites URLs** to point to Cloudflare
3. **First request:** Worker caches image in R2
4. **Future requests:** Served directly from R2 (zero cost!)

## Requirements

- WordPress 6.2 or higher
- PHP 7.4 or higher
- Cloudflare account (free tier works!)
- R2 bucket enabled
- Cloudflare Worker deployed ([see worker repo](https://github.com/img-pro/wp-image-cdn-worker))

## Installation

### 1. Install Plugin

**From WordPress.org (Recommended):**
```
WordPress Admin → Plugins → Add New → Search "Image CDN"
```

**Manual Installation:**
```bash
# Download latest release
cd wp-content/plugins
git clone https://github.com/img-pro/wp-image-cdn.git imgpro-cdn
```

### 2. Deploy Cloudflare Worker

The Cloudflare Worker must be deployed separately to your Cloudflare account.
See the [worker repository](https://github.com/img-pro/wp-image-cdn-worker) for detailed deployment instructions.

### 3. Configure Plugin

```
WordPress Admin → Settings → Image CDN
```

**Settings:**
- **CDN Domain**: Your R2 public bucket domain (e.g., `cdn.yourdomain.com`)
- **Worker Domain**: Your Cloudflare Worker domain (e.g., `worker.yourdomain.com`)
- **Enable CDN**: Toggle to activate

**Optional Settings:**
- **Allowed Domains**: Restrict CDN to specific domains (leave empty for all)
- **Excluded Paths**: Skip CDN for specific paths (e.g., `/cart`, `/checkout`)
- **Debug Mode**: Add data attributes for troubleshooting (requires WP_DEBUG)

## Configuration

### Basic Setup

```php
// Default settings (automatically applied)
CDN Domain: (your-bucket).r2.dev or custom domain
Worker Domain: worker.your-domain.com
Enabled: false (enable after configuration)
```

### Advanced Options

**Allowed Domains** - Whitelist specific domains:
```
example.com
blog.example.com
shop.example.com
```

**Excluded Paths** - Skip CDN for specific paths:
```
/cart
/checkout
/my-account
```
Note: Admin areas (`/wp-admin`, REST API, AJAX requests) are automatically excluded.

## Compatibility

**Works With:**
- ✅ WordPress 6.2, 6.3, 6.4, 6.5, 6.6, 6.7
- ✅ PHP 7.4, 8.0, 8.1, 8.2, 8.3
- ✅ Multisite installations
- ✅ All page builders (Gutenberg, Elementor, etc.)
- ✅ All image optimization plugins
- ✅ WooCommerce product images
- ✅ Jetpack (REST API compatible)
- ✅ Block Editor

**Image Optimization Plugins:**
Works seamlessly with:
- Smush
- ShortPixel
- Imagify
- EWWW Image Optimizer
- Optimole
- Any WordPress image optimization plugin

## Architecture

**Two-Domain Setup:**
- `cdn.yourdomain.com` → R2 Public Bucket (99% of traffic, zero worker cost)
- `worker.yourdomain.com` → Cloudflare Worker (1% of traffic, cache misses only)

**Request Flow:**
1. Browser requests image from CDN domain
2. If cached: Served directly from R2 (20-40ms)
3. If not cached: Fallback to worker domain
4. Worker fetches from WordPress, stores in R2, redirects to CDN
5. Future requests: Served from R2 (zero worker invocations)

## Performance

- **Cached requests:** 20-40ms (R2 direct)
- **Cache miss:** 200-400ms (fetch + store + redirect)
- **Cache hit rate:** 99%+ after warmup
- **Worker invocations:** ~1% of total requests
- **Global coverage:** 300+ edge locations

## Cost

Most small/medium WordPress sites pay **$0/month** on Cloudflare's free tier.

**Cost breakdown:**
- Small site (100k views/mo): **$0/mo**
- Medium site (500k views/mo): **$0-2/mo**
- Large site (3M views/mo): **$0.68/mo**

**Free tier limits:**
- R2 Storage: 10 GB free
- R2 Operations: 1M reads/mo free
- Worker Requests: 100k/day free
- Zero egress fees (Cloudflare's advantage)

## Security

**Plugin Security:**
- ✅ All input sanitized
- ✅ All output escaped
- ✅ Nonce verification on AJAX
- ✅ Capability checks (`manage_options`)
- ✅ No SQL injection vulnerabilities
- ✅ No XSS vulnerabilities
- ✅ No CSRF vulnerabilities

**Privacy:**
- ✅ No data collection
- ✅ No tracking
- ✅ No cookies
- ✅ No analytics
- ✅ Images cached in YOUR Cloudflare account
- ✅ Plugin author has no access to your data

## Development

### Local Development

```bash
# Clone repository
git clone https://github.com/img-pro/wp-image-cdn.git imgpro-cdn
cd imgpro-cdn

# Install in WordPress
ln -s $(pwd) /path/to/wordpress/wp-content/plugins/imgpro-cdn

# Activate plugin
wp plugin activate imgpro-cdn
```

### File Structure

```
wp-image-cdn/
├── imgpro-cdn.php                      # Main plugin file
├── readme.txt                          # WordPress.org readme
├── LICENSE                             # GPL v2 license
├── uninstall.php                       # Clean uninstall
├── includes/
│   ├── class-imgpro-cdn-core.php      # Core functionality
│   ├── class-imgpro-cdn-settings.php  # Settings management
│   ├── class-imgpro-cdn-rewriter.php  # URL rewriting
│   └── class-imgpro-cdn-admin.php     # Admin interface
├── admin/
│   ├── css/
│   │   └── imgpro-cdn-admin.css       # Admin styles
│   └── js/
│       └── imgpro-cdn-admin.js        # Admin JavaScript
├── assets/
│   └── css/
│       └── imgpro-cdn-frontend.css    # Frontend styles
└── languages/
    └── imgpro-cdn.pot                  # Translation template
```

### Translation

```bash
# Generate .pot file
wp i18n make-pot . languages/imgpro-cdn.pot

# Translations via WordPress.org
# Visit: https://translate.wordpress.org/projects/wp-plugins/imgpro-cdn
```

## Support

**WordPress.org Support Forum:**
https://wordpress.org/support/plugin/imgpro-cdn/

**GitHub Issues:**
https://github.com/img-pro/wp-image-cdn/issues

**Documentation:**
See `readme.txt` for detailed FAQ and troubleshooting

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
Image CDN by ImgPro - WordPress Plugin
Copyright (C) 2025 ImgPro

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## Related Projects

- **Cloudflare Worker:** [wp-image-cdn-worker](https://github.com/img-pro/wp-image-cdn-worker)

## Credits

Built for the WordPress community.

**Powered by:**
- Cloudflare R2 (object storage)
- Cloudflare Workers (edge compute)
- Cloudflare CDN (global delivery)

---

**Made with ❤️ by [ImgPro](https://img.pro)**
