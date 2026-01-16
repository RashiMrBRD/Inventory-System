(function () {
  'use strict';

  function decodeConfig(value) {
    if (!value) {
      return null;
    }

    try {
      var decoded = decodeURIComponent(value);
      var parsed = atob(decoded);
      return JSON.parse(parsed);
    } catch (error) {
      if (typeof window.debugLog === 'function') {
        window.debugLog('Experimental warning init: unable to decode config', error);
      } else {
        console.warn('Experimental warning init: unable to decode config', error);
      }
    }

    return null;
  }

  function getConfigFromScript() {
    var script = document.currentScript;
    if (!script) {
      return null;
    }

    var src = new URL(script.src, window.location.href);
    return src.searchParams.get('config');
  }

  var configPayload = decodeConfig(getConfigFromScript());
  if (!configPayload) {
    return;
  }

  if (typeof window.initExperimentalWarning === 'function') {
    window.initExperimentalWarning(configPayload);
  } else if (typeof window.debugLog === 'function') {
    window.debugLog('Experimental warning init: initExperimentalWarning not available yet');
  }
})();
