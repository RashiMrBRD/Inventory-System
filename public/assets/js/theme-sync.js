(function () {
  'use strict';

  var STORAGE_KEY = 'app_theme_preference';
  var logWarn = function (message, detail) {
    if (typeof window.debugLog === 'function') {
      window.debugLog(message, detail);
    } else {
      console.warn(message, detail);
    }
  };

  function syncThemePreference() {
    var body = document.body;
    if (!body) {
      return;
    }

    var sessionTheme = body.getAttribute('data-theme') || 'light';
    var resolvedTheme = sessionTheme;

    if (sessionTheme === 'system' && window.matchMedia) {
      resolvedTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    body.setAttribute('data-resolved-theme', resolvedTheme);

    try {
      if (window.localStorage) {
        var storedTheme = window.localStorage.getItem(STORAGE_KEY);
        if (storedTheme !== sessionTheme) {
          window.localStorage.setItem(STORAGE_KEY, sessionTheme);
        }
      }
    } catch (err) {
      logWarn('Unable to sync theme preference', err);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncThemePreference);
  } else {
    syncThemePreference();
  }
})();
