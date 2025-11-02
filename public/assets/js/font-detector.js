/**
 * Font Detector
 * Detects available fonts in the user's system using FontFaceSet API
 */

class FontDetector {
  constructor() {
    this.testFonts = [
      // Sans-Serif Fonts
      'Arial', 'Helvetica', 'Helvetica Neue', 'Verdana', 'Tahoma', 'Trebuchet MS',
      'Segoe UI', 'Calibri', 'Candara', 'Franklin Gothic Medium', 'Gill Sans',
      'Lucida Sans', 'Lucida Grande', 'Century Gothic', 'Futura', 'Optima',
      'Avenir', 'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Raleway',
      'Ubuntu', 'Source Sans Pro', 'Nunito', 'Poppins', 'Inter',
      
      // Serif Fonts
      'Times New Roman', 'Times', 'Georgia', 'Garamond', 'Palatino',
      'Baskerville', 'Cambria', 'Didot', 'Bodoni', 'Rockwell',
      'Courier New', 'Courier',
      
      // Monospace Fonts
      'Consolas', 'Monaco', 'Menlo', 'Source Code Pro', 'Fira Code',
      'JetBrains Mono', 'Anonymous Pro', 'Inconsolata', 'Liberation Mono',
      
      // Display/Decorative
      'Impact', 'Comic Sans MS', 'Brush Script MT', 'Papyrus',
      'Copperplate', 'Zapfino'
    ];
    
    this.baseFonts = ['monospace', 'sans-serif', 'serif'];
    this.testString = 'mmmmmmmmmmlli';
    this.testSize = '72px';
  }

  /**
   * Check if a specific font is available
   * @param {string} fontFamily - Font family name to check
   * @returns {Promise<boolean>}
   */
  async checkFont(fontFamily) {
    if (!document.fonts || !document.fonts.check) {
      // Fallback for older browsers
      return this.checkFontFallback(fontFamily);
    }

    try {
      // Modern approach using FontFaceSet API
      const font = `12px "${fontFamily}"`;
      return document.fonts.check(font);
    } catch (e) {
      console.warn(`Error checking font ${fontFamily}:`, e);
      return false;
    }
  }

  /**
   * Fallback method for browsers without FontFaceSet API
   * @param {string} fontFamily - Font family name to check
   * @returns {boolean}
   */
  checkFontFallback(fontFamily) {
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');
    
    if (!context) return false;

    context.font = `${this.testSize} ${this.baseFonts[0]}`;
    const baselineSize = context.measureText(this.testString).width;

    context.font = `${this.testSize} "${fontFamily}", ${this.baseFonts[0]}`;
    const testSize = context.measureText(this.testString).width;

    return testSize !== baselineSize;
  }

  /**
   * Detect all available fonts from test list
   * @returns {Promise<Array<{name: string, category: string}>>}
   */
  async detectAvailableFonts() {
    const availableFonts = [];
    
    // Wait for fonts to load
    if (document.fonts && document.fonts.ready) {
      await document.fonts.ready;
    }

    for (const fontName of this.testFonts) {
      const isAvailable = await this.checkFont(fontName);
      
      if (isAvailable) {
        availableFonts.push({
          name: fontName,
          category: this.categorizFont(fontName),
          stack: this.generateFontStack(fontName)
        });
      }
    }

    return availableFonts;
  }

  /**
   * Categorize font by type
   * @param {string} fontName - Font family name
   * @returns {string}
   */
  categorizFont(fontName) {
    const monospaceFonts = ['Consolas', 'Monaco', 'Menlo', 'Courier', 'Source Code Pro', 
                            'Fira Code', 'JetBrains Mono', 'Anonymous Pro', 'Inconsolata'];
    const serifFonts = ['Times', 'Georgia', 'Garamond', 'Palatino', 'Baskerville', 
                        'Cambria', 'Didot', 'Bodoni', 'Rockwell'];
    
    if (monospaceFonts.some(f => fontName.includes(f))) return 'monospace';
    if (serifFonts.some(f => fontName.includes(f))) return 'serif';
    return 'sans-serif';
  }

  /**
   * Generate appropriate font stack
   * @param {string} fontName - Font family name
   * @returns {string}
   */
  generateFontStack(fontName) {
    const category = this.categorizFont(fontName);
    const fallbacks = {
      'sans-serif': '-apple-system, BlinkMacSystemFont, system-ui, sans-serif',
      'serif': 'Georgia, Times, Times New Roman, serif',
      'monospace': 'Consolas, Monaco, Courier New, monospace'
    };
    
    return `"${fontName}", ${fallbacks[category]}`;
  }

  /**
   * Get categorized fonts
   * @returns {Promise<Object>}
   */
  async getCategorizedFonts() {
    const fonts = await this.detectAvailableFonts();
    
    return {
      'sans-serif': fonts.filter(f => f.category === 'sans-serif'),
      'serif': fonts.filter(f => f.category === 'serif'),
      'monospace': fonts.filter(f => f.category === 'monospace')
    };
  }

  /**
   * Create preview for font
   * @param {string} fontFamily - Font family name
   * @param {string} text - Preview text
   * @returns {HTMLElement}
   */
  createFontPreview(fontFamily, text = 'The quick brown fox jumps over the lazy dog') {
    const preview = document.createElement('div');
    preview.style.fontFamily = fontFamily;
    preview.style.fontSize = '14px';
    preview.style.padding = '0.5rem';
    preview.style.backgroundColor = '#f5f5f5';
    preview.style.borderRadius = '4px';
    preview.style.marginTop = '0.5rem';
    preview.textContent = text;
    return preview;
  }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
  module.exports = FontDetector;
}
