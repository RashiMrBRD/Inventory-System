/**
 * Pull-to-Refresh - Scroll-Driven Animation
 * Inspired by nerdy.dev + shadcn design
 * Uses scroll-snap and view-timeline
 * Version: 2.0.0
 */

(function() {
  'use strict';

  const CONFIG = {
    refreshDelay: 1500,
    breakpoint: 768
  };

  let isRefreshing = false;
  let ptrScrollport = null;
  let ptrHeader = null;
  let ptrIcon = null;
  let ptrText = null;
  let ptrLoader = null;
  let mainContent = null;
  let startY = 0;
  let isPrimed = false;
  let hasActivated = false;

  function isMobile() {
    return window.innerWidth <= CONFIG.breakpoint;
  }

  function createPTRHeader() {
    const header = document.createElement('div');
    header.id = 'ptr-header';
    header.innerHTML = `
      <svg id="ptr-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="23 4 23 10 17 10"></polyline>
        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
      </svg>
      <div id="ptr-loader">
        <span class="ptr-wave"></span>
        <span class="ptr-wave"></span>
        <span class="ptr-wave"></span>
        <span class="ptr-wave"></span>
        <span class="ptr-wave"></span>
      </div>
      <span id="ptr-text">Pull to refresh</span>
    `;
    document.body.insertBefore(header, document.body.firstChild);
    return header;
  }

  function setPTRActive(active) {
    const root = document.documentElement;
    if (active) {
      root.classList.add('ptr-active');
    } else {
      root.classList.remove('ptr-active');
    }
  }

  function initPullToRefresh() {
    if (!isMobile()) return;

    // Check if browser supports scroll-driven animations
    if (!CSS.supports('animation-timeline', 'view()')) {
      console.warn('⚠️ Scroll-driven animations not supported');
      return;
    }

    ptrScrollport = document.documentElement;
    mainContent = document.querySelector('.content');
    
    if (!mainContent) {
      console.warn('⚠️ Main content element not found');
      return;
    }

    ptrHeader = createPTRHeader();
    ptrIcon = ptrHeader.querySelector('#ptr-icon');
    ptrText = ptrHeader.querySelector('#ptr-text');
    ptrLoader = ptrHeader.querySelector('#ptr-loader');
    setPTRActive(false);

    // Listen for scroll end to trigger refresh
    window.addEventListener('scrollend', handleScrollEnd);
    document.addEventListener('touchstart', handleTouchStart, { passive: true });
    document.addEventListener('touchmove', handleTouchMove, { passive: true });
    document.addEventListener('touchend', handleTouchEnd, { passive: true });

    console.log('✅ Pull-to-refresh initialized (scroll-driven)');
  }

  function handleTouchStart(event) {
    if (!ptrHeader || isRefreshing) return;

    startY = event.touches[0].clientY;
    isPrimed = (ptrScrollport.scrollTop <= 0);
    hasActivated = false;

    if (!isPrimed) {
      setPTRActive(false);
    }
  }

  function handleTouchMove(event) {
    if (!isPrimed || isRefreshing) return;

    const currentY = event.touches[0].clientY;
    const delta = currentY - startY;

    if (delta > 12) {
      setPTRActive(true);
      hasActivated = true;
    } else if (delta < -8 && hasActivated) {
      setPTRActive(false);
      hasActivated = false;
    }
  }

  function handleTouchEnd() {
    if (isRefreshing) return;

    if (hasActivated) {
      setPTRActive(true);
      hasActivated = false;
      isPrimed = false;

      // ensure we are at top before triggering refresh
      ptrScrollport.scrollTo({ top: 0, behavior: 'auto' });
      triggerRefresh();
      return;
    }

    setPTRActive(false);
    hasActivated = false;
    isPrimed = false;
  }

  function handleScrollEnd(event) {
    if (isRefreshing) return;

    // Check if scrolled to the very top (PTR header visible)
    const scrollTop = ptrScrollport.scrollTop;
    if (scrollTop > 0) {
      setPTRActive(false);
    }
  }

  function triggerRefresh() {
    if (isRefreshing) return;

    isRefreshing = true;
    
    // Update UI to loading state
    ptrHeader.setAttribute('loading-state', 'loading');
    ptrText.textContent = 'Refreshing...';

    // Simulate refresh or trigger actual reload
    setTimeout(() => {
      ptrText.textContent = 'Done!';
      
      setTimeout(() => {
        ptrHeader.removeAttribute('loading-state');
        setPTRActive(false);
        
        // Scroll back to main content
        mainContent.scrollIntoView({ behavior: 'smooth' });
        
        // Wait for scroll to complete, then reload
        window.addEventListener('scrollend', () => {
          window.location.reload();
        }, { once: true });
      }, 500);
    }, CONFIG.refreshDelay);
  }

  function resetPTR() {
    if (!ptrHeader) return;
    
    isRefreshing = false;
    ptrHeader.removeAttribute('loading-state');
    ptrText.textContent = 'Pull to refresh';
    setPTRActive(false);
  }

  function handleResize() {
    const mobile = isMobile();
    
    if (mobile && !ptrHeader) {
      initPullToRefresh();
    } else if (!mobile && ptrHeader) {
      destroy();
    }
  }

  function destroy() {
    if (ptrHeader && ptrHeader.parentNode) {
      ptrHeader.parentNode.removeChild(ptrHeader);
    }
    
    window.removeEventListener('scrollend', handleScrollEnd);
    document.removeEventListener('touchstart', handleTouchStart);
    document.removeEventListener('touchmove', handleTouchMove);
    document.removeEventListener('touchend', handleTouchEnd);
    
    ptrHeader = null;
    ptrIcon = null;
    ptrText = null;
    ptrLoader = null;
    setPTRActive(false);
  }

  window.addEventListener('resize', handleResize);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPullToRefresh);
  } else {
    initPullToRefresh();
  }

  window.PullToRefresh = {
    destroy,
    reset: resetPTR
  };

})();
