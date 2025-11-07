/**
 * Mobile Menu System - Shadcn Inspired
 * Beautiful slide-in navigation for mobile devices
 * Version: 1.0.0
 */

(function() {
  'use strict';

  // Configuration
  const CONFIG = {
    breakpoint: 768, // Mobile breakpoint in pixels
    animationDuration: 400, // Animation duration in ms
    enableTouchSwipe: true // Enable swipe-to-close
  };

  // State
  let isOpen = false;
  let startX = 0;
  let currentX = 0;
  let isSwiping = false;

  // Get elements
  const menuButton = document.getElementById('mobile-menu-button');
  const menuOverlay = document.getElementById('mobile-menu-overlay');
  const menu = document.getElementById('mobile-menu');
  const menuClose = document.getElementById('mobile-menu-close');
  const body = document.body;

  // Check if running on mobile
  function isMobile() {
    return window.innerWidth <= CONFIG.breakpoint;
  }

  /**
   * Open mobile menu
   */
  function openMenu() {
    if (!isMobile() || isOpen) return;

    // Add classes
    menuOverlay.classList.add('open');
    menu.classList.add('open');
    body.classList.add('mobile-menu-open');
    
    isOpen = true;

    // Add event listeners
    menuOverlay.addEventListener('click', closeMenu);
    menuClose.addEventListener('click', closeMenu);
    document.addEventListener('keydown', handleEscapeKey);

    // Enable touch swipe
    if (CONFIG.enableTouchSwipe) {
      menu.addEventListener('touchstart', handleTouchStart, { passive: true });
      menu.addEventListener('touchmove', handleTouchMove, { passive: false });
      menu.addEventListener('touchend', handleTouchEnd);
    }

    console.log('📱 Mobile menu opened');
  }

  /**
   * Close mobile menu
   */
  function closeMenu() {
    if (!isOpen) return;

    // Add closing animation
    menuOverlay.classList.add('closing');
    menu.classList.add('closing');

    setTimeout(() => {
      menuOverlay.classList.remove('open', 'closing');
      menu.classList.remove('open', 'closing');
      body.classList.remove('mobile-menu-open');
      isOpen = false;
    }, CONFIG.animationDuration);

    // Remove event listeners
    menuOverlay.removeEventListener('click', closeMenu);
    menuClose.removeEventListener('click', closeMenu);
    document.removeEventListener('keydown', handleEscapeKey);

    if (CONFIG.enableTouchSwipe) {
      menu.removeEventListener('touchstart', handleTouchStart);
      menu.removeEventListener('touchmove', handleTouchMove);
      menu.removeEventListener('touchend', handleTouchEnd);
    }

    console.log('📱 Mobile menu closed');
  }

  /**
   * Toggle mobile menu
   */
  function toggleMenu() {
    if (isOpen) {
      closeMenu();
    } else {
      openMenu();
    }
  }

  /**
   * Handle escape key press
   */
  function handleEscapeKey(e) {
    if (e.key === 'Escape' && isOpen) {
      closeMenu();
    }
  }

  /**
   * Touch event handlers for swipe-to-close
   */
  function handleTouchStart(e) {
    startX = e.touches[0].clientX;
    currentX = startX;
    isSwiping = true;
  }

  function handleTouchMove(e) {
    if (!isSwiping) return;

    currentX = e.touches[0].clientX;
    const diffX = currentX - startX;

    // Only allow swipe left (close)
    if (diffX < 0) {
      e.preventDefault();
      const translateX = Math.max(diffX, -menu.offsetWidth);
      menu.style.transform = `translateX(${translateX}px)`;
      
      // Update overlay opacity based on swipe
      const opacity = 1 + (translateX / menu.offsetWidth);
      menuOverlay.style.opacity = opacity;
    }
  }

  function handleTouchEnd() {
    if (!isSwiping) return;

    const diffX = currentX - startX;
    const threshold = menu.offsetWidth * 0.3; // 30% swipe threshold

    // Reset transform
    menu.style.transform = '';
    menuOverlay.style.opacity = '';

    // Close if swiped more than threshold
    if (diffX < -threshold) {
      closeMenu();
    }

    isSwiping = false;
    startX = 0;
    currentX = 0;
  }

  /**
   * Handle window resize
   */
  function handleResize() {
    if (!isMobile() && isOpen) {
      closeMenu();
    }
  }

  /**
   * Initialize mobile menu
   */
  function init() {
    // Check if elements exist
    if (!menuButton || !menuOverlay || !menu || !menuClose) {
      console.warn('Mobile menu elements not found');
      return;
    }

    // Add event listener to menu button
    menuButton.addEventListener('click', toggleMenu);

    // Handle window resize
    window.addEventListener('resize', handleResize);

    // Add active class to current page links
    const currentPage = window.location.pathname.split('/').pop();
    const menuLinks = menu.querySelectorAll('.mobile-menu-link');
    
    menuLinks.forEach(link => {
      const href = link.getAttribute('href');
      if (href === currentPage) {
        link.classList.add('active');
      }
    });

    console.log('✅ Mobile menu system initialized');
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Export functions to global scope for debugging
  window.MobileMenu = {
    open: openMenu,
    close: closeMenu,
    toggle: toggleMenu
  };

})();
