/**
 * Pull-to-refresh for mobile
 * This handles drag, overlay, and wave behavior
 */

(function() {
  'use strict';

  const rootEl = document.documentElement;
  const METRICS = {
    activate: 110,
    max: 180
  };

  const TEXT = {
    idle: 'Pull to refresh',
    ready: 'Release to refresh',
    refreshing: 'Refreshing',
    done: 'Done!'
  };

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
  let headerEl = null;
  let overlayEl = null;
  let startY = 0;
  let isPrimed = false;
  let hasActivated = false;
  let currentOffset = 0;
  let wavePath = null;
  let wavePhase = 0;
  let waveRAF = null;

  function updateHeader(delta) {
    const clamped = Math.max(0, Math.min(delta, METRICS.max));
    const eased = clamped === 0 ? 0 : METRICS.max * (1 - Math.exp(-clamped / METRICS.max));
    currentOffset = eased;
    const progress = Math.min(clamped / METRICS.activate, 1);
    rootEl.style.setProperty('--ptr-offset', `${eased}px`);
    rootEl.style.setProperty('--ptr-header-opacity', Math.min(1, progress * 1.1));
    if (overlayEl) {
      overlayEl.style.opacity = String(Math.min(0.5, progress * 0.5));
    }

    if (mainContent) {
      mainContent.style.transform = `translateY(${eased}px)`;
    }
    if (headerEl) {
      headerEl.style.transform = `translateY(${eased}px)`;
    }

    if (!isRefreshing && ptrText) {
      ptrText.textContent = progress >= 1 ? TEXT.ready : TEXT.idle;
    }
  }

  function resetHeader() {
    currentOffset = 0;
    rootEl.style.setProperty('--ptr-offset', '0px');
    rootEl.style.setProperty('--ptr-header-opacity', '0');

    if (mainContent) {
      mainContent.style.transform = 'translateY(0px)';
      mainContent.style.transition = 'transform 0.25s ease';
    }

    if (headerEl) {
      headerEl.style.transform = 'translateY(0px)';
      headerEl.style.transition = 'transform 0.25s ease';
    }

    if (ptrIcon) {
      ptrIcon.style.transform = 'rotate(0deg)';
    }
    if (ptrText && !isRefreshing) {
      ptrText.textContent = TEXT.idle;
    }
    drawWave(0);
    if (overlayEl) {
      overlayEl.style.opacity = '0';
    }
  }

  // Curvy wave rendering with animated phase and amplitude
  function drawWave(progress) {
    if (!wavePath) return;
    const amp = Math.max(0, Math.min(progress, 1)) * 8 + (isRefreshing ? 2 : 0);
    const mid = 10; // vertical center (0..20)
    const freq = 0.18; // number of waves across width
    const phase = wavePhase;
    let d = '';
    const step = 2; // resolution
    for (let x = 0; x <= 100; x += step) {
      const y = mid + Math.sin((x * freq + phase)) * amp;
      d += `${x === 0 ? 'M' : 'L'} ${x} ${y} `;
    }
    // close shape to bottom
    d += 'L 100 20 L 0 20 Z';
    wavePath.setAttribute('d', d);
  }

  function startWaveAnimation() {
    if (waveRAF) return;
    const loop = () => {
      wavePhase += 0.2;
      drawWave(Math.min(currentOffset / METRICS.max, 1));
      waveRAF = requestAnimationFrame(loop);
    };
    waveRAF = requestAnimationFrame(loop);
  }

  function stopWaveAnimation() {
    if (waveRAF) {
      cancelAnimationFrame(waveRAF);
      waveRAF = null;
    }
  }

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
      <div id="ptr-loader" aria-hidden="true">
        <span class="ptr-bar"></span>
        <span class="ptr-bar"></span>
        <span class="ptr-bar"></span>
        <span class="ptr-bar"></span>
        <span class="ptr-bar"></span>
        <span class="ptr-bar"></span>
        <span class="ptr-bar"></span>
      </div>
      <span id="ptr-text">Pull to refresh</span>
      <div id="ptr-wave">
        <svg viewBox="0 0 100 20" preserveAspectRatio="none" aria-hidden="true">
          <path id="ptr-wave-path" d="" fill="currentColor" fill-opacity="0.12"></path>
        </svg>
      </div>
    `;
    document.body.insertBefore(header, document.body.firstChild);
    return header;
  }

  function createPTROverlay() {
    const el = document.createElement('div');
    el.id = 'ptr-overlay';
    document.body.insertBefore(el, document.body.firstChild);
    return el;
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

    ptrScrollport = document.documentElement;
    mainContent = document.querySelector('.content');
    headerEl = document.querySelector('.app-header');
    
    if (!mainContent) {
      console.warn('⚠️ Main content element not found');
      return;
    }

    overlayEl = createPTROverlay();
    ptrHeader = createPTRHeader();
    ptrIcon = ptrHeader.querySelector('#ptr-icon');
    ptrText = ptrHeader.querySelector('#ptr-text');
    ptrLoader = ptrHeader.querySelector('#ptr-loader');
    wavePath = ptrHeader.querySelector('#ptr-wave-path');
    setPTRActive(false);
    if (mainContent) {
      mainContent.style.transform = 'translateY(0px)';
      mainContent.style.transition = 'transform 0.25s ease';
      mainContent.style.willChange = 'transform';
    }
    if (headerEl) {
      headerEl.style.transform = 'translateY(0px)';
      headerEl.style.transition = 'transform 0.25s ease';
      headerEl.style.willChange = 'transform';
    }
    resetHeader();

    // Listen for scroll end to trigger refresh
    window.addEventListener('scrollend', handleScrollEnd);
    document.addEventListener('touchstart', handleTouchStart, { passive: true });
    document.addEventListener('touchmove', handleTouchMove, { passive: false });
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
      resetHeader();
    } else {
      rootEl.classList.add('ptr-tracking');
      if (mainContent) {
        mainContent.style.transition = 'none';
      }
      if (headerEl) {
        headerEl.style.transition = 'none';
      }
      startWaveAnimation();
    }
  }

  function handleTouchMove(event) {
    if (!isPrimed || isRefreshing) return;

    const currentY = event.touches[0].clientY;
    const delta = currentY - startY;

    if (delta <= 0) {
      setPTRActive(false);
      updateHeader(0);
      hasActivated = false;
      return;
    }

    if (event.cancelable) {
      event.preventDefault();
    }

    updateHeader(delta);

    if (delta >= METRICS.activate) {
      setPTRActive(true);
      hasActivated = true;
    } else {
      setPTRActive(false);
      hasActivated = false;
    }
  }

  function handleTouchEnd() {
    if (isRefreshing) return;

    rootEl.classList.remove('ptr-tracking');
    if (mainContent) {
      mainContent.style.transition = 'transform 0.25s ease';
    }
    if (headerEl) {
      headerEl.style.transition = 'transform 0.25s ease';
    }

    if (hasActivated) {
      setPTRActive(true);
      hasActivated = false;
      isPrimed = false;

      ptrScrollport.scrollTo({ top: 0, behavior: 'auto' });
      triggerRefresh();
      return;
    }

    setPTRActive(false);
    hasActivated = false;
    isPrimed = false;
    resetHeader();
    stopWaveAnimation();
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
    ptrText.textContent = TEXT.refreshing;
    if (mainContent) {
      mainContent.style.transition = 'transform 0.25s ease';
    }
    if (headerEl) {
      headerEl.style.transition = 'transform 0.25s ease';
    }
    startWaveAnimation();
    if (overlayEl) {
      overlayEl.style.opacity = '0.4';
    }
    updateHeader(METRICS.max);

    // Simulate refresh or trigger actual reload
    setTimeout(() => {
      ptrText.textContent = TEXT.done;
      
      setTimeout(() => {
        ptrHeader.removeAttribute('loading-state');
        setPTRActive(false);
        resetHeader();
        stopWaveAnimation();
        if (overlayEl) {
          overlayEl.style.opacity = '0';
        }
        
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
    ptrText.textContent = TEXT.idle;
    setPTRActive(false);
    resetHeader();
    stopWaveAnimation();
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
    if (overlayEl && overlayEl.parentNode) {
      overlayEl.parentNode.removeChild(overlayEl);
    }
    
    window.removeEventListener('scrollend', handleScrollEnd);
    document.removeEventListener('touchstart', handleTouchStart);
    document.removeEventListener('touchmove', handleTouchMove, { passive: false });
    document.removeEventListener('touchend', handleTouchEnd);
    
    ptrHeader = null;
    ptrIcon = null;
    ptrText = null;
    ptrLoader = null;
    overlayEl = null;
    wavePath = null;
    headerEl = null;
    setPTRActive(false);
    resetHeader();
    stopWaveAnimation();
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
