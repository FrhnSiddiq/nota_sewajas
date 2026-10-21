import './bootstrap';
import './notifications.js'
// Import scanner module (library is loaded via CDN in blade templates)
import './scanner.js'
// Browser-side image compression helper (window.compressImageFile)
import './compressedUpload.js'
// Import Alpine components BEFORE Alpine.start()
import './components/scannerPage.js'
import Alpine from 'alpinejs'
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
window.Alpine = Alpine
window.Swal = Swal;
Alpine.start()