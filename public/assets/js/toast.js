/**
 * Toast Notification System
 * Unified toast notification API for the entire application
 * 
 * Usage:
 *   Toast.show('Message', 'success');
 *   Toast.success('Operation completed!');
 *   Toast.error('Something went wrong');
 *   Toast.warning('Please be careful');
 *   Toast.info('Here is some information');
 * 
 * @author Inventory Management System
 */

(function(window) {
  'use strict';

  /**
   * Toast Notification Class
   */
  class ToastNotification {
    constructor() {
      this.container = null;
      this.toasts = [];
      this.defaultDuration = 3000;
      this.maxToasts = 5;
      this.init();
    }

    /**
     * Initialize toast container
     */
    init() {
      // Check if container already exists
      this.container = document.getElementById('toast-container');
      
      if (!this.container) {
        this.container = document.createElement('div');
        this.container.id = 'toast-container';
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
      }
    }

    /**
     * Show a toast notification
     * @param {string} message - The message to display
     * @param {string} type - Type of toast (success, error, warning, info, danger)
     * @param {number} duration - Duration in milliseconds (default: 3000)
     * @param {object} options - Additional options
     * @returns {HTMLElement} The toast element
     */
    show(message, type = 'info', duration = null, options = {}) {
      // Ensure container exists
      if (!this.container) {
        this.init();
      }

      // Set default duration
      const toastDuration = duration !== null ? duration : this.defaultDuration;

      // Map 'danger' to 'danger' (consistency)
      const toastType = type === 'error' ? 'danger' : type;

      // Create toast element
      const toast = document.createElement('div');
      toast.className = `toast toast-${toastType}`;
      toast.setAttribute('role', 'alert');
      toast.setAttribute('aria-live', 'polite');
      toast.setAttribute('aria-atomic', 'true');

      // Get icon for toast type
      const icon = this.getIcon(toastType);

      // Build toast HTML
      const closable = options.closable !== false;
      toast.innerHTML = `
        ${icon}
        <span class="toast-message">${this.escapeHtml(message)}</span>
        ${closable ? `
        <button class="toast-close" aria-label="Close notification">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
        ` : ''}
      `;

      // Add custom class if provided
      if (options.className) {
        toast.classList.add(options.className);
      }

      // Limit number of toasts
      if (this.toasts.length >= this.maxToasts) {
        this.remove(this.toasts[0]);
      }

      // Add to container
      this.container.appendChild(toast);
      this.toasts.push(toast);

      // Close button functionality
      const closeBtn = toast.querySelector('.toast-close');
      if (closeBtn) {
        closeBtn.addEventListener('click', () => {
          this.remove(toast);
        });
      }

      // Auto remove after duration (if not permanent)
      if (toastDuration > 0) {
        toast.dataset.timeoutId = setTimeout(() => {
          this.remove(toast);
        }, toastDuration);
      }

      // Stop auto-remove on hover
      if (options.stopOnFocus !== false) {
        toast.addEventListener('mouseenter', () => {
          if (toast.dataset.timeoutId) {
            clearTimeout(toast.dataset.timeoutId);
          }
        });

        toast.addEventListener('mouseleave', () => {
          if (toastDuration > 0) {
            toast.dataset.timeoutId = setTimeout(() => {
              this.remove(toast);
            }, 1000); // Give 1 more second after mouse leave
          }
        });
      }

      // Click callback
      if (options.onClick && typeof options.onClick === 'function') {
        toast.addEventListener('click', (e) => {
          if (!e.target.closest('.toast-close')) {
            options.onClick(e);
          }
        });
        toast.style.cursor = 'pointer';
      }

      return toast;
    }

    /**
     * Remove a toast notification
     * @param {HTMLElement} toast - The toast element to remove
     */
    remove(toast) {
      if (!toast || !toast.parentNode) return;

      // Clear timeout if exists
      if (toast.dataset.timeoutId) {
        clearTimeout(toast.dataset.timeoutId);
      }

      // Slide out animation
      toast.style.animation = 'slideOut 0.3s ease-out forwards';

      setTimeout(() => {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
          
          // Remove from tracking array
          const index = this.toasts.indexOf(toast);
          if (index > -1) {
            this.toasts.splice(index, 1);
          }
        }
      }, 300);
    }

    /**
     * Success toast shorthand
     * @param {string} message - The message to display
     * @param {number} duration - Duration in milliseconds
     * @param {object} options - Additional options
     */
    success(message, duration = null, options = {}) {
      return this.show(message, 'success', duration, options);
    }

    /**
     * Error toast shorthand
     * @param {string} message - The message to display
     * @param {number} duration - Duration in milliseconds (default: 5000 for errors)
     * @param {object} options - Additional options
     */
    error(message, duration = 5000, options = {}) {
      return this.show(message, 'danger', duration, options);
    }

    /**
     * Warning toast shorthand
     * @param {string} message - The message to display
     * @param {number} duration - Duration in milliseconds
     * @param {object} options - Additional options
     */
    warning(message, duration = null, options = {}) {
      return this.show(message, 'warning', duration, options);
    }

    /**
     * Info toast shorthand
     * @param {string} message - The message to display
     * @param {number} duration - Duration in milliseconds
     * @param {object} options - Additional options
     */
    info(message, duration = null, options = {}) {
      return this.show(message, 'info', duration, options);
    }

    /**
     * Loading toast shorthand
     * @param {string} message - The message to display
     * @param {object} options - Additional options
     */
    loading(message, options = {}) {
      return this.show(message, 'info', 0, { ...options, closable: false });
    }

    /**
     * Clear all toasts
     */
    clearAll() {
      const toastsToRemove = [...this.toasts];
      toastsToRemove.forEach(toast => this.remove(toast));
    }

    /**
     * Get icon SVG for toast type
     * @param {string} type - Toast type
     * @returns {string} SVG HTML string
     */
    getIcon(type) {
      const icons = {
        success: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17L4 12" stroke-linecap="round"/></svg>',
        warning: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke-linecap="round"/></svg>',
        danger: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6L18 18" stroke-linecap="round"/></svg>',
        info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8V12M12 16H12.01" stroke-linecap="round"/></svg>',
        loading: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2C12 2 12 6 12 6" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></path></svg>'
      };

      return icons[type] || icons.info;
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    /**
     * Set configuration
     * @param {object} config - Configuration object
     */
    configure(config) {
      if (config.defaultDuration !== undefined) {
        this.defaultDuration = config.defaultDuration;
      }
      if (config.maxToasts !== undefined) {
        this.maxToasts = config.maxToasts;
      }
    }
  }

  // Create global Toast instance
  const Toast = new ToastNotification();

  // Expose to window
  window.Toast = Toast;

  // Backward compatibility - Create showToast function
  window.showToast = function(message, type = 'info', duration = 3000) {
    return Toast.show(message, type, duration);
  };

  // Auto-initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      Toast.init();
    });
  } else {
    Toast.init();
  }

})(window);
