/**
 * Experimental Feature Warning Modal System
 * 
 * Displays a warning modal for experimental features that are not production-ready.
 * This module can be easily removed by deleting the features/experimental-warning folder.
 * 
 * @version 1.0.0
 */

(function() {
  'use strict';

  /**
   * Configuration
   */
  const CONFIG = {
    storageKey: 'experimental-warning-dismissed',
    welcomeStorageKey: 'welcome-modal-shown',
    sessionDuration: 24 * 60 * 60 * 1000, // 24 hours in milliseconds
    animationDuration: 200, // matches CSS animation duration
    alwaysShow: true // Set to true to show modal every time, false for 24-hour session
  };

  /**
   * ExperimentalWarning Class
   */
  class ExperimentalWarning {
    constructor(options = {}) {
      this.options = {
        pageName: options.pageName || 'This Feature',
        pageFile: options.pageFile || 'unknown.php',
        title: options.title || '⚠️ Experimental Feature Detected',
        description: options.description || 'This feature is currently in experimental status and is not yet ready for production use.',
        details: options.details || 'Because this feature is still under development, you may encounter unexpected behavior.',
        recommendation: options.recommendation || 'If you have any suggestions or ideas, please contact the administrator.',
        isWelcome: options.isWelcome || false,
        appInfo: options.appInfo || null,
        ...options
      };

      this.overlay = null;
      this.isOpen = false;
    }

    /**
     * Check if warning should be shown
     */
    shouldShow() {
      // For welcome modals, check sessionStorage (shows once per login session)
      if (this.options.isWelcome) {
        const welcomeShown = sessionStorage.getItem(CONFIG.welcomeStorageKey);
        if (welcomeShown) {
          return false; // Already shown in this session
        }
        return true; // Show welcome modal
      }
      
      // For experimental warnings, if alwaysShow is enabled, always display
      if (CONFIG.alwaysShow) {
        return true;
      }
      
      const dismissed = localStorage.getItem(CONFIG.storageKey);
      
      if (!dismissed) return true;
      
      try {
        const data = JSON.parse(dismissed);
        const now = Date.now();
        
        // Check if 24 hours have passed
        if (now - data.timestamp > CONFIG.sessionDuration) {
          localStorage.removeItem(CONFIG.storageKey);
          return true;
        }
        
        return false;
      } catch (e) {
        // Invalid data, show warning
        localStorage.removeItem(CONFIG.storageKey);
        return true;
      }
    }

    /**
     * Mark warning as dismissed
     */
    dismiss() {
      // For welcome modals, mark as shown in sessionStorage
      if (this.options.isWelcome) {
        sessionStorage.setItem(CONFIG.welcomeStorageKey, 'true');
        return;
      }
      
      // For experimental warnings, only store dismissal if alwaysShow is disabled
      if (!CONFIG.alwaysShow) {
        const data = {
          timestamp: Date.now(),
          pageName: this.options.pageName
        };
        
        localStorage.setItem(CONFIG.storageKey, JSON.stringify(data));
      }
      // If alwaysShow is true, we don't store anything so modal appears next time
    }

    /**
     * Create modal HTML
     */
    createModal() {
      const overlay = document.createElement('div');
      overlay.className = 'experimental-warning-overlay';
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-modal', 'true');
      overlay.setAttribute('aria-labelledby', 'experimental-warning-title');
      overlay.setAttribute('aria-describedby', 'experimental-warning-description');

      overlay.innerHTML = `
        <div class="experimental-warning-modal">
          <!-- Close Button -->
          <button 
            type="button" 
            class="experimental-warning-close" 
            aria-label="Close warning"
            data-action="close"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>

          <!-- Scrollable Content Wrapper -->
          <div class="experimental-warning-content">
            <!-- Header -->
            <div class="experimental-warning-header">
              <div class="experimental-warning-title" id="experimental-warning-title">
                ${this.options.isWelcome ? 
                  '<span class="experimental-warning-icon" aria-hidden="true">👋</span>' : 
                  '<span class="experimental-warning-icon" aria-hidden="true">⚠️</span>'
                }
                ${this.escapeHtml(this.options.title)}
              </div>
              <div class="experimental-warning-description" id="experimental-warning-description">
                ${this.escapeHtml(this.options.description)}
              </div>
            </div>

            <!-- Body - Page Identification or App Info -->
            <div class="experimental-warning-body ${this.options.isWelcome ? 'experimental-warning-welcome' : ''}">
              ${this.options.isWelcome && this.options.appInfo ? 
                this.renderAppInfo() : 
                this.renderPageInfo()
              }
            </div>

            <!-- Recommendation Section -->
            <div class="experimental-warning-recommendation">
              <div class="experimental-warning-recommendation-icon">💡</div>
              <div class="experimental-warning-recommendation-text">
                ${this.escapeHtml(this.options.recommendation)}
              </div>
            </div>
          </div>

          <!-- Footer (Fixed at bottom) -->
          <div class="experimental-warning-footer">
            <button 
              type="button" 
              class="experimental-warning-btn experimental-warning-btn-secondary" 
              data-action="cancel"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
              </svg>
              Go Back
            </button>
            <button 
              type="button" 
              class="experimental-warning-btn experimental-warning-btn-primary" 
              data-action="continue"
            >
              Continue Anyway
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </button>
          </div>
        </div>
      `;

      return overlay;
    }

    /**
     * Render app information for welcome modal
     */
    renderAppInfo() {
      const appInfo = this.options.appInfo;
      const teamList = appInfo.team.map(member => 
        `<li class="team-member">👤 ${this.escapeHtml(member)}</li>`
      ).join('');
      
      return `
        <div class="experimental-warning-body-title">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
          </svg>
          System Information
        </div>
        <div class="experimental-warning-body-text app-info-content">
          <div class="app-info-item">
            <strong>📦 Application:</strong> ${this.escapeHtml(appInfo.name)}
          </div>
          <div class="app-info-item">
            <strong>🏷️ Version:</strong> ${this.escapeHtml(appInfo.version)}
          </div>
          <div class="app-info-item">
            <strong>👥 Development Team:</strong>
            <ul class="team-list">
              ${teamList}
            </ul>
          </div>
          <div class="app-info-item">
            ${this.escapeHtml(this.options.details)}
          </div>
        </div>
      `;
    }

    /**
     * Render page identification for experimental warning
     */
    renderPageInfo() {
      return `
        <div class="experimental-warning-body-title">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
          Page Identified: <strong>${this.escapeHtml(this.options.pageFile)}</strong>
        </div>
        <div class="experimental-warning-body-text">
          ${this.escapeHtml(this.options.details)}
        </div>
      `;
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    /**
     * Open the modal
     */
    open() {
      if (this.isOpen) return;

      // Create and add modal to DOM
      this.overlay = this.createModal();
      document.body.appendChild(this.overlay);
      document.body.classList.add('experimental-warning-active');

      // Bind events
      this.bindEvents();

      // Focus trap
      this.trapFocus();

      this.isOpen = true;
    }

    /**
     * Close the modal
     */
    close(callback) {
      if (!this.isOpen || !this.overlay) return;

      // Add closing animation
      this.overlay.classList.add('closing');

      // Remove after animation
      setTimeout(() => {
        if (this.overlay && this.overlay.parentNode) {
          this.overlay.parentNode.removeChild(this.overlay);
        }
        document.body.classList.remove('experimental-warning-active');
        this.overlay = null;
        this.isOpen = false;

        if (callback) callback();
      }, CONFIG.animationDuration);
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
      if (!this.overlay) return;

      // Close button
      const closeBtn = this.overlay.querySelector('[data-action="close"]');
      if (closeBtn) {
        closeBtn.addEventListener('click', () => this.handleClose());
      }

      // Cancel button (go back)
      const cancelBtn = this.overlay.querySelector('[data-action="cancel"]');
      if (cancelBtn) {
        cancelBtn.addEventListener('click', () => this.handleCancel());
      }

      // Continue button
      const continueBtn = this.overlay.querySelector('[data-action="continue"]');
      if (continueBtn) {
        continueBtn.addEventListener('click', () => this.handleContinue());
      }

      // Overlay click (close on backdrop)
      this.overlay.addEventListener('click', (e) => {
        if (e.target === this.overlay) {
          this.handleClose();
        }
      });

      // Escape key
      this.escapeKeyHandler = (e) => {
        if (e.key === 'Escape') {
          this.handleClose();
        }
      };
      document.addEventListener('keydown', this.escapeKeyHandler);
    }

    /**
     * Handle close action
     */
    handleClose() {
      this.close();
      // Clean up escape key listener
      if (this.escapeKeyHandler) {
        document.removeEventListener('keydown', this.escapeKeyHandler);
      }
    }

    /**
     * Handle cancel action (go back)
     */
    handleCancel() {
      this.close(() => {
        // Navigate back in history
        if (window.history.length > 1) {
          window.history.back();
        } else {
          // Fallback to homepage if no history
          window.location.href = '/';
        }
      });
      
      if (this.escapeKeyHandler) {
        document.removeEventListener('keydown', this.escapeKeyHandler);
      }
    }

    /**
     * Handle continue action
     */
    handleContinue() {
      this.dismiss();
      this.close();
      
      if (this.escapeKeyHandler) {
        document.removeEventListener('keydown', this.escapeKeyHandler);
      }

      // Optional: Show toast notification
      if (typeof Toast !== 'undefined') {
        if (this.options.isWelcome) {
          Toast.success('Welcome! Proceeding to dashboard.', 2000);
        } else if (CONFIG.alwaysShow) {
          Toast.info('Proceeding to experimental feature. Warning will appear again on next visit.', 3000);
        } else {
          Toast.info('Proceeding to experimental feature. Warning dismissed for 24 hours.', 3000);
        }
      }
    }

    /**
     * Focus trap for accessibility
     */
    trapFocus() {
      if (!this.overlay) return;

      const focusableElements = this.overlay.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      
      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];

      // Focus first element
      if (firstElement) {
        setTimeout(() => firstElement.focus(), 100);
      }

      // Trap focus within modal
      this.overlay.addEventListener('keydown', (e) => {
        if (e.key !== 'Tab') return;

        if (e.shiftKey) {
          if (document.activeElement === firstElement) {
            e.preventDefault();
            lastElement.focus();
          }
        } else {
          if (document.activeElement === lastElement) {
            e.preventDefault();
            firstElement.focus();
          }
        }
      });
    }
  }

  /**
   * Initialize warning modal
   */
  function initExperimentalWarning(options = {}) {
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => init(options));
    } else {
      init(options);
    }
  }

  function init(options) {
    const warning = new ExperimentalWarning(options);
    
    if (warning.shouldShow()) {
      // Small delay for better UX
      setTimeout(() => {
        warning.open();
      }, 500);
    }
  }

  // Export to global scope
  window.ExperimentalWarning = ExperimentalWarning;
  window.initExperimentalWarning = initExperimentalWarning;

  // Console command to force show any modal (bypasses sessionStorage/localStorage)
  window.forceShowModal = function() {
    console.log('%c🚀 Force showing modal...', 'color: #22c55e; font-weight: bold; font-size: 14px;');
    
    // Clear sessionStorage for welcome modal
    sessionStorage.removeItem(CONFIG.welcomeStorageKey);
    
    // Clear localStorage for experimental warnings
    localStorage.removeItem(CONFIG.storageKey);
    
    console.log('%c✅ Storage cleared. Reloading page...', 'color: #3b82f6; font-weight: bold;');
    
    // Reload the page to trigger modal
    setTimeout(() => {
      location.reload();
    }, 500);
  };

  // Console helper to show command info
  console.log(
    '%c💡 Modal Control Commands Available:', 
    'color: #a855f7; font-weight: bold; font-size: 16px; margin-top: 10px;'
  );
  console.log(
    '%cforceShowModal()%c - Force show welcome/warning modal on current page',
    'color: #22c55e; font-weight: bold; background: #000; padding: 2px 6px; border-radius: 3px;',
    'color: #64748b;'
  );
  console.log(
    '%cExample: %cforceShowModal()',
    'color: #64748b;',
    'color: #22c55e; font-family: monospace;'
  );

})();
