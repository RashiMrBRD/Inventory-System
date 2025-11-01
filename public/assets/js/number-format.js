/**
 * Number Format API
 * Smart currency and number formatting with abbreviation and click-to-reveal
 * 
 * Usage:
 *   NumberFormat.currency(21835265, '₱', 100);  // Returns abbreviated HTML
 *   NumberFormat.applyCurrencyAbbreviation('.stat-value', '₱');
 *   NumberFormat.applyToTable('[data-label="Price"] span', '₱', 70);
 * 
 * Features:
 * - Smart abbreviation (T, B, M, K)
 * - Click-to-reveal full values
 * - Dynamic font scaling for large numbers
 * - Automatic application to page elements
 * 
 * @author Inventory Management System
 * @version 0.2.4
 */

(function(window) {
  'use strict';

  /**
   * Number Format Class
   */
  class NumberFormatter {
    constructor() {
      this.init();
    }

    /**
     * Initialize event listeners
     */
    init() {
      // Click handler for abbreviated numbers with dynamic font scaling
      document.addEventListener('click', (e) => {
        if (e.target.classList.contains('abbreviated-number')) {
          this.handleAbbreviationClick(e.target);
        }
      });
    }

    /**
     * Format currency with smart abbreviation
     * @param {number} amount - The numeric amount
     * @param {string} symbol - Currency symbol (e.g., '₱', '$', '€')
     * @param {number} maxWidth - Max width in pixels before abbreviating (default: 80)
     * @returns {string} Formatted HTML string
     */
    currency(amount, symbol = '', maxWidth = 80) {
      const absAmount = Math.abs(amount);
      const isNegative = amount < 0;
      
      // Format full number for reference
      const fullNumber = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(absAmount);
      
      const fullValue = `${symbol}${isNegative ? '-' : ''}${fullNumber}`;
      
      // Determine if abbreviation is needed based on character count
      // Approximate: each char is ~8px wide at normal font size
      const estimatedWidth = fullValue.length * 8;
      
      if (estimatedWidth <= maxWidth) {
        return fullValue; // No abbreviation needed
      }
      
      // Apply abbreviation
      let abbreviated, suffix;
      
      if (absAmount >= 1e12) {
        abbreviated = (absAmount / 1e12).toFixed(2);
        suffix = 'T';
      } else if (absAmount >= 1e9) {
        abbreviated = (absAmount / 1e9).toFixed(2);
        suffix = 'B';
      } else if (absAmount >= 1e6) {
        abbreviated = (absAmount / 1e6).toFixed(2);
        suffix = 'M';
      } else if (absAmount >= 1e3) {
        abbreviated = (absAmount / 1e3).toFixed(2);
        suffix = 'K';
      } else {
        return fullValue; // Too small to abbreviate
      }
      
      const shortValue = `${symbol}${isNegative ? '-' : ''}${abbreviated}${suffix}`;
      
      // Return clickable abbreviated value with tooltip
      return `<span class="abbreviated-number" 
                    data-full="${fullValue}" 
                    title="Click to see full amount"
                    style="cursor: pointer; text-decoration: underline; text-decoration-style: dotted; text-underline-offset: 2px;">
                ${shortValue}
              </span>`;
    }

    /**
     * Format number with abbreviation (no currency symbol)
     * @param {number} amount - The numeric amount
     * @param {number} maxWidth - Max width in pixels before abbreviating
     * @returns {string} Formatted HTML string
     */
    abbreviate(amount, maxWidth = 80) {
      return this.currency(amount, '', maxWidth);
    }

    /**
     * Handle click on abbreviated number
     * @param {HTMLElement} element - The clicked element
     */
    handleAbbreviationClick(element) {
      const fullValue = element.getAttribute('data-full');
      const currentValue = element.innerHTML.trim();
      const shortValue = element.getAttribute('data-short') || currentValue;
      const originalFontSize = element.getAttribute('data-original-fontsize');
      
      if (!element.hasAttribute('data-short')) {
        // First click - save short value and show full with scaled font
        element.setAttribute('data-short', currentValue);
        
        // Save original font size if not already saved
        if (!originalFontSize) {
          const computedSize = window.getComputedStyle(element).fontSize;
          element.setAttribute('data-original-fontsize', computedSize);
        }
        
        // Show full value
        element.innerHTML = fullValue;
        element.style.textDecoration = 'none';
        element.title = 'Click to abbreviate';
        
        // Calculate and apply scaled font size to fit container
        const container = element.closest('.stat-value') || element.parentElement;
        const containerWidth = container.offsetWidth;
        const textLength = fullValue.length;
        
        // Estimate required font size (rough calculation)
        const currentFontSize = parseFloat(originalFontSize || window.getComputedStyle(element).fontSize);
        const estimatedWidth = textLength * (currentFontSize * 0.6); // ~60% of font size per char
        
        if (estimatedWidth > containerWidth) {
          const scaleFactor = containerWidth / estimatedWidth;
          const newFontSize = Math.max(currentFontSize * scaleFactor * 0.9, 12); // Min 12px
          element.style.fontSize = newFontSize + 'px';
          element.style.lineHeight = '1.2';
        }
      } else {
        // Toggle back to abbreviated
        element.innerHTML = shortValue;
        element.style.textDecoration = 'underline';
        element.style.textDecorationStyle = 'dotted';
        element.title = 'Click to see full amount';
        
        // Restore original font size
        if (originalFontSize) {
          element.style.fontSize = originalFontSize;
          element.style.lineHeight = '';
        }
        
        // Remove data-short attribute to allow toggling again
        element.removeAttribute('data-short');
      }
    }

    /**
     * Extract numeric value from currency string
     * @param {string} text - Text containing currency value (e.g., "₱280.00")
     * @returns {number|null} Parsed number or null
     */
    parseFromText(text) {
      const match = text.match(/([\d,]+\.\d{2})/);
      if (match) {
        return parseFloat(match[1].replace(/,/g, ''));
      }
      return null;
    }

    /**
     * Apply currency abbreviation to specific elements
     * @param {string} selector - CSS selector for target elements
     * @param {string} symbol - Currency symbol
     * @param {number} maxWidth - Max width before abbreviating
     */
    applyToElements(selector, symbol, maxWidth = 80) {
      document.querySelectorAll(selector).forEach(element => {
        const text = element.textContent.trim();
        
        // Skip if text contains % (percentage), or doesn't look like currency
        if (text.includes('%') || text.includes('low') || text.includes('out') || 
            text.includes('sales') || text.includes('purchase') || text.includes('urgent')) {
          return;
        }
        
        const numericValue = this.parseFromText(text);
        
        if (numericValue !== null && numericValue >= 100) {  // Only format values >= 100
          const formatted = this.currency(numericValue, symbol, maxWidth);
          element.innerHTML = formatted;
        }
      });
    }

    /**
     * Apply abbreviation to table cells
     * @param {string} selector - CSS selector for table cells
     * @param {string} symbol - Currency symbol
     * @param {number} maxWidth - Max width before abbreviating
     */
    applyToTable(selector, symbol, maxWidth = 70) {
      this.applyToElements(selector, symbol, maxWidth);
    }

    /**
     * Apply abbreviation to stat cards with aggressive abbreviation
     * @param {string} selector - CSS selector for stat cards (default: '.stat-value')
     * @param {string} symbol - Currency symbol
     */
    applyCurrencyAbbreviation(selector = '.stat-value', symbol) {
      document.querySelectorAll(selector).forEach(element => {
        const text = element.textContent.trim();
        const numericValue = this.parseFromText(text);
        
        if (numericValue !== null) {
          // For stat cards, always abbreviate if >= 1M to keep them compact
          if (numericValue >= 1000000) {
            const formatted = this.currency(numericValue, symbol, 1); // Force abbreviation
            element.innerHTML = formatted;
          } else {
            // For smaller values, use normal width check
            const formatted = this.currency(numericValue, symbol, 120);
            element.innerHTML = formatted;
          }
        }
      });
    }

    /**
     * Apply all formatting on page load
     * @param {string} currencySymbol - Currency symbol to use
     * @param {Object} options - Configuration options
     */
    autoApply(currencySymbol, options = {}) {
      const defaults = {
        statCardsSelector: '.stat-value',
        tablePriceSelector: '[data-label="Price"] span',
        tableValueSelector: '[data-label="Value"] span',
        tableMaxWidth: 70,
        customSelectors: [] // Array of {selector, maxWidth}
      };
      
      const config = { ...defaults, ...options };
      
      // Apply to stat cards
      this.applyCurrencyAbbreviation(config.statCardsSelector, currencySymbol);
      
      // Apply to table cells
      if (config.tablePriceSelector) {
        this.applyToTable(config.tablePriceSelector, currencySymbol, config.tableMaxWidth);
      }
      if (config.tableValueSelector) {
        this.applyToTable(config.tableValueSelector, currencySymbol, config.tableMaxWidth);
      }
      
      // Apply to custom selectors
      config.customSelectors.forEach(({ selector, maxWidth }) => {
        this.applyToElements(selector, currencySymbol, maxWidth || 80);
      });
    }
  }

  // Create singleton instance
  const formatter = new NumberFormatter();

  // Expose global API
  window.NumberFormat = {
    /**
     * Format currency with abbreviation
     */
    currency: (amount, symbol, maxWidth) => formatter.currency(amount, symbol, maxWidth),
    
    /**
     * Format number with abbreviation (no symbol)
     */
    abbreviate: (amount, maxWidth) => formatter.abbreviate(amount, maxWidth),
    
    /**
     * Parse number from text
     */
    parse: (text) => formatter.parseFromText(text),
    
    /**
     * Apply to specific elements
     */
    applyTo: (selector, symbol, maxWidth) => formatter.applyToElements(selector, symbol, maxWidth),
    
    /**
     * Apply to table cells
     */
    applyToTable: (selector, symbol, maxWidth) => formatter.applyToTable(selector, symbol, maxWidth),
    
    /**
     * Apply to stat cards
     */
    applyToStats: (selector, symbol) => formatter.applyCurrencyAbbreviation(selector, symbol),
    
    /**
     * Auto-apply all formatting
     */
    autoApply: (currencySymbol, options) => formatter.autoApply(currencySymbol, options)
  };

  // Backward compatibility
  window.formatCurrencyWithAbbreviation = (amount, symbol, maxWidth) => {
    return formatter.currency(amount, symbol, maxWidth);
  };

})(window);
