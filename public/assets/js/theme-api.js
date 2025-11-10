/**
 * Theme API - Centralized Theme Management System
 * Provides consistent theming across the application
 * Works with settings.php theme selection
 */

(function() {
  'use strict';

  /**
   * Theme Color Schemes
   * Define all theme colors here for centralized management
   */
  const THEMES = {
    light: {
      // Background colors
      bgPrimary: '#ffffff',
      bgSecondary: '#f9fafb',
      bgTertiary: '#f3f4f6',
      
      // Border colors
      borderPrimary: '#e5e7eb',
      borderSecondary: '#d1d5db',
      borderAccent: '#9ca3af',
      
      // Text colors
      textPrimary: '#111827',
      textSecondary: '#6b7280',
      textMuted: '#9ca3af',
      
      // Status colors
      success: { bg: '#d1fae5', border: '#6ee7b7', text: '#065f46' },
      warning: { bg: '#fef3c7', border: '#fcd34d', text: '#78350f' },
      error: { bg: '#fee2e2', border: '#fca5a5', text: '#991b1b' },
      info: { bg: '#dbeafe', border: '#93c5fd', text: '#1e3a8a' },
      
      // Component-specific
      modal: {
        overlay: 'rgba(0, 0, 0, 0.5)',
        background: '#ffffff',
        border: '#e5e7eb',
        shadow: '0 10px 15px -3px rgba(0, 0, 0, 0.1)'
      },
      
      // Icon backgrounds
      iconNeutral: '#f3f4f6',
      iconNeutralColor: '#6b7280',
      
      // Card backgrounds
      cardBg: '#f9fafb',
      cardBorder: '#e5e7eb'
    },
    
    dark: {
      // Background colors
      bgPrimary: '#1f2937',
      bgSecondary: '#111827',
      bgTertiary: '#374151',
      
      // Border colors
      borderPrimary: '#374151',
      borderSecondary: '#4b5563',
      borderAccent: '#6b7280',
      
      // Text colors
      textPrimary: '#f9fafb',
      textSecondary: '#d1d5db',
      textMuted: '#9ca3af',
      
      // Status colors
      success: { bg: '#065f46', border: '#059669', text: '#d1fae5' },
      warning: { bg: '#78350f', border: '#d97706', text: '#fef3c7' },
      error: { bg: '#991b1b', border: '#dc2626', text: '#fee2e2' },
      info: { bg: '#1e3a8a', border: '#2563eb', text: '#dbeafe' },
      
      // Component-specific
      modal: {
        overlay: 'rgba(0, 0, 0, 0.7)',
        background: '#1f2937',
        border: '#374151',
        shadow: '0 10px 15px -3px rgba(0, 0, 0, 0.5)'
      },
      
      // Icon backgrounds
      iconNeutral: '#374151',
      iconNeutralColor: '#9ca3af',
      
      // Card backgrounds
      cardBg: '#111827',
      cardBorder: '#374151'
    }
  };

  /**
   * Theme API Class
   */
  class ThemeAPI {
    constructor() {
      this.currentTheme = this.detectTheme();
      this.updateResolvedTheme(this.currentTheme);
      this.listeners = [];
      this.initSystemThemeListener();
      this.persistPreference();
    }

    /**
     * Detect current theme from session/system
     */
    detectTheme() {
      const storageKey = 'app_theme_preference';
      const storedPreference = window.localStorage ? window.localStorage.getItem(storageKey) : null;

      if (storedPreference) {
        if (storedPreference === 'system') {
          this.preference = 'system';
          this.followSystem = true;
          return this.computeSystemTheme();
        }

        this.preference = storedPreference;
        this.followSystem = false;
        return storedPreference;
      }

      const bodyEl = document.body;
      const sessionTheme = bodyEl.getAttribute('data-theme');

      if (sessionTheme && sessionTheme !== 'system') {
        this.preference = sessionTheme;
        this.followSystem = false;
        return sessionTheme;
      }

      this.preference = 'system';
      this.followSystem = true;
      return this.computeSystemTheme();
    }

    computeSystemTheme() {
      if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        return 'dark';
      }
      return 'light';
    }

    /**
     * Get current theme name
     */
    getTheme() {
      return this.currentTheme;
    }

    /**
     * Get color scheme for current theme
     */
    getColors() {
      return THEMES[this.currentTheme] || THEMES.light;
    }

    /**
     * Get specific color by path
     * Example: getColor('modal.background')
     */
    getColor(path) {
      const colors = this.getColors();
      const keys = path.split('.');
      let value = colors;

      for (const key of keys) {
        if (value && typeof value === 'object' && key in value) {
          value = value[key];
        } else {
          return null;
        }
      }

      return value;
    }

    /**
     * Check if dark mode is active
     */
    isDark() {
      return this.currentTheme === 'dark';
    }

    /**
     * Check if light mode is active
     */
    isLight() {
      return this.currentTheme === 'light';
    }

    /**
     * Switch theme (for testing/preview)
     */
    setTheme(themeName) {
      // Allow 'system' as a preference as well
      const valid = themeName === 'system' || Object.prototype.hasOwnProperty.call(THEMES, themeName);
      if (!valid) {
        console.error(`Theme "${themeName}" not found`);
        return;
      }

      if (themeName === 'system') {
        this.preference = 'system';
        this.followSystem = true;
        document.body.setAttribute('data-theme', 'system');
        this.currentTheme = this.computeSystemTheme();
      } else {
        this.preference = themeName;
        this.followSystem = false;
        document.body.setAttribute('data-theme', themeName);
        this.currentTheme = themeName;
      }

      this.persistPreference();
      this.updateResolvedTheme(this.currentTheme);
      this.injectCSSVariables();
      this.notifyListeners(this.currentTheme);
    }

    /**
     * Add listener for theme changes
     */
    onChange(callback) {
      this.listeners.push(callback);
      return () => {
        this.listeners = this.listeners.filter(cb => cb !== callback);
      };
    }

    /**
     * Notify all listeners of theme change
     */
    notifyListeners(theme) {
      this.listeners.forEach(callback => {
        try {
          callback(theme, this.getColors());
        } catch (err) {
          console.error('Theme listener error:', err);
        }
      });
    }

    /**
     * Listen to system theme changes
     */
    initSystemThemeListener() {
      if (!window.matchMedia) return;
      const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

      mediaQuery.addEventListener('change', (e) => {
        if (this.followSystem) {
          this.currentTheme = e.matches ? 'dark' : 'light';
          this.updateResolvedTheme(this.currentTheme);
          this.injectCSSVariables();
          this.notifyListeners(this.currentTheme);
          this.persistPreference();
        }
      });
    }

    /**
     * Get CSS variables for current theme
     */
    getCSSVariables() {
      const colors = this.getColors();
      const cssVars = {};

      const flatten = (obj, prefix = '--theme') => {
        Object.entries(obj).forEach(([key, value]) => {
          const varName = `${prefix}-${key.replace(/([A-Z])/g, '-$1').toLowerCase()}`;
          
          if (typeof value === 'object' && !Array.isArray(value)) {
            flatten(value, varName);
          } else {
            cssVars[varName] = value;
          }
        });
      };

      flatten(colors);
      return cssVars;
    }

    /**
     * Inject CSS variables into document
     */
    injectCSSVariables() {
      const vars = this.getCSSVariables();
      const root = document.documentElement;

      Object.entries(vars).forEach(([name, value]) => {
        root.style.setProperty(name, value);
      });
    }

    /**
     * Keep body[data-resolved-theme] in sync with the effective theme
     */
    updateResolvedTheme(theme) {
      if (document.body) {
        document.body.setAttribute('data-resolved-theme', theme);
      }
    }
  }

  // Create global instance
  window.ThemeAPI = new ThemeAPI();

  // Inject CSS variables on load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      window.ThemeAPI.injectCSSVariables();
    });
  } else {
    window.ThemeAPI.injectCSSVariables();
  }

  // Make THEMES available for debugging
  window.ThemeAPI.THEMES = THEMES;

  console.log('🎨 Theme API initialized -', window.ThemeAPI.getTheme());

})();
