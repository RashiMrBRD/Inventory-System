(function () {
  'use strict';

  const state = {
    html5QrcodeScanner: null,
    isScanning: false,
    currentBarcode: null,
    ready: false,
    elements: {}
  };

  const log = (...args) => {
    if (typeof window.debugLog === 'function') {
      window.debugLog(...args);
    } else {
      console.log(...args);
    }
  };

  const warn = (...args) => {
    if (typeof window.debugLog === 'function') {
      window.debugLog(...args);
    } else {
      console.warn(...args);
    }
  };

  const error = (...args) => {
    if (typeof window.debugLog === 'function') {
      window.debugLog(...args);
    } else {
      console.error(...args);
    }
  };

  const ELEMENT_IDS = {
    backdrop: 'scanner-backdrop',
    modal: 'scanner-modal',
    tabCamera: 'scanner-tab-camera',
    tabManual: 'scanner-tab-manual',
    contentCamera: 'scanner-content-camera',
    contentManual: 'scanner-content-manual',
    startBtn: 'scanner-start-btn',
    stopBtn: 'scanner-stop-btn',
    manualInput: 'scanner-manual-input',
    generateBtn: 'scanner-generate-btn',
    resultSection: 'scanner-result',
    detectedCode: 'scanner-detected-code',
    barcodeElement: 'scanner-barcode',
    useBtn: 'scanner-use-btn',
    clearBtn: 'scanner-clear-btn'
  };

  function resolveElements() {
    Object.entries(ELEMENT_IDS).forEach(([key, id]) => {
      state.elements[key] = document.getElementById(id);
    });
  }

  function setActiveTab(active) {
    const { tabCamera, tabManual, contentCamera, contentManual } = state.elements;
    if (!tabCamera || !tabManual || !contentCamera || !contentManual) {
      return;
    }
    if (active === 'camera') {
      tabCamera.classList.add('btn-primary');
      tabCamera.classList.remove('btn-secondary');
      tabManual.classList.remove('btn-primary');
      tabManual.classList.add('btn-secondary');
      contentCamera.classList.remove('hidden');
      contentManual.classList.add('hidden');
    } else {
      tabManual.classList.add('btn-primary');
      tabManual.classList.remove('btn-secondary');
      tabCamera.classList.remove('btn-primary');
      tabCamera.classList.add('btn-secondary');
      contentManual.classList.remove('hidden');
      contentCamera.classList.add('hidden');
    }
    if (state.isScanning) {
      stopScanning();
    }
  }

  function attachHandlers() {
    const { backdrop, tabCamera, tabManual, startBtn, stopBtn, manualInput, generateBtn, useBtn, clearBtn } = state.elements;

    if (backdrop) {
      backdrop.addEventListener('click', closeBarcodeScanner);
    }

    if (tabCamera) {
      tabCamera.addEventListener('click', () => setActiveTab('camera'));
    }

    if (tabManual) {
      tabManual.addEventListener('click', () => setActiveTab('manual'));
    }

    if (startBtn) {
      startBtn.addEventListener('click', startScanning);
    }

    if (stopBtn) {
      stopBtn.addEventListener('click', stopScanning);
    }

    if (generateBtn && manualInput) {
      generateBtn.addEventListener('click', () => {
        const code = manualInput.value.trim();
        if (code) {
          displayBarcode(code);
        } else if (typeof showToast === 'function') {
          showToast('Please enter a barcode number', 'warning');
        }
      });
    }

    if (manualInput && generateBtn) {
      manualInput.addEventListener('keypress', (event) => {
        if (event.key === 'Enter') {
          generateBtn.click();
        }
      });
    }

    if (useBtn) {
      useBtn.addEventListener('click', () => {
        if (!state.currentBarcode) {
          return;
        }
        const barcodeInput = document.getElementById('barcode');
        if (!barcodeInput) {
          return;
        }
        barcodeInput.value = state.currentBarcode;
        if (typeof showToast === 'function') {
          showToast('Barcode added to form', 'success');
        }
        closeBarcodeScanner();
      });
    }

    if (clearBtn) {
      clearBtn.addEventListener('click', clearScanner);
    }
  }

  function initScanner() {
    if (state.ready) {
      return;
    }
    resolveElements();
    attachHandlers();
    state.ready = true;
  }

  function ensureReady() {
    if (!state.ready) {
      initScanner();
    }
  }

  function openBarcodeScanner() {
    ensureReady();
    const { backdrop, modal } = state.elements;
    if (backdrop && modal) {
      backdrop.classList.add('show');
      modal.classList.add('show');
      document.body.style.overflow = 'hidden';
    }
  }

  function closeBarcodeScanner() {
    ensureReady();
    if (state.isScanning) {
      stopScanning();
    }
    const { backdrop, modal } = state.elements;
    if (backdrop && modal) {
      backdrop.classList.remove('show');
      modal.classList.remove('show');
      document.body.style.overflow = '';
      clearScanner();
    }
  }

  function onScanSuccess(decodedText) {
    displayBarcode(decodedText);
    stopScanning();
  }

  function startScanning() {
    ensureReady();
    const lib = window.__Html5QrcodeLibrary__ || {};
    let ctor = window.Html5Qrcode || lib.Html5Qrcode || (lib.default ? lib.default.Html5Qrcode : null);
    let formats = window.Html5QrcodeSupportedFormats || lib.Html5QrcodeSupportedFormats || (lib.default ? lib.default.Html5QrcodeSupportedFormats : null);

    if (!ctor) {
      if (typeof showToast === 'function') {
        showToast('Barcode scanner library is not available. Please reload the page.', 'danger');
      }
      return;
    }

    if (state.html5QrcodeScanner && state.isScanning) {
      stopScanning();
    }

    state.html5QrcodeScanner = new ctor('scanner-reader');
    const config = {
      fps: 10,
      qrbox: { width: 250, height: 250 },
      aspectRatio: 1.0
    };

    if (formats) {
      config.formatsToSupport = [
        formats.QR_CODE,
        formats.AZTEC,
        formats.CODABAR,
        formats.CODE_39,
        formats.CODE_93,
        formats.CODE_128,
        formats.DATA_MATRIX,
        formats.MAXICODE,
        formats.ITF,
        formats.EAN_13,
        formats.EAN_8,
        formats.PDF_417,
        formats.RSS_14,
        formats.RSS_EXPANDED,
        formats.UPC_A,
        formats.UPC_E,
        formats.UPC_EAN_EXTENSION
      ];
    }

    state.html5QrcodeScanner.start(
      { facingMode: 'environment' },
      config,
      onScanSuccess,
      () => {}
    ).then(() => {
      state.isScanning = true;
      if (state.elements.startBtn) {
        state.elements.startBtn.classList.add('hidden');
      }
      if (state.elements.stopBtn) {
        state.elements.stopBtn.classList.remove('hidden');
      }
      if (typeof showToast === 'function') {
        showToast('Camera started. Point at barcode or QR code', 'info');
      }
    }).catch((err) => {
      error('Camera error:', err);
      if (typeof showToast === 'function') {
        showToast('Unable to start camera. Please check permissions.', 'danger');
      }
    });
  }

  function stopScanning() {
    if (!state.html5QrcodeScanner || !state.isScanning) {
      return;
    }

    state.html5QrcodeScanner.stop().then(() => {
      state.isScanning = false;
      if (state.elements.startBtn) {
        state.elements.startBtn.classList.remove('hidden');
      }
      if (state.elements.stopBtn) {
        state.elements.stopBtn.classList.add('hidden');
      }
    }).catch((err) => {
      error('Error stopping scanner:', err);
    });
  }

  function displayBarcode(code) {
    state.currentBarcode = code;
    if (state.elements.detectedCode) {
      state.elements.detectedCode.textContent = code;
    }
    if (state.elements.resultSection) {
      state.elements.resultSection.classList.remove('hidden');
    }
    if (state.elements.barcodeElement) {
      try {
        JsBarcode(state.elements.barcodeElement, code, {
          format: 'CODE128',
          width: 2,
          height: 80,
          displayValue: true,
          fontSize: 14,
          margin: 10
        });
      } catch (err) {
        warn('Invalid barcode format:', err);
        if (typeof showToast === 'function') {
          showToast('Invalid barcode format: ' + err.message, 'danger');
        }
      }
    }
  }

  function clearScanner() {
    state.currentBarcode = null;
    if (state.elements.resultSection) {
      state.elements.resultSection.classList.add('hidden');
    }
    if (state.elements.manualInput) {
      state.elements.manualInput.value = '';
    }
    if (state.elements.barcodeElement) {
      state.elements.barcodeElement.innerHTML = '';
    }
    if (state.elements.detectedCode) {
      state.elements.detectedCode.textContent = '';
    }
  }

  document.addEventListener('DOMContentLoaded', initScanner);

  window.openBarcodeScanner = openBarcodeScanner;
  window.closeBarcodeScanner = closeBarcodeScanner;
  window.startBarcodeScanner = startScanning;
  window.stopBarcodeScanner = stopScanning;
})();
