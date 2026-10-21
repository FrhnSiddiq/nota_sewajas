console.log('DEBUG: scannerPage.js dimuat');

// Alpine.js component untuk halaman Scan QR
function scannerPage() {
  console.log('DEBUG: scannerPage() function dipanggil');
  return {
    isScanning: false,

    async init() {
      console.log('DEBUG: Alpine component init() dijalankan');
      // Populate camera dropdown on page load
      if (window.QRScanner && window.QRScanner.getAvailableCameras) {
        console.log('DEBUG: Memanggil QRScanner.getAvailableCameras()');
        await window.QRScanner.getAvailableCameras();
        console.log('DEBUG: QRScanner.getAvailableCameras() selesai');
      }
      // Listen for camera list updates from scanner.js
      window.addEventListener('cameras-updated', (e) => {
        console.log('DEBUG: Event cameras-updated diterima:', e.detail);
        this.$nextTick(() => {});
      });
      // Listen for scanner state changes from scanner.js
      window.addEventListener('scanner-state-changed', (e) => {
        console.log('DEBUG: Event scanner-state-changed diterima:', e.detail);
        this.isScanning = e.detail.isScanning;
        console.log('DEBUG: this.isScanning di-set ke:', this.isScanning);
      });
    },

    async startCamera() {
      console.log('DEBUG: Alpine startCamera() dipanggil');
      if (window.QRScanner && window.QRScanner.start) {
        await window.QRScanner.start();
        this.isScanning = true;
        console.log('DEBUG: Alpine startCamera() selesai, this.isScanning=', this.isScanning);
      }
    },

    async stopCamera() {
      console.log('DEBUG: Alpine stopCamera() dipanggil');
      if (window.QRScanner && window.QRScanner.stop) {
        await window.QRScanner.stop();
        this.isScanning = false;
        console.log('DEBUG: Alpine stopCamera() selesai, this.isScanning=', this.isScanning);
      }
    },

    async switchCamera() {
      console.log('DEBUG: Alpine switchCamera() dipanggil');
      if (window.QRScanner && window.QRScanner.switch) {
        await window.QRScanner.switch();
      }
    }
  };
}

// Make globally available for x-data="scannerPage()"
window.scannerPage = scannerPage;

console.log('DEBUG: window.scannerPage registered:', typeof window.scannerPage);