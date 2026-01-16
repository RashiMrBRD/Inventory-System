/**
 * Keyboard Shortcuts System
 * Handles global keyboard navigation across all pages
 * 
 * NAVIGATION:
 * - ←→ Arrows: Tab switching, View switching, Pagination
 * - ↑↓ Arrows: Menu navigation
 * 
 * ACTIONS:
 * - V: View Sessions Modal
 * - G: Generate Report
 * - S: Save (Branding/Settings)
 * - N: New Entry/Account/Project/Order/Invoice/Quote
 * - E: Export
 * - I: Initialize
 * - Shift+I: Import
 * - C: Create Shipment
 * - Shift+B: Back to Dashboard
 * - B: Bulk Select
 * - A: Add Item
 * - R: Reports
 * - Shift+R: Refresh Page
 * - ?: Show shortcuts help
 */

(function() {
  'use strict';

  // Menu navigation order with first-letter shortcuts
  const menuItems = [
    { name: 'Dashboard', url: 'dashboard', key: 'd' },
    { name: 'Analytics', url: 'analytics-dashboard', key: 'y' },
    { name: 'Inventory', url: 'inventory-list', key: 'i' },
    // { name: 'Add Item', url: 'add_item' },
    { name: 'Quotations', url: 'quotations', key: 'q' },
    { name: 'Invoicing', url: 'invoicing', key: 'v' },
    { name: 'Orders', url: 'orders', key: 'o' },
    { name: 'Projects', url: 'projects', key: 'p' },
    { name: 'Shipping', url: 'shipping', key: 'h' },
    { name: 'BIR Compliance', url: 'bir-compliance', key: 'b' },
    { name: 'FDA Compliance', url: 'fda-compliance', key: 'f' },
    { name: 'Notifications', url: 'notifications', key: 'n' },
    { name: 'Chart of Accounts', url: 'chart-of-accounts', key: 't' },
    { name: 'Journal Entries', url: 'journal-entries', key: 'j' },
    { name: 'Financial Reports', url: 'financial-reports', key: 'l' },
    { name: 'Settings', url: 'settings', key: 'x' }
  ];

  // Normalized current page key (without .php), e.g., "dashboard", "inventory-list"
  function getCurrentPageKey() {
    const path = window.location.pathname || '';
    const segments = path.split('/').filter(Boolean);
    let base = segments.pop() || '';
    base = base.replace(/\.php$/i, '');
    return base || 'dashboard';
  }

  // Sidebar DOM helpers for ArrowUp/ArrowDown navigation
  function isSidebarLinkNavigable(link) {
    const href = (link.getAttribute('href') || '').split('?')[0];
    if (!href) return false;
    const normalized = href.replace(/^\//, '').replace(/\.php$/i, '');
    if (!normalized) return false;
    if (normalized === 'logout') return false;
    return true;
  }

  function getSidebarLinks() {
    const sidebar = document.querySelector('.sidebar-nav');
    if (!sidebar) return [];
    return Array.from(sidebar.querySelectorAll('.sidebar-link')).filter(isSidebarLinkNavigable);
  }

  function getCurrentSidebarIndex() {
    const links = getSidebarLinks();
    if (!links.length) return -1;
    const currentKey = getCurrentPageKey();
    return links.findIndex(link => {
      const href = (link.getAttribute('href') || '').split('?')[0];
      const hrefBase = href.replace(/^\//, '').replace(/\.php$/i, '');
      return hrefBase === currentKey;
    });
  }

  function navigateToSidebarIndex(targetIndex) {
    const links = getSidebarLinks();
    if (targetIndex >= 0 && targetIndex < links.length) {
      const targetLink = links[targetIndex];
      const href = targetLink.getAttribute('href');
      if (href) {
        // Apply active state immediately for perceived instant switch
        links.forEach(link => link.classList.toggle('active', link === targetLink));
        showPageLoading(targetLink);
        window.location.href = href;
      }
    }
  }

  // Lightweight page loading overlay (content-only shimmer/blur)
  function ensurePageLoadingOverlay() {
    const content = document.querySelector('main.content');
    let overlay = document.getElementById('page-loading-overlay');
    if (overlay) return overlay;
    overlay = document.createElement('div');
    overlay.id = 'page-loading-overlay';
    overlay.innerHTML = `
      <div class="page-loading-shimmer">
        <div class="page-loading-line line-1"></div>
        <div class="page-loading-line line-2"></div>
        <div class="page-loading-line line-3"></div>
        <div class="page-loading-line line-4"></div>
      </div>
    `;
    if (content) {
      content.appendChild(overlay);
    } else {
      document.body.appendChild(overlay);
    }
    return overlay;
  }

  function showPageLoading(targetLink) {
    const overlay = ensurePageLoadingOverlay();
    document.body.classList.add('page-loading');
    overlay.classList.add('visible');
    if (targetLink) {
      const links = getSidebarLinks();
      links.forEach(link => link.classList.toggle('active', link === targetLink));
    }
  }

  function hidePageLoading() {
    const overlay = ensurePageLoadingOverlay();
    document.body.classList.remove('page-loading');
    overlay.classList.remove('visible');
  }

  function attachSidebarLoadingHandlers() {
    const links = getSidebarLinks();
    links.forEach(link => {
      link.addEventListener('click', function(event) {
        // Allow default new-tab behaviors
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
        const href = link.getAttribute('href');
        if (!href) return;
        event.preventDefault();
        showPageLoading(link);
        // Navigate after a tiny delay to allow paint of active highlight
        setTimeout(() => { window.location.href = href; }, 10);
      });
    });
  }

  function scrollActiveSidebarLinkIntoView() {
    const sidebarTop = document.querySelector('.sidebar-nav-top');
    if (!sidebarTop) return;
    const activeLink = sidebarTop.querySelector('.sidebar-link.active');
    if (!activeLink) return;

    const containerHeight = sidebarTop.clientHeight;
    const linkOffsetTop = activeLink.offsetTop;
    const linkHeight = activeLink.offsetHeight;
    const targetScrollTop = linkOffsetTop - (containerHeight / 2) + (linkHeight / 2);
    sidebarTop.scrollTo({
      top: Math.max(0, targetScrollTop),
      behavior: 'auto'
    });
  }

  // Get current page (normalized key)
  function getCurrentPage() {
    return getCurrentPageKey();
  }

  // Find current menu index (used for some legacy behaviors)
  function getCurrentMenuIndex() {
    const currentPage = getCurrentPage();
    return menuItems.findIndex(item => item.url === currentPage);
  }

  // Navigate to menu item by index
  function navigateToMenuItem(index) {
    if (index >= 0 && index < menuItems.length) {
      window.location.href = menuItems[index].url;
    }
  }

  // Detect if any application modal is open (visible)
  function isElementRendered(el) {
    const style = window.getComputedStyle(el);
    if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
      return false;
    }
    const rect = el.getBoundingClientRect();
    return rect.width > 0 || rect.height > 0;
  }

  function isAnyModalOpen() {
    const modals = Array.from(document.querySelectorAll('[id*="Modal"], [role="dialog"], .modal'));
    return modals.some(m => isElementRendered(m));
  }

  // Return the first open modal element (if any)
  function getOpenModalElement() {
    const modals = Array.from(document.querySelectorAll('[id*="Modal"], [role="dialog"], .modal'));
    return modals.find(m => isElementRendered(m)) || null;
  }

  // Handle tab switching (settings page, modal windows, etc.)
  function handleTabSwitching(direction) {
    // Helper to find active tab name via visible content
    function resolveActiveNameByContent(container, contentSelector, idPrefix) {
      const contents = Array.from(container.querySelectorAll(contentSelector));
      const visible = contents.find(el => window.getComputedStyle(el).display !== 'none');
      if (visible && visible.id && visible.id.startsWith(idPrefix)) {
        return visible.id.replace(idPrefix, '');
      }
      return null;
    }

    // Try within an open modal first (highest priority)
    const openModal = getOpenModalElement();
    if (openModal) {
      const modalTabConfigs = [
        { btnSel: '.order-tab-btn',    contentSel: '.order-tab-content',    idPrefix: 'order-tab-' },
        { btnSel: '.project-tab-btn',  contentSel: '.project-tab-content',  idPrefix: 'project-tab-' },
        { btnSel: '.shipment-tab-btn', contentSel: '.shipment-tab-content', idPrefix: 'shipment-tab-' },
        { btnSel: '.invoice-tab-btn',  contentSel: '.invoice-tab-content',  idPrefix: 'invoice-tab-' },
        { btnSel: '.quote-tab-btn',    contentSel: '.quote-tab-content',    idPrefix: 'quote-tab-' },
        { btnSel: '.qv-tab',           contentSel: '.qv-tab-content',       idPrefix: 'qv-tab-' }
      ];

      for (const cfg of modalTabConfigs) {
        const btns = Array.from(openModal.querySelectorAll(cfg.btnSel));
        if (btns.length === 0) continue;

        // Determine active name
        let activeName = null;
        // 1) via visible content
        activeName = resolveActiveNameByContent(openModal, cfg.contentSel, cfg.idPrefix) || activeName;
        // 2) via active class on button
        if (!activeName) {
          const activeBtn = btns.find(b => b.classList.contains('active'));
          if (activeBtn) activeName = activeBtn.getAttribute('data-tab');
        }
        // 3) fallback to first button's data-tab
        if (!activeName && btns[0]) activeName = btns[0].getAttribute('data-tab');

        if (!activeName) continue;

        const currentIndex = btns.findIndex(b => b.getAttribute('data-tab') === activeName);
        if (currentIndex === -1) continue;

        let nextIndex;
        if (direction === 'next') {
          nextIndex = (currentIndex + 1) % btns.length;
        } else {
          nextIndex = currentIndex - 1 < 0 ? btns.length - 1 : currentIndex - 1;
        }

        btns[nextIndex].click();
        return true;
      }
    }

    // Fallback: Page-level tabs (e.g., add_item.php uses .tab-btn / .tab-content)
    const pageBtns = Array.from(document.querySelectorAll('.tab-btn'));
    if (pageBtns.length > 0) {
      // Active via class or visible content
      let activeName = null;
      const activeBtn = pageBtns.find(b => b.classList.contains('active'));
      if (activeBtn) activeName = activeBtn.getAttribute('data-tab');
      if (!activeName) {
        const visibleContent = Array.from(document.querySelectorAll('.tab-content'))
          .find(el => window.getComputedStyle(el).display !== 'none');
        if (visibleContent && visibleContent.id && visibleContent.id.startsWith('tab-')) {
          activeName = visibleContent.id.replace('tab-', '');
        }
      }
      if (!activeName && pageBtns[0]) activeName = pageBtns[0].getAttribute('data-tab');
      if (activeName) {
        const currentIndex = pageBtns.findIndex(b => b.getAttribute('data-tab') === activeName);
        let nextIndex;
        if (direction === 'next') {
          nextIndex = (currentIndex + 1) % pageBtns.length;
        } else {
          nextIndex = currentIndex - 1 < 0 ? pageBtns.length - 1 : currentIndex - 1;
        }
        pageBtns[nextIndex].click();
        return true;
      }
    }

    // Last resort: settings tabs (legacy)
    const tabs = Array.from(document.querySelectorAll('.tab-trigger:not([disabled])'));
    const activeTab = document.querySelector('.tab-trigger.active');
    if (tabs.length === 0 || !activeTab) return false;

    const tabsArray = Array.isArray(tabs) ? tabs : Array.from(tabs);
    const currentIndex = tabsArray.indexOf(activeTab);
    let nextIndex;
    if (direction === 'next') {
      nextIndex = (currentIndex + 1) % tabsArray.length;
    } else {
      nextIndex = currentIndex - 1 < 0 ? tabsArray.length - 1 : currentIndex - 1;
    }
    tabsArray[nextIndex].click();
    return true;
  }

  // Create menu shortcuts map from menuItems
  function getMenuShortcuts() {
    const shortcuts = {};
    menuItems.forEach(item => {
      if (item.key) {
        shortcuts[item.key] = function() {
          window.location.href = item.url;
          return true;
        };
      }
    });
    return shortcuts;
  }

  // Preemptive, context-aware actions that should override menu navigation when applicable
  function tryPriorityAction(key) {
    switch (key) {
      // V: Open Sessions Modal if present, otherwise let menu 'v' handle navigation
      case 'v': {
        if (typeof window.openSessionsModal === 'function') { window.openSessionsModal(); return true; }
        const btn = document.querySelector('[onclick*="openSessionsModal"], [data-action="openSessionsModal"]');
        if (btn) { btn.click(); return true; }
        return false;
      }
      // N: New entity (Entry/Account/Project/Order/Invoice/Quote) only if clearly available
      case 'n': {
        // Prefer calling known modal openers if available
        if (typeof window.showNewOrderModal === 'function') { window.showNewOrderModal(); return true; }
        if (typeof window.showNewProjectModal === 'function') { window.showNewProjectModal(); return true; }
        if (typeof window.showNewInvoiceModal === 'function') { window.showNewInvoiceModal(); return true; }
        if (typeof window.showNewQuoteModal === 'function') { window.showNewQuoteModal(); return true; }
        if (typeof window.showNewShipmentModal === 'function') { window.showNewShipmentModal(); return true; }
        if (typeof window.showNewEntryModal === 'function') { window.showNewEntryModal(); return true; }
        if (typeof window.openNewAccountModal === 'function') { window.openNewAccountModal(); return true; }

        const selectors = [
          '[onclick*="showNewOrderModal"]',
          '[onclick*="showNewProjectModal"]',
          '[onclick*="showNewInvoiceModal"]',
          '[onclick*="showNewQuoteModal"]',
          '[onclick*="showNewShipmentModal"]',
          '[onclick*="showNewEntryModal"]',
          '[onclick*="openNewAccountModal"]',
          '[onclick*="createNewProject"]',
          '[onclick*="createNewOrder"]',
          '[onclick*="createNewInvoice"]',
          '[onclick*="createNewQuote"]',
          'a[href*="new"], .btn-primary[href*="add"]'
        ];
        for (const sel of selectors) {
          const el = document.querySelector(sel);
          if (el && !el.disabled) { el.click(); return true; }
        }
        return false;
      }
      // I: Initialize if present (Import remains Shift+I)
      case 'i': {
        if (typeof window.initialize === 'function') { window.initialize(); return true; }
        const el = document.querySelector('[onclick*="initialize"], [data-action*="initialize"]');
        if (el && !el.disabled) { el.click(); return true; }
        return false;
      }
      // B: Bulk select if present (BIR menu wins otherwise)
      case 'b': {
        const el = document.querySelector('[onclick*="bulk"], [data-action*="bulk"], .bulk-select, button[id*="bulk"], a[id*="bulk"]');
        if (el && !el.disabled) { el.click(); return true; }
        return false;
      }
      default:
        return false;
    }
  }

  // Handle view switching (grouped/list)
  function handleViewSwitching(direction) {
    // Block view switching when a modal is open
    if (isAnyModalOpen()) return false;
    const urlParams = new URLSearchParams(window.location.search);
    const currentView = urlParams.get('view') || 'grouped';
    
    // Check if view switcher exists on page
    const viewButtons = document.querySelectorAll('[href*="view="]');
    if (viewButtons.length === 0) return false;

    if (direction === 'next') {
      const newView = currentView === 'grouped' ? 'list' : 'grouped';
      urlParams.set('view', newView);
      window.location.search = urlParams.toString();
      return true;
    } else {
      const newView = currentView === 'list' ? 'grouped' : 'list';
      urlParams.set('view', newView);
      window.location.search = urlParams.toString();
      return true;
    }
  }

  // Handle pagination navigation
  function handlePaginationNavigation(direction) {
    // Block background pagination when a modal is open
    if (isAnyModalOpen()) return false;
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

  // Action handlers for shortcut keys
  const actionHandlers = {
    // Menu shortcuts (dynamic from menuItems)
    ...getMenuShortcuts(),
    
    // Action shortcuts (these can override menu shortcuts if needed)
    // Note: Menu shortcuts 'i', 'n', 'b', 'f' conflict with actions below
    // Menu shortcuts take priority; actions moved to Shift combinations

    // G: Generate Report
    'g': function() {
      if (typeof window.generateReport === 'function') {
        window.generateReport();
        return true;
      }
      const generateBtn = document.querySelector('[onclick*="generateReport"]');
      if (generateBtn) {
        generateBtn.click();
        return true;
      }
      return false;
    },

    // S: Save (Branding/Settings) - non-conflicting single-key
    's': function() {
      const saveButtons = [
        document.querySelector('button[type="submit"][form*="branding"]'),
        document.querySelector('button[type="submit"]:not([id])'),
        document.querySelector('.btn-primary[type="submit"]'),
        document.getElementById('save-branding-btn'),
        document.querySelector('[onclick*="saveBranding"]')
      ];
      for (const btn of saveButtons) {
        if (btn && !btn.disabled) { btn.click(); return true; }
      }
      return false;
    },

    // E: Export - non-conflicting single-key
    'e': function() {
      if (typeof window.exportData === 'function') { window.exportData(); return true; }
      const exportBtn = document.querySelector('[onclick*="export"]');
      if (exportBtn) { exportBtn.click(); return true; }
      return false;
    },

    // C: Create Shipment - non-conflicting single-key
    'c': function() {
      if (typeof window.createShipment === 'function') { window.createShipment(); return true; }
      if (typeof window.showNewShipmentModal === 'function') { window.showNewShipmentModal(); return true; }
      const shipmentBtn = document.querySelector('[onclick*="createShipment"], [onclick*="showNewShipmentModal"]');
      if (shipmentBtn) { shipmentBtn.click(); return true; }
      return false;
    },

    // A: Add Item - non-conflicting single-key
    'a': function() {
      const addItemLink = document.querySelector('a[href*="add_item"]');
      if (addItemLink) { window.location.href = addItemLink.href; return true; }
      return false;
    },

    // R: Reports - non-conflicting single-key
    'r': function() {
      window.location.href = 'financial-reports.php';
      return true;
    },

    // Note: Removed conflicting single-letter actions
    // These are now handled by Shift combinations below
    // Menu shortcuts (d,y,i,q,v,o,p,h,b,f,n,t,j,l,x) take priority

    // W: Toggle help (? requires Shift, this is backup)
    'w': function() {
      showShortcutsHelp();
      return true;
    }
  };

  // Shift + Key handlers
  const shiftActionHandlers = {
    // Shift+S: Save (Branding/Settings)
    'S': function() {
      const saveButtons = [
        document.querySelector('button[type="submit"][form*="branding"]'),
        document.querySelector('button[type="submit"]:not([id])'),
        document.querySelector('.btn-primary[type="submit"]'),
        document.getElementById('save-branding-btn'),
        document.querySelector('[onclick*="saveBranding"]')
      ];
      
      for (const btn of saveButtons) {
        if (btn && !btn.disabled) {
          btn.click();
          return true;
        }
      }
      return false;
    },
    
    // Shift+N: New Entry/Account/Project/Order/Invoice/Quote
    'N': function() {
      // Prefer calling known modal openers
      if (typeof window.showNewOrderModal === 'function') { window.showNewOrderModal(); return true; }
      if (typeof window.showNewProjectModal === 'function') { window.showNewProjectModal(); return true; }
      if (typeof window.showNewInvoiceModal === 'function') { window.showNewInvoiceModal(); return true; }
      if (typeof window.showNewQuoteModal === 'function') { window.showNewQuoteModal(); return true; }
      if (typeof window.showNewShipmentModal === 'function') { window.showNewShipmentModal(); return true; }
      if (typeof window.showNewEntryModal === 'function') { window.showNewEntryModal(); return true; }
      if (typeof window.openNewAccountModal === 'function') { window.openNewAccountModal(); return true; }

      const newButtons = [
        document.querySelector('[onclick*="showNewOrderModal"]'),
        document.querySelector('[onclick*="showNewProjectModal"]'),
        document.querySelector('[onclick*="showNewInvoiceModal"]'),
        document.querySelector('[onclick*="showNewQuoteModal"]'),
        document.querySelector('[onclick*="showNewShipmentModal"]'),
        document.querySelector('[onclick*="showNewEntryModal"]'),
        document.querySelector('[onclick*="openNewAccountModal"]'),
        document.querySelector('[onclick*="createNewProject"]'),
        document.querySelector('[onclick*="createNewOrder"]'),
        document.querySelector('[onclick*="createNewInvoice"]'),
        document.querySelector('[onclick*="createNewQuote"]'),
        document.querySelector('[href*="new"]'),
        document.querySelector('.btn-primary[href*="add"]')
      ];

      for (const btn of newButtons) {
        if (btn) { btn.click(); return true; }
      }
      return false;
    },
    
    // Shift+E: Export
    'E': function() {
      if (typeof window.exportData === 'function') {
        window.exportData();
        return true;
      }
      const exportBtn = document.querySelector('[onclick*="export"]');
      if (exportBtn) {
        exportBtn.click();
        return true;
      }
      return false;
    },
    
    // Shift+I: Import
    'I': function() {
      if (typeof window.importData === 'function') {
        window.importData();
        return true;
      }
      const importBtn = document.querySelector('[onclick*="import"]');
      if (importBtn) {
        importBtn.click();
        return true;
      }
      return false;
    },
    
    // Shift+C: Create Shipment
    'C': function() {
      if (typeof window.createShipment === 'function') { window.createShipment(); return true; }
      if (typeof window.showNewShipmentModal === 'function') { window.showNewShipmentModal(); return true; }
      const shipmentBtn = document.querySelector('[onclick*="createShipment"], [onclick*="showNewShipmentModal"]');
      if (shipmentBtn) { shipmentBtn.click(); return true; }
      return false;
    },
    
    // Shift+V: View Sessions Modal
    'V': function() {
      if (typeof window.openSessionsModal === 'function') {
        window.openSessionsModal();
        return true;
      }
      return false;
    },
    
    // Shift+G: Generate Report
    'G': function() {
      if (typeof window.generateReport === 'function') {
        window.generateReport();
        return true;
      }
      const generateBtn = document.querySelector('[onclick*="generateReport"]');
      if (generateBtn) {
        generateBtn.click();
        return true;
      }
      return false;
    },
    
    // Shift+A: Add Item
    'A': function() {
      const addItemLink = document.querySelector('a[href*="add_item"]');
      if (addItemLink) {
        window.location.href = addItemLink.href;
        return true;
      }
      return false;
    },
    
    // Shift+B: Back to Dashboard
    'B': function() {
      window.location.href = 'dashboard.php';
      return true;
    },

    // Shift+R: Refresh
    'R': function() {
      window.location.reload();
      return true;
    },
    
    // Shift+/: Show help (? key)
    '?': function() {
      showShortcutsHelp();
      return true;
    }
  };

  // Show keyboard shortcuts help modal
  function showShortcutsHelp() {
    const helpModal = document.createElement('div');
    helpModal.id = 'keyboard-shortcuts-help';
    helpModal.style.cssText = 'position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem;';
    
    helpModal.innerHTML = `
      <div style="background: var(--bg-primary); border-radius: var(--radius-lg); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); max-width: 980px; width: 95vw; padding: 1.5rem; border: 1px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h.01M10 8h.01M14 8h.01M6 12h.01M10 12h.01M14 12h.01M6 16h.01M10 16h.01M14 16h.01"/></svg>
            Keyboard Shortcuts
          </h2>
          <button onclick="this.closest('#keyboard-shortcuts-help').remove()" style="padding: 0.5rem; background: none; border: none; cursor: pointer; color: var(--text-secondary); border-radius: var(--radius-sm); transition: background 0.2s;" onmouseover="this.style.background='var(--bg-secondary)'" onmouseout="this.style.background='none'">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
          </button>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem;">
          <div>
            <h3 style="font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; margin: 0 0 0.5rem 0; letter-spacing: 0.05em;">NAVIGATION</h3>
            <div style="display: grid; gap: 0.375rem; font-size: 0.8125rem;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Tab Switching</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">← →</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Menu Navigation</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">↑ ↓</kbd>
              </div>
            </div>
            
            <h3 style="font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; margin: 0.875rem 0 0.5rem 0; letter-spacing: 0.05em;">PAGES (1-8)</h3>
            <div style="display: grid; gap: 0.375rem; font-size: 0.8125rem;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Dashboard</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">D</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Analytics</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">Y</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Inventory</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">I</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Quotations</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">Q</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Invoicing</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">V</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Orders</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">O</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Projects</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">P</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Shipping</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">H</kbd>
              </div>
            </div>
          </div>
          
          <div>
            <h3 style="font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; margin: 0 0 0.5rem 0; letter-spacing: 0.05em;">PAGES (9-15)</h3>
            <div style="display: grid; gap: 0.375rem; font-size: 0.8125rem;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>BIR Compliance</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">B</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>FDA Compliance</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">F</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Notifications</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">N</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Chart of Accounts</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">T</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Journal Entries</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">J</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Financial Reports</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">L</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Settings</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">X</kbd>
              </div>
            </div>
            
            <h3 style="font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; margin: 0.875rem 0 0.5rem 0; letter-spacing: 0.05em;">QUICK ACTIONS (Context-Aware)</h3>
            <div style="display: grid; gap: 0.375rem; font-size: 0.8125rem;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>New (Order/Project/Invoice/Quote/Shipment)</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">N</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Open Sessions</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">V</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Initialize</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">I</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Save</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">S</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Export</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">E</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Create Shipment</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">C</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Add Item</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">A</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Reports</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">R</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Generate Report</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">G</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Show Help</span>
                <div style="display:flex; gap: 0.25rem; align-items:center;">
                  <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">W</kbd>
                  <span style="color: var(--text-secondary); font-size: 0.75rem;">or</span>
                  <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">Shift+/</kbd>
                </div>
              </div>
            </div>
          </div>
          
          <div>
            <h3 style="font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; margin: 0 0 0.5rem 0; letter-spacing: 0.05em;">SHIFT ACTIONS</h3>
            <div style="display: grid; gap: 0.375rem; font-size: 0.8125rem;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Save</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">Shift+S</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>New Entry</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">Shift+N</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Export</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">Shift+E</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Import</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">Shift+I</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Create Shipment</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">Shift+C</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>View Sessions</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">Shift+V</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Generate Report</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">Shift+G</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Add Item</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">Shift+A</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Back to Dashboard</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">Shift+B</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Refresh</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">Shift+R</kbd>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Show Help</span>
                <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">Shift+/</kbd>
              </div>
            </div>
          </div>
        </div>
        
        <div style="margin-top: 0.875rem; padding: 0.75rem 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); font-size: 0.8125rem; color: var(--text-secondary);">
          <strong>💡 Tip:</strong> Press <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">Shift+/</kbd> or <kbd style="padding: 0.125rem 0.375rem; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 3px; font-family: monospace; font-size: 0.75rem;">W</kbd> anytime
        </div>
      </div>
    `;
    
    document.body.appendChild(helpModal);
    
    // Close on click outside
    helpModal.addEventListener('click', function(e) {
      if (e.target === helpModal) {
        helpModal.remove();
      }
    });

    // Close on Escape
    const escHandler = function(e) {
      if (e.key === 'Escape') {
        helpModal.remove();
        document.removeEventListener('keydown', escHandler);
      }
    };
    document.addEventListener('keydown', escHandler);
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

    // Allow arrow keys for tab switching even when in input fields (for modal tabs)
    const isArrowKey = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key);
    
    if (isInputField && !isArrowKey) return;

    // Handle Shift + Key combinations
    if (event.shiftKey && !event.ctrlKey && !event.altKey && !event.metaKey) {
      const handler = shiftActionHandlers[event.key];
      if (handler && handler()) {
        event.preventDefault();
        return;
      }
    }

    // Ignore if modifier keys are pressed (except for specific Shift combos handled above)
    if (event.ctrlKey || event.altKey || event.metaKey) return;
    if (event.shiftKey) return; // Already handled above

    // Arrow key navigation
    switch(event.key) {
      case 'ArrowLeft':
        // If a modal is open, consume the event to avoid background handlers (e.g., pagination)
        if (isAnyModalOpen()) {
          event.preventDefault();
          event.stopPropagation();
          if (event.stopImmediatePropagation) event.stopImmediatePropagation();
          if (!handleTabSwitching('prev')) {
            // No modal tabs to switch; do nothing when modal is open
          }
        } else {
          event.preventDefault();
          // Try tab switching first, then view switching, then pagination
          if (!handleTabSwitching('prev')) {
            if (!handleViewSwitching('prev')) {
              handlePaginationNavigation('prev');
            }
          }
        }
        break;

      case 'ArrowRight':
        // If a modal is open, consume the event to avoid background handlers (e.g., pagination)
        if (isAnyModalOpen()) {
          event.preventDefault();
          event.stopPropagation();
          if (event.stopImmediatePropagation) event.stopImmediatePropagation();
          if (!handleTabSwitching('next')) {
            // No modal tabs to switch; do nothing when modal is open
          }
        } else {
          event.preventDefault();
          // Try tab switching first, then view switching, then pagination
          if (!handleTabSwitching('next')) {
            if (!handleViewSwitching('next')) {
              handlePaginationNavigation('next');
            }
          }
        }
        break;

      case 'ArrowUp':
        if (isAnyModalOpen()) {
          // Let the modal handle default Up behavior (scroll/select navigation)
          return;
        }
        event.preventDefault();
        {
          const linksUp = getSidebarLinks();
          if (!linksUp.length) break;
          const currentIndexUp = getCurrentSidebarIndex();
          const lastIndex = linksUp.length - 1;
          const targetIndexUp = currentIndexUp > 0 ? currentIndexUp - 1 : lastIndex;
          navigateToSidebarIndex(targetIndexUp);
        }
        break;

      case 'ArrowDown':
        if (isAnyModalOpen()) {
          // Let the modal handle default Down behavior (scroll/select navigation)
          return;
        }
        event.preventDefault();
        {
          const linksDown = getSidebarLinks();
          if (!linksDown.length) break;
          const currentIndexDown = getCurrentSidebarIndex();
          const targetIndexDown = currentIndexDown === -1
            ? 0
            : (currentIndexDown + 1) % linksDown.length;
          navigateToSidebarIndex(targetIndexDown);
        }
        break;

      default:
        // Handle priority actions first (may preempt menu navigation when context exists)
        {
          const key = (event.key && event.key.length === 1) ? event.key.toLowerCase() : event.key;
          if (tryPriorityAction(key)) { event.preventDefault(); break; }
          // Then handle menu shortcuts and non-conflicting actions
          const handler = actionHandlers[key] || actionHandlers[event.key];
          if (handler && handler()) {
            event.preventDefault();
          }
        }
        break;
    }
  }

  // Initialize keyboard shortcuts
  function init() {
    // Use capture phase so we can intercept and block arrow keys before page-specific listeners
    document.addEventListener('keydown', handleKeyboardShortcut, true);
    attachSidebarLoadingHandlers();
    scrollActiveSidebarLinkIntoView();
    hidePageLoading(); // Clear any stale overlay after initial render
    console.log('⌨️ Keyboard shortcuts enabled: ←→ Pagination | ↑↓ Menu Navigation');
    console.log('📖 Press Shift+/ (?) or W to view all shortcuts');
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
