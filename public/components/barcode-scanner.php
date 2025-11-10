<!-- Barcode Scanner Modal -->
<div class="modal-backdrop" id="scanner-backdrop"></div>

<div class="modal" id="scanner-modal">
  <div class="modal-header">
    <h3 class="modal-title">Scan Barcode / QR Code</h3>
    <button class="modal-close" onclick="closeBarcodeScanner()">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>
  </div>

  <div class="modal-body">
    <!-- Tabs -->
    <div class="flex gap-2 mb-4">
      <button id="scanner-tab-camera" class="btn btn-sm flex-1 btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <circle cx="12" cy="12" r="1" fill="currentColor"/>
        </svg>
        Camera Scan
      </button>
      <button id="scanner-tab-manual" class="btn btn-sm flex-1 btn-secondary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Manual Entry
      </button>
    </div>

    <!-- Camera Scan Content -->
    <div id="scanner-content-camera">
      <div id="scanner-reader" style="width: 100%; max-width: 500px; margin: 0 auto;"></div>
      
      <div class="flex gap-3 mt-4">
        <button id="scanner-start-btn" class="btn btn-primary w-full">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            <circle cx="12" cy="12" r="3" fill="currentColor"/>
          </svg>
          Start Camera
        </button>
        <button id="scanner-stop-btn" class="btn btn-secondary w-full hidden">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <rect x="6" y="6" width="12" height="12" fill="currentColor"/>
          </svg>
          Stop Camera
        </button>
      </div>
    </div>

    <!-- Manual Entry Content -->
    <div id="scanner-content-manual" class="hidden">
      <div class="form-group">
        <label for="scanner-manual-input" class="form-label">Enter Barcode Number</label>
        <input 
          type="text" 
          id="scanner-manual-input" 
          class="form-input" 
          placeholder="Enter barcode number..."
        >
      </div>
      <button id="scanner-generate-btn" class="btn btn-primary w-full">
        Generate Barcode
      </button>
    </div>

    <!-- Result Section -->
    <div id="scanner-result" class="hidden mt-6 pt-6 border-t">
      <div class="form-group">
        <label class="form-label">Detected Code</label>
        <div id="scanner-detected-code" class="form-input bg-secondary font-mono"></div>
      </div>
      
      <div class="form-group">
        <label class="form-label">Barcode Preview</label>
        <div class="flex justify-center p-4 bg-primary border rounded-md">
          <svg id="scanner-barcode"></svg>
        </div>
      </div>

      <div class="flex gap-3">
        <button id="scanner-use-btn" class="btn btn-primary flex-1">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Use This Code
        </button>
        <button id="scanner-clear-btn" class="btn btn-secondary flex-1">Clear</button>
      </div>
    </div>
  </div>
</div>

<!-- External Libraries - Local copies for offline use -->
<?php
if (!function_exists('asset_proxy_url')) {
  function asset_proxy_url(string $rel): string {
    $configPath = __DIR__ . '/../../config/app.php';
    $cfg = file_exists($configPath) ? require $configPath : [];
    $key = $cfg['assets']['signing_key'] ?? (getenv('ASSET_SIGNING_KEY') ?: 'change-me-dev-key');
    $exp = time() + 3600;
    try { $nonce = bin2hex(random_bytes(12)); } catch (\Throwable $e) { $nonce = bin2hex((string)mt_rand()); }
    $b64 = rtrim(strtr(base64_encode($rel), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $rel . '|' . $exp . '|' . $nonce, $key);
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($dir === '/' || $dir === '\\' || $dir === '.') { $dir = ''; }
    $prefix = $dir !== '' ? ('/' . trim($dir, '/') . '/') : '/';
    return $prefix . 'asset.php?d=' . rawurlencode($b64) . '&e=' . $exp . '&n=' . rawurlencode($nonce) . '&s=' . $sig;
  }
}
?>
<script src="<?php echo asset_proxy_url('assets/js/vendor/jsbarcode.min.js'); ?>"></script>
<script src="<?php echo asset_proxy_url('assets/js/vendor/html5-qrcode.min.js'); ?>"></script>

<script>
(function() {
  let html5QrcodeScanner = null;
  let isScanning = false;
  let currentBarcode = null;

  // Elements
  const backdrop = document.getElementById('scanner-backdrop');
  const modal = document.getElementById('scanner-modal');
  const tabCamera = document.getElementById('scanner-tab-camera');
  const tabManual = document.getElementById('scanner-tab-manual');
  const contentCamera = document.getElementById('scanner-content-camera');
  const contentManual = document.getElementById('scanner-content-manual');
  const startBtn = document.getElementById('scanner-start-btn');
  const stopBtn = document.getElementById('scanner-stop-btn');
  const manualInput = document.getElementById('scanner-manual-input');
  const generateBtn = document.getElementById('scanner-generate-btn');
  const resultSection = document.getElementById('scanner-result');
  const detectedCode = document.getElementById('scanner-detected-code');
  const barcodeElement = document.getElementById('scanner-barcode');
  const useBtn = document.getElementById('scanner-use-btn');
  const clearBtn = document.getElementById('scanner-clear-btn');

  // Open scanner
  window.openBarcodeScanner = function() {
    backdrop.classList.add('show');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
  };

  // Close scanner
  window.closeBarcodeScanner = function() {
    if (isScanning) {
      stopScanning();
    }
    backdrop.classList.remove('show');
    modal.classList.remove('show');
    document.body.style.overflow = '';
    clearScanner();
  };

  // Close on backdrop click
  backdrop.addEventListener('click', closeBarcodeScanner);

  // Tab switching
  tabCamera.addEventListener('click', () => {
    tabCamera.classList.remove('btn-secondary');
    tabCamera.classList.add('btn-primary');
    tabManual.classList.remove('btn-primary');
    tabManual.classList.add('btn-secondary');
    contentCamera.classList.remove('hidden');
    contentManual.classList.add('hidden');
    if (isScanning) stopScanning();
  });

  tabManual.addEventListener('click', () => {
    tabManual.classList.remove('btn-secondary');
    tabManual.classList.add('btn-primary');
    tabCamera.classList.remove('btn-primary');
    tabCamera.classList.add('btn-secondary');
    contentManual.classList.remove('hidden');
    contentCamera.classList.add('hidden');
    if (isScanning) stopScanning();
  });

  // Camera scanning
  function onScanSuccess(decodedText) {
    displayBarcode(decodedText);
    stopScanning();
  }

  function startScanning() {
    html5QrcodeScanner = new Html5Qrcode("scanner-reader");
    
    const config = {
      fps: 10,
      qrbox: { width: 250, height: 250 },
      aspectRatio: 1.0,
      // Support ALL barcode and QR code formats
      formatsToSupport: [
        Html5QrcodeSupportedFormats.QR_CODE,
        Html5QrcodeSupportedFormats.AZTEC,
        Html5QrcodeSupportedFormats.CODABAR,
        Html5QrcodeSupportedFormats.CODE_39,
        Html5QrcodeSupportedFormats.CODE_93,
        Html5QrcodeSupportedFormats.CODE_128,
        Html5QrcodeSupportedFormats.DATA_MATRIX,
        Html5QrcodeSupportedFormats.MAXICODE,
        Html5QrcodeSupportedFormats.ITF,
        Html5QrcodeSupportedFormats.EAN_13,
        Html5QrcodeSupportedFormats.EAN_8,
        Html5QrcodeSupportedFormats.PDF_417,
        Html5QrcodeSupportedFormats.RSS_14,
        Html5QrcodeSupportedFormats.RSS_EXPANDED,
        Html5QrcodeSupportedFormats.UPC_A,
        Html5QrcodeSupportedFormats.UPC_E,
        Html5QrcodeSupportedFormats.UPC_EAN_EXTENSION
      ]
    };
    
    html5QrcodeScanner.start(
      { facingMode: "environment" },
      config,
      onScanSuccess,
      onScanFailure
    ).then(() => {
      isScanning = true;
      startBtn.classList.add('hidden');
      stopBtn.classList.remove('hidden');
      showToast('Camera started. Point at barcode or QR code', 'info');
    }).catch(err => {
      console.error('Camera error:', err);
      showToast('Unable to start camera. Please check permissions.', 'danger');
    });
  }
  
  function onScanFailure(error) {
    // Silent fail - scanning continuously
    // Only log if needed for debugging
    // console.warn('Scan error:', error);
  }

  function stopScanning() {
    if (html5QrcodeScanner && isScanning) {
      html5QrcodeScanner.stop().then(() => {
        isScanning = false;
        startBtn.classList.remove('hidden');
        stopBtn.classList.add('hidden');
      }).catch(err => {
        console.error('Error stopping scanner:', err);
      });
    }
  }

  startBtn.addEventListener('click', startScanning);
  stopBtn.addEventListener('click', stopScanning);

  // Manual entry
  generateBtn.addEventListener('click', () => {
    const code = manualInput.value.trim();
    if (code) {
      displayBarcode(code);
    } else {
      showToast('Please enter a barcode number', 'warning');
    }
  });

  manualInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
      generateBtn.click();
    }
  });

  // Display barcode
  function displayBarcode(code) {
    currentBarcode = code;
    detectedCode.textContent = code;
    resultSection.classList.remove('hidden');

    try {
      JsBarcode(barcodeElement, code, {
        format: "CODE128",
        width: 2,
        height: 80,
        displayValue: true,
        fontSize: 14,
        margin: 10
      });
    } catch (err) {
      showToast('Invalid barcode format: ' + err.message, 'danger');
    }
  }

  // Use barcode
  useBtn.addEventListener('click', () => {
    if (currentBarcode) {
      const barcodeInput = document.getElementById('barcode');
      if (barcodeInput) {
        barcodeInput.value = currentBarcode;
        showToast('Barcode added to form', 'success');
        closeBarcodeScanner();
      }
    }
  });

  // Clear
  clearBtn.addEventListener('click', clearScanner);

  function clearScanner() {
    resultSection.classList.add('hidden');
    manualInput.value = '';
    barcodeElement.innerHTML = '';
    detectedCode.textContent = '';
    currentBarcode = null;
  }
})();
</script>

<style>
/* Scanner specific styles */
#scanner-reader video {
  border-radius: var(--radius-lg);
  max-width: 100%;
}

#scanner-modal {
  max-width: 600px;
}

#scanner-barcode {
  max-width: 100%;
  height: auto;
}
</style>
