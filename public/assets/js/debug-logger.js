/**
 * Debug Logger Utility
 * Controls verbose console logging so that logs only appear when explicitly enabled by a developer.
 */
 (function () {
  'use strict';

  const STORAGE_KEY = 'inventory_verbose_logging';
  const supportsStorage = typeof window.localStorage !== 'undefined';

  const nativeConsoleLogs = {
    log: console.log,
    info: console.info,
    warn: console.warn,
    error: console.error,
  };

  const logger = {
    enabled: false,
    setEnabled(value, persist = true) {
      this.enabled = !!value;
      if (supportsStorage && persist) {
        try {
          window.localStorage.setItem(STORAGE_KEY, this.enabled ? '1' : '0');
        } catch (error) {
          nativeConsoleLogs.warn('Verbose logging: unable to persist preference', error);
        }
      }
    },
    log(...args) {
      if (!this.enabled) return;
      nativeConsoleLogs.log(...args);
    },
    info(...args) {
      if (!this.enabled) return;
      nativeConsoleLogs.info(...args);
    },
    enable() {
      this.setEnabled(true);
      nativeConsoleLogs.info('Verbose logging enabled');
    },
    disable() {
      this.setEnabled(false);
      nativeConsoleLogs.info('Verbose logging disabled');
    }
  };

  logger.initFromStorage = function () {
    if (!supportsStorage) return;
    try {
      const stored = window.localStorage.getItem(STORAGE_KEY);
      if (stored === '1') {
        this.setEnabled(true, false);
      }
    } catch (error) {
      nativeConsoleLogs.warn('Verbose logging: unable to read preference', error);
    }
  };

  const overrideConsole = () => {
    console.log = (...args) => logger.log(...args);
    console.info = (...args) => logger.info(...args);
    console.warn = (...args) => logger.log(...args);
    console.error = (...args) => logger.log(...args);
  };

  logger.initFromStorage();
  overrideConsole();

  window.VerboseLogger = logger;
  window.debugLog = function (...args) {
    logger.log(...args);
  };
  window.enableVerboseLogging = function () {
    logger.enable();
    return logger.enabled;
  };
  window.disableVerboseLogging = function () {
    logger.disable();
    return logger.enabled;
  };
  window.isVerboseLoggingEnabled = function () {
    return !!logger.enabled;
  };
  window.toggleVerboseLogging = function () {
    if (logger.enabled) {
      logger.disable();
    } else {
      logger.enable();
    }
    return logger.enabled;
  };
})();
