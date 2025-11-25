<?php
/**
 * ImgPro URL Rewriter
 *
 * @package ImgPro_CDN
 * @since   0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * URL rewriting engine
 *
 * Transforms image URLs to point to CDN, handles context detection,
 * and processes various WordPress image output hooks.
 *
 * @since 0.1.0
 */
class ImgPro_CDN_Rewriter {

    /**
     * Settings instance
     *
     * @since 0.1.0
     * @var ImgPro_CDN_Settings
     */
    private $settings;

    /**
     * URL cache
     *
     * @since 0.1.0
     * @var array
     */
    private $url_cache = [];

    /**
     * Processing flag
     *
     * @since 0.1.0
     * @var bool
     */
    private $processing = false;

    /**
     * Context check cache (performance optimization)
     *
     * @since 0.1.0
     * @var bool|null
     */
    private $is_unsafe_context_cache = null;

    /**
     * Constructor
     *
     * @since 0.1.0
     * @param ImgPro_CDN_Settings $settings Settings instance.
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
     * @since 0.1.0
     * @return bool True if context is unsafe for rewriting.
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
     *
     * @since 0.1.0
     * @return void
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
    }

    /**
     * Rewrite URL
     *
     * @since 0.1.0
     * @param string   $url           Image URL.
     * @param int|null $attachment_id Attachment ID.
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
     *
     * @since 0.1.0
     * @param array|false $image         Image data array or false.
     * @param int         $attachment_id Attachment ID.
     * @param string      $size          Image size.
     * @param bool        $icon          Whether to use icon.
     * @return array|false
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
     *
     * @since 0.1.0
     * @param array  $sources       Srcset sources array.
     * @param array  $size_array    Size array.
     * @param string $image_src     Image source URL.
     * @param array  $image_meta    Image metadata.
     * @param int    $attachment_id Attachment ID.
     * @return array
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
     * SINGLE SOURCE OF TRUTH for origin extraction.
     *
     * @since 0.1.0
     * @param string $url Input URL (origin, CDN, or worker).
     * @return string Origin URL.
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

        // Reconstruct origin URL preserving query string and fragment
        $origin_url = 'https://' . $path_parts[0] . '/' . $path_parts[1];

        // Preserve query string if present
        if (!empty($parsed['query'])) {
            $origin_url .= '?' . $parsed['query'];
        }

        // Preserve fragment if present
        if (!empty($parsed['fragment'])) {
            $origin_url .= '#' . $parsed['fragment'];
        }

        return $origin_url;
    }

    /**
     * Rewrite image attributes
     *
     * Processes images generated by wp_get_attachment_image()
     *
     * ARCHITECTURE:
     * - ALWAYS processes every image (no early returns except validation)
     * - ALWAYS sets data-worker-domain to identify plugin-managed images
     * - ALWAYS sets src to CDN URL
     * - Runs at priority 999 to override other plugins
     *
     * @since 0.1.0
     * @param array        $attributes Image attributes.
     * @param WP_Post      $attachment Attachment post object.
     * @param string|array $size       Image size.
     * @return array
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

        // Store worker domain for CDN warming
        $attributes['data-worker-domain'] = esc_attr($this->settings->get('worker_url'));

        // Add data attribute for event delegation (CSP-compliant, no inline handlers)
        $attributes['data-imgpro-cdn'] = '1';

        return $attributes;
    }

    /**
     * Rewrite content HTML
     *
     * Processes images in HTML content that weren't processed by rewrite_attributes()
     *
     * ARCHITECTURE:
     * - ONLY processes images WITHOUT data-worker-domain (not yet processed)
     * - NEVER modifies images already processed by rewrite_attributes()
     * - ALWAYS sets data-worker-domain to identify plugin-managed images
     * - Uses WP_HTML_Tag_Processor for safe, spec-compliant HTML parsing (requires WP 6.2+)
     *
     * @since 0.1.0
     * @param string $content HTML content.
     * @return string
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

        // Early bail-out: Skip processing if no image tags present
        // This is a performance optimization for text-only content
        if (false === stripos($content, '<img') && false === stripos($content, '<amp-img') && false === stripos($content, '<amp-anim')) {
            return $content;
        }

        $this->processing = true;

        // Use WordPress HTML Tag Processor (WP 6.2+) for safe HTML parsing
        // This is more robust than regex and handles malformed HTML gracefully
        $content = $this->rewrite_content_with_tag_processor($content);

        $this->processing = false;

        return $content;
    }

    /**
     * Rewrite content using WP_HTML_Tag_Processor (modern approach)
     *
     * @since 0.1.0
     * @param string $content HTML content.
     * @return string Modified content.
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

            // Skip if already processed (has our data-worker-domain attribute)
            if ($processor->get_attribute('data-worker-domain')) {
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

            // Store worker domain to identify plugin-managed images
            $processor->set_attribute('data-worker-domain', $worker_domain);

            // Add data attribute for event delegation (CSP-compliant, no inline handlers)
            $processor->set_attribute('data-imgpro-cdn', '1');
        }

        return $processor->get_updated_html();
    }

    /**
     * Check if URL is a CDN URL
     *
     * @since 0.1.0
     * @param string $url URL to check.
     * @return bool
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
     *
     * @since 0.1.0
     * @param string $url URL to check.
     * @return bool
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
     *
     * @since 0.1.0
     * @param string $url URL to check.
     * @return bool
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
     * @since 0.1.0
     * @param string $url URL to check.
     * @return bool True if URL points to an image file.
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
     *
     * @since 0.1.0
     * @param string $host            Host to check.
     * @param array  $allowed_domains Allowed domains list.
     * @return bool
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
     * Build CDN URL
     *
     * @since 0.1.0
     * @param string $url Original image URL.
     * @return string CDN URL or original URL if conversion fails.
     */
    private function build_cdn_url($url) {
        // Normalize first to ensure consistent cache keys
        $normalized = $this->normalize_url($url);
        $cache_key = 'cdn_' . md5($normalized);

        if (isset($this->url_cache[$cache_key])) {
            return $this->url_cache[$cache_key];
        }

        $parsed = wp_parse_url($normalized);

        // wp_parse_url() can return false on severely malformed URLs
        // Cache the normalized URL to maintain consistency (cache key is based on normalized)
        if ($parsed === false || !is_array($parsed) || empty($parsed['host']) || empty($parsed['path'])) {
            $this->url_cache[$cache_key] = $normalized;
            return $normalized;
        }

        $cdn_domain = $this->settings->get('cdn_url');

        // Guard against empty domain - cache and return normalized URL
        if (empty($cdn_domain)) {
            $this->url_cache[$cache_key] = $normalized;
            return $normalized;
        }

        // Build CDN URL preserving query string and fragment
        $cdn_url = sprintf('https://%s/%s%s', $cdn_domain, $parsed['host'], $parsed['path']);

        // Append query string if present
        if (!empty($parsed['query'])) {
            $cdn_url .= '?' . $parsed['query'];
        }

        // Append fragment if present
        if (!empty($parsed['fragment'])) {
            $cdn_url .= '#' . $parsed['fragment'];
        }

        $this->url_cache[$cache_key] = $cdn_url;
        return $cdn_url;
    }

    /**
     * Normalize URL
     *
     * Converts relative and protocol-relative URLs to absolute URLs.
     *
     * @since 0.1.0
     * @param string $url URL to normalize.
     * @return string Normalized absolute URL.
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

}
