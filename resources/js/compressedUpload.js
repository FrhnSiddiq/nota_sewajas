/**
 * Kompresi gambar di browser sebelum upload foto jaminan (KTP/SIM).
 *
 * File > 2MB di-resize maksimal 1920px dan di-encode ulang sebagai JPEG
 * (kualitas 0.8) sehingga aman melewati rule `max:2048` di server tanpa
 * membebani bandwidth admin atau limit PHP shared hosting. File kecil
 * dikembalikan apa adanya (tanpa re-encode lossy kedua).
 *
 * Dipakai dari blade (Alpine) via window.compressImageFile — lihat
 * processFile di resources/views/rentals/create.blade.php & edit.blade.php.
 *
 * Untuk form upload lain gunakan window.compressAndAssign(input, file):
 * kompres bila perlu, sinkronkan hasilnya ke <input type="file">, lalu
 * kembalikan file final untuk preview. Dipakai produk, avatar pengguna,
 * dan settings (QRIS/logo).
 */
const MAX_BYTES = 2 * 1024 * 1024;
const MAX_DIMENSION = 1920;
const JPEG_QUALITY = 0.8;

async function decodeWithOrientation(file) {
    // Cara modern: decode + auto-putar EXIF dalam satu langkah.
    if (typeof createImageBitmap === 'function') {
        try {
            return await createImageBitmap(file, { imageOrientation: 'from-image' });
        } catch (e) {
            // gagal -> lanjut ke fallback di bawah
        }
    }

    // Fallback: render lewat <img>; browser modern sudah menerapkan orientasi EXIF.
    const url = URL.createObjectURL(file);
    try {
        const img = new Image();
        await new Promise((resolve, reject) => {
            img.onload = resolve;
            img.onerror = () => reject(new Error('Gambar tidak dapat dibaca'));
            img.src = url;
        });
        return img;
    } finally {
        URL.revokeObjectURL(url);
    }
}

async function compressImageFile(file) {
    if (!file || !(file.size > MAX_BYTES)) {
        return file; // <= 2MB: tidak perlu di-encode ulang
    }

    const source = await decodeWithOrientation(file);
    try {
        const scale = Math.min(MAX_DIMENSION / Math.max(source.width, source.height), 1);
        const targetW = Math.round(source.width * scale);
        const targetH = Math.round(source.height * scale);

        const canvas = document.createElement('canvas');
        canvas.width = targetW;
        canvas.height = targetH;
        const ctx = canvas.getContext('2d');
        // Latar putih: bagian PNG transparan jangan berubah menjadi hitam di JPEG.
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, targetW, targetH);
        ctx.drawImage(source, 0, 0, targetW, targetH);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY));
        if (!blob || blob.size >= file.size) {
            return file; // kompresi tidak menguntungkan/gagal -> pakai file asli
        }

        const baseName = file.name.replace(/\.[^.]+$/, '') || 'foto';
        return new File([blob], `${baseName}.jpg`, {
            type: 'image/jpeg',
            lastModified: Date.now(),
        });
    } finally {
        if (typeof source.close === 'function') source.close();
    }
}

window.compressImageFile = compressImageFile;

/**
 * Kompres file (bila perlu) lalu tulis hasilnya ke `input` lewat DataTransfer,
 * sehingga file yang benar-benar terkirim = file yang ditampilkan preview.
 * Mengembalikan file final.
 *
 * Aman dipanggil walau bundle belum memuat helper (kompresi dilewati), dan
 * aman bila DataTransfer tidak tersedia (input dibiarkan apa adanya).
 */
async function compressAndAssign(input, file) {
    let finalFile = file;

    if (typeof window.compressImageFile === 'function') {
        try {
            finalFile = await window.compressImageFile(file);
        } catch (e) {
            finalFile = file; // gagal kompresi -> pakai file asli
        }
    }

    if (input) {
        try {
            const dt = new DataTransfer();
            dt.items.add(finalFile);
            input.files = dt.files;
        } catch (e) {
            return file; // DataTransfer tak tersedia -> preview ikut file asli
        }
    }

    return finalFile;
}

window.compressAndAssign = compressAndAssign;
