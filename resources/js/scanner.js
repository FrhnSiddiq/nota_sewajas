// ════════════════════════════════════════════════════════════════
// QR SCANNER - CLEAN VERSION
// Using Html5Qrcode + navigator.mediaDevices.enumerateDevices()
// ════════════════════════════════════════════════════════════════

console.log('[QR Scanner] Module loading...');

// Global state
let scanner = null;
let isScanning = false;
let selectedCameraId = null;
let availableCameras = [];
let scanHistory = [];
const LS_DEVICE_KEY = 'qr_scanner_selected_device_id';

// ════════════════════════════════════════════════════════════════
// LOGGING
// ════════════════════════════════════════════════════════════════

function log(msg, data) {
  const time = new Date().toLocaleTimeString();
  console.log(`[QR Scanner ${time}] ${msg}`, data || '');
}

function logError(msg, err) {
  const time = new Date().toLocaleTimeString();
  console.error(`[QR Scanner ERROR ${time}] ${msg}`, err || '');
}

function logWarn(msg, data) {
  const time = new Date().toLocaleTimeString();
  console.warn(`[QR Scanner WARN ${time}] ${msg}`, data || '');
}

// ════════════════════════════════════════════════════════════════
// BROWSER SUPPORT CHECK
// ════════════════════════════════════════════════════════════════

function checkBrowserSupport() {
  if (!navigator.mediaDevices) {
    logError('navigator.mediaDevices not available');
    return false;
  }
  if (!navigator.mediaDevices.getUserMedia) {
    logError('getUserMedia not available');
    return false;
  }
  log('✓ Browser support OK');
  return true;
}

// ════════════════════════════════════════════════════════════════
// DEVICE ENUMERATION
// ════════════════════════════════════════════════════════════════

async function enumerateVideoDevices() {
  try {
    const devices = await navigator.mediaDevices.enumerateDevices();
    const videoDevices = devices.filter(device => device.kind === 'videoinput');
    log(`Found ${videoDevices.length} video input device(s)`, videoDevices);
    return videoDevices;
  } catch (err) {
    logError('Error enumerating devices:', err);
    return [];
  }
}

const VIRTUAL_KEYWORDS = ['virtual', 'obs', 'snap camera', 'manycam', 'xsplit', 'camo', 'epoccam'];

function isLikelyVirtualCamera(label) {
  if (!label) return false;
  const lower = label.toLowerCase();
  return VIRTUAL_KEYWORDS.some(keyword => lower.includes(keyword));
}

function detectMobile() {
  return /Android|iPhone|iPad|iPod|webOS/i.test(navigator.userAgent) || (navigator.maxTouchPoints > 1 && window.innerWidth < 768);
}

async function populateCameraSelector(videoDevices) {
  const selectorWrapper = document.getElementById('camera-selector-wrapper');
  const selector = document.getElementById('camera-select');
  if (!selectorWrapper || !selector) return;

  availableCameras = videoDevices;

  if (videoDevices.length === 0) {
    selectorWrapper.style.display = 'none';
    return;
  }

  selectorWrapper.style.display = 'block';
  selector.innerHTML = '';

  const savedId = localStorage.getItem(LS_DEVICE_KEY);
  let defaultDeviceId = savedId && videoDevices.some(d => d.deviceId === savedId) ? savedId : null;

  if (!defaultDeviceId) {
    if (detectMobile()) {
      const backCamera = videoDevices.find(d => {
        const label = (d.label || '').toLowerCase();
        return label.includes('back') || label.includes('rear') || label.includes('environment');
      });
      defaultDeviceId = backCamera ? backCamera.deviceId : null;
    }

    if (!defaultDeviceId) {
      const physicalCamera = videoDevices.find(d => !isLikelyVirtualCamera(d.label));
      defaultDeviceId = physicalCamera ? physicalCamera.deviceId : videoDevices[0].deviceId;
    }
  }

  selectedCameraId = defaultDeviceId;

  videoDevices.forEach((device, index) => {
    const option = document.createElement('option');
    option.value = device.deviceId;
    option.textContent = device.label || `Kamera ${index + 1}`;
    if (device.deviceId === selectedCameraId) {
      option.selected = true;
    }
    selector.appendChild(option);
  });

  if (!savedId && defaultDeviceId) {
    localStorage.setItem(LS_DEVICE_KEY, defaultDeviceId);
  }

  saveCachedCameras(videoDevices);

  // Notify Alpine.js that camera list has been updated
  window.dispatchEvent(new CustomEvent('cameras-updated', { detail: { cameras: videoDevices } }));
}

// ════════════════════════════════════════════════════════════════
// GET CAMERAS
// ════════════════════════════════════════════════════════════════

function loadCachedCameras() {
  try {
    const data = localStorage.getItem('cached_cameras');
    if (data) return JSON.parse(data);
  } catch (e) {
    logWarn('Failed to load cached cameras:', e);
  }
  return [];
}

function saveCachedCameras(devices) {
  try {
    localStorage.setItem('cached_cameras', JSON.stringify(devices));
  } catch (e) {
    logWarn('Failed to cache cameras:', e);
  }
}

async function refreshCameraList() {
  let videoDevices = await enumerateVideoDevices();

  if (videoDevices.length === 0) {
    handleError('NO_CAMERA', 'Tidak ada kamera yang terdeteksi. Pastikan kamera terhubung dan izin diberikan di browser.');
    return;
  }

  await populateCameraSelector(videoDevices);
}

async function getAvailableCameras() {
  try {
    if (!window.Html5Qrcode) {
      logError('Html5Qrcode not loaded');
      return [];
    }

    const cached = loadCachedCameras();
    if (cached.length > 0) {
      availableCameras = cached;
      await populateCameraSelector(cached);
      return availableCameras;
    }

    await refreshCameraList();

    if (availableCameras.length === 0) {
      handleError('NO_CAMERA', 'Tidak ada kamera yang terdeteksi. Pastikan kamera terhubung dan izin diberikan di browser.');
      return [];
    }

    return availableCameras;
  } catch (err) {
    logError('Error getting cameras:', err);
    return [];
  }
}

// ════════════════════════════════════════════════════════════════
// LOCAL STORAGE HELPERS
// ════════════════════════════════════════════════════════════════

function saveSelectedCamera(deviceId) {
  try {
    localStorage.setItem(LS_DEVICE_KEY, deviceId);
  } catch (err) {
    logWarn('Could not save camera selection:', err);
  }
}

function loadSavedCamera() {
  try {
    return localStorage.getItem(LS_DEVICE_KEY);
  } catch (err) {
    logWarn('Could not load saved camera:', err);
    return null;
  }
}

// ════════════════════════════════════════════════════════════════
// INITIALIZE SCANNER
// ════════════════════════════════════════════════════════════════

async function initializeScanner() {
  try {
    log('Creating Html5Qrcode instance...');

    scanner = new window.Html5Qrcode('qr-reader');

    log('✓ Scanner instance created');
    log('Scanner type:', typeof scanner);
    log('Scanner.start:', typeof scanner.start);
    log('Scanner.stop:', typeof scanner.stop);
    log('Scanner.clear:', typeof scanner.clear);

    if (typeof scanner.start !== 'function') {
      logError('CRITICAL: scanner.start is not a function');
      logError('scanner object:', scanner);
      return false;
    }

    setupEventListeners();
    log('✓ Event listeners registered');

    return true;

  } catch (err) {
    logError('Failed to initialize scanner:', err);
    return false;
  }
}

// ════════════════════════════════════════════════════════════════
// EVENT LISTENERS
// ════════════════════════════════════════════════════════════════

function setupEventListeners() {
  const cameraSelect = document.getElementById('camera-select');

  // Camera selector change - still handled by scanner.js
  if (cameraSelect) {
    cameraSelect.addEventListener('change', async (e) => {
      const newDeviceId = e.target.value;
      if (!newDeviceId || newDeviceId === selectedCameraId) return;

      selectedCameraId = newDeviceId;
      saveSelectedCamera(newDeviceId);
      log('Camera selector changed to:', newDeviceId);

      if (isScanning) {
        await restartCameraWithDevice(newDeviceId);
      }
    });
    log('✓ camera-select listener added');
  }

  // Button clicks are now handled by Alpine.js @click handlers
  log('✓ Button click handlers delegated to Alpine.js');
}

// ════════════════════════════════════════════════════════════════
// START CAMERA
// ════════════════════════════════════════════════════════════════

async function startCamera() {
  log('═══════════════════════════════════════════');
  log('START CAMERA BUTTON CLICKED');
  log('═══════════════════════════════════════════');

  if (!scanner) {
    logError('Scanner not initialized');
    handleError('NOT_READY', 'Scanner not ready. Refresh page.');
    return;
  }

  if (isScanning) {
    logWarn('Already scanning');
    return;
  }

  try {
    showLoading();

    const cameras = await getAvailableCameras();
    if (cameras.length === 0) {
      hideLoading();
      return;
    }

    if (!selectedCameraId && availableCameras.length > 0) {
      selectedCameraId = availableCameras[0].deviceId;
    }

    if (!selectedCameraId) {
      hideLoading();
      handleError('NO_CAMERA', 'Pilih kamera terlebih dahulu.');
      return;
    }

    log('Starting camera...');
    log('Using camera ID:', selectedCameraId);

    // Wait for container to have dimensions, then calculate proportional qrbox (70% of shorter side)
    const qrbox = await calculateProportionalQrbox();
    log('DEBUG: qrbox config:', qrbox);

    await scanner.start(
      selectedCameraId,
      { fps: 10, qrbox: qrbox },
      onScanSuccess,
      onScanFailure
    );

    log('DEBUG: scanner.start() selesai, memanggil waitForVideoPlaying...');

    // Wait for video element to actually start playing (fixes race condition)
    await waitForVideoPlaying();

    log('DEBUG: waitForVideoPlaying() selesai, isScanning=' + isScanning);

    isScanning = true;
    log('✓✓✓ Camera started ✓✓✓');
    updateStatus('active');
    hideLoading();
    showToast('Camera started', 'success');

    // Notify Alpine.js of state change
    log('DEBUG: dispatching scanner-state-changed, isScanning=' + isScanning);
    window.dispatchEvent(new CustomEvent('scanner-state-changed', { detail: { isScanning: true } }));

  } catch (err) {
    logError('Error starting camera:', err);
    handleError('START_ERROR', `Camera error: ${err.message}`);
    hideLoading();
    isScanning = false;
    updateStatus('inactive');
    log('DEBUG: dispatching scanner-state-changed (error), isScanning=' + isScanning);
    window.dispatchEvent(new CustomEvent('scanner-state-changed', { detail: { isScanning: false } }));
  }
}

async function calculateProportionalQrbox() {
  const container = document.getElementById('qr-reader');
  if (!container) {
    logWarn('Container #qr-reader not found, using fallback qrbox 250');
    return { width: 250, height: 250 };
  }

  // Wait for container to have dimensions (max 500ms)
  const startTime = Date.now();
  while (container.offsetWidth === 0 || container.offsetHeight === 0) {
    if (Date.now() - startTime > 500) {
      logWarn('Container dimensions still 0 after 500ms, using fallback qrbox 250');
      return { width: 250, height: 250 };
    }
    await new Promise(r => setTimeout(r, 50));
  }

  const size = Math.floor(Math.min(container.offsetWidth, container.offsetHeight) * 0.7);
  const finalSize = Math.max(200, Math.min(size, 350)); // clamp 200-350
  log('DEBUG: Container size:', container.offsetWidth, 'x', container.offsetHeight, '→ qrbox:', finalSize);
  return { width: finalSize, height: finalSize };
}

async function waitForVideoPlaying() {
  log('DEBUG: waitForVideoPlaying dipanggil');
  return new Promise((resolve) => {
    const checkVideo = () => {
      const video = document.querySelector('#qr-reader video');
      if (video) {
        log('DEBUG: video element ditemukan, readyState=' + video.readyState + ', paused=' + video.paused + ', ended=' + video.ended);
        if (video.readyState >= 2 && !video.paused && !video.ended) {
          log('DEBUG: Video is playing (readyState >= 2)');
          resolve();
          return;
        }
        // Listen for playing event
        video.addEventListener('playing', () => {
          log('DEBUG: video playing event fired');
          resolve();
        }, { once: true });
        // Also listen for loadedmetadata as fallback
        video.addEventListener('loadedmetadata', () => {
          log('DEBUG: video loadedmetadata event fired');
        }, { once: true });
      } else {
        log('DEBUG: video element BELUM ditemukan, cek lagi 100ms');
        // Video element not ready yet, check again
        setTimeout(checkVideo, 100);
      }
    };
    checkVideo();

    // Timeout fallback
    setTimeout(() => {
      logWarn('DEBUG: fallback timeout hit (5 detik), resolving anyway');
      resolve();
    }, 5000);
  });
}

// ════════════════════════════════════════════════════════════════
// STOP CAMERA
// ════════════════════════════════════════════════════════════════

async function stopCamera() {
  log('═══════════════════════════════════════════');
  log('STOP CAMERA BUTTON CLICKED');
  log('═══════════════════════════════════════════');

  if (!scanner || !isScanning) {
    logWarn('Scanner not running');
    return;
  }

  try {
    log('Stopping scanner...');
    await scanner.stop();

    log('Clearing scanner...');
    await scanner.clear();

    // CRITICAL: Explicitly stop all MediaStream tracks (privacy fix)
    await stopMediaStreamTracks();

    isScanning = false;
    log('✓ Scanner stopped');
    updateStatus('inactive');
    showToast('Camera stopped', 'info');
    log('DEBUG: dispatching scanner-state-changed (stop), isScanning=' + isScanning);
    window.dispatchEvent(new CustomEvent('scanner-state-changed', { detail: { isScanning: false } }));

  } catch (err) {
    logError('Error stopping camera:', err);
    // Safety net: try to stop tracks even on error
    await stopMediaStreamTracks();
    isScanning = false;
    updateStatus('inactive');
  }
}

async function stopMediaStreamTracks() {
  try {
    const video = document.querySelector('#qr-reader video');
    if (video && video.srcObject) {
      const stream = video.srcObject;
      if (stream instanceof MediaStream) {
        log('DEBUG: Stopping MediaStream tracks...');
        stream.getTracks().forEach(track => {
          log('DEBUG: Stopping track:', track.kind, track.label);
          track.stop();
        });
        video.srcObject = null;
        log('✓ MediaStream tracks stopped');
      }
    } else {
      logWarn('DEBUG: No video element or srcObject found to stop tracks');
    }
  } catch (err) {
    logError('Error stopping MediaStream tracks:', err);
  }
}

// ════════════════════════════════════════════════════════════════
// RESTART CAMERA WITH NEW DEVICE
// ════════════════════════════════════════════════════════════════

async function restartCameraWithDevice(deviceId) {
  try {
    if (scanner && isScanning) {
      await scanner.stop();
      await scanner.clear();
      // CRITICAL: Stop old MediaStream tracks before starting new device
      await stopMediaStreamTracks();
      isScanning = false;
    }

    const qrbox = await calculateProportionalQrbox();

    await scanner.start(
      deviceId,
      { fps: 10, qrbox: qrbox },
      onScanSuccess,
      onScanFailure
    );

    isScanning = true;
    selectedCameraId = deviceId;
    log('✓ Camera restarted with device:', deviceId);
    updateStatus('active');
    showToast('Camera switched', 'success');
  } catch (err) {
    logError('Error restarting camera:', err);
    // Safety net: try to stop tracks on error
    await stopMediaStreamTracks();
    isScanning = false;
    updateStatus('inactive');
    handleError('SWITCH_ERROR', `Switch failed: ${err.message}`);
  }
}

// ════════════════════════════════════════════════════════════════
// SWITCH CAMERA
// ════════════════════════════════════════════════════════════════

async function switchCamera() {
  log('═══════════════════════════════════════════');
  log('SWITCH CAMERA BUTTON CLICKED');
  log('═══════════════════════════════════════════');

  if (!scanner || availableCameras.length < 2) {
    logWarn('Not enough cameras or scanner not ready');
    return;
  }

  try {
    const currentIdx = availableCameras.findIndex(c => c.deviceId === selectedCameraId);
    const nextIdx = (currentIdx + 1) % availableCameras.length;
    const nextCamera = availableCameras[nextIdx];

    log('Switching to camera:', nextCamera.label);

    selectedCameraId = nextCamera.deviceId;
    saveSelectedCamera(nextCamera.deviceId);

    const cameraSelect = document.getElementById('camera-select');
    if (cameraSelect) {
      cameraSelect.value = nextCamera.deviceId;
    }

    await restartCameraWithDevice(nextCamera.deviceId);

    log('✓ Camera switched to:', nextCamera.label);
    showToast(`Switched to ${nextCamera.label}`, 'success');

  } catch (err) {
    logError('Error switching camera:', err);
    handleError('SWITCH_ERROR', `Switch failed: ${err.message}`);
  }
}

// ════════════════════════════════════════════════════════════════
// QR SCAN CALLBACKS
// ════════════════════════════════════════════════════════════════

function onScanSuccess(decodedText, decodedResult) {
  log('═══════════════════════════════════════════');
  log('QR CODE DETECTED:', decodedText);
  log('═══════════════════════════════════════════');

  handleQRCode(decodedText);
}

function onScanFailure(error) {
  if (!error.includes('NotFound')) {
    logWarn('Scan error:', error);
  }
}

// ════════════════════════════════════════════════════════════════
// HANDLE QR CODE
// ════════════════════════════════════════════════════════════════

async function handleQRCode(qrContent) {
  try {
    if (isScanning) {
      await stopCamera();
    }

    playBeep();

    if (navigator.vibrate) {
      navigator.vibrate([100, 50, 100]);
    }

    showSuccessAnimation();
    addToHistory(qrContent);
    updateResultPanel(qrContent);

    setTimeout(() => {
      redirectToQR(qrContent);
    }, 1000);

  } catch (err) {
    logError('Error handling QR code:', err);
  }
}

function redirectToQR(qrContent) {
  const invoiceRegex = /^(INV|RCPT-INV)(\d{12})$/i;
  const match = qrContent.match(invoiceRegex);

  if (match) {
    const url = `/rentals/scan/${qrContent}`;
    log('Redirecting to:', url);
    window.location.href = url;
  } else if (/^\d+$/.test(qrContent)) {
    const url = `/rentals/scan/${qrContent}`;
    log('Redirecting to (scan id -> scan-result):', url);
    window.location.href = url;
  } else if (qrContent.startsWith('http')) {
    log('Redirecting to:', qrContent);
    window.location.href = qrContent;
  } else {
    logWarn('Unknown QR format:', qrContent);
    showToast('QR format not recognized', 'warning');
  }
}

// ════════════════════════════════════════════════════════════════
// UI HELPERS
// ════════════════════════════════════════════════════════════════

function updateStatus(state) {
  const statusEl = document.getElementById('camera-status');
  if (!statusEl) return;

  if (state === 'active') {
    statusEl.innerHTML = `
      <div class="flex items-center gap-2">
        <span class="inline-block w-3 h-3 rounded-full bg-green-500 animate-pulse"></span>
        <span class="text-sm text-green-600">Kamera Aktif</span>
      </div>
    `;
  } else {
    statusEl.innerHTML = `
      <div class="flex items-center gap-2">
        <span class="inline-block w-3 h-3 rounded-full bg-red-500"></span>
        <span class="text-sm text-red-600">Kamera Tidak Aktif</span>
      </div>
    `;
  }
}

function showLoading() {
  const loader = document.getElementById('camera-loader');
  if (loader) loader.classList.remove('hidden');
}

function hideLoading() {
  const loader = document.getElementById('camera-loader');
  if (loader) loader.classList.add('hidden');
}

function showSuccessAnimation() {
  const reader = document.getElementById('qr-reader');
  if (!reader) return;

  reader.classList.add('ring-2', 'ring-green-500', 'ring-offset-2');
  setTimeout(() => {
    reader.classList.remove('ring-2', 'ring-green-500', 'ring-offset-2');
  }, 500);
}

function playBeep() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();

    osc.connect(gain);
    gain.connect(ctx.destination);

    osc.frequency.value = 800;
    gain.gain.setValueAtTime(0.3, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.2);

    osc.start(ctx.currentTime);
    osc.stop(ctx.currentTime + 0.2);
  } catch (err) {
    logWarn('Could not play beep:', err);
  }
}

function showToast(msg, type = 'info') {
  const toast = document.createElement('div');
  toast.className = `fixed bottom-4 right-4 px-4 py-3 rounded text-white text-sm z-50`;

  if (type === 'error') toast.classList.add('bg-red-500');
  else if (type === 'success') toast.classList.add('bg-green-500');
  else if (type === 'warning') toast.classList.add('bg-yellow-500');
  else toast.classList.add('bg-blue-500');

  toast.textContent = msg;
  document.body.appendChild(toast);

  setTimeout(() => {
    toast.remove();
  }, 3000);
}

function handleError(code, msg) {
  logError(`[${code}] ${msg}`);
  showToast(msg, 'error');
}

function updateResultPanel(qrContent) {
  const container = document.getElementById('scan-result-container');
  if (!container) return;

  container.innerHTML = `
    <div class="text-center py-4">
      <div class="text-green-600 text-lg font-semibold mb-2">✓ QR Terbaca</div>
      <div class="text-sm text-bark bg-cream-warm rounded p-3 break-all">
        ${qrContent}
      </div>
    </div>
  `;
}

function addToHistory(qrContent) {
  scanHistory.unshift({
    content: qrContent,
    timestamp: new Date().toLocaleString()
  });

  if (scanHistory.length > 10) {
    scanHistory.pop();
  }

  try {
    localStorage.setItem('qr_history', JSON.stringify(scanHistory));
  } catch (err) {
    logWarn('Could not save history:', err);
  }
}

// ════════════════════════════════════════════════════════════════
// PAGE INITIALIZATION
// ════════════════════════════════════════════════════════════════

async function initPage() {
  log('═══════════════════════════════════════════');
  log('QR SCANNER READY');
  log('═══════════════════════════════════════════');

  const qrReader = document.getElementById('qr-reader');
  if (!qrReader) {
    log('SKIP: Not on scan page');
    return;
  }

  log('✓ Scanner container found');

  let waitCount = 0;
  while (!window.Html5Qrcode && waitCount < 150) {
    await new Promise(r => setTimeout(r, 100));
    waitCount++;
  }

  if (!window.Html5Qrcode) {
    logError('CRITICAL: Html5Qrcode library not loaded');
    handleError('LIBRARY', 'QR library failed to load. Please refresh the page.');
    return;
  }

  log('✓ Html5Qrcode library detected');
  log('Library version:', window.Html5Qrcode.VERSION || 'unknown');

  if (!checkBrowserSupport()) {
    handleError('BROWSER', 'Browser does not support camera access');
    return;
  }

  const initialized = await initializeScanner();
  if (!initialized) {
    handleError('INIT', 'Failed to initialize scanner');
    return;
  }

  // Populate camera dropdown on page load
  await getAvailableCameras();

  log('✓ Scanner initialized and ready');
  log('════════════════════════════════════════════');
}

// Auto-initialize on page load
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPage);
} else {
  initPage();
}

// Cleanup on page unload
window.addEventListener('beforeunload', async () => {
  if (scanner && isScanning) {
    try {
      await scanner.stop();
      await scanner.clear();
      await stopMediaStreamTracks();
    } catch (err) {
      console.error('Error cleaning up scanner:', err);
    }
  }
});

// Export public API
window.QRScanner = {
  start: startCamera,
  stop: stopCamera,
  switch: switchCamera,
  getAvailableCameras: getAvailableCameras,
  getScanner: () => scanner,
  getStatus: () => ({
    isScanning,
    scanner: scanner ? 'initialized' : 'not initialized',
    cameras: availableCameras.length
  })
};

log('════════════════════════════════════════════');
log('QR Scanner module loaded');
log('Public API available at window.QRScanner');
log('════════════════════════════════════════════');
