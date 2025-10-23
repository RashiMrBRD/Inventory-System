/**
 * Sidebar JavaScript
 * Handles sidebar collapse/expand and mobile interactions
 * Version: 1.0
 */

(function() {
  'use strict';

  // State management
  const SIDEBAR_STATE_KEY = 'sidebar-state';
  const STATE_EXPANDED = 'expanded';
  const STATE_COLLAPSED = 'collapsed';

  // Get elements
  const sidebar = document.querySelector('.sidebar');
  const sidebarToggle = document.querySelector('.sidebar-toggle');
  const mainContainer = document.querySelector('.main-container');
  
  if (!sidebar) {
    console.warn('Sidebar element not found');
    return;
  }

  /**
   * Get saved sidebar state from localStorage
   */
  function getSavedState() {
    return localStorage.getItem(SIDEBAR_STATE_KEY) || STATE_EXPANDED;
  }

  /**
   * Save sidebar state to localStorage
   */
  function saveState(state) {
    localStorage.setItem(SIDEBAR_STATE_KEY, state);
  }

  /**
   * Get current sidebar state
   */
  function getCurrentState() {
    return sidebar.getAttribute('data-state') || STATE_EXPANDED;
  }

  /**
   * Set sidebar state
   */
  function setState(state) {
    sidebar.setAttribute('data-state', state);
    saveState(state);
    
    // Dispatch custom event for other scripts
    const event = new CustomEvent('sidebarStateChange', { 
      detail: { state } 
    });
    window.dispatchEvent(event);
  }

  /**
   * Toggle sidebar state
   */
  function toggleSidebar() {
    const currentState = getCurrentState();
    const newState = currentState === STATE_EXPANDED ? STATE_COLLAPSED : STATE_EXPANDED;
    setState(newState);
  }

  /**
   * Check if device is mobile
   */
  function isMobile() {
    return window.innerWidth < 1024;
  }

  /**
   * Handle mobile menu
   */
  function handleMobileMenu() {
    if (isMobile()) {
      sidebar.setAttribute('data-state', 'collapsed');
      
      // Add mobile overlay when menu is open
      const overlay = document.createElement('div');
      overlay.className = 'mobile-sidebar-overlay';
      overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 35;
        display: none;
      `;
      
      document.body.appendChild(overlay);
      
      // Toggle mobile menu
      if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
          const isMobileOpen = sidebar.hasAttribute('data-mobile-open');
          
          if (isMobileOpen) {
            sidebar.removeAttribute('data-mobile-open');
            overlay.style.display = 'none';
          } else {
            sidebar.setAttribute('data-mobile-open', '');
            overlay.style.display = 'block';
          }
        });
      }
      
      // Close on overlay click
      overlay.addEventListener('click', () => {
        sidebar.removeAttribute('data-mobile-open');
        overlay.style.display = 'none';
      });
    }
  }

  /**
   * Initialize sidebar
   */
  function init() {
    // Restore saved state (desktop only)
    if (!isMobile()) {
      const savedState = getSavedState();
      setState(savedState);
    } else {
      handleMobileMenu();
    }

    // Add toggle button event listener
    if (sidebarToggle) {
      sidebarToggle.addEventListener('click', (e) => {
        e.preventDefault();
        if (!isMobile()) {
          toggleSidebar();
        }
      });
    }

    // Keyboard shortcut: Ctrl/Cmd + B
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
        e.preventDefault();
        if (!isMobile()) {
          toggleSidebar();
        }
      }
    });

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        if (isMobile()) {
          handleMobileMenu();
        } else {
          // Remove mobile attributes when switching to desktop
          sidebar.removeAttribute('data-mobile-open');
          const overlay = document.querySelector('.mobile-sidebar-overlay');
          if (overlay) {
            overlay.remove();
          }
          
          // Restore saved state
          const savedState = getSavedState();
          setState(savedState);
        }
      }, 250);
    });

    // Auto-collapse on mobile link click
    if (isMobile()) {
      const sidebarLinks = sidebar.querySelectorAll('.sidebar-link');
      sidebarLinks.forEach(link => {
        link.addEventListener('click', () => {
          sidebar.removeAttribute('data-mobile-open');
          const overlay = document.querySelector('.mobile-sidebar-overlay');
          if (overlay) {
            overlay.style.display = 'none';
          }
        });
      });
    }
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Export functions for external use
  window.Sidebar = {
    toggle: toggleSidebar,
    expand: () => setState(STATE_EXPANDED),
    collapse: () => setState(STATE_COLLAPSED),
    getState: getCurrentState
  };

})();
