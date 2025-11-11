<?php
/**
 * ImgPro URL Rewriter
 *
 * @package ImgPro_CDN
 * @version 2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ImgPro_CDN_Rewriter {

    /**
     * Settings instance
     *
     * @var ImgPro_CDN_Settings
     */
    private $settings;

    /**
     * URL cache
     *
     * @var array
     */
    private $url_cache = [];

    /**
     * Processing flag
     *
     * @var bool
     */
    private $processing = false;

    /**
     * Context check cache (performance optimization)
     *
     * @var bool|null
     */
    private $is_unsafe_context_cache = null;

    /**
     * Constructor
     *
     * @param ImgPro_CDN_Settings $settings Settings instance
     */
    public function __construct(ImgPro_CDN_Settings $settings) {
        $this->settings = $settings;
    }

    /**
     * Check if current context is unsafe for URL rewriting
     *
     * ARCHITECTURE: This method is called when hooks execute (lazy evaluation),
     * not during init(). By this time, WordPress has parsed the request and
     * all constants are properly defined.
     *
     * PERFORMANCE: Result is cached per request to avoid repeated constant checks.
     *
     * Returns true if we're in a context where rewriting URLs would break:
     * - Plugin communication (REST API, AJAX)
     * - External services (Jetpack, backups, webhooks)
     * - WordPress admin area
     * - CLI/Cron operations
     * - Any non-frontend rendering context
     *
     * @return bool True if context is unsafe for rewriting
     */
    private function is_unsafe_context() {
        // Return cached result if available (performance optimization)
        if ($this->is_unsafe_context_cache !== null) {
            return $this->is_unsafe_context_cache;
        }

        $is_unsafe = false;

        // Admin area - plugins need original URLs for Media Library, etc.
        if (is_admin() && !apply_filters('imgpro_admin_allow_rewrite', false)) {
            $is_unsafe = true;
        }
        // REST API requests - plugins/services need original URLs
        // This includes Jetpack, backup plugins, mobile apps, etc.
        elseif (defined('REST_REQUEST') && REST_REQUEST) {
            $is_unsafe = true;
        }
        // AJAX requests - could be from any plugin needing original URLs
        elseif (defined('DOING_AJAX') && DOING_AJAX) {
            $is_unsafe = true;
        }
        // Cron jobs - background tasks need original URLs
        elseif (defined('DOING_CRON') && DOING_CRON) {
            $is_unsafe = true;
        }
        // WP-CLI - command line operations need original URLs
        elseif (defined('WP_CLI') && WP_CLI) {
            $is_unsafe = true;
        }
        // XML-RPC - remote publishing tools need original URLs
        elseif (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            $is_unsafe = true;
        }
        // Autosave - editor needs original URLs
        elseif (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            $is_unsafe = true;
        }
        // WordPress core is installing/upgrading
        elseif (defined('WP_INSTALLING') && WP_INSTALLING) {
            $is_unsafe = true;
        }
        // Allow plugins to mark their own unsafe contexts
        elseif (apply_filters('imgpro_is_unsafe_context', false)) {
            $is_unsafe = true;
        }

        // Cache the result for this request
        $this->is_unsafe_context_cache = $is_unsafe;

        return $is_unsafe;
    }

    /**
     * Initialize hooks
     *
     * ARCHITECTURE: Always register hooks, but check context when they execute.
     * This is necessary because WordPress hasn't parsed the request yet at
     * plugins_loaded time, so we can't reliably determine request type here.
     */
    public function init() {
        if (!$this->settings->get('enabled')) {
            return;
        }

        // ALWAYS register hooks - we'll check context when they execute
        // This is the only reliable way to handle the WordPress request lifecycle

        // Core image hooks
        add_filter('wp_get_attachment_url', [$this, 'rewrite_url'], 10, 2);
        add_filter('wp_get_attachment_image_src', [$this, 'rewrite_image_src'], 10, 4);
        add_filter('wp_calculate_image_srcset', [$this, 'rewrite_srcset'], 10, 5);
        // Run late (priority 999) to override any lazy loading plugins that modify src
        add_filter('wp_get_attachment_image_attributes', [$this, 'rewrite_attributes'], 999, 3);

        // Content filters
        add_filter('the_content', [$this, 'rewrite_content'], 999);
        add_filter('post_thumbnail_html', [$this, 'rewrite_content'], 999);
        add_filter('widget_text', [$this, 'rewrite_content'], 999);

        // Lazy loading handler (fixes browser caching 404s on lazy images)
        add_action('wp_footer', [$this, 'output_lazy_handler'], 999);
    }

    /**
     * Rewrite URL
     *
     * @param string $url           Image URL
     * @param int    $attachment_id Attachment ID
     * @return string
     */
    public function rewrite_url($url, $attachment_id = null) {
        // Check context NOW (lazy evaluation)
        // By the time this hook executes, WordPress has parsed the request
        // and all constants (REST_REQUEST, DOING_AJAX, etc.) are defined
        if ($this->is_unsafe_context()) {
            return $url;
        }

        // $processing prevents infinite recursion in rewrite_content()
        if ($this->processing || !$this->should_rewrite($url)) {
            return $url;
        }

        return $this->build_cdn_url($url);
    }

    /**
     * Rewrite image src array
     */
    public function rewrite_image_src($image, $attachment_id, $size, $icon) {
        // Check context (lazy evaluation)
        if ($this->is_unsafe_context()) {
            return $image;
        }

        if (!is_array($image) || $this->processing) {
            return $image;
        }

        if (!empty($image[0]) && $this->should_rewrite($image[0])) {
            $image[0] = $this->build_cdn_url($image[0]);
        }

        return $image;
    }

    /**
     * Rewrite srcset
     */
    public function rewrite_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        // Check context (lazy evaluation)
        if ($this->is_unsafe_context()) {
            return $sources;
        }

        if (!is_array($sources) || $this->processing) {
            return $sources;
        }

        foreach ($sources as &$source) {
            if (!empty($source['url']) && $this->should_rewrite($source['url'])) {
                $source['url'] = $this->build_cdn_url($source['url']);
            }
        }

        return $sources;
    }

    /**
     * Get true origin URL from any URL type (origin/CDN/worker)
     *
     * SINGLE SOURCE OF TRUTH for origin extraction
     *
     * @param string $url Input URL (origin, CDN, or worker)
     * @return string Origin URL
     */
    private function get_true_origin($url) {
        if (empty($url)) {
            return $url;
        }

        // If already origin URL, return as-is
        if (!$this->is_cdn_url($url) && !$this->is_worker_url($url)) {
            return $url;
        }

        // Extract origin from CDN/Worker URL
        // Format: https://cdn-or-worker-domain/origin-domain/path
        // Result: https://origin-domain/path
        $parsed = wp_parse_url($url);

        // Handle wp_parse_url() failure
        if ($parsed === false || !is_array($parsed) || empty($parsed['path'])) {
            return $url;
        }

        $path = trim($parsed['path'], '/');
        $path_parts = explode('/', $path, 2);

        if (count($path_parts) !== 2) {
            // Malformed CDN URL - return as-is
            return $url;
        }

        return 'https://' . $path_parts[0] . '/' . $path_parts[1];
    }

    /**
     * Rewrite image attributes
     *
     * Processes images generated by wp_get_attachment_image()
     *
     * ARCHITECTURE:
     * - ALWAYS processes every image (no early returns except validation)
     * - ALWAYS sets data-original-src to TRUE origin (never CDN/Worker)
     * - ALWAYS sets src to CDN URL
     * - Runs at priority 999 to override other plugins
     */
    public function rewrite_attributes($attributes, $attachment, $size) {
        // Check context (lazy evaluation)
        if ($this->is_unsafe_context()) {
            return $attributes;
        }

        if (empty($attributes['src'])) {
            return $attributes;
        }

        // Get true origin URL (extracts if already CDN/Worker)
        $origin_url = $this->get_true_origin($attributes['src']);

        // Skip if not a valid image URL
        if (!$this->should_rewrite($origin_url)) {
            return $attributes;
        }

        // Build CDN URL from origin
        $cdn_url = $this->build_cdn_url($origin_url);

        // Set src to CDN
        $attributes['src'] = $cdn_url;

        // CRITICAL: Store TRUE origin URL (never CDN/Worker)
        $attributes['data-original-src'] = esc_url($origin_url);
        $attributes['data-worker-domain'] = esc_attr($this->settings->get('worker_url'));

        // Add onload handler to add 'imgpro-loaded' class for CSS transitions
        $attributes['onload'] = 'this.classList.add("imgpro-loaded")';

        // Build onerror handler
        $debug_enabled = $this->settings->get('debug_mode') && defined('WP_DEBUG') && WP_DEBUG;

        if ($debug_enabled) {
            // Debug version with console logging
            $attributes['onerror'] = 'if(!this.dataset.fallback){var t0=Date.now();this.dataset.fallbackStart=t0;var failedCdnUrl=this.currentSrc||this.src;console.log("ImgPro: CDN failed for",failedCdnUrl,"→ Loading from",this.dataset.originalSrc);this.dataset.fallback="1";this.classList.remove("imgpro-loaded");this.removeAttribute("srcset");this.removeAttribute("sizes");this.src=this.dataset.originalSrc;this.onload=function(){console.log("ImgPro: Origin loaded in "+(Date.now()-t0)+"ms");this.classList.add("imgpro-loaded");this.onload=null};if(this.dataset.workerDomain){var failedFilename=failedCdnUrl.split("/").pop();var originBase=this.dataset.originalSrc;var originDir=originBase.substring(0,originBase.lastIndexOf("/")+1);var originVariantUrl=originDir+failedFilename;var warmUrl="https://"+this.dataset.workerDomain+"/"+originVariantUrl.replace(/^https?:\\/\\//,"");(new Image()).src=warmUrl;console.log("ImgPro: Warming origin variant",originVariantUrl,"via",warmUrl)}}else if(this.dataset.fallback==="1"){var elapsed=this.dataset.fallbackStart?(Date.now()-this.dataset.fallbackStart)+"ms":"unknown";console.error("ImgPro: Origin ALSO failed after",elapsed,"for",this.dataset.originalSrc);this.dataset.fallback="2";this.classList.remove("imgpro-loaded");this.onerror=null}else{console.warn("ImgPro: Unexpected fallback state:",this.dataset.fallback);this.onerror=null}';
        } else {
            // Production version (no logging)
            $attributes['onerror'] = 'if(!this.dataset.fallback){var failedCdnUrl=this.currentSrc||this.src;this.dataset.fallback="1";this.classList.remove("imgpro-loaded");this.removeAttribute("srcset");this.removeAttribute("sizes");this.src=this.dataset.originalSrc;this.onload=function(){this.classList.add("imgpro-loaded")};if(this.dataset.workerDomain){var failedFilename=failedCdnUrl.split("/").pop();var originBase=this.dataset.originalSrc;var originDir=originBase.substring(0,originBase.lastIndexOf("/")+1);var originVariantUrl=originDir+failedFilename;var warmUrl="https://"+this.dataset.workerDomain+"/"+originVariantUrl.replace(/^https?:\\/\\//,"");(new Image()).src=warmUrl}}else{this.dataset.fallback="2";this.classList.remove("imgpro-loaded");this.onerror=null}';
        }

        return $attributes;
    }

    /**
     * Rewrite content HTML
     *
     * Processes images in HTML content that weren't processed by rewrite_attributes()
     *
     * ARCHITECTURE:
     * - ONLY processes images WITHOUT data-original-src (not yet processed)
     * - NEVER modifies images already processed by rewrite_attributes()
     * - ALWAYS sets data-original-src to TRUE origin (never CDN/Worker)
     * - Uses WP_HTML_Tag_Processor for safe, spec-compliant HTML parsing (requires WP 6.2+)
     */
    public function rewrite_content($content) {
        // Check context (lazy evaluation)
        if ($this->is_unsafe_context()) {
            return $content;
        }

        // $processing flag prevents infinite recursion when content filters call each other
        if ($this->processing || empty($content)) {
            return $content;
        }

        $this->processing = true;

        // Use WordPress HTML Tag Processor (WP 6.2+) for safe HTML parsing
        // This is more robust than regex and handles malformed HTML gracefully
        if (class_exists('WP_HTML_Tag_Processor')) {
            $content = $this->rewrite_content_with_tag_processor($content);
        } else {
            // Fallback to regex for WordPress < 6.2 (though plugin requires 6.2+)
            $content = $this->rewrite_content_with_regex($content);
        }

        $this->processing = false;

        return $content;
    }

    /**
     * Rewrite content using WP_HTML_Tag_Processor (modern approach)
     *
     * @param string $content HTML content
     * @return string Modified content
     */
    private function rewrite_content_with_tag_processor($content) {
        $processor = new WP_HTML_Tag_Processor($content);

        // Process all image tags (img, amp-img, amp-anim)
        $tag_names = ['IMG', 'AMP-IMG', 'AMP-ANIM'];

        while ($processor->next_tag()) {
            $tag = $processor->get_tag();

            // Skip if not an image tag
            if (!in_array($tag, $tag_names, true)) {
                continue;
            }

            // Skip if already processed (has our data-original-src attribute)
            if ($processor->get_attribute('data-original-src')) {
                continue;
            }

            // Get src attribute
            $src = $processor->get_attribute('src');
            if (empty($src)) {
                continue;
            }

            // Get true origin URL (extracts if already CDN/Worker)
            $origin_url = $this->get_true_origin($src);

            // Skip if not a valid image URL
            if (!$this->should_rewrite($origin_url)) {
                continue;
            }

            // Build CDN URL from origin
            $cdn_url = $this->build_cdn_url($origin_url);

            // Update src attribute to CDN URL
            $processor->set_attribute('src', esc_url($cdn_url));

            $worker_domain = esc_attr($this->settings->get('worker_url'));
            $debug_enabled = $this->settings->get('debug_mode') && defined('WP_DEBUG') && WP_DEBUG;

            // CRITICAL: Store TRUE origin URL (never CDN/Worker)
            $processor->set_attribute('data-original-src', esc_url($origin_url));
            $processor->set_attribute('data-worker-domain', $worker_domain);

            // Add onload handler
            $processor->set_attribute('onload', 'this.classList.add("imgpro-loaded")');

            // Build onerror handler
            if ($debug_enabled) {
                // Debug version with console logging
                $onerror = 'if(!this.dataset.fallback){var t0=Date.now();this.dataset.fallbackStart=t0;var failedCdnUrl=this.currentSrc||this.src;console.log("ImgPro: CDN failed for",failedCdnUrl,"→ Loading from",this.dataset.originalSrc);this.dataset.fallback="1";this.classList.remove("imgpro-loaded");this.removeAttribute("srcset");this.removeAttribute("sizes");this.src=this.dataset.originalSrc;this.onload=function(){console.log("ImgPro: Origin loaded in "+(Date.now()-t0)+"ms");this.classList.add("imgpro-loaded");this.onload=null};if(this.dataset.workerDomain){var failedFilename=failedCdnUrl.split("/").pop();var originBase=this.dataset.originalSrc;var originDir=originBase.substring(0,originBase.lastIndexOf("/")+1);var originVariantUrl=originDir+failedFilename;var warmUrl="https://"+this.dataset.workerDomain+"/"+originVariantUrl.replace(/^https?:\\/\\//,"");(new Image()).src=warmUrl;console.log("ImgPro: Warming origin variant",originVariantUrl,"via",warmUrl)}}else if(this.dataset.fallback==="1"){var elapsed=this.dataset.fallbackStart?(Date.now()-this.dataset.fallbackStart)+"ms":"unknown";console.error("ImgPro: Origin ALSO failed after",elapsed,"for",this.dataset.originalSrc);this.dataset.fallback="2";this.classList.remove("imgpro-loaded");this.onerror=null}else{console.warn("ImgPro: Unexpected fallback state:",this.dataset.fallback);this.onerror=null}';
            } else {
                // Production version (no logging)
                $onerror = 'if(!this.dataset.fallback){var failedCdnUrl=this.currentSrc||this.src;this.dataset.fallback="1";this.classList.remove("imgpro-loaded");this.removeAttribute("srcset");this.removeAttribute("sizes");this.src=this.dataset.originalSrc;this.onload=function(){this.classList.add("imgpro-loaded")};if(this.dataset.workerDomain){var failedFilename=failedCdnUrl.split("/").pop();var originBase=this.dataset.originalSrc;var originDir=originBase.substring(0,originBase.lastIndexOf("/")+1);var originVariantUrl=originDir+failedFilename;var warmUrl="https://"+this.dataset.workerDomain+"/"+originVariantUrl.replace(/^https?:\\/\\//,"");(new Image()).src=warmUrl}}else{this.dataset.fallback="2";this.classList.remove("imgpro-loaded");this.onerror=null}';
            }

            $processor->set_attribute('onerror', $onerror);
        }

        return $processor->get_updated_html();
    }

    /**
     * Rewrite content using regex (legacy fallback for WordPress < 6.2)
     *
     * @param string $content HTML content
     * @return string Modified content
     */
    private function rewrite_content_with_regex($content) {
        // IMPROVED REGEX PATTERN:
        // - Supports AMP images (amp-img, amp-anim) in addition to standard img tags
        // - Uses 's' modifier for multi-line attribute handling
        // - More robust whitespace and src attribute matching
        // - Captures: $1=tag name, $2=before src, $3=src value, $4=after src
        $pattern = '#<(img|amp-img|amp-anim)\s+([^>]*?\s+)?src=["\']([^"\']+)["\']([^>]*)>#is';

        return preg_replace_callback($pattern, function($matches) {
            $tag_name = $matches[1];      // img, amp-img, or amp-anim
            $before = $matches[2] ?? '';   // attributes before src (may be empty)
            $src = $matches[3];            // src value
            $after = $matches[4];          // attributes after src

            // Skip if already processed (has our data-original-src attribute)
            if (stripos($matches[0], 'data-original-src') !== false) {
                return $matches[0];
            }

            // Get true origin URL (extracts if already CDN/Worker)
            $origin_url = $this->get_true_origin($src);

            // Skip if not a valid image URL
            if (!$this->should_rewrite($origin_url)) {
                return $matches[0];
            }

            // Build CDN URL from origin
            $cdn_url = $this->build_cdn_url($origin_url);

            // Build replacement HTML
            // CRITICAL: Store TRUE origin URL (never CDN/Worker)
            $worker_domain = esc_attr($this->settings->get('worker_url'));
            $data_attr = sprintf(' data-original-src="%s" data-worker-domain="%s"',
                esc_url($origin_url),
                $worker_domain
            );

            // Add onload handler to add 'imgpro-loaded' class for CSS transitions
            $onload = ' onload="this.classList.add(&quot;imgpro-loaded&quot;)"';

            // Build onerror handler
            $debug_enabled = $this->settings->get('debug_mode') && defined('WP_DEBUG') && WP_DEBUG;

            if ($debug_enabled) {
                // Debug version with console logging
                $onerror = ' onerror="if(!this.dataset.fallback){var t0=Date.now();this.dataset.fallbackStart=t0;var failedCdnUrl=this.currentSrc||this.src;console.log(&quot;ImgPro: CDN failed for&quot;,failedCdnUrl,&quot;→ Loading from&quot;,this.dataset.originalSrc);this.dataset.fallback=&quot;1&quot;;this.classList.remove(&quot;imgpro-loaded&quot;);this.removeAttribute(&quot;srcset&quot;);this.removeAttribute(&quot;sizes&quot;);this.src=this.dataset.originalSrc;this.onload=function(){console.log(&quot;ImgPro: Origin loaded in &quot;+(Date.now()-t0)+&quot;ms&quot;);this.classList.add(&quot;imgpro-loaded&quot;);this.onload=null};if(this.dataset.workerDomain){var failedFilename=failedCdnUrl.split(&quot;/&quot;).pop();var originBase=this.dataset.originalSrc;var originDir=originBase.substring(0,originBase.lastIndexOf(&quot;/&quot;)+1);var originVariantUrl=originDir+failedFilename;var warmUrl=&quot;https://&quot;+this.dataset.workerDomain+&quot;/&quot;+originVariantUrl.replace(/^https?:\\/\\//,&quot;&quot;);(new Image()).src=warmUrl;console.log(&quot;ImgPro: Warming origin variant&quot;,originVariantUrl,&quot;via&quot;,warmUrl)}}else if(this.dataset.fallback===&quot;1&quot;){var elapsed=this.dataset.fallbackStart?(Date.now()-this.dataset.fallbackStart)+&quot;ms&quot;:&quot;unknown&quot;;console.error(&quot;ImgPro: Origin ALSO failed after&quot;,elapsed,&quot;for&quot;,this.dataset.originalSrc);this.dataset.fallback=&quot;2&quot;;this.classList.remove(&quot;imgpro-loaded&quot;);this.onerror=null}else{console.warn(&quot;ImgPro: Unexpected fallback state:&quot;,this.dataset.fallback);this.onerror=null}"';
            } else {
                // Production version (no logging)
                $onerror = ' onerror="if(!this.dataset.fallback){var failedCdnUrl=this.currentSrc||this.src;this.dataset.fallback=&quot;1&quot;;this.classList.remove(&quot;imgpro-loaded&quot;);this.removeAttribute(&quot;srcset&quot;);this.removeAttribute(&quot;sizes&quot;);this.src=this.dataset.originalSrc;this.onload=function(){this.classList.add(&quot;imgpro-loaded&quot;)};if(this.dataset.workerDomain){var failedFilename=failedCdnUrl.split(&quot;/&quot;).pop();var originBase=this.dataset.originalSrc;var originDir=originBase.substring(0,originBase.lastIndexOf(&quot;/&quot;)+1);var originVariantUrl=originDir+failedFilename;var warmUrl=&quot;https://&quot;+this.dataset.workerDomain+&quot;/&quot;+originVariantUrl.replace(/^https?:\\/\\//,&quot;&quot;);(new Image()).src=warmUrl}}else{this.dataset.fallback=&quot;2&quot;;this.classList.remove(&quot;imgpro-loaded&quot;);this.onerror=null}"';
            }

            return sprintf('<%s%s%ssrc="%s"%s%s%s%s>', $tag_name, $before ? ' ' . $before : '', $before ? '' : ' ', esc_url($cdn_url), $data_attr, $onload, $onerror, $after);
        }, $content);
    }

    /**
     * Check if URL is a CDN URL
     */
    private function is_cdn_url($url) {
        if (empty($url) || !is_string($url)) {
            return false;
        }
        $cdn_url = $this->settings->get('cdn_url');
        return !empty($cdn_url) && strpos($url, $cdn_url) !== false;
    }

    /**
     * Check if URL is a worker URL
     */
    private function is_worker_url($url) {
        if (empty($url) || !is_string($url)) {
            return false;
        }
        $worker_url = $this->settings->get('worker_url');
        return !empty($worker_url) && strpos($url, $worker_url) !== false;
    }

    /**
     * Check if URL should be rewritten
     */
    private function should_rewrite($url) {
        if (empty($url) || !is_string($url)) {
            return false;
        }

        // Already CDN URL?
        if ($this->is_cdn_url($url)) {
            return false;
        }

        // Already worker URL?
        if ($this->is_worker_url($url)) {
            return false;
        }

        // Excluded paths (with wildcard support)
        $excluded = $this->settings->get('excluded_paths', []);
        foreach ($excluded as $pattern) {
            if (!empty($pattern) && $this->matches_pattern($url, $pattern)) {
                return false;
            }
        }

        // Allowed domains (with subdomain support)
        $allowed = $this->settings->get('allowed_domains', []);
        if (!empty($allowed)) {
            $url_host = wp_parse_url($url, PHP_URL_HOST);
            if (!$url_host || !$this->is_domain_allowed($url_host, $allowed)) {
                return false;
            }
        }

        // Must be an image
        if (!$this->is_image_url($url)) {
            return false;
        }

        return true;
    }

    /**
     * Check if URL is an image
     *
     * @param string $url URL to check
     * @return bool True if URL points to an image file
     */
    private function is_image_url($url) {
        /**
         * Filter the list of allowed image extensions
         *
         * @param array $extensions List of file extensions (without dots)
         */
        $extensions = apply_filters('imgpro_image_extensions', [
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'avif',
            'svg',
        ]);

        $path = wp_parse_url($url, PHP_URL_PATH);

        if (!$path) {
            return false;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, $extensions, true);
    }

    /**
     * Check if domain matches allowed domains (with subdomain support)
     */
    private function is_domain_allowed($host, $allowed_domains) {
        if (empty($host) || empty($allowed_domains)) {
            return false;
        }

        $host = strtolower($host);

        foreach ($allowed_domains as $domain) {
            $domain = strtolower(trim($domain));

            if (empty($domain)) {
                continue;
            }

            // Exact match
            if ($host === $domain) {
                return true;
            }

            // Subdomain match: www.example.com matches example.com
            if (substr($host, -strlen('.' . $domain)) === '.' . $domain) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if URL matches exclusion pattern
     */
    private function matches_pattern($url, $pattern) {
        // Trim whitespace
        $pattern = trim($pattern);

        if (empty($pattern)) {
            return false;
        }

        // Check if pattern contains wildcard
        if (strpos($pattern, '*') !== false) {
            // Convert wildcard pattern to regex
            $regex_pattern = preg_quote($pattern, '/');
            $regex_pattern = str_replace('\*', '.*', $regex_pattern);
            return preg_match('/^' . $regex_pattern . '$/i', $url) === 1 ||
                   preg_match('/' . $regex_pattern . '/i', $url) === 1;
        }

        // Simple substring match for backwards compatibility
        return strpos($url, $pattern) !== false;
    }

    /**
     * Build CDN URL
     *
     * @param string $url Original image URL
     * @return string CDN URL or original URL if conversion fails
     */
    private function build_cdn_url($url) {
        $cache_key = 'cdn_' . md5($url);
        if (isset($this->url_cache[$cache_key])) {
            return $this->url_cache[$cache_key];
        }

        $normalized = $this->normalize_url($url);
        $parsed = wp_parse_url($normalized);

        // wp_parse_url() can return false on severely malformed URLs
        if ($parsed === false || !is_array($parsed) || empty($parsed['host']) || empty($parsed['path'])) {
            return $url;
        }

        $cdn_domain = $this->settings->get('cdn_url');

        // Guard against empty domain - return original URL
        if (empty($cdn_domain)) {
            return $url;
        }

        $cdn_url = sprintf('https://%s/%s%s', $cdn_domain, $parsed['host'], $parsed['path']);

        $this->url_cache[$cache_key] = $cdn_url;
        return $cdn_url;
    }

    /**
     * Build worker URL
     *
     * @param string $url Original image URL
     * @return string Worker URL or original URL if conversion fails
     */
    private function build_worker_url($url) {
        $cache_key = 'worker_' . md5($url);
        if (isset($this->url_cache[$cache_key])) {
            return $this->url_cache[$cache_key];
        }

        $normalized = $this->normalize_url($url);
        $parsed = wp_parse_url($normalized);

        // wp_parse_url() can return false on severely malformed URLs
        if ($parsed === false || !is_array($parsed) || empty($parsed['host']) || empty($parsed['path'])) {
            return $url;
        }

        $worker_domain = $this->settings->get('worker_url');

        // Guard against empty domain - return original URL
        if (empty($worker_domain)) {
            return $url;
        }

        $worker_url = sprintf('https://%s/%s%s', $worker_domain, $parsed['host'], $parsed['path']);

        $this->url_cache[$cache_key] = $worker_url;
        return $worker_url;
    }

    /**
     * Normalize URL
     *
     * Converts relative and protocol-relative URLs to absolute URLs
     *
     * @param string $url URL to normalize
     * @return string Normalized absolute URL
     */
    private function normalize_url($url) {
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        if (substr($url, 0, 2) === '//') {
            return 'https:' . $url;
        }

        if (substr($url, 0, 1) === '/') {
            $home = wp_parse_url(home_url());
            // Handle wp_parse_url() failure gracefully
            if ($home === false || !is_array($home)) {
                return $url;
            }
            $scheme = $home['scheme'] ?? 'https';
            $host = $home['host'] ?? 'localhost';
            return $scheme . '://' . $host . $url;
        }

        return rtrim(home_url(), '/') . '/' . ltrim($url, '/');
    }

    /**
     * Output lazy loading handler
     *
     * Fixes browser caching issue with lazy-loaded images:
     * - Browser caches CDN 404 response when page loads
     * - When lazy image scrolls into view, browser uses cached 404
     * - onerror doesn't fire because no network request is made
     * - This handler checks all lazy images and triggers fallback for failed ones
     */
    public function output_lazy_handler() {
        $debug_enabled = $this->settings->get('debug_mode') && defined('WP_DEBUG') && WP_DEBUG;

        ?>
        <script>
        (function() {
            'use strict';

            var intervalId = null;
            var checkCount = 0;
            var maxChecks = 10; // Stop after 20 seconds (10 * 2s)

            /**
             * Check and fix failed lazy-loaded images
             * Returns true if all images are resolved
             */
            function checkLazyImages() {
                var lazyImages = document.querySelectorAll('img[loading="lazy"][data-original-src]');
                var needsChecking = false;

                <?php if ($debug_enabled): ?>
                console.log('ImgPro: Checking ' + lazyImages.length + ' lazy-loaded images (check #' + (checkCount + 1) + ')');
                <?php endif; ?>

                lazyImages.forEach(function(img) {
                    // Already fell back - skip
                    if (img.dataset.fallback) {
                        return;
                    }

                    // Check if image failed to load
                    if (img.complete && img.naturalWidth === 0) {
                        // CRITICAL: Capture failed URL BEFORE changing img.src
                        var failedCdnUrl = img.currentSrc || img.src;

                        // Image failed - trigger fallback
                        <?php if ($debug_enabled): ?>
                        var t0 = Date.now();
                        img.dataset.fallbackStart = t0;
                        console.log('ImgPro: Lazy image failed (cached 404) for', failedCdnUrl, '→ Loading from', img.dataset.originalSrc);
                        <?php endif; ?>

                        img.dataset.fallback = '1';
                        img.removeAttribute('srcset');
                        img.removeAttribute('sizes');
                        img.src = img.dataset.originalSrc;

                        <?php if ($debug_enabled): ?>
                        img.onload = function() {
                            console.log('ImgPro: Lazy origin loaded in ' + (Date.now() - t0) + 'ms');
                            img.onload = null;
                        };
                        <?php endif; ?>

                        // Warm CDN in background (origin-centric approach)
                        if (img.dataset.workerDomain) {
                            var failedFilename = failedCdnUrl.split('/').pop();
                            var originBase = img.dataset.originalSrc;
                            var originDir = originBase.substring(0, originBase.lastIndexOf('/') + 1);
                            var originVariantUrl = originDir + failedFilename;
                            var warmUrl = 'https://' + img.dataset.workerDomain + '/' + originVariantUrl.replace(/^https?:\/\//, '');
                            (new Image()).src = warmUrl;

                            <?php if ($debug_enabled): ?>
                            console.log('ImgPro: Warming lazy origin variant', originVariantUrl, 'via', warmUrl);
                            <?php endif; ?>
                        }
                    } else if (!img.complete || img.naturalWidth === 0) {
                        // Still loading or waiting - keep checking
                        needsChecking = true;
                    }
                });

                // Stop interval if all images resolved or max checks reached
                checkCount++;
                if (!needsChecking || checkCount >= maxChecks) {
                    if (intervalId) {
                        clearInterval(intervalId);
                        intervalId = null;
                        <?php if ($debug_enabled): ?>
                        console.log('ImgPro: Stopped checking lazy images' + (checkCount >= maxChecks ? ' (max checks reached)' : ' (all resolved)'));
                        <?php endif; ?>
                    }
                }
            }

            /**
             * Start checking interval if not already running
             */
            function startChecking() {
                if (!intervalId) {
                    checkCount = 0; // Reset counter
                    checkLazyImages();
                    intervalId = setInterval(checkLazyImages, 2000);
                    <?php if ($debug_enabled): ?>
                    console.log('ImgPro: Started checking lazy images');
                    <?php endif; ?>
                }
            }

            // Check on page load
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(startChecking, 100);
                });
            } else {
                setTimeout(startChecking, 100);
            }

            // Watch for new images added via infinite scroll/AJAX
            if ('MutationObserver' in window) {
                var observer = new MutationObserver(function(mutations) {
                    var hasNewLazyImages = false;
                    mutations.forEach(function(mutation) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) {
                                // Check if node itself is a lazy image
                                if (node.tagName === 'IMG' && node.loading === 'lazy' && node.dataset.originalSrc) {
                                    hasNewLazyImages = true;
                                }
                                // Check children
                                if (node.querySelectorAll) {
                                    var lazyImgs = node.querySelectorAll('img[loading="lazy"][data-original-src]');
                                    if (lazyImgs.length > 0) {
                                        hasNewLazyImages = true;
                                    }
                                }
                            }
                        });
                    });

                    // Restart checking if new lazy images detected
                    if (hasNewLazyImages) {
                        <?php if ($debug_enabled): ?>
                        console.log('ImgPro: New lazy images detected, restarting checks');
                        <?php endif; ?>
                        startChecking();
                    }
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        })();
        </script>
        <?php
    }

}
