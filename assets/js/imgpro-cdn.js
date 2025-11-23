/**
 * ImgPro CDN - Frontend JavaScript
 * @version 0.1.1
 *
 * Handles:
 * 1. Image error fallback (CDN → Origin)
 * 2. Lazy loading cache miss detection
 * 3. Worker warming for future requests
 */

var ImgProCDN = (function() {
    'use strict';

    var debugMode = (typeof imgproCdnConfig !== 'undefined' && imgproCdnConfig.debug) || false;

    /**
     * Extract origin URL from CDN URL
     *
     * @param {string} cdnUrl - CDN URL (e.g., https://cdn.domain.com/origin.com/path/image.jpg?v=123#section)
     * @return {string} Origin URL (e.g., https://origin.com/path/image.jpg?v=123#section)
     */
    function extractOriginFromCdnUrl(cdnUrl) {
        try {
            // Parse the CDN URL
            var url = new URL(cdnUrl);

            // Path format: /origin.domain.com/path/to/image.jpg
            // Remove leading slash and split
            var pathParts = url.pathname.substring(1).split('/');

            // First part is the origin domain, rest is the path
            if (pathParts.length < 2) {
                // Malformed URL - return as-is
                return cdnUrl;
            }

            var originDomain = pathParts[0];
            var originPath = pathParts.slice(1).join('/');

            // Reconstruct origin URL preserving protocol, query string, and fragment
            var originUrl = url.protocol + '//' + originDomain + '/' + originPath;

            // Preserve query string (e.g., ?v=123 for cache busting)
            if (url.search) {
                originUrl += url.search;
            }

            // Preserve fragment (e.g., #section for image maps or SVG references)
            if (url.hash) {
                originUrl += url.hash;
            }

            return originUrl;
        } catch (e) {
            // URL parsing failed - return as-is
            return cdnUrl;
        }
    }

    /**
     * Handle image load error
     * Called from inline onerror handlers
     *
     * @param {HTMLImageElement} img - The image element that failed
     */
    function handleError(img) {
        // Already fell back to origin - check if origin also failed
        if (img.dataset.fallback === '1') {
            var elapsed = img.dataset.fallbackStart
                ? (Date.now() - img.dataset.fallbackStart) + 'ms'
                : 'unknown';

            if (debugMode) {
                console.error('ImgPro: Origin ALSO failed after', elapsed);
            }

            img.dataset.fallback = '2';
            img.classList.remove('imgpro-loaded');
            img.onerror = null;
            return;
        }

        // Already in unexpected fallback state
        if (img.dataset.fallback) {
            if (debugMode) {
                console.warn('ImgPro: Unexpected fallback state:', img.dataset.fallback);
            }
            img.onerror = null;
            return;
        }

        // First failure - fallback from CDN to origin
        var t0 = debugMode ? Date.now() : 0;
        if (debugMode) {
            img.dataset.fallbackStart = t0;
        }

        // Capture the failed CDN URL BEFORE changing src
        var failedCdnUrl = img.currentSrc || img.src;

        // Extract origin URL directly from failed CDN URL
        // CDN format: https://cdn.domain.com/origin.domain.com/path/to/image.jpg
        // Extract: https://origin.domain.com/path/to/image.jpg
        var originUrl = extractOriginFromCdnUrl(failedCdnUrl);

        if (debugMode) {
            console.log('ImgPro: CDN failed for', failedCdnUrl, '→ Loading from', originUrl);
        }

        // Update image to load from origin
        img.dataset.fallback = '1';
        img.classList.remove('imgpro-loaded');
        img.removeAttribute('srcset');
        img.removeAttribute('sizes');
        img.src = originUrl;

        // Add success handler for origin load
        img.onload = function() {
            if (debugMode) {
                console.log('ImgPro: Origin loaded in ' + (Date.now() - t0) + 'ms');
            }
            img.classList.add('imgpro-loaded');
            img.onload = null;
        };

        // Warm CDN in background so next request is cached
        if (img.dataset.workerDomain) {
            var warmUrl = 'https://' + img.dataset.workerDomain + '/' + originUrl.replace(/^https?:\/\//, '');
            (new Image()).src = warmUrl;

            if (debugMode) {
                console.log('ImgPro: Warming origin', originUrl, 'via', warmUrl);
            }
        }
    }

    /**
     * Lazy loading handler
     * Fixes browser caching issue with lazy-loaded images
     */
    var LazyHandler = (function() {
        var intervalId = null;
        var checkCount = 0;
        var maxChecks = 10; // Stop after 20 seconds (10 * 2s)

        /**
         * Check and fix failed lazy-loaded images
         */
        function checkLazyImages() {
            var lazyImages = document.querySelectorAll('img[loading="lazy"][data-worker-domain]');
            var needsChecking = false;

            if (debugMode) {
                console.log('ImgPro: Checking ' + lazyImages.length + ' lazy-loaded images (check #' + (checkCount + 1) + ')');
            }

            lazyImages.forEach(function(img) {
                // Already fell back - skip
                if (img.dataset.fallback) {
                    return;
                }

                // Check if image failed to load (complete but 0 dimensions = cached 404)
                if (img.complete && img.naturalWidth === 0) {
                    // Trigger the error handler
                    handleError(img);
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
                    if (debugMode) {
                        console.log('ImgPro: Stopped checking lazy images' +
                            (checkCount >= maxChecks ? ' (max checks reached)' : ' (all resolved)'));
                    }
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
                if (debugMode) {
                    console.log('ImgPro: Started checking lazy images');
                }
            }
        }

        /**
         * Initialize lazy loading handler
         */
        function init() {
            // Wait for DOM to be ready before setting up observers
            function setupObservers() {
                // Start checking lazy images
                setTimeout(startChecking, 100);

                // Watch for new images added via infinite scroll/AJAX
                if ('MutationObserver' in window && document.body) {
                    var observer = new MutationObserver(function(mutations) {
                        var hasNewLazyImages = false;
                        mutations.forEach(function(mutation) {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.nodeType === 1) {
                                    // Check if node itself is a lazy image
                                    if (node.tagName === 'IMG' && node.loading === 'lazy' && node.dataset.workerDomain) {
                                        hasNewLazyImages = true;
                                    }
                                    // Check children
                                    if (node.querySelectorAll) {
                                        var lazyImgs = node.querySelectorAll('img[loading="lazy"][data-worker-domain]');
                                        if (lazyImgs.length > 0) {
                                            hasNewLazyImages = true;
                                        }
                                    }
                                }
                            });
                        });

                        // Restart checking if new lazy images detected
                        if (hasNewLazyImages) {
                            if (debugMode) {
                                console.log('ImgPro: New lazy images detected, restarting checks');
                            }
                            startChecking();
                        }
                    });

                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                }
            }

            // Setup observers when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', setupObservers);
            } else {
                setupObservers();
            }
        }

        return {
            init: init
        };
    })();

    // Initialize lazy handler
    LazyHandler.init();

    // Public API
    return {
        handleError: handleError
    };
})();
