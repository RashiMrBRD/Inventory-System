/**
 * Keyboard Shortcuts System
 * Handles global keyboard navigation across all pages
 * - Left/Right arrows: Pagination navigation
 * - Up/Down arrows: Menu navigation
 */

(function() {
  'use strict';

  // Menu navigation order (excluding section headers and logout)
  const menuItems = [
    { name: 'Dashboard', url: 'dashboard.php' },
    { name: 'Analytics', url: 'analytics-dashboard.php' },
    { name: 'Inventory', url: 'inventory-list.php' },
    // { name: 'Add Item', url: 'add_item.php' },
    { name: 'Quotations', url: 'quotations.php' },
    { name: 'Invoicing', url: 'invoicing.php' },
    { name: 'Orders', url: 'orders.php' },
    { name: 'Projects', url: 'projects.php' },
    { name: 'Shipping', url: 'shipping.php' },
    { name: 'BIR Compliance', url: 'bir-compliance.php' },
    { name: 'FDA Compliance', url: 'fda-compliance.php' },
    { name: 'Notifications', url: 'notifications.php' },
    { name: 'Chart of Accounts', url: 'chart-of-accounts.php' },
    { name: 'Journal Entries', url: 'journal-entries.php' },
    { name: 'Financial Reports', url: 'financial-reports.php' },
    { name: 'Settings', url: 'settings.php' }
  ];

  // Get current page
  function getCurrentPage() {
    const path = window.location.pathname;
    const filename = path.split('/').pop();
    return filename || 'dashboard.php';
  }

  // Find current menu index
  function getCurrentMenuIndex() {
    const currentPage = getCurrentPage();
    return menuItems.findIndex(item => item.url === currentPage);
  }

  // Navigate to menu item
  function navigateToMenuItem(index) {
    if (index >= 0 && index < menuItems.length) {
      window.location.href = menuItems[index].url;
    }
  }

  // Handle pagination navigation
  function handlePaginationNavigation(direction) {
    // Strategy 1: Look for buttons with onclick containing goToPage or changePage
    const allButtons = Array.from(document.querySelectorAll('button:not([disabled])'));
    
    if (direction === 'next') {
      // Look for Next button with various patterns
      const nextButton = allButtons.find(btn => {
        const onclick = btn.getAttribute('onclick') || '';
        const text = btn.textContent.trim().toLowerCase();
        
        // Check for changePage(1) or changePage(+1)
        if (onclick.includes('changePage') && (onclick.includes('(1)') || onclick.includes('(+1)'))) {
          return true;
        }
        
        // Check for goToPage with current + 1
        if (onclick.includes('goToPage') && text.includes('next')) {
          return true;
        }
        
        // Check for Next text
        if (text === 'next' || text.includes('next')) {
          return true;
        }
        
        return false;
      });
      
      if (nextButton) {
        nextButton.click();
        return true;
      }
      
      // Try calling goToPage function directly if it exists
      if (typeof window.goToPage === 'function') {
        // Find current page and go to next
        const currentPageBtn = document.querySelector('button.btn-primary[onclick*="goToPage"]');
        if (currentPageBtn) {
          const match = currentPageBtn.textContent.match(/\d+/);
          if (match) {
            const currentPage = parseInt(match[0]);
            window.goToPage(currentPage + 1);
            return true;
          }
        }
      }
      
      // Try changePage function
      if (typeof window.changePage === 'function') {
        window.changePage(1);
        return true;
      }
    } else if (direction === 'prev') {
      // Look for Previous button with various patterns
      const prevButton = allButtons.find(btn => {
        const onclick = btn.getAttribute('onclick') || '';
        const text = btn.textContent.trim().toLowerCase();
        
        // Check for changePage(-1)
        if (onclick.includes('changePage') && (onclick.includes('(-1)') || onclick.includes('-1'))) {
          return true;
        }
        
        // Check for goToPage with current - 1
        if (onclick.includes('goToPage') && (text.includes('prev') || text.includes('previous'))) {
          return true;
        }
        
        // Check for Previous text
        if (text === 'previous' || text === 'prev' || text.includes('previous')) {
          return true;
        }
        
        return false;
      });
      
      if (prevButton) {
        prevButton.click();
        return true;
      }
      
      // Try calling goToPage function directly
      if (typeof window.goToPage === 'function') {
        const currentPageBtn = document.querySelector('button.btn-primary[onclick*="goToPage"]');
        if (currentPageBtn) {
          const match = currentPageBtn.textContent.match(/\d+/);
          if (match) {
            const currentPage = parseInt(match[0]);
            if (currentPage > 1) {
              window.goToPage(currentPage - 1);
              return true;
            }
          }
        }
      }
      
      // Try changePage function
      if (typeof window.changePage === 'function') {
        window.changePage(-1);
        return true;
      }
    }

    return false;
  }

  // Main keyboard event handler
  function handleKeyboardShortcut(event) {
    // Ignore if user is typing in input/textarea/select
    const activeElement = document.activeElement;
    const isInputField = activeElement && (
      activeElement.tagName === 'INPUT' ||
      activeElement.tagName === 'TEXTAREA' ||
      activeElement.tagName === 'SELECT' ||
      activeElement.isContentEditable
    );

    if (isInputField) return;

    // Ignore if modifier keys are pressed (Ctrl, Alt, Shift, Meta)
    if (event.ctrlKey || event.altKey || event.metaKey || event.shiftKey) return;

    switch(event.key) {
      case 'ArrowLeft':
        event.preventDefault();
        handlePaginationNavigation('prev');
        break;

      case 'ArrowRight':
        event.preventDefault();
        handlePaginationNavigation('next');
        break;

      case 'ArrowUp':
        event.preventDefault();
        const currentIndexUp = getCurrentMenuIndex();
        if (currentIndexUp > 0) {
          navigateToMenuItem(currentIndexUp - 1);
        }
        break;

      case 'ArrowDown':
        event.preventDefault();
        const currentIndexDown = getCurrentMenuIndex();
        if (currentIndexDown < menuItems.length - 1) {
          navigateToMenuItem(currentIndexDown + 1);
        }
        break;
    }
  }

  // Initialize keyboard shortcuts
  function init() {
    document.addEventListener('keydown', handleKeyboardShortcut);
    console.log('⌨️ Keyboard shortcuts enabled: ←→ Pagination | ↑↓ Menu Navigation');
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
