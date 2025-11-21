/**
 * Global Error Handler
 * Silently handles common blocked requests (ad blockers, analytics blockers)
 */

(function() {
  'use strict';

  // List of patterns to suppress in console
  const SUPPRESSED_PATTERNS = [
    'google-analytics.com',
    'ERR_BLOCKED_BY_CLIENT',
    'ERR_BLOCKED_BY_RESPONSE',
    'ERR_NAME_NOT_RESOLVED',
    'favicon.ico',
    'analytics',
    'gtag',
    'doubleclick',
    'ThemeAPI not available',
    'experimental warning',
    'Permissions-Policy',
    'browsing-topics',
    'interest-cohort',
    'measurement_id',
    'api_secret'
  ];

  // Store original console.error
  const originalError = console.error;
  const originalWarn = console.warn;

  /**
   * Check if error message should be suppressed
   */
  function shouldSuppress(message) {
    if (!message || typeof message !== 'string') {
      return false;
    }

    return SUPPRESSED_PATTERNS.some(pattern => 
      message.toLowerCase().includes(pattern.toLowerCase())
    );
  }

  /**
   * Override console.error to suppress specific errors
   */
  console.error = function(...args) {
    const message = args.join(' ');
    
    if (!shouldSuppress(message)) {
      originalError.apply(console, args);
    }
    // Silently ignore suppressed errors
  };

  /**
   * Override console.warn to suppress specific warnings
   */
  console.warn = function(...args) {
    const message = args.join(' ');
    
    if (!shouldSuppress(message)) {
      originalWarn.apply(console, args);
    }
    // Silently ignore suppressed warnings
  };

  /**
   * Global error event handler
   */
  window.addEventListener('error', function(event) {
    // Check if error is from analytics or ad blockers
    if (event.message && shouldSuppress(event.message)) {
      event.preventDefault();
      return true;
    }
  }, true);

  /**
   * Unhandled promise rejection handler
   */
  window.addEventListener('unhandledrejection', function(event) {
    if (event.reason && typeof event.reason === 'string') {
      if (shouldSuppress(event.reason)) {
        event.preventDefault();
        return;
      }
    }
    
    // Check if reason has a message property
    if (event.reason && event.reason.message && shouldSuppress(event.reason.message)) {
      event.preventDefault();
      return;
    }
  });

  /**
   * Fetch error handler wrapper
   * Wrap fetch to catch and suppress blocked requests
   */
  const originalFetch = window.fetch;
  window.fetch = function(...args) {
    return originalFetch.apply(this, args).catch(error => {
      // Check if it's a blocked request
      if (error.message && shouldSuppress(error.message)) {
        // Silently ignore
        return Promise.reject(error);
      }
      // Re-throw other errors
      throw error;
    });
  };

})();
