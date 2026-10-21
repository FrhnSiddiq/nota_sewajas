# BAB III

## HASIL DAN PEMBAHASAN

### 3.1 Gambaran Umum Sewajas

#### 3.1.1 Fungsi Sewajas

Sewajas adalah sistem informasi manajemen rental jas yang dikembangkan untuk mengelola operasional penyewaan jas secara digital. Sistem ini dibangun menggunakan framework Laravel dengan bahasa pemrograman PHP 8.2 dan dirancang untuk mengelola proses rental dari awal hingga selesai.

Fungsi utama Sewajas dapat dijabarkan sebagai berikut:

1. **Manajemen Multi-Cabang**. Sewajas menyimpan cabang dan relasi akses pengguna. Super Admin mengelola data lintas cabang. Admin Toko wajib memiliki cabang utama, tetapi kode saat ini menetapkan `branch_access_type = all` untuk role ini; karena itu pembatasan data per cabang tidak dapat diasumsikan berlaku pada semua query. Scope aktual mengikuti middleware, route, dan query masing-masing fitur.

2. **Manajemen Customer**. Sistem menyimpan data pelanggan yang meliputi nama, nomor telepon, email, alamat, ukuran badan (tinggi, berat, ukuran jas, ukuran kemeja, ukuran celana, ukuran sepatu), foto, dan data identitas (NIK KTP/SIM). Setiap customer diasosiasikan dengan satu cabang dan opsional dengan satu user sales yang membuatnya. Terdapat fitur blacklist untuk mencegah customer tertentu melakukan rental.

3. **Manajemen Produk**. Produk dikelola dengan kode otomatis (PRD-XXX), nama, kategori, warna, merek, harga sewa, foto, dan QR code. Ukuran dan jumlah stok per ukuran disimpan di `product_sizes`; kolom stok pada `products` merupakan agregat yang disinkronkan. Produk juga memiliki relasi cabang melalui `product_branch`.

4. **Manajemen Rental**. Fitur inti sistem ini adalah pengelolaan transaksi rental. Proses dimulai dari pembuatan rental baru, pemilihan customer, pemilihan produk, penetapan jaminan, generate nomor invoice, generate QR code, proses pembayaran, serah terima barang, monitoring status rental, hingga pengembalian barang.

5. **Manajemen Pembayaran**. Setiap rental dapat memiliki satu atau lebih pembayaran dengan metode cash, transfer, QRIS, atau lainnya. Sistem mendukung pembayaran parsial, void pembayaran, refund, dan tracking status pembayaran (unpaid, partial, paid).

6. **Manajemen Jaminan**. Sistem mencatat jaminan yang diberikan customer dalam bentuk KTP, SIM, deposit uang, atau jaminan custom. Setiap jaminan memiliki status held, returned, atau forfeited.

7. **Sistem Broadcast**. Sewajas memiliki sistem komunikasi massal yang dapat mengirim pesan melalui dua channel: notifikasi in-app dan WhatsApp. Campaign dapat ditargetkan berdasarkan cabang, sales, status rental, produk, kategori, atau customer tertentu.

8. **Sistem Points**. Sales mendapatkan poin insentif dari setiap penyerahan barang (3 poin) dan pengembalian barang (5 poin). Poin ini ditampilkan dalam bentuk leaderboard.

9. **Laporan dan Export**. Sistem menyediakan laporan pendapatan, transaksi, pengembalian, stok, customer, pembayaran, dan activity log. Laporan operasional utama menyediakan export Excel/CSV sesuai implementasinya dan PDF; export activity log tersedia sebagai CSV dan PDF, bukan seluruh format yang sama untuk semua laporan.

10. **Pencarian Global**. Fitur pencarian memungkinkan pengguna mencari customer, rental, dan produk secara bersamaan dalam satu antarmuka.

11. **Activity Log**. Aksi penting dicatat ke `activity_logs`. Saat record berhasil dibuat, event model meminta `ActivityLogFileService` menulis salinan file; kegagalan penulisan file dilaporkan ke Laravel log. Ini tidak menjamin kegagalan penulisan record database selalu dapat diabaikan.

12. **Notifikasi In-App**. Bell menampilkan jumlah notifikasi belum dibaca dan daftar milik pengguna; tersedia aksi baca, tandai semua dibaca, hapus, serta halaman notifikasi.

13. **Logout**. Proses keluar meminta konfirmasi modal; pembatalan tidak mengirim request dan persetujuan memakai route POST/CSRF yang sudah ada.

#### 3.1.2 Manfaat Sewajas

Manfaat Sewajas dapat dilihat dari dua perspektif, yaitu manfaat untuk pengguna internal (pengelola rental) dan manfaat untuk proses operasional.

Bagi pengelola rental, Sewajas memberikan manfaat sebagai berikut:

1. **Otomatisasi Proses Rental**. Proses rental yang sebelumnya manual dapat dilakukan secara digital, mulai dari input customer, pemilihan produk, pembuatan invoice, pembayaran, hingga pengembalian. Hal ini mengurangi waktu proses dan risiko kesalahan manusia.

2. **Pencegahan Konflik Stok**. Sewajas menggunakan mekanisme pessimistic locking (`lockForUpdate()`) pada transaksi database untuk mencegah race condition. Hal ini memastikan bahwa dua transaksi tidak dapat melakukan booking pada produk yang sama secara bersamaan, sehingga stok tetap akurat.

3. **Transparansi Keuangan**. Setiap pembayaran tercatat dengan detail nomor pembayaran, metode, waktu, dan petugas yang menerima. Sistem juga menghitung otomatis denda keterlambatan dan denda kerusakan. Laporan keuangan dapat diekspor dalam format Excel dan PDF.

4. **Audit Trail**. Aksi penting dicatat pada activity log database. Setelah record tercipta, aplikasi mencoba menulis salinan file; kegagalan file dilaporkan ke Laravel log tanpa membatalkan aksi hanya karena penulisan file gagal. Kegagalan saat membuat record database tetap mengikuti transaksi/penanganan pada alur pemanggil.

5. **Notifikasi Otomatis**. Sistem dapat mengirim reminder pengembalian melalui WhatsApp dan menampilkan notifikasi in-app untuk berbagai event seperti rental baru, pembayaran diterima, dan barang terlambat dikembalikan.

6. **Skema Poin untuk Sales**. Sistem poin memberi insentif kepada Sales atas pembayaran pertama rental dan pemrosesan pengembalian.

7. **Pengelolaan Multi-Cabang**. Super Admin dapat mengelola beberapa cabang. Admin Toko memiliki cabang utama yang dipilih saat akun dibuat atau diedit. Namun, implementasi menyetel akses Admin Toko menjadi `all`; scope query perlu dibaca per fitur dan saat ini tidak boleh digambarkan sebagai isolasi yang konsisten ke satu cabang.

Bagi pelanggan (customer), Sewajas memberikan manfaat berupa:

1. **Penyewaan yang Terstruktur**. Customer mendapatkan invoice resmi dengan QR code dan detail transaksi yang jelas.

2. **Komunikasi yang Efektif**. Customer dapat menerima notifikasi dan reminder melalui WhatsApp.

3. **Data yang Tercatat**. Setiap transaksi customer tercatat dalam sistem, sehingga riwayat penyewaan dapat dilacak kapan saja.

#### 3.1.3 Target Pengguna

Sewajas dirancang untuk digunakan oleh empat role pengguna yang berbeda. Keempat role ini dikelola melalui kolom `role` pada tabel `users` dengan nilai `super_admin`, `admin_toko`, `sales`, dan `customer`.

Role ini memegang peran penting dalam sistem karena menentukan tingkat akses dan fitur yang dapat digunakan. Setiap role memiliki dashboard, menu, dan hak akses yang berbeda sesuai dengan tanggung jawab operasionalnya. Penjelasan lebih detail mengenai masing-masing role akan dibahas pada subbab 3.5.

Platform yang digunakan adalah aplikasi web (web-based) yang dapat diakses melalui browser. Sistem ini dibangun dengan framework Laravel yang berjalan di sisi server (backend) dan dilengkapi dengan antarmuka pengguna yang menggunakan Tailwind CSS untuk styling dan Alpine.js untuk interaktivitas di sisi klien.

---

### 3.2 Perancangan Sistem

#### 3.2.1 Arsitektur Sistem

Sewajas dibangun menggunakan arsitektur Model-View-Controller (MVC) yang merupakan standar framework Laravel. Arsitektur ini memisahkan logika bisnis, presentasi, dan routing menjadi komponen-komponen yang terstruktur.

Arsitektur sistem dapat dijabarkan sebagai berikut:

1. **Layer Presentasi (View)**. Antarmuka pengguna dibangun menggunakan Blade template engine dari Laravel dengan styling Tailwind CSS v4.0.0 dan interaktivitas Alpine.js v3.15.12. Setiap halaman view memiliki layout utama (`resources/views/Layouts/app.blade.php`) yang menyediakan struktur sidebar, navbar, dan area konten. Sidebar menampilkan menu-menu yang disesuaikan berdasarkan role pengguna.

2. **Layer Kontrol (Controller)**. Setiap fitur memiliki controller dedicated yang menangani request dari pengguna, memvalidasi input, memanggil logic bisnis di layer service, dan mengembalikan response berupa view atau JSON. Controller-controller utama antara lain `RentalController`, `CustomerController`, `ProductController`, `ReportController`, `DashboardController`, `BroadcastCampaignController`, dan lain sebagainya.

3. **Layer Bisnis (Service)**. Logika bisnis yang kompleks dipisahkan ke dalam service class agar controller tetap ringkas. Service yang utama adalah `RentalService` yang menangani seluruh logika rental, pembayaran, pengembalian, dan generate invoice/QR. `DashboardService` menangani agregasi data untuk dashboard. `PaymentService` menangani operasi pembayaran. `ActivityLogFormatter` dan `ActivityLogFileService` menangani logging aktivitas.

4. **Layer Data (Model)**. Setiap tabel database memiliki model Eloquent yang sesuai. Model ini tidak hanya menjadi representasi tabel, tetapi juga mengandung accessor, mutator, scope, dan relationship yang memudahkan pengambilan data relasional. Contohnya, model `Rental` memiliki hubungan dengan `Customer`, `Product`, `Payment`, `Guarantee`, `ActivityLog`, dan banyak lagi.

5. **Layer Routing**. Semua route didefinisikan di `routes/web.php`. Route ini mengorganisir akses berdasarkan middleware autentikasi dan otorisasi. Setiap route group dilindungi oleh middleware `auth` untuk memastikan pengguna sudah login, `EnsureBranchScope` untuk memastikan scope cabang, dan `CheckRole` untuk memastikan role yang diizinkan.

6. **Layer Database**. Sewajas menggunakan MySQL sebagai database utama. Konfigurasi database terdapat di `config/database.php` dengan driver default MySQL. Sistem ini memiliki 50+ migration files yang merefleksikan evolusi schema database seiring pengembangan.

#### 3.2.2 Teknologi yang Digunakan

Teknologi yang digunakan dalam pengembangan Sewajas dapat dikelompokkan menjadi backend, frontend, dan library pendukung.

**Backend:**
- **PHP 8.2+**. Bahasa pemrograman backend.
- **Laravel 12.62.0**. Framework PHP yang menjadi fondasi sistem. Laravel menyediakan fitur-fitur seperti Eloquent ORM, routing, middleware, session management, dan banyak lagi.
- **MySQL/MariaDB**. Database relasional untuk menyimpan seluruh data sistem.

**Frontend:**
- **Tailwind CSS v4.0.0**. Framework CSS utility-first untuk styling antarmuka.
- **Alpine.js v3.15.12**. Library JavaScript untuk interaktivitas ringan di sisi klien, digunakan untuk dropdown, modal, theme toggle, form handling, dan state management.
- **Vite v7.0.7**. Build tool untuk mengelola aset frontend.
- **Laravel Vite Plugin v2.0.0**. Plugin untuk integrasi Vite dengan Laravel.

**Library Pendukung:**
- **DomPDF (barryvdh/laravel-dompdf) v3.1.5**. Library untuk generate dokumen PDF, digunakan untuk export laporan dan struk pembayaran.
- **Maatwebsite Excel**. Library export spreadsheet yang digunakan pada export customer; beberapa export laporan lain disajikan sebagai CSV melalui streaming.
- **Simple QR Code (simplesoftwareio/simple-qrcode) v4.2.0**. Library untuk generate QR code, digunakan untuk invoice dan produk.
- **Bacon QR Code v2.0.8**. Library pendukung untuk QR code generation.
- **Carbon v3.13.0**. Library untuk manipulasi tanggal dan waktu, digunakan untuk perhitungan overdue days, late fee, dan format tanggal.
- **Guzzle HTTP v7.12.3**. HTTP client, digunakan untuk integrasi dengan WhatsApp API melalui external worker.
- **Symfony Mailer v7.4.12**. Library untuk pengiriman email. Saat ini menggunakan log driver, artinya email tidak benar-benar terkirim namun dicatat dalam log.
- **html5-qrcode v2.3.4**. Library JavaScript untuk scan QR code menggunakan kamera perangkat, digunakan pada halaman scan QR rental.

**Ikon dan Grafik:**
- **Lucide Icons**. Library ikon SVG yang digunakan di seluruh antarmuka.
- **Chart.js**. Library untuk menampilkan grafik interaktif pada dashboard.

#### 3.2.3 Struktur Folder/Struktur Project

Struktur folder Sewajas mengikuti konvensi standar Laravel dengan beberapa penyesuaian sesuai kebutuhan proyek. Berikut adalah struktur utama yang relevan untuk BAB III:

```
rental-jas-main/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   └── AuthenticatedSessionController.php
│   │   │   ├── RentalController.php
│   │   │   ├── RentalReceiptController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── CustomerController.php
│   │   │   ├── ProductController.php
│   │   │   ├── ReportController.php
│   │   │   ├── BroadcastCampaignController.php
│   │   │   ├── BroadcastController.php
│   │   │   ├── PaymentController.php
│   │   │   ├── TransactionController.php
│   │   │   ├── UserController.php
│   │   │   ├── BranchController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── ProfileController.php
│   │   │   ├── SearchController.php
│   │   │   ├── PointsController.php
│   │   │   ├── SettingsController.php
│   │   │   ├── NotificationController.php
│   │   │   └── Controller.php
│   │   └── Middleware/
│   │       ├── CheckRole.php
│   │       ├── EnsureBranchScope.php
│   │       └── EnsureBranchAccess.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Customer.php
│   │   ├── Branch.php
│   │   ├── Category.php
│   │   ├── Product.php
│   │   ├── ProductSize.php
│   │   ├── Rental.php
│   │   ├── RentalItem.php
│   │   ├── RentalReturn.php
│   │   ├── Payment.php
│   │   ├── Guarantee.php
│   │   ├── Laundry.php
│   │   ├── ActivityLog.php
│   │   ├── Notification.php
│   │   ├── Chat.php
│   │   ├── Message.php
│   │   ├── StatusHistory.php
│   │   └── Broadcast/
│   │       ├── BroadcastCampaign.php
│   │       ├── BroadcastLog.php
│   │       ├── BroadcastTemplate.php
│   │       ├── BroadcastProviderConfig.php
│   │       └── BroadcastSchedule.php
│   └── Services/
│       ├── RentalService.php
│       ├── RentalReceiptService.php
│       ├── PaymentService.php
│       ├── DashboardService.php
│       ├── TransactionService.php
│       ├── NotificationService.php
│       ├── ActivityLogFormatter.php
│       └── ActivityLogFileService.php
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 2026_05_15_014234_create_branches_table.php
│   │   ├── 2026_05_15_014353_create_customers_table.php
│   │   ├── 2026_05_15_014526_create_categories_products_table.php
│   │   ├── 2026_05_15_014606_create_rental_table.php
│   │   ├── 2026_05_15_014707_create_logs_notifications_table.php
│   │   ├── 2026_06_05_145146_create_rental_returns_table.php
│   │   ├── 2026_06_05_150017_add_rental_return_and_settings_features.php
│   │   ├── 2026_06_20_094406_create_chats_table.php
│   │   ├── 2026_08_21_112856_create_product_branch_table.php
│   │   ├── 2026_08_22_182200_replace_commission_with_points.php
│   │   └── ... (50+ migration files)
│   └── config/
├── resources/
│   └── views/
│       ├── Layouts/
│       │   └── app.blade.php
│       ├── auth/
│       │   └── login.blade.php
│       ├── dashboard/
│       │   ├── _dashboard_common.blade.php
│       │   ├── super-admin.blade.php
│       │   ├── admin.blade.php
│       │   └── sales.blade.php
│       ├── rentals/
│       ├── customers/
│       ├── products/
│       ├── categories/
│       ├── branches/
│       ├── users/
│       ├── transactions/
│       ├── reports/
│       ├── broadcast_campaigns/
│       ├── search/
│       ├── points/
│       ├── settings/
│       ├── profile/
│       ├── notifications/
│       └── components/
├── routes/
│   └── web.php
├── config/
│   └── database.php
├── composer.json
├── package.json
├── vite.config.js
└── railway.json
```

Struktur folder ini menunjukkan bahwa Sewajas mengadopsi pola MVC dengan pemisahan yang jelas antara layer controller, model, dan view. Setiap fitur utama memiliki controller, model, dan viewnya masing-masing. Service layer digunakan untuk menampung logika bisnis yang kompleks agar controller tetap fokus menangani HTTP request dan response.

---

### 3.3 Alur Sistem

#### 3.3.1 Flowchart Sistem

Berikut adalah flowchart alur sistem Sewajas dari tahap pengguna membuka aplikasi hingga logout:

```
START
  ↓
[User Membuka Website] → Redirect ke Halaman Login
  ↓
[Login] → Validasi Email dan Password
  ↓
[Session Dibuat] → Redirect ke Dashboard
  ↓
[Cek Role User]
  ↓
  ├─→ SUPER ADMIN → Dashboard Super Admin
  │   ↓
  │   [Akses: Semua cabang, kelola user, kelola cabang, kelola kategori, settings]
  │   ↓
  │   [Menu: Dashboard, Customer, Product, Rental, Report, Broadcast, Settings, dll]
  │
  ├─→ ADMIN TOKO → Dashboard Admin
  │   ↓
  │   [Akun harus memiliki cabang utama; scope data mengikuti branch_access_type dan query tiap fitur]
  │   ↓
  │   [Menu: Dashboard, Customer, Product, Rental, Report, Broadcast]
  │
  ├─→ SALES → Dashboard Sales
  │   ↓
  │   [Akses: Data pribadi, customer yang dibuat, rental yang dibuat]
  │   ↓
  │   [Menu: Dashboard, Buat Sewa, Pelanggan Saya, Scan QR, Transaksi Saya]
  │
  └─→ CUSTOMER → Tidak ada dashboard
      ↓
      [Data customer hanya sebagai master data dalam rental]
      ↓
[Proses Operasional]
  ↓
  ├─→ [Manajemen Customer] → Tambah/Lihat/Update sesuai role/Blacklist/Restore/Export
  ├─→ [Manajemen Produk] → Tambah/Edit sesuai role/Update Stok per Ukuran/QR Code
  ├─→ [Penyewaan] → Buat Sewa → Pilih Customer → Pilih Produk → Pilih Jaminan
  │   ↓
  │   [Generate Invoice + QR Code]
  │   ↓
   │   [Proses Pembayaran] → Cash/Transfer/QRIS
   │   ↓
   │   [Serah Terima] → Status: Active
   │   ↓
   │   DUA CABANG PARALEL:
   │   (a) Monitor Jatuh Tempo -> cek overdue -> kirim reminder WA
   │       (berjalan terus, tidak menghalangi cabang b)
   │   (b) Proses Pengembalian (bisa dipicu kapan saja) -> cek kondisi
   │       barang -> hitung denda (keterlambatan jika overdue + denda
   │       kerusakan jika rusak/hilang) -> update stok ukuran rental -> status: Returned
   │
   ├─→ [Scan QR] → Lihat Detail Rental → Aksi (Bayar/Kembali/Print)
  ├─→ [Broadcast] → Buat Campaign → Pilih Target → Kirim WhatsApp/In-App
  ├─→ [Notifikasi] → Bell/Unread Count → Baca/Tandai Semua/Hapus → Daftar Notifikasi
  ├─→ [Laporan] → Filter → View → Export sesuai format tiap laporan
  ├─→ [Profile] → Edit Profile/Password/Theme
  └─→ [Keluar] → Modal Konfirmasi
          ├─→ [Batal] → Tutup Modal, Tetap di Halaman
          └─→ [Ya, Keluar] → POST /logout + CSRF → Invalidate Session → Redirect Login
                              ↓
                            END
```

#### 3.3.2 Alur Proses Pengguna

Alur proses pengguna diawali dengan pembukaan aplikasi Sewajas melalui browser. Sistem akan melakukan redirect ke halaman login karena route root didefinisikan untuk melakukan redirect ke route `login` (`routes/web.php:23-25`).

Setelah di halaman login, pengguna memasukkan email dan password. Proses autentikasi dilakukan oleh `AuthenticatedSessionController` yang memvalidasi kredensial terhadap tabel `users`. Model menyimpan password dalam bentuk hash melalui cast `hashed`.

Jika kredensial valid, sistem meregenerasi session dan mengarahkan ke dashboard. Jika kredensial tidak valid, sistem menampilkan pesan umum `Email atau password salah.` dan pengguna tetap berada di halaman login.

Setelah login, sistem memeriksa role pengguna yang tersimpan dalam kolom `role` pada tabel `users`. Berdasarkan role tersebut, pengguna diarahkan ke dashboard yang sesuai:
- `super_admin` mengarah ke `DashboardController::superAdminDashboard()`
- `admin_toko` mengarah ke `DashboardController::adminDashboard()`
- `sales` mengarah ke `DashboardController::salesDashboard()`

Pada tahap ini, `EnsureBranchScope` menolak akun nonaktif sebelum request dilanjutkan. Pemeriksaan cabang juga bergantung pada role, `branch_access_type`, route, dan query controller; middleware ini bukan filter otomatis bagi seluruh query.

Setelah berada di dashboard, pengguna dapat mengakses route sesuai role. Aksi penting dicatat pada activity log; pembuatan record database dan penulisan file log merupakan dua tahapan terpisah.

Logout menggunakan modal konfirmasi custom dengan judul `Keluar dari Sistem`, pesan `Apakah Anda yakin ingin keluar dari web ini?`, dan tombol `Batal`/`Ya, Keluar`. Pilihan `Batal` menutup modal tanpa request logout. Pilihan `Ya, Keluar` mengirim form `POST /logout` dengan CSRF; controller autentikasi yang ada mengeluarkan user, menginvalidasi session, meregenerasi token, lalu mengarahkan ke login.

#### 3.3.3 Alur Proses Rental

Proses rental adalah alur bisnis utama Sewajas. Berikut adalah tahapan-tahapan yang terjadi:

**Tahap 1: Pembuatan Rental**

Pengguna dengan role yang diizinkan mengakses halaman buat rental melalui route `rentals.create`. Sistem memuat customer dan produk sesuai scope query dan cabang yang dipilih. Ketersediaan rental diperiksa terhadap ukuran produk yang dipilih; stok agregat `products.stock_available` bukan satu-satunya dasar pemilihan ukuran.

Pengguna memilih customer dari dropdown atau membuat customer baru melalui modal inline. Kemudian pengguna memilih produk yang akan disewa beserta jumlah dan ukuran. Setiap kali produk dipilih, sistem melakukan validasi stok.

Setelah semua produk dipilih, pengguna memilih jenis jaminan (KTP, SIM, Deposit Uang, atau Custom). Jika memilih KTP atau SIM, sistem meminta upload foto identitas. Jika memilih Deposit Uang, sistem meminta input jumlah deposit.

Setelah form diisi, pengguna menekan tombol submit. Sistem melakukan proses berikut dalam satu transaksi database (`DB::transaction`):

1. Mengambil produk dan baris ukuran yang dipilih, lalu mengunci data terkait selama transaksi.
2. Memvalidasi bahwa semua produk berasal dari cabang yang sama.
3. Menghitung subtotal: `Σ(rental_price × quantity × duration_days)`.
4. Menghitung total amount: `subtotal - discount`.
5. Menghasilkan nomor invoice unik dalam format `INV{YYYYMMDD}{BB}{SSSS}`. Proses generasi nomor invoice menggunakan mekanisme retry dengan `lockForUpdate()` untuk menghindari collision.
6. Membuat record rental baru dengan status `waiting` dan payment_status `unpaid`.
7. Membuat record `rental_items` untuk setiap produk/ukuran yang disewa.
8. Mengurangi stok hanya pada `product_sizes` yang dipilih, kemudian menyinkronkan agregat stok produk.
9. Membuat record `guarantee` jika ada jaminan.
10. Generate QR code untuk invoice dan simpan di `storage/app/public/qrcodes/rentals/{invoice}.svg`.
11. Mencatat activity log dengan action `create_rental`.

**Tahap 2: Pembayaran**

Setelah rental dibuat, pengguna dapat memproses pembayaran melalui halaman detail rental. Sistem mendukung multiple payments per rental. Metode pembayaran yang tersedia adalah cash, transfer, dan QRIS.

Proses pembayaran dilakukan oleh `RentalService::processPayment()`:
1. Membuat record `payments` dengan payment number dalam format `PAY-YYYYMMDDSSSS`.
2. Menambahkan amount ke `paid_amount`.
3. Menghitung ulang `payment_status` berdasarkan perbandingan `paid_amount` dan `total_amount`.
4. Menghitung `remaining_amount`, `change_amount`, dan `fine_status`.
5. Memberikan 3 poin kepada Sales pembuat rental ketika pembayaran pertama diproses, dengan flag agar tidak diberikan berulang.

**Tahap 3: Serah Terima**

Setelah pembayaran dilakukan, pengguna dapat melakukan serah terima barang. Proses ini mengubah status rental dari `waiting` menjadi `active`. Sistem mencatat activity log dengan action `handover_rental`.

Jika rental belum dibayar sekalipun, serah terima tetap dapat dilakukan, tetapi status payment akan tetap `unpaid` atau `partial`.

**Tahap 4: Monitoring dan Overdue**

Selama rental aktif, sistem melakukan pengecekan overdue secara manual melalui `RentalService::updateOverdueRentals()`. Rental yang melebihi `return_due_date` dan masih berstatus `active` akan diubah statusnya menjadi `overdue`.

Dashboard menampilkan reminder untuk rental yang jatuh tempo hari ini dan rental yang terlambat. Sistem juga dapat mengirim reminder pengembalian melalui WhatsApp menggunakan `RentalController::sendReminder()`.

**Tahap 5: Pengembalian**

Pengembalian barang dilakukan melalui halaman detail rental. Pengguna memilih kondisi setiap item yang dikembalikan:
- `baik`: barang dalam kondisi baik
- `rusak_ringan`: barang mengalami kerusakan ringan
- `rusak_berat`: barang mengalami kerusakan berat
- `hilang`: barang hilang

Jika kondisi barang rusak atau hilang, sistem meminta input denda kerusakan (`damage_fee`).

Proses pengembalian dilakukan oleh `RentalService::processReturn()`:
1. Menghitung overdue days jika rental terlambat: `return_due_date.diffInDays(now())`.
2. Menghitung late fee: `(subtotal / duration_days) × overdue_days × 0.5`.
3. Mengumpulkan total damage fee dari semua item yang rusak/hilang.
4. Mengupdate setiap `rental_item`: `is_returned = true`, `return_condition`, `damage_fee`, `returned_at`.
5. Mengembalikan stok pada ukuran rental yang sama di `product_sizes`, lalu menyinkronkan agregat stok produk.
6. Mengupdate status guarantee menjadi `returned`.
7. Mengupdate rental: `rental_status = returned`, `actual_return_date`, `returned_at`, `returned_by`, `late_fee`, `fine_amount`, `fine_status`.
8. Menghitung ulang payment status.
9. Memberikan poin sales (5 poin) untuk pengembalian.

#### 3.3.4 Alur Pembayaran dan Pengembalian

**Alur Pembayaran:**

Setiap rental dapat memiliki satu atau lebih pembayaran. Proses pembayaran dimulai ketika pengguna mengakses halaman detail rental dan mengisi form pembayaran dengan jumlah, metode, dan jenis pembayaran (rental, deposit, late_fee, damage_fee).

Sistem melakukan validasi jumlah pembayaran harus lebih besar dari 0. Setelah pembayaran dibuat, sistem mencatat:
- `payment_number` dalam format `PAY-YYYYMMDDSSSS`
- `amount` yang dibayar
- `method` (cash/transfer/qris/other)
- `type` (rental/deposit/late_fee/damage_fee/refund)
- `paid_at` (waktu pembayaran)
- `received_by` (user yang menerima pembayaran)

Sistem kemudian menghitung ulang status pembayaran:
- Jika `paid_amount >= total_amount`: status menjadi `paid`
- Jika `paid_amount > 0` tapi kurang dari total: status menjadi `partial`
- Jika belum ada pembayaran: status tetap `unpaid`

Sistem juga menghitung `fine_status` secara terpisah:
- Jika `fine_amount <= 0`: status denda menjadi `none`
- Jika total pembayaran >= total tagihan + denda: status denda menjadi `paid`
- Jika ada pembayaran untuk denda tapi belum lunas: status denda menjadi `partial`
- Jika belum ada pembayaran untuk denda: status denda menjadi `unpaid`

**Alur Pengembalian:**

Pengembalian adalah proses penutupan rental. Setelah barang dikembalikan dan kondisi dicatat, sistem menghitung denda keterlambatan dan denda kerusakan. Jika ada denda, sistem akan menunggu pembayaran denda sebelum menyatakan rental sepenuhnya selesai.

Proses pengembalian mengubah stok ukuran yang tercatat pada item rental dan menghitung ulang agregat produk. Alur aktif menyimpan data pengembalian pada `rentals` dan `rental_items`, bukan membuat record baru pada `rental_returns`.

---

### 3.4 Sistem Login dan Registrasi

#### 3.4.1 Sistem Registrasi

Sewajas tidak memiliki sistem registrasi publik. Setiap pengguna sistem harus dibuat oleh Super Admin melalui halaman manajemen user. Proses pembuatan user dilakukan di `UserController::store()` dengan langkah-langkah sebagai berikut:

1. Super Admin mengakses halaman `users.create`.
2. Mengisi form dengan nama, email, password, phone, branch_id, dan role.
3. Password di-hash menggunakan `Hash::make()` sebelum disimpan.
4. Role internal yang dapat dipilih adalah `super_admin`, `admin_toko`, dan `sales`. Role `customer` tidak disediakan pada form user internal; data pelanggan transaksi dikelola terpisah.
5. Avatar dapat diupload dan disimpan di `storage/app/public/avatars`.
6. User dibuat dengan status `is_active = true`.

Validasi yang dilakukan pada pembuatan user meliputi:
- Nama harus diisi dan maksimal 150 karakter.
- Email harus unik, valid format email, dan maksimal 255 karakter.
- Password minimal 8 karakter dan harus dikonfirmasi (confirmed).
- Phone harus numerik dan maksimal 20 digit.
- Cabang utama divalidasi terhadap data cabang. Admin Toko wajib memilih `branch_id`; Sales menggunakan cabang spesifik dan relasi akses cabang.
- Role harus salah satu dari `super_admin`, `admin_toko`, atau `sales`.

#### 3.4.2 Sistem Login

Halaman login Sewajas berada di route `GET /login` yang ditangani oleh `AuthenticatedSessionController::create()`. Halaman ini menggunakan Tailwind CDN secara standalone tanpa melalui layout utama, sehingga dapat dimuat dengan cepat.

Form login meminta pengguna memasukkan:
- **Email**: harus sesuai dengan yang tersimpan di tabel `users`.
- **Password**: diverifikasi terhadap hash yang tersimpan.
- **Remember Me**: opsional, menggunakan `remember_token` pada tabel `users`.

Setelah kredensial divalidasi, sistem meregenerasi session dan mengarahkan pengguna ke dashboard tujuan. `.env.example` menetapkan `SESSION_DRIVER=database`, tetapi driver aktif ditentukan oleh konfigurasi environment runtime. Tabel `sessions` menyediakan penyimpanan session database bila driver tersebut dipilih.

Setelah login berhasil, pengguna diarahkan ke dashboard sesuai dengan role-nya. Jika login gagal, sistem menampilkan pesan umum `Email atau password salah.`

**Gambar 3.1 Tampilan Halaman Login Sewajas**

[Sisipkan Screenshot: Halaman Login Sewajas]

Gambar 3.1 menunjukkan tampilan halaman login Sewajas. Halaman ini menampilkan branding aplikasi dengan logo dan tagline yang dapat dikonfigurasi melalui settings. Form login terdiri dari dua field utama yaitu email dan password, dilengkapi dengan tombol toggle untuk menampilkan/menyembunyikan password. Tombol "Masuk" berfungsi untuk mengirimkan kredensial ke server untuk proses autentikasi. Halaman login juga menampilkan informasi keamanan dan responsivitas aplikasi.

#### 3.4.3 Authentication dan Session

Sewajas menggunakan sistem autentikasi berbasis session yang disediakan oleh Laravel. Konfigurasi autentikasi terdapat di `config/auth.php` dengan guard default `web` yang menggunakan driver `session`.

Contoh konfigurasi menetapkan database session driver; driver yang aktif ditentukan oleh environment runtime:
```
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
```

Tabel `sessions` memiliki struktur sebagai berikut:
- `id`: primary key untuk session
- `user_id`: foreign key ke `users.id` (nullable untuk guest)
- `ip_address`: IP address pengguna
- `user_agent`: user agent browser pengguna
- `payload`: data session yang di-encode
- `last_activity`: timestamp aktivitas terakhir

Password disimpan dalam bentuk hash melalui cast `password => hashed` pada model `User`. Login yang gagal menggunakan pesan umum, bukan membedakan apakah email atau password yang salah.

Tidak terdapat fitur password reset atau email verification dalam sistem Sewajas berdasarkan audit source code. Tidak ada route atau controller yang menangani forgot password atau email verification.

#### 3.4.4 Middleware

Sewajas menggunakan beberapa middleware untuk mengontrol akses ke route-route tertentu.

**Middleware `auth`**
Middleware bawaan Laravel yang memastikan pengguna sudah login sebelum mengakses route. Jika belum login, pengguna diarahkan ke halaman login.

**Middleware `guest`**
Middleware bawaan Laravel yang memastikan pengguna belum login sebelum mengakses route. Digunakan pada route login agar pengguna yang sudah login tidak dapat mengakses halaman login kembali.

**Middleware `CheckRole`**
Middleware kustom yang memeriksa apakah role pengguna termasuk dalam daftar role yang diizinkan untuk mengakses route tertentu. Middleware ini didefinisikan di `app/Http/Middleware/CheckRole.php`.

```php
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (!in_array(auth()->user()->role, $roles)) {
            abort(403, 'Akses ditolak.');
        }

        return $next($request);
    }
}
```

Middleware ini digunakan dengan cara menambahkan parameter role yang diizinkan:
```php
->middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko,sales')
```

Artinya route tersebut hanya dapat diakses oleh pengguna dengan role `super_admin`, `admin_toko`, atau `sales`.

**Middleware `EnsureBranchScope`**
Middleware ini menolak akun nonaktif sebelum melanjutkan request: sesi autentikasi dikeluarkan dan pengguna diarahkan ke login. Untuk akun aktif, middleware menerapkan pemeriksaan akses cabang yang didefinisikan model, dengan pengecualian sesuai role dan `branch_access_type`. Pemeriksaan ini tidak dengan sendirinya menambahkan filter cabang pada setiap query; route dan controller juga menentukan scope data. Akun Admin Toko lama tanpa `branch_id` perlu diperbarui melalui form edit user.

Penggunaan middleware ini dapat dilihat pada route group di `routes/web.php`:
```php
Route::middleware(['auth', \App\Http\Middleware\EnsureBranchScope::class])->group(function () {
    // Semua route authenticated
});
```

---

### 3.5 Role dan Hak Akses

Sewajas memiliki empat nilai role yang didefinisikan dalam model `User` pada konstanta `ROLES`:

```php
const ROLES = [
    self::ROLE_SUPER_ADMIN => 'Super Admin',
    self::ROLE_ADMIN_TOKO  => 'Admin Toko',
    self::ROLE_SALES       => 'Sales',
    self::ROLE_CUSTOMER    => 'Customer',
];
```

Keempat role ini memiliki tingkat akses yang berbeda-beda. Berikut adalah penjelasan masing-masing role beserta hak aksesnya berdasarkan implementasi yang ditemukan dalam source code.

Route group `CheckRole` dan pemeriksaan role pada controller menjadi acuan akses aktual. Keberadaan permission seeder/policy tidak berarti setiap fitur menggunakan matrix permission tersebut. Role `customer` ada pada model, tetapi belum memiliki dashboard operasional.

#### 3.5.1 Super Admin

Super Admin adalah role dengan hak akses penuh dalam sistem Sewajas. Super Admin dapat mengakses seluruh fitur tanpa batasan cabang.

**Hak akses yang teridentifikasi:**

1. **Dashboard**: Melihat statistik seluruh cabang, dapat memfilter data berdasarkan cabang dan sales.
2. **Manajemen Cabang**: CRUD cabang (create, read, update, delete).
3. **Manajemen User**: CRUD user internal (super_admin, admin_toko, sales), dapat mengaktifkan/menonaktifkan akun, mengupload avatar.
4. **Manajemen Kategori**: CRUD kategori produk (hanya super admin yang dapat mengakses route kategori).
5. **Manajemen Produk**: CRUD produk, assign produk ke multiple cabang, generate QR code produk, update stok.
6. **Manajemen Customer**: CRUD customer, blacklist, restore, soft delete, export Excel.
7. **Manajemen Rental**: CRUD rental, proses pembayaran, void, refund, pengembalian, cancel rental, scan QR, cetak PDF, kirim WhatsApp.
8. **Manajemen Pembayaran**: CRUD pembayaran, void, refund.
9. **Manajemen Transaksi**: CRUD transaksi (alternatif interface untuk rental).
10. **Broadcast Campaign**: Buat, lihat, kirim, edit pesan, dan hapus campaign broadcast.
11. **Laporan**: Akses laporan sesuai route Super Admin/Admin Toko; format export berbeda menurut laporan. Activity log menyediakan CSV dan PDF.
12. **Settings**: Mengubah pengaturan aplikasi dan perusahaan.

Super Admin mengakses dashboard melalui `DashboardController::superAdminDashboard()` yang menampilkan:
- Statistik seluruh cabang
- Filter cabang dan sales
- Tombol aksi: Kelola Cabang, Kelola Admin, Broadcast, Laporan, Scan QR

#### 3.5.2 Admin Toko

Admin Toko mengelola operasional aplikasi dan harus memiliki satu `branch_id` utama yang dipilih ketika akun dibuat atau diedit. Pada implementasi saat ini, `UserController` menyimpan `branch_access_type = all` untuk Admin Toko, dan accessor `accessible_branch_ids` dapat mengembalikan semua cabang. Karena itu cabang utama tidak sama dengan jaminan bahwa seluruh query Admin Toko dibatasi ke satu cabang. Middleware menolak akun nonaktif dan route memeriksa role; pembatasan per cabang bergantung pada implementasi query fitur.

**Hak akses yang teridentifikasi:**

1. **Dashboard**: Melihat dashboard Admin. Cabang utama tersedia pada akun, tetapi agregasi yang menggunakan `accessible_branch_ids` dapat mencakup seluruh cabang karena akses Admin Toko disetel `all`.
2. **Manajemen Customer**: Melihat dan menambahkan customer; edit dan soft delete tersedia untuk Super Admin/Admin Toko; blacklist dibatasi ke Super Admin. Scope daftar mengikuti query role/cabang.
3. **Manajemen Produk**: Dapat membuat, mengubah, dan menghapus produk sesuai route, serta melihat/memperbarui stok. Stok diperbarui untuk ukuran tertentu.
4. **Manajemen Rental**: Mengelola rental, pembayaran, pengembalian, scan QR dan dokumen sesuai route; edit/cancel dibatasi ke Super Admin/Admin Toko.
5. **Broadcast Campaign**: Dapat mengelola campaign sesuai route; cakupan cabang tidak boleh diasumsikan satu cabang karena akses Admin Toko disetel `all`.
6. **Laporan**: Route laporan mengizinkan Super Admin dan Admin Toko; scope data mengikuti filter/query laporan.

Admin Toko tidak dapat mengakses:
- Manajemen cabang
- Manajemen user
- Manajemen kategori
- Settings global

Admin Toko mengakses dashboard melalui `DashboardController::adminDashboard()` yang menampilkan:
- Statistik sesuai query agregasi dashboard dan scope akses yang dihitung aplikasi
- Filter sales
- Tombol aksi: Tambah Pelanggan, Buat Sewa, Pengembalian, Broadcast, Scan QR

#### 3.5.3 Sales

Sales adalah role operasional yang aksesnya dibatasi melalui route dan query. Akun Sales menggunakan `branch_access_type = specific` serta cabang yang dipilih; sejumlah daftar customer/rental juga dibatasi berdasarkan Sales pembuat.

**Hak akses yang teridentifikasi:**

1. **Dashboard**: Melihat statistik pribadi (rental yang mereka buat, transaksi mereka).
2. **Manajemen Customer**: Melihat dan menambahkan customer yang dikaitkan dengan Sales; update/delete dan blacklist tidak tersedia untuk Sales.
3. **Manajemen Rental**: Membuat rental, melihat rental milik mereka, melakukan pembayaran, pengembalian, scan QR.
4. **Transaksi**: Melihat transaksi mereka sendiri.
5. **Laporan**: Tidak termasuk akses ke route laporan umum; halaman poin menampilkan poin pribadi.

Sales tidak dapat mengakses:
- Manajemen cabang
- Manajemen user
- Membuat/mengedit/menghapus produk; Sales dapat melihat produk dan memperbarui stok per ukuran
- Manajemen kategori
- Broadcast campaign
- Settings
- Route laporan umum

Sales mengakses dashboard melalui `DashboardController::salesDashboard()` yang menampilkan:
- Statistik pribadi
- Tombol aksi: Buat Sewa, Pelanggan Saya, Scan QR, Pengembalian, Transaksi Saya

Pada route `rentals.index`, sales hanya melihat rental yang mereka buat (`created_by = user.id`). Pada route `customers.index`, sales hanya melihat customer yang mereka buat (`user_id = user.id`).

#### 3.5.4 Customer

Role `customer` terdapat pada model `User`, dan terdapat controller/view registrasi yang belum terhubung sebagai alur fungsional: tidak ada route publik `/register`, sementara redirect controller merujuk ke route dashboard customer yang tidak tersedia. `DashboardController` juga tidak menyediakan dashboard customer.

Data customer disimpan dalam tabel `customers` yang terpisah dari tabel `users`. Tabel `customers` memiliki kolom `user_id` yang opsional, yang menghubungkan customer dengan user sales yang membuatnya. Customer digunakan sebagai master data dalam proses rental, bukan sebagai pengguna yang login ke sistem.

Dengan demikian, fitur customer self-service belum dapat dinyatakan tersedia. Data pelanggan pada tabel `customers` dipakai sebagai master data transaksi rental; ini berbeda dari role login `customer` pada tabel `users`.

---

### 3.6 Fitur dan Layanan

#### 3.6.1 Dashboard

Dashboard adalah halaman utama yang ditampilkan setelah pengguna login. Sewajas memiliki tiga dashboard yang berbeda sesuai dengan role pengguna. Ketiga dashboard ini menggunakan template bersama `resources/views/dashboard/_dashboard_common.blade.php` yang menampilkan greeting, filter data, stat cards, charts, dan widgets.

Semua dashboard melakukan polling AJAX setiap 10 detik ke endpoint `/dashboard/data` untuk memperbarui data tanpa reload halaman. Interval tersebut ditentukan oleh kode saat ini; belum ada bukti pengujian beban untuk menyimpulkan dampaknya pada production.

**Data yang ditampilkan pada dashboard meliputi:**
- Statistik customer (total, aktif, baru hari ini, baru minggu ini, baru bulan ini)
- Statistik produk (total, tersedia, disewa, maintenance, hampir habis)
- Statistik rental (total, hari ini, minggu ini, bulan ini, waiting, active, overdue, returned, cancelled)
- Statistik keuangan (pendapatan hari ini, omzet minggu ini, omzet bulan ini, omzet tahun ini, total invoice, invoice belum dibayar, receipt hari ini, pengembalian hari ini, deposit masuk, denda, piutang)
- Statistik sales (total sales, sales aktif)
- Charts: tren rental 30 hari, tren omzet 30 hari, status rental, top 5 produk terlaris
- Widgets: transaksi terbaru, jadwal kembali, reminder belum lunas, stok menipis, customer terbaru

Widget pengembalian hari ini mengambil data dari `rental_returns`, sedangkan alur pengembalian aplikasi saat ini menyimpan hasilnya pada `rentals` dan `rental_items`. Karena tabel `rental_returns` tidak diisi oleh alur tersebut, metrik yang bergantung padanya dapat tetap nol.

**Gambar 3.2 Dashboard Super Admin**

[Sisipkan Screenshot: Dashboard Super Admin]

Gambar 3.2 menunjukkan tampilan dashboard Super Admin. Dashboard ini menampilkan greeting berdasarkan waktu (pagi/siang/sore/malam) beserta tanggal dan waktu saat ini. Terdapat filter cabang, sales, dan rentang tanggal untuk menyesuaikan data yang ditampilkan. Lima stat cards menampilkan informasi utama: total customer, koleksi produk, sedang disewa, sewa minggu ini, dan pemasukan hari ini. Empat chart menampilkan tren rental, omzet, status rental, dan model terlaris. Tiga widget menampilkan transaksi terbaru, jadwal kembali, dan stok menipis. Tombol aksi cepat memungkinkan akses langsung ke Kelola Cabang, Kelola Admin, Broadcast, Laporan, dan Scan QR.

#### 3.6.2 Manajemen Customer

Manajemen customer adalah fitur untuk mengelola data pelanggan yang menyewa jas. Fitur ini dapat diakses oleh Super Admin, Admin Toko, dan Sales dengan izin tiap aksi yang berbeda.

**Struktur Data Customer**

Setiap customer memiliki data utama: nama, nomor telepon, email, alamat, dan foto. Sistem juga menyimpan data ukuran badan customer seperti tinggi badan, berat badan, ukuran jas, ukuran kemeja, ukuran celana, ukuran sepatu, dan catatan ukuran badan. Data identitas seperti NIK KTP/SIM dan foto KTP/SIM juga disimpan untuk keperluan jaminan.

**Proses Create**

Customer dapat ditambahkan melalui `CustomerController::store()`. Proses pembuatan customer melibatkan:

1. Normalisasi nomor telepon ke format internasional (62xxx) melalui metode `normalizePhone()`.
2. Validasi nomor telepon unik: sistem mengecek apakah nomor telepon sudah terdaftar pada customer aktif atau customer yang telah dinonaktifkan.
3. Jika nomor telepon sudah terdaftar pada customer yang dinonaktifkan, sistem menampilkan peringatan dan menawarkan opsi restore.
4. Jika nomor telepon sudah terdaftar pada customer aktif, sistem menolak pembuatan.
5. Customer otomatis diasosiasikan dengan cabang pengguna yang membuat (kecuali super admin yang dapat memilih cabang).
6. Jika dibuat oleh sales, customer diasosiasikan dengan user sales tersebut (`user_id`).

**Proses Read**

Daftar customer ditampilkan pada `customers.index` dengan fitur:
- Search by nama atau nomor telepon
- Filter by status (aktif, dinonaktifkan, semua) — hanya untuk super admin
- Filter by status blacklist (normal, blacklisted)
- Pagination 10 data per halaman

Detail customer menampilkan data pribadi dan riwayat rental yang pernah dilakukan.

**Proses Update**

Customer dapat diperbarui melalui `CustomerController::update()` oleh Super Admin dan Admin Toko. Sales tidak memiliki akses update. Proses update melibatkan validasi nomor telepon unik yang sama seperti pada create.

**Proses Delete**

Customer dapat dinonaktifkan (soft delete) oleh Super Admin atau Admin Toko melalui `CustomerController::destroy()`. Sebelum dinonaktifkan, sistem memeriksa apakah customer memiliki rental yang masih aktif (waiting, active, atau overdue). Jika ada, customer tidak dapat dinonaktifkan.

Customer yang dinonaktifkan dapat dipulihkan (restore) melalui `CustomerController::restore()`.

Super Admin juga dapat menghapus customer secara permanen (force delete) melalui `CustomerController::forceDestroy()`. Sebelum dihapus permanen, sistem memeriksa apakah customer memiliki riwayat rental, pembayaran, atau jaminan. Jika ada, customer tidak dapat dihapus permanen.

**Fitur Blacklist**

Customer dapat dicegah melakukan rental melalui fitur blacklist. Super Admin dapat menoggle status blacklist customer melalui `CustomerController::toggleBlacklist()`. Saat customer di-blacklist, alasan blacklist harus diisi.

**Export Excel**

Data customer dapat diekspor ke format Excel melalui `CustomerController::export()` menggunakan package Maatwebsite Excel. Fitur export ini hanya dapat diakses oleh Super Admin dan Admin Toko.

**Gambar 3.3 Daftar Customer**

[Sisipkan Screenshot: Daftar Customer]

Gambar 3.3 menunjukkan tampilan halaman daftar customer. Halaman ini menampilkan tabel customer dengan kolom nama, nomor telepon, cabang, total rental, status blacklist, dan aksi. Terdapat form search untuk mencari customer berdasarkan nama atau nomor telepon. Filter status memungkinkan menampilkan customer aktif, dinonaktifkan, atau semua. Tombol "Tambah Pelanggan" memungkinkan penambahan customer baru. Tombol "Export Excel" memungkinkan ekspor data customer.

#### 3.6.3 Manajemen Produk

Manajemen produk adalah fitur untuk mengelola koleksi jas yang tersedia untuk disewa. Fitur ini dapat diakses oleh Super Admin, Admin Toko, dan Sales. CRUD produk tersedia untuk Super Admin/Admin Toko; Sales dapat melihat produk dan memakai fitur update stok per ukuran.

**Struktur Data Produk**

Setiap produk memiliki data: kode unik (PRD-XXX), nama, deskripsi, kategori, warna, merek, harga sewa, harga deposit, foto, QR code, kondisi, status, dan catatan. Variasi ukuran serta stok total/tersedia per ukuran disimpan di `product_sizes`. Kolom `products.stock_total` dan `products.stock_available` dipakai sebagai agregat, bukan identitas stok per ukuran.

Produk dapat di-assign ke beberapa cabang secara bersamaan melalui tabel pivot `product_branch`. Kolom `branch_id` pada tabel `products` digunakan untuk kompatibilitas legacy, tetapi relasi utama adalah melalui pivot table.

**Proses Create**

Produk dapat ditambahkan melalui `ProductController::store()` dengan langkah-langkah:

1. Pengguna yang diizinkan mengisi data produk dan variasi ukuran berikut stok awal masing-masing ukuran.
2. Form menampilkan kode produk berikutnya dalam input non-editable; saat penyimpanan server membuat kode otomatis dalam format `PRD-XXX`. Saat edit, kode juga tidak dapat diubah melalui form.
3. Jika ada upload foto, foto disimpan di storage produk.
4. Baris ukuran dan stok disimpan pada `product_sizes`; total stok produk disinkronkan dari baris ukuran.
5. Produk di-assign ke cabang-cabang yang dipilih.
6. QR code digenerate untuk produk.

**Proses Read**

Daftar produk ditampilkan pada `products.index` dengan fitur:
- Search by nama, kode, atau warna
- Filter by kategori
- Filter by status (available, rented, maintenance, inactive)
- Filter by ukuran (XS, S, M, L, XL, XXL)
- Filter by cabang
- Stat cards: total produk, tersedia, maintenance, unit disewa
- Pagination 16 data per halaman

Detail produk menampilkan informasi lengkap produk beserta riwayat rental yang pernah menggunakan produk tersebut.

**Proses Update**

Produk dapat diperbarui melalui `ProductController::update()`. Route mengizinkan Super Admin dan Admin Toko. Kode produk tidak menjadi input perubahan; update mencakup field produk, foto, ukuran/stok yang diizinkan, dan assignment cabang sesuai validasi controller.

**Proses Delete**

Produk dapat dihapus permanen melalui `ProductController::destroy()`. Sebelum dihapus, sistem memeriksa apakah produk masih digunakan pada rental yang belum selesai. Jika ada, produk tidak dapat dihapus. Saat dihapus, referensi produk pada `rental_items`, `chats`, `laundries`, dan `product_branch` di-set menjadi NULL atau dihapus.

**Update Stok**

Stok diperbarui melalui `ProductController::updateStock()`, termasuk oleh Sales. Form modal menampilkan nama produk, ukuran, stok tersedia, dan stok total untuk ukuran yang dipilih. Request membawa `size_id`; backend memvalidasi bahwa ukuran tersebut milik produk yang sedang dibuka, lalu dalam transaksi mengunci dan mengubah hanya baris `product_sizes` itu. Ukuran lain tetap, agregat stok produk disinkronkan, dan status produk diperbarui sesuai aturan controller. Activity log database memuat `size_id`, label ukuran, nilai lama/baru, dan catatan perubahan. Penulisan salinan file log dilakukan pada event pembuatan log dan kegagalannya dilaporkan, bukan menjadi bukti bahwa semua kegagalan database diabaikan.

**QR Code**

Setiap produk memiliki QR code yang dapat digunakan untuk scanning. QR code digenerate menggunakan library `SimpleSoftwareIO\QrCode` dan disimpan dalam format SVG. QR code dapat di-regenerate melalui `ProductController::regenerateQr()`.

**Gambar 3.4 Daftar Produk**

[Sisipkan Screenshot: Daftar Produk]

Gambar 3.4 menunjukkan tampilan halaman daftar produk. Halaman ini menampilkan stat cards di bagian atas yang menunjukkan total produk, produk tersedia, produk dalam maintenance, dan unit yang sedang disewa. Produk ditampilkan dalam bentuk grid card yang menampilkan foto produk, nama, kategori, harga sewa, stok tersedia, dan status. Terdapat filter untuk mencari produk berdasarkan nama atau kode, memfilter berdasarkan kategori, status, ukuran, dan cabang.

#### 3.6.4 Manajemen Rental

Manajemen rental adalah fitur inti Sewajas. Fitur ini mengelola seluruh siklus hidup transaksi rental dari pembuatan hingga pengembalian.

**Struktur Data Rental**

Setiap rental memiliki data: nomor invoice unik, cabang, customer, pembuat (sales), pengembalian (user yang memproses pengembalian), tanggal sewa, tanggal jatuh tempo, tanggal kembali aktual, durasi hari, subtotal, diskon, denda keterlambatan, total amount, paid amount, change amount, payment status, rental status, QR code, dan catatan.

Setiap rental terdiri dari satu atau lebih `rental_items`. Setiap rental item merepresentasikan satu produk yang disewa dengan data: produk, nama produk (snapshot), ukuran produk, warna produk, jumlah, harga per hari, durasi hari, subtotal, kondisi pengembalian, denda kerusakan, catatan pengembalian, dan status pengembalian.

**Proses Create Rental**

Proses create rental telah dijelaskan pada subbab 3.3.3. Fitur ini mencakup:
- Pilih customer dari dropdown atau buat customer baru
- Pilih produk beserta jumlah dan ukuran
- Pilih jaminan (KTP, SIM, Deposit Uang, Custom)
- Input diskon dan catatan
- Validasi stok dengan `lockForUpdate()`
- Generate invoice number
- Generate QR code
- Buat rental items
- Kurangi stok pada ukuran produk yang dipilih di `product_sizes`, lalu sinkronkan agregat produk
- Buat guarantee record
- Log activity

Invoice number digenerate dalam format `INV{YYYYMMDD}{BB}{SSSS}` di mana:
- `INV`: prefix
- `YYYYMMDD`: tanggal hari ini
- `BB`: kode cabang (2 digit)
- `SSSS`: sequence number (4 digit)

Proses generasi invoice menggunakan mekanisme retry dengan `lockForUpdate()` untuk memastikan keunikan nomor invoice bahkan dalam kondisi concurrent requests.

**Proses Read Rental**

Daftar rental ditampilkan pada `rentals.index` dengan fitur:
- Search by nomor invoice atau nama/nomor telepon customer
- Filter by status rental (waiting, active, overdue, returned, cancelled)
- Sort by created_at, rental_date, total_amount, atau invoice_number
- Pagination 15 data per halaman

Detail rental (`rentals.show`) menampilkan informasi lengkap:
- Info rental: invoice, customer, cabang, tanggal sewa, jatuh tempo, durasi, status
- Items: daftar produk yang disewa dengan harga, jumlah, subtotal
- Payments: riwayat pembayaran dengan nomor pembayaran, jumlah, metode, waktu
- Guarantees: jaminan yang diberikan
- Activity Log: riwayat aksi pada rental
- Action buttons: Serah Terima, Pengembalian, Edit, Hapus, PDF, WhatsApp, Reminder, Thermal Print, Receipt

**Proses Update Rental**

Rental dapat diperbarui melalui `RentalController::update()`. Update terbatas pada field tertentu jika rental sudah dalam status paid atau active. Field yang dikunci meliputi:
- Customer ID
- Tanggal sewa
- Tanggal jatuh tempo
- Item yang disewa (produk dan jumlah)

Jika rental belum dibayar atau belum aktif, seluruh data dapat diubah. Update melibatasi:
- Validasi stok kembali
- Recalculate harga berdasarkan produk dan durasi
- Update guarantee jika ada perubahan
- Log activity

**Proses Delete Rental**

Rental dapat dihapus permanen oleh Super Admin melalui `RentalController::destroy()`. Rental dalam status waiting, active, atau overdue tidak dapat dihapus. Saat dihapus, sistem juga menghapus semua payments, guarantees, items, dan return record yang terasosiasi.

**Scan QR**

Sewajas memiliki fitur scan QR code untuk mengakses detail rental secara cepat. Fitur ini menggunakan library `html5-qrcode` yang berjalan di browser. Pengguna dapat mengakses halaman scan QR melalui `rentals.scan`. Setelah QR code discan, sistem menampilkan detail rental yang sesuai.

QR code untuk rental berisi route ke `rentals.scan` dengan parameter invoice number. Setelah scan, sistem mencari rental berdasarkan nomor invoice atau ID rental, kemudian menampilkan informasi lengkap rental.

**Gambar 3.5 Form Buat Rental**

[Sisipkan Screenshot: Form Buat Rental]

Gambar 3.5 menunjukkan form pembuatan rental baru. Form ini memiliki beberapa section: (1) pemilihan customer dengan search dropdown dan opsi tambah customer baru, (2) pemilihan produk dengan ukuran dan jumlah, (3) pilihan jaminan dengan upload foto KTP/SIM jika diperlukan, (4) input diskon dan catatan, dan (5) ringkasan biaya. Setiap produk yang dipilih menampilkan harga sewa per hari, jumlah, dan subtotal. Form ini menggunakan Alpine.js untuk interaktivitas real-time.

**Gambar 3.6 Detail Rental**

[Sisipkan Screenshot: Detail Rental]

Gambar 3.6 menunjukkan halaman detail rental. Halaman ini menampilkan informasi lengkap transaksi rental including nomor invoice, customer, cabang, tanggal sewa, jatuh tempo, status rental dan pembayaran. Terdapat tab atau section untuk items, payments, guarantees, dan activity log. Action buttons memungkinkan serah terima, pengembalian, edit, hapus, cetak PDF, kirim WhatsApp, dan reminder.

#### 3.6.5 Manajemen Pembayaran

Manajemen pembayaran adalah fitur untuk mencatat dan mengelola pembayaran yang dilakukan oleh customer. Setiap rental dapat memiliki satu atau lebih pembayaran.

**Metode Pembayaran**

Metode pembayaran yang tersedia adalah:
- Cash
- Transfer
- QRIS
- Other

**Jenis Pembayaran**

Setiap pembayaran memiliki jenis (type):
- `rental`: pembayaran untuk sewa
- `deposit`: pembayaran untuk deposit uang
- `late_fee`: pembayaran untuk denda keterlambatan
- `damage_fee`: pembayaran untuk denda kerusakan
- `refund`: pengembalian dana (overpayment)

**Proses Pembayaran**

Pembayaran dilakukan melalui halaman detail rental. Pengguna mengisi form dengan jumlah, metode, jenis pembayaran, nomor referensi (jika ada), dan catatan. Proses pembayaran dilakukan oleh `RentalService::processPayment()`:

1. Validasi jumlah pembayaran harus lebih besar dari 0.
2. Buat record `payments` dengan `payment_number` dalam format `PAY-YYYYMMDDSSSS`.
3. Update `paid_amount` pada rental.
4. Hitung ulang `payment_status`:
   - `unpaid`: jika total paid_amount <= 0
   - `partial`: jika paid_amount > 0 tapi kurang dari total_amount
   - `paid`: jika paid_amount >= total_amount
5. Hitung `change_amount` (overpayment) untuk dikembalikan ke customer.
6. Hitung `fine_status` untuk denda.
7. Log activity.
8. Berikan poin sales (3 poin) jika ini adalah pembayaran pertama untuk penyerahan.

**Update Payment**

Super Admin dapat memperbarui data pembayaran yang sudah ada melalui `RentalController::paymentUpdate()`. Update dapat mengubah jumlah, metode, nomor referensi, dan catatan.

**Void Payment**

Super Admin dapat membatalkan pembayaran melalui `RentalController::paymentVoid()`. Proses void memanggil `PaymentService::voidPayment()`.

**Refund Payment**

Super Admin dapat melakukan refund pembayaran melalui `RentalController::paymentRefund()`. Refund digunakan untuk mengembalikan dana ke customer ketika ada overpayment.

**Payment Number**

Nomor pembayaran digenerate dalam format `PAY-YYYYMMDDSSSS` oleh `RentalService::generatePaymentNumber()`. Proses generasi menggunakan `lockForUpdate()` untuk menghindari race condition.

**Gambar 3.7 Panel Pembayaran pada Detail Rental**

[Sisipkan Screenshot: Panel Pembayaran pada Detail Rental]

Gambar 3.7 menunjukkan panel pembayaran pada halaman detail rental. Panel ini menampilkan ringkasan total tagihan, sudah dibayar, sisa tagihan, dan denda. Terdapat form untuk menambahkan pembayaran baru dengan input jumlah, metode, jenis pembayaran, dan catatan. Riwayat pembayaran ditampilkan dalam tabel dengan nomor pembayaran, jumlah, metode, waktu, dan aksi (edit/void/refund).

#### 3.6.6 Manajemen Pengembalian

Manajemen pengembalian adalah fitur untuk memproses pengembalian barang dari customer. Fitur ini mencakup pengecekan kondisi barang, perhitungan denda, dan update stok.

**Proses Pengembalian**

Pengembalian dilakukan melalui halaman detail rental. Pengguna mengakses form pengembalian yang meminta input kondisi untuk setiap item yang disewa:

| Kondisi | Keterangan |
|---------|------------|
| Baik | Barang dalam kondisi normal, dapat disewakan kembali |
| Rusak Ringan | Barang mengalami kerusakan minor |
| Rusak Berat | Barang mengalami kerusakan major |
| Hilang | Barang tidak dapat ditemukan |

Jika kondisi barang adalah rusak ringan, rusak berat, atau hilang, sistem meminta input denda kerusakan (`damage_fee`) untuk setiap item.

Proses pengembalian dilakukan oleh `RentalService::processReturn()`:

1. Hitung overdue days jika rental terlambat: `return_due_date.diffInDays(now())`.
2. Hitung late fee: `(subtotal / duration_days) × overdue_days × 0.5`.
3. Hitung total damage fee dari semua item yang rusak/hilang.
4. Update setiap rental item: `is_returned = true`, `return_condition`, `damage_fee`, `returned_at`.
5. Kembalikan stok pada ukuran rental yang sama di `product_sizes`, lalu sinkronkan agregat stok produk.
6. Update status guarantee menjadi `returned` dengan `returned_at`.
7. Update rental: `rental_status = returned`, `actual_return_date`, `returned_at`, `returned_by`, `late_fee`, `fine_amount = late_fee + damage_fee`, `fine_status`.
8. Hitung ulang payment status.
9. Log activity dengan action `return_rental`.
10. Berikan poin sales (5 poin) untuk pengembalian.

**Late Fee**

Denda keterlambatan dihitung dengan rumus:

```
late_fee = (subtotal / duration_days) × overdue_days × 0,5
```

Dimana:
- `subtotal` adalah total biaya sewa sebelum diskon
- `duration_days` adalah durasi sewa dalam hari
- `overdue_days` adalah jumlah hari keterlambatan

Rumus berbasis subtotal/durasi digunakan oleh `RentalService::processReturn()`. Jalur `RentalController::updateStatus()` menghitung late fee dari konfigurasi `app.late_fee_per_day`; kedua jalur ini belum seragam dan dapat menghasilkan nominal berbeda.

**Damage Fee**

Denda kerusakan diinput manual oleh pengguna saat proses pengembalian. Sistem memvalidasi bahwa jika kondisi barang rusak atau hilang, denda kerusakan harus diisi.

**Cancel Return**

Sistem memungkinkan pembatalan pengembalian melalui `RentalController::cancelReturn()`. Proses ini mengembalikan status rental ke active atau overdue (tergantung jatuh tempo), mengembalikan stok pada ukuran item, dan menghapus flag `is_returned` pada rental items.

**Gambar 3.8 Form Pengembalian Barang**

[Sisipkan Screenshot: Form Pengembalian Barang]

Gambar 3.8 menunjukkan form pengembalian barang pada halaman detail rental. Form ini menampilkan daftar item yang disewa beserta kondisi yang dapat dipilih (baik, rusak ringan, rusak berat, hilang). Setiap item memiliki input untuk denda kerusakan jika kondisi bukan baik. Ringkasan di bagian atas menampilkan total denda keterlambatan dan denda kerusakan. Tombol "Proses Pengembalian" menyelesaikan proses pengembalian.

#### 3.6.7 Activity Log dan Notifikasi

**Activity Log**

Aktivitas penting dicatat sebagai record `activity_logs` oleh controller atau service terkait. Model `ActivityLog` memiliki event `created` yang mengambil `ActivityLogFileService` melalui container dan mencoba menulis record ke file. Kegagalan penulisan file dilaporkan ke Laravel log. Hal ini tidak membungkus semua pemanggilan `ActivityLog::create()`; kegagalan penyimpanan record database tetap dapat memengaruhi transaksi utama. Log perubahan stok menyertakan ukuran dan nilai stok sebelum/sesudah perubahan.

**Notifikasi In-App**

Komponen bell menampilkan unread count dan memuat daftar notifikasi pengguna yang sedang login. Daftar bell mendukung navigasi ke halaman seluruh notifikasi, paging/load more dan filter. Pengguna dapat membuka notifikasi (yang menandainya dibaca), menandai semua sebagai dibaca, atau menghapus notifikasi. Controller membatasi record ke pemilik (`notifiable_type` dan `notifiable_id`).

JavaScript memakai request JSON untuk fetch/AJAX, termasuk pembaruan badge/list dan aksi baca/hapus. Untuk form HTML biasa, controller mengembalikan redirect dengan flash message; browser tidak diarahkan ke halaman JSON.

#### 3.6.8 Sistem Broadcast

Sistem broadcast adalah fitur komunikasi massal yang dapat mengirim pesan melalui dua channel: notifikasi in-app dan WhatsApp. Fitur ini dikelola melalui `BroadcastCampaignController`.

**Konsep Campaign**

Broadcast diorganisir dalam bentuk campaign. Setiap campaign memiliki:
- Nama campaign
- Channel (in_app atau whatsapp)
- Recipient type (customer, user, atau both)
- Target type (all, branch, sales, rental_status, product, category, customer)
- Target filters (berdasarkan target type)
- Status (draft, scheduled, queued, processing, completed, failed, partial)
- Total target, total success, total failed
- Custom message
- Delay seconds (untuk in-app)

**Channel In-App**

Channel in-app mengirim notifikasi ke dalam aplikasi. Notifikasi disimpan dalam tabel `notifications` dengan struktur polymorphic (`notifiable_type`, `notifiable_id`). Setiap notifikasi memiliki title, message, icon, color, action_url, dan status read/unread.

Channel in-app hanya dapat digunakan untuk target User/Sales, bukan Customer.

**Channel WhatsApp**

Integrasi WhatsApp menggunakan external worker untuk sebagian alur campaign, sementara pengiriman ke customer juga menggunakan URL `wa.me` yang membuka percakapan di browser. Pengiriman tidak boleh diasumsikan otomatis berhasil hanya karena campaign dibuat; worker dan konfigurasi eksternal harus tersedia.

Konfigurasi worker melibatkan environment variable:
- `BROADCAST_WORKER_URL`: URL worker service
- `BROADCAST_WORKER_TOKEN`: Token autentikasi untuk worker
- `BROADCAST_DAILY_LIMIT`: Batas harian pesan (default 50)

WhatsApp mengirim pesan dengan membuka URL `https://wa.me/{phone}?text={message}` di browser.

**Targeting**

Campaign dapat menargetkan penerima berdasarkan:
- **All**: Semua customer/user
- **Branch**: Cabang tertentu
- **Sales**: Sales tertentu
- **Rental Status**: Customer dengan rental dalam status tertentu
- **Product**: Customer yang pernah menyewa produk tertentu
- **Category**: Customer yang pernah menyewa produk dalam kategori tertentu
- **Customer**: Pilih customer secara manual

**Template**

Pesan broadcast dapat menggunakan template yang telah dibuat atau custom message. Template mendukung variabel seperti `{{name}}`, `{{phone}}`, `{{sender_name}}` yang akan diganti dengan data penerima saat pengiriman.

**Status Tracking**

Setiap pengiriman pesan dicatat dalam `broadcast_logs` dengan status:
- `submitted`: pesan dikirim ke worker
- `sent`: pesan terkirim
- `delivered`: pesan diterima
- `read`: pesan dibaca
- `failed`: pengiriman gagal

Campaign memiliki progress bar yang menampilkan jumlah pesan yang berhasil dikirim vs total target.

**Opt-Out**

Customer dan user dapat menolak menerima broadcast melalui kolom `opt_out` pada tabel `customers` dan `users`. Worker akan memeriksa status opt-out sebelum mengirim pesan.

**Gambar 3.9 Form Broadcast Campaign**

[Sisipkan Screenshot: Form Broadcast Campaign]

Gambar 3.9 menunjukkan form pembuatan campaign broadcast. Form ini memungkinkan pengguna memilih channel (In-App atau WhatsApp), recipient type, target type, dan target filters. Terdapat selector template dan field untuk custom message. Untuk channel WhatsApp, pengguna dapat memilih customer secara manual atau berdasarkan filter. Form ini menampilkan preview pesan yang akan dikirim.

#### 3.6.9 Manajemen Points

Manajemen points adalah fitur insentif untuk sales. Setiap sales mendapatkan poin berdasarkan performa penyerahan dan pengembalian barang.

**Skema Poin**

| Aksi | Poin |
|------|------|
| Pembayaran pertama rental | 3 poin |
| Pengembalian rental | 5 poin |

Poin diberikan melalui `RentalService::addSalesPoints()`:
- Saat pembayaran pertama kali diproses (`serah`): sales pembuat rental mendapatkan 3 poin.
- Saat pengembalian dilakukan (`kembali`): sales yang memproses pengembalian mendapatkan 5 poin.

Poin hanya diberikan sekali per aksi. Sistem menggunakan flag `points_awarded_serah` dan `points_awarded_kembali` pada tabel `rentals` untuk mencegah pemberian poin ganda.

**Leaderboard**

Points ditampilkan dalam bentuk leaderboard di halaman `/points`. Tampilan berbeda berdasarkan role:
- **Sales**: melihat "Poin Saya" yang menampilkan total poin pribadi.
- **Admin/Super Admin**: melihat "Laporan Poin" yang menampilkan peringkat semua sales dengan filter cabang.

Data poin disimpan dalam kolom `total_points` pada tabel `users`.

#### 3.6.10 Pencarian Global

Pencarian global memungkinkan pengguna mencari customer, rental, dan produk secara bersamaan dalam satu halaman. Fitur ini diakses melalui route `/search` yang ditangani oleh `SearchController::index()`.

**Fitur Pencarian:**

1. **Customer**: Mencari berdasarkan nama atau nomor telepon. Nomor telepon dinormalisasi sebelum pencarian.
2. **Rental/Invoice**: Mencari berdasarkan nomor invoice atau nama/nomor telepon customer.
3. **Produk**: Mencari berdasarkan nama, kode, atau warna produk.

Hasil pencarian ditampilkan dalam tiga kolom: Pelanggan, Invoice, dan Produk. Masing-masing kolom menampilkan maksimal 8 hasil.

Pencarian menerapkan scope sesuai role dan query: Super Admin dapat melihat semua data; hasil Sales dibatasi berdasarkan kepemilikan; scope Admin Toko dapat lebih luas karena `branch_access_type = all` saat ini. Batasan tersebut tidak boleh digambarkan sebagai isolasi pasti ke cabang utama.

**Catatan**: Pencarian customer menggunakan `withTrashed()`, yang berarti customer yang sudah dinonaktifkan tetap muncul dalam hasil pencarian.

#### 3.6.11 Laporan

Sistem laporan menyediakan berbagai laporan operasional dan keuangan. Enam laporan operasional memiliki route export berlabel Excel dan PDF; implementasi export Excel tertentu mengirim CSV streaming, bukan workbook Excel. Laporan activity log menyediakan CSV dan PDF.

**Jenis Laporan:**

1. **Laporan Pendapatan**: Menampilkan pendapatan per periode (hari ini, minggu ini, bulan ini, atau custom range). Data dikelompokkan per tanggal. Menampilkan total revenue, total late fee, dan total rentals. Include chart dan route export Excel/CSV serta PDF.

2. **Laporan Transaksi**: Menampilkan daftar transaksi rental dengan filter periode, status, dan search. Menampilkan ringkasan rental (waiting, active, overdue, returned, cancelled, total nilai). Include export sesuai route laporan.

3. **Laporan Pengembalian**: Menampilkan data pengembalian dengan summary (returned, due today, overdue). Menampilkan daftar overdue, jatuh tempo hari ini, dan riwayat pengembalian. Include route export Excel/CSV serta PDF.

4. **Laporan Stok**: Menampilkan status stok produk dengan summary (total produk, total stok, tersedia, disewa, active rentals, out of stock, low stock). Include route export Excel/CSV serta PDF.

5. **Laporan Customer**: Menampilkan data customer dengan summary (total, with transactions, no transactions). Include route export Excel/CSV serta PDF.

6. **Laporan Pembayaran**: Menampilkan data pembayaran. Include route export Excel/CSV serta PDF.

7. **Laporan Activity Log**: Menampilkan log aktivitas sistem dengan filter periode, tab (rental, payment, customer, product, user), dan search. Include export CSV/PDF.

**Filter Laporan**

Jenis dan parameter filter berbeda menurut laporan. Filter cabang tersedia pada laporan yang mendukungnya; jangan menganggap semua laporan memakai filter periode/search yang identik. Route laporan saat ini mengizinkan Super Admin dan Admin Toko.

**Export**

Export menggunakan:
- **Excel/CSV**: Maatwebsite Excel digunakan untuk export customer; laporan tertentu menghasilkan CSV. Nama tombol/route export tidak berarti setiap file merupakan workbook Excel.
- **PDF**: DomPDF (barryvdh/laravel-dompdf) dengan template PDF khusus di `resources/views/reports/pdf/`.

**Gambar 3.10 Laporan Pendapatan**

[Sisipkan Screenshot: Laporan Pendapatan]

Gambar 3.10 menunjukkan halaman laporan pendapatan. Halaman ini menampilkan filter yang tersedia untuk laporan tersebut, stat cards, grafik tren pendapatan, dan tabel rincian. Tombol export mengikuti format yang disediakan route laporan.

#### 3.6.12 Manajemen Profile

Manajemen profile memungkinkan pengguna mengubah data pribadi, password, dan preferensi tampilan.

**Fitur Profile:**

1. **Edit Profil**: Ubah nama dan email. Validasi email unik.
2. **Ubah Password**: Ganti password dengan memasukkan password lama dan password baru. Menggunakan Laravel's `current_password` rule.
3. **Ubah Theme**: Pilih antara tema light atau dark. Nilai disimpan di kolom `theme` pada tabel `users`.
4. **Hapus Akun**: Pengguna dapat menghapus akun sendiri dengan konfirmasi password. Proses ini melakukan logout, menghapus user, dan menginvalidasi session.

**Gambar 3.11 Halaman Profile**

[Sisipkan Screenshot: Halaman Profile]

Gambar 3.11 menunjukkan halaman edit profile dengan tabbed interface. Tab "Profil" memungkinkan pengguna mengubah nama dan email. Tab "Keamanan" memungkinkan pengguna mengubah password dengan strength meter. Tab "Preferensi" memungkinkan pengguna memilih tema light atau dark.

#### 3.6.13 Manajemen Settings

Settings memungkinkan Super Admin mengkonfigurasi aplikasi dan perusahaan.

**Fitur Settings:**

1. **Pengaturan Aplikasi**: Nama aplikasi, tagline, logo aplikasi.
2. **Pengaturan Perusahaan**: Alamat, telepon, website, tagline perusahaan.
3. **Pengaturan Bank**: Nama bank, nomor rekening, nama pemilik rekening.
4. **QRIS**: Upload gambar QRIS untuk pembayaran dengan validasi resolusi minimal 200x200.
5. **Pengaturan Denda**: Denda keterlambatan per hari.
6. **Pengaturan Durasi**: Default durasi rental.
7. **Theme**: Preferensi tema (light/dark).

Settings disimpan dalam tabel `settings` dengan struktur key-value. Setiap setting memiliki key unik dan value yang dapat di-update melalui `SettingsController::update()`.

---

### 3.7 Dashboard

#### 3.7.1 Dashboard Super Admin

Dashboard Super Admin menampilkan overview seluruh sistem multi-cabang. Data yang ditampilkan mencakup semua cabang tanpa batasan.

**Karakteristik Dashboard Super Admin:**

1. **Filter**: Cabang (Semua Cabang atau pilih cabang tertentu), Sales (Semua Sales atau pilih sales tertentu), rentang tanggal (Dari Tanggal, Sampai Tanggal).
2. **Stat Cards**: 5 kartu statistik utama:
   - Pelanggan: total customer aktif
   - Koleksi Produk: total produk tersedia
   - Sedang Disewa: jumlah produk yang sedang disewa
   - Sewa Minggu Ini: jumlah rental minggu ini
   - Pemasukan Hari Ini: total pendapatan hari ini
3. **Charts**:
   - Grafik Rental 30 Hari: tren jumlah rental harian
   - Grafik Pemasukan 30 Hari: tren pendapatan harian
   - Grafik Status Rental: distribusi status rental (waiting, active, overdue, returned)
   - Grafik Model Terlaris: top 5 produk berdasarkan jumlah rental
4. **Widgets**:
   - Transaksi Terbaru: 8 transaksi rental terbaru
   - Jadwal Kembali: rental yang jatuh tempo hari ini
   - Stok Menipis: produk dengan stok tersisa <= 3
5. **Quick Actions**: Kelola Cabang, Kelola Admin, Broadcast, Laporan, Scan QR

Data dashboard diambil dari `DashboardService::getSuperAdminDashboardPayload()` yang menjalankan agregasi queries tanpa batasan cabang.

#### 3.7.2 Dashboard Admin Toko

Dashboard Admin Toko menampilkan overview operasional. Walaupun akun memiliki cabang utama, `branch_access_type` saat ini disetel `all`; cakupan tiap query agregasi perlu diperiksa dan tidak dapat dinyatakan selalu terbatas ke satu cabang.

**Karakteristik Dashboard Admin Toko:**

1. **Filter**: Filter sales dan rentang tanggal; filter cabang mengikuti implementasi dashboard.
2. **Stat Cards**: Mengikuti query agregasi dan scope akses yang diterapkan dashboard.
3. **Charts**: Mengikuti query agregasi dan scope akses yang diterapkan dashboard.
4. **Widgets**: Mengikuti query agregasi dan scope akses yang diterapkan dashboard.
5. **Quick Actions**: Tambah Pelanggan, Buat Sewa, Pengembalian, Broadcast, Scan QR

Data dashboard diambil dari `DashboardService`. Jangan menganggap semua agregasi Admin Toko otomatis satu cabang: model saat ini memberi role tersebut `branch_access_type = all`, sehingga query yang memakai `accessible_branch_ids` dapat mencakup seluruh cabang. Scope tiap agregasi perlu dinilai sesuai query-nya.

#### 3.7.3 Dashboard Sales

Dashboard Sales menampilkan overview pribadi sales. Data dibatasi hanya untuk rental yang dibuat oleh sales tersebut.

**Karakteristik Dashboard Sales:**

1. **Filter**: Tidak ada filter cabang atau sales (secara default menampilkan data pribadi).
2. **Stat Cards**: Menampilkan statistik yang relevan untuk sales:
   - Pelanggan: total customer yang dibuat sales
   - Koleksi Produk: total produk tersedia di cabang sales
   - Sedang Disewa: jumlah produk yang disewa di cabang sales
   - Sewa Minggu Ini: rental yang dibuat sales minggu ini
   - Pemasukan Hari Ini: pendapatan dari rental yang dibuat sales
3. **Charts**: Sama seperti dashboard lain, tetapi data hanya untuk rental yang dibuat sales.
4. **Widgets**: Transaksi terbaru milik sales, jadwal kembali milik sales, stok menipis di cabang sales.
5. **Quick Actions**: Buat Sewa, Pelanggan Saya, Scan QR, Pengembalian, Transaksi Saya

Data dashboard diambil dari `DashboardService::getSalesDashboardPayload($branchId, $userId)` yang meneruskan `branch_id` dan `sales_user_id` ke semua query agregasi.

**Perbandingan Dashboard:**

| Aspek | Super Admin | Admin Toko | Sales |
|-------|-------------|--------------|-------|
| Scope Data | Semua cabang | `branch_access_type = all`; cakupan tiap query perlu diperiksa | Cabang spesifik dan data milik Sales sesuai query |
| Filter Cabang | Ya | Tidak | Tidak |
| Filter Sales | Ya | Ya | Tidak |
| Kelola Cabang | Ya | Tidak | Tidak |
| Kelola User | Ya | Tidak | Tidak |
| Kelola Kategori | Ya | Tidak | Tidak |
| Kelola Produk | Ya (CRUD) | Ya (CRUD/update stok sesuai route) | Lihat produk + update stok per ukuran |
| Broadcast | Ya | Ya (scope mengikuti query/branch access) | Tidak |
| Laporan | Sesuai route | Sesuai route (Super Admin/Admin Toko) | Tidak ada akses ke route laporan umum |

---

### 3.8 Database

#### 3.8.1 Perancangan Database

Sewajas menggunakan MySQL sebagai database relasional. Database dirancang dengan pendekatan normalisasi untuk memastikan konsistensi data dan menghindari redundansi.

Tabel-tabel utama dalam database Sewajas dapat dikelompokkan menjadi beberapa kategori:

1. **User Management**: `users`, `branches`, `user_branch_access`
2. **Master Data**: `customers`, `categories`, `products`, `product_sizes`, `product_branch`
3. **Transaction**: `rentals`, `rental_items`, `payments`, `guarantees`
4. **Return**: `rental_returns`, `status_histories`
5. **Communication**: `notifications`, `chats`, `messages`, `broadcast_templates`, `broadcast_campaigns`, `broadcast_schedules`, `broadcast_logs`, `broadcast_provider_configs`
6. **Audit**: `activity_logs`
7. **System**: `settings`, `sessions`, `password_reset_tokens`

Pada source yang diaudit terdapat 58 file migration. Jumlah tabel aktual bergantung pada migration yang sudah dijalankan pada environment; daftar ini menggambarkan struktur yang didefinisikan repository, bukan status database production.

#### 3.8.2 Struktur Tabel

Berikut adalah tabel-tabel utama yang menjadi fondasi sistem Sewajas:

**Tabel users**

Tabel `users` menyimpan data pengguna sistem. Kolom penting meliputi:
- `id`: primary key
- `branch_id`: foreign key ke `branches.id` (nullable untuk super admin)
- `name`: nama lengkap pengguna
- `email`: email unik untuk login
- `password`: password hash
- `role`: role aplikasi (`super_admin`, `admin_toko`, `sales`, `customer`)
- `branch_access_type`: enum `all` atau `specific`; menentukan cara accessor akses cabang menghitung cakupan
- `phone`: nomor telepon
- `avatar`: path foto profil
- `is_active`: status akun aktif
- `theme`: preferensi tema (light/dark)
- `total_points`: total poin sales
- `opt_out`: status opt-out broadcast
- `remember_token`: untuk fitur "remember me"
- `email_verified_at`: timestamp verifikasi email
- `deleted_at`: soft delete timestamp

Tabel ini menggunakan trait `SoftDeletes` untuk menghapus data secara soft.

**Tabel branches**

Tabel `branches` menyimpan data cabang toko. Kolom penting:
- `id`: primary key
- `name`: nama cabang
- `code`: kode unik cabang
- `address`: alamat cabang
- `phone`, `email`: kontak cabang
- `city`, `province`: lokasi cabang
- `logo`: path logo cabang
- `is_active`: status cabang aktif
- `deleted_at`: soft delete timestamp

**Tabel customers**

Tabel `customers` menyimpan data pelanggan. Kolom penting:
- `id`: primary key
- `branch_id`: foreign key ke cabang
- `user_id`: foreign key ke user sales yang membuat (nullable)
- `name`: nama customer
- `phone`: nomor telepon (dinormalisasi ke format 62xxx)
- `email`: email customer
- `address`: alamat
- `id_number`: NIK KTP/SIM
- `photo`, `id_photo`: path foto
- `height`, `weight`, `suit_size`, `shirt_size`, `trouser_size`, `shoe_size`: data ukuran badan
- `body_notes`: catatan ukuran badan
- `is_blacklisted`: status blacklist
- `blacklist_reason`: alasan blacklist
- `opt_out`: status opt-out broadcast
- `deleted_at`: soft delete timestamp

Tabel ini menggunakan soft delete untuk menonaktifkan customer tanpa menghapus data.

**Tabel categories**

Tabel `categories` menyimpan kategori produk. Kolom penting:
- `id`: primary key
- `name`: nama kategori
- `slug`: slug unik untuk URL
- `icon`: ikon kategori
- `description`: deskripsi
- `sort_order`: urutan tampilan
- `is_active`: status aktif

**Tabel products**

Tabel `products` menyimpan data produk. Kolom penting:
- `id`: primary key
- `branch_id`: foreign key ke cabang
- `category_id`: foreign key ke kategori
- `code`: kode produk unik (PRD-XXX)
- `name`: nama produk
- `description`: deskripsi
- `size`: kolom legacy; variasi ukuran dan stok aktual disimpan di `product_sizes`
- `color`: warna
- `brand`: merek
- `rental_price`: harga sewa per hari
- `deposit_price`: harga deposit
- `stock_total`: agregat jumlah seluruh ukuran
- `stock_available`: agregat stok tersedia seluruh ukuran
- `photo`: path foto produk
- `qr_code`: path QR code
- `condition`: kondisi (excellent, good, fair, poor)
- `status`: status (available, rented, maintenance, inactive)
- `notes`: catatan
- `deleted_at`: soft delete timestamp

**Tabel product_branch**

Tabel pivot `product_branch` menghubungkan produk dengan beberapa cabang. Kolom:
- `product_id`: foreign key ke `products`
- `branch_id`: foreign key ke `branches`

**Tabel product_sizes**

Tabel `product_sizes` menyimpan stok per variasi ukuran. Setiap baris memiliki `product_id`, `size`, `stock_total`, dan `stock_available`; pasangan produk/ukuran memiliki unique constraint. Update stok mengirim `size_id`, backend memverifikasi ukuran itu milik produk terkait, lalu hanya mengubah baris ukuran tersebut. Agregat pada produk disinkronkan kembali dari relasi ukuran.

**Tabel user_branch_access**

Pivot ini menyimpan relasi akses cabang spesifik antara user dan branch. `branch_access_type` menentukan pemakaian akses tersebut; role Admin Toko saat ini dipaksa ke `all`, sedangkan Sales memakai akses `specific`.

**Tabel rentals**

Tabel `rentals` adalah tabel inti yang menyimpan data transaksi rental. Kolom penting:
- `id`: primary key
- `invoice_number`: nomor invoice unik (INVYYYYMMDDBBSSSS)
- `branch_id`: foreign key ke cabang
- `customer_id`: foreign key ke customer
- `created_by`: foreign key ke user yang membuat
- `returned_by`: foreign key ke user yang memproses pengembalian (nullable)
- `rental_date`: tanggal sewa
- `return_due_date`: tanggal jatuh tempo
- `actual_return_date`: tanggal kembali aktual (nullable)
- `duration_days`: durasi sewa dalam hari
- `subtotal`: subtotal sebelum diskon
- `discount`: jumlah diskon
- `late_fee`: denda keterlambatan
- `total_amount`: total tagihan
- `paid_amount`: jumlah yang sudah dibayar
- `change_amount`: kembalian/overpayment
- `payment_status`: enum (unpaid, partial, paid)
- `rental_status`: enum (waiting, active, overdue, returned, cancelled)
- `qr_code`: path QR code invoice
- `notes`: catatan
- `returned_at`: timestamp pengembalian
- `return_condition`: kondisi pengembalian (baik, kurang_baik, rusak_ringan, rusak_berat)
- `return_notes`: catatan pengembalian
- `overdue_days`: hari keterlambatan
- `fine_status`: enum (none, unpaid, partial, paid)
- `fine_amount`: total denda
- `fine_paid_amount`: denda yang sudah dibayar
- `points_awarded_serah`: flag poin penyerahan
- `points_awarded_kembali`: flag poin pengembalian
- `payment_method`: metode pembayaran

Tabel ini memiliki index pada `branch_id`, `rental_status`, `rental_date`, `return_due_date`, dan `invoice_number`.

**Tabel rental_items**

Tabel `rental_items` menyimpan setiap item produk dalam satu rental. Kolom penting:
- `id`: primary key
- `rental_id`: foreign key ke rental
- `product_id`: foreign key ke produk
- `product_name`: nama produk (snapshot)
- `product_size`: ukuran produk
- `product_color`: warna produk
- `quantity`: jumlah
- `price_per_day`: harga sewa per hari
- `duration_days`: durasi sewa
- `subtotal`: subtotal item
- `return_condition`: kondisi pengembalian (good, damaged, lost)
- `damage_fee`: denda kerusakan
- `return_notes`: catatan pengembalian
- `is_returned`: status pengembalian
- `returned_at`: timestamp pengembalian

**Tabel payments**

Tabel `payments` menyimpan data pembayaran. Kolom penting:
- `id`: primary key
- `rental_id`: foreign key ke rental
- `received_by`: foreign key ke user yang menerima pembayaran
- `payment_number`: nomor pembayaran unik (PAY-YYYYMMDDSSSS)
- `amount`: jumlah pembayaran
- `method`: metode (cash, transfer, qris, other)
- `reference_number`: nomor referensi (nullable)
- `type`: jenis (rental, deposit, late_fee, damage_fee, refund)
- `notes`: catatan
- `paid_at`: timestamp pembayaran

Tabel ini memiliki unique index pada `payment_number`.

**Tabel guarantees**

Tabel `guarantees` menyimpan data jaminan. Kolom penting:
- `id`: primary key
- `rental_id`: foreign key ke rental
- `type`: jenis jaminan (ktp, sim, deposit, custom)
- `id_number`: nomor identitas (nullable)
- `id_name`: nama pada identitas (nullable)
- `deposit_amount`: jumlah deposit
- `description`: deskripsi jaminan
- `photo`: foto barang jaminan (nullable)
- `id_photo`: foto KTP/SIM (nullable)
- `status`: status (held, returned, forfeited)
- `notes`: catatan
- `returned_at`: timestamp pengembalian

**Tabel rental_returns**

Tabel `rental_returns` dibuat untuk menyimpan detail pengembalian, tetapi berdasarkan audit, tabel ini tidak di-populate oleh business logic. Data pengembalian disimpan langsung pada tabel `rentals` dan `rental_items`.

**Tabel laundries**

> **Catatan:** Tabel `laundries` disiapkan untuk pengembangan fitur di masa depan. Belum ada UI/antarmuka pengguna yang mengelola data laundry, sehingga tabel ini tidak termasuk ruang lingkup fungsi sistem yang dibahas di laporan ini.

Tabel `laundries` menyimpan data proses laundry. Kolom penting:
- `id`: primary key
- `transaksi_id`: foreign key ke `rentals`
- `produk_id`: foreign key ke `products`
- `status`: status laundry (menunggu_laundry, dalam_laundry, siap_disewakan)
- `dikembalikan_at`: waktu barang dikembalikan
- `mulai_laundry_at`: waktu mulai laundry
- `selesai_laundry_at`: waktu selesai laundry
- `diproses_oleh`: user yang memproses
- `catatan`: catatan

Tabel ini menggunakan soft delete.

**Tabel status_histories**

Tabel `status_histories` menyimpan riwayat perubahan status untuk berbagai model (polymorphic). Kolom penting:
- `id`: primary key
- `model_type`: tipe model (Laundry, Rental, dll)
- `model_id`: ID model
- `status_lama`: status sebelumnya
- `status_baru`: status baru
- `keterangan`: catatan
- `user_id`: user yang melakukan perubahan
- `changed_at`: timestamp perubahan

**Tabel activity_logs**

Tabel `activity_logs` menyimpan log aktivitas sistem. Kolom penting:
- `id`: primary key
- `user_id`: user yang melakukan aksi (nullable)
- `branch_id`: cabang terkait
- `action`: jenis aksi (create_rental, process_payment, return_rental, dll)
- `model_type`: tipe model yang diubah
- `model_id`: ID model
- `description`: deskripsi aksi
- `old_values`: data sebelum perubahan (JSON)
- `new_values`: data setelah perubahan (JSON)
- `ip_address`: IP address pengguna
- `user_agent`: user agent browser
- `created_at`: timestamp aksi

Tabel ini memiliki index pada `model_type`, `model_id`, dan `user_id`.

**Tabel notifications**

Tabel `notifications` menyimpan notifikasi. Kolom penting:
- `id`: primary key
- `notifiable_type`: tipe model penerima (polymorphic)
- `notifiable_id`: ID penerima
- `branch_id`: cabang terkait
- `type`: jenis notifikasi
- `title`: judul
- `message`: isi pesan
- `icon`: ikon
- `color`: warna
- `action_url`: URL aksi
- `meta`: metadata tambahan (JSON)
- `is_read`: status dibaca
- `read_at`: timestamp dibaca

**Tabel settings**

Tabel `settings` menyimpan pengaturan aplikasi dalam bentuk key-value. Kolom:
- `id`: primary key
- `key`: kunci pengaturan (unik)
- `value`: nilai pengaturan
- `created_at`, `updated_at`: timestamp

Contoh settings: `fine_per_day`, `rental_duration_days`, `app_name`, `app_tagline`, `company_address`, `company_phone`, `bank_name`, `bank_account`, `qris_image`, `app_logo`.

#### 3.8.3 Relasi Antar Tabel

Berikut adalah diagram relasi antar tabel utama dalam Sewajas:

```
users
├── branch_id → branches.id
├── branchAccess → branches (via user_branch_access)
├── rentals (created_by) → rentals.created_by
├── customer → customers.user_id (hasOne)
├── notifications → notifications.notifiable (morphMany)
├── chatsAsCustomer → chats.customer_id
├── chatsAsSales → chats.sales_id
└── activityLogs → activity_logs.user_id

branches
├── users → users.branch_id
├── rentals → rentals.branch_id
├── customers → customers.branch_id
├── products → products.branch_id
└── product_branch → product_branch.branch_id

customers
├── branch_id → branches.id
├── user_id → users.id
├── rentals → rentals.customer_id
└── broadcastLogs → broadcast_logs.recipient (morphMany)

products
├── branch_id → branches.id
├── category_id → categories.id
├── sizes → product_sizes.product_id
├── branches → branches (via product_branch)
└── rentalItems → rental_items.product_id

product_sizes
└── product_id → products.id

rentals
├── branch_id → branches.id
├── customer_id → customers.id
├── created_by → users.id
├── returned_by → users.id
├── items → rental_items.rental_id
├── guarantees → guarantees.rental_id
├── payments → payments.rental_id
├── returnRecord → rental_returns.rental_id (hasOne)
├── activityLogs → activity_logs.model_id (morphMany)
└── statusHistories → status_histories.model_id (morphMany)

rental_items
├── rental_id → rentals.id
└── product_id → products.id

payments
├── rental_id → rentals.id
└── received_by → users.id

guarantees
└── rental_id → rentals.id

laundries
├── transaksi_id → rentals.id
├── produk_id → products.id
├── diproses_oleh → users.id
└── statusHistories → status_histories.model_id (morphMany)

broadcast_campaigns
├── branch_id → branches.id
├── created_by → users.id
├── template → broadcast_templates.id
└── logs → broadcast_logs.campaign_id
```

Relasi ini menunjukkan bahwa tabel `rentals` merupakan tabel pusat yang menghubungkan hampir seluruh tabel lain dalam sistem. Setiap rental terasosiasi dengan branch, customer, user (pembuat dan pengembali), items, payments, guarantees, activity logs, dan status histories.

#### 3.8.4 Implementasi Database

Database Sewajas diimplementasikan menggunakan Laravel's Eloquent ORM dengan MySQL sebagai database driver. Konfigurasi database terdapat di `config/database.php` dengan default connection `mysql`.

Migrasi database digunakan untuk membuat dan memodifikasi struktur tabel. Source yang diaudit memiliki 58 file migration; jumlah yang benar-benar diterapkan bergantung pada environment. Beberapa migrasi penting antara lain:

1. `2026_05_15_014606_create_rental_table.php`: Membuat tabel `rentals`, `rental_items`, `guarantees`, dan `payments` dalam satu migrasi.
2. `2026_06_05_145146_create_rental_returns_table.php`: Membuat tabel `rental_returns`.
3. `2026_06_05_150017_add_rental_return_and_settings_features.php`: Menambahkan kolom return ke rentals, membuat tabel `settings`, dan seed default settings.
4. `2026_08_21_112856_create_product_branch_table.php`: Membuat tabel pivot `product_branch`.
5. `2026_08_22_182200_replace_commission_with_points.php`: Mengganti sistem komisi dengan sistem poin.
6. `2026_09_09_145855_drop_deleted_at_from_rentals_table.php`: Menghapus soft delete dari rentals.
7. Migration ukuran produk mendefinisikan `product_sizes`; migration akses cabang mendefinisikan `user_branch_access` dan `branch_access_type`.

Database menggunakan foreign key constraints untuk menjaga integritas referensial. Contohnya, `rentals.branch_id` di-constrain ke `branches.id`, `rentals.customer_id` di-constrain ke `customers.id`, dan `rental_items.rental_id` di-constrain ke `rentals.id` dengan `cascadeOnDelete`.

Tabel `rentals` memiliki unique index pada `invoice_number` dan index pada `branch_id`, `rental_status`, `rental_date`, `return_due_date` untuk mempercepat query. Tabel `payments` memiliki unique index pada `payment_number`. Tabel `activity_logs` memiliki index pada `model_type`, `model_id`, dan `user_id`. Tabel `product_sizes` memiliki unique index gabungan pada `product_id` dan `size`.

---

### 3.9 Hasil Implementasi

Berdasarkan analisis terhadap source code Sewajas, hasil implementasi dapat diklasifikasikan menjadi tiga kategori: fitur yang berhasil diimplementasikan, fitur yang tersedia tetapi memiliki keterbatasan, dan fitur yang belum tersedia.

#### 3.9.1 Fitur yang Berhasil Diimplementasikan

Fitur berikut tersedia berdasarkan implementasi source code; ketersediaan kode tidak menyatakan bahwa fitur sudah diuji atau aktif di production:

1. **Multi-Role Authentication**: Login berbasis session dengan role `super_admin`, `admin_toko`, `sales`, dan `customer`; tiga role internal memiliki dashboard/route, sedangkan role customer belum memiliki dashboard fungsional.
2. **Akses Cabang**: Cabang utama dan `branch_access_type` dikelola pada user. Sales memakai akses spesifik; Admin Toko saat ini disetel `all`, sehingga isolasi per cabang tidak konsisten untuk seluruh query.
3. **Rental Management**: Pembuatan rental, invoice unik, QR, pembayaran, pengembalian, dan aktivitas terkait.
4. **Stock Management**: Stok terpisah per ukuran pada `product_sizes`; pembaruan stok memilih `size_id`, memvalidasi kepemilikan pada produk, memperbarui satu ukuran, lalu menyinkronkan agregat produk.
5. **Payment System**: Pembayaran berulang, status pembayaran, void/refund yang tersedia pada route tertentu, serta perhitungan late fee/damage fee yang belum seragam pada semua jalur.
6. **Guarantee System**: Jenis jaminan KTP, SIM, deposit, atau custom dengan data/foto sesuai jenisnya.
7. **Broadcast System**: Campaign in-app dan WhatsApp dengan targeting/template; sebagian alur WhatsApp bergantung pada worker atau membuka `wa.me`.
8. **Reporting**: Tujuh halaman laporan; export tersedia menurut route dan format masing-masing. Activity log memakai CSV/PDF.
9. **Activity Log**: Record database dibuat pada aksi penting; model mencoba menulis salinan file setelah record tercipta. Kegagalan file dilaporkan ke Laravel log, sedangkan kegagalan database tidak otomatis ditelan.
10. **Notifikasi In-App**: Bell/unread count, daftar notifikasi, paging/filter, baca, tandai semua dibaca, hapus, dan ownership berdasarkan user.
11. **Logout**: Modal konfirmasi custom; pembatalan tidak mengirim request, persetujuan menggunakan POST/CSRF dan mekanisme logout Laravel yang ada.
12. **Points, Search, QR Code, Theme, Settings**: Fitur tersedia melalui route/view terkait dengan batas role dan scope yang diterapkan masing-masing.

#### 3.9.2 Fitur yang Tersedia tetapi Memiliki Keterbatasan

Fitur berikut tersedia dalam sistem tetapi memiliki keterbatasan atau masalah yang teridentifikasi:

1. **Chat System**: Tabel/model chat tersedia, tetapi belum ditemukan route/controller web yang mengekspos fungsi chat.
2. **Broadcast WhatsApp Worker**: Sebagian integrasi bergantung pada konfigurasi worker eksternal; keberhasilan deployment/operasional worker tidak dapat dibuktikan dari source code.
3. **Broadcast Schedule**: Tabel dan model schedule tersedia; operasional penjadwalan belum diverifikasi melalui runtime.
4. **Rental Return Table**: Tabel `rental_returns` ada, tetapi alur return aktif tidak membuat record di sana. Data utama tersimpan pada `rentals`/`rental_items`; dashboard yang query tabel return dapat keliru.
5. **Customer sebagai Role**: Role ada, tetapi route registrasi publik tidak tersedia dan redirect customer dashboard yang dirujuk controller tidak tersedia. Customer self-service belum berfungsi sebagai alur lengkap.
6. **Policy Authorization**: Policies terdaftar pada `AuthServiceProvider`, tetapi sebagian besar route/controller menggunakan middleware role atau pemeriksaan inline. Pendaftaran policy tidak berarti seluruh aksi menggunakannya.
7. **Admin Toko Branch Scope**: Cabang utama wajib dipilih, tetapi `branch_access_type` disimpan `all`; akses data efektif perlu diperiksa per query dan bukan dijelaskan sebagai selalu satu cabang.

#### 3.9.3 Fitur yang Belum Tersedia

Berdasarkan audit source code, fitur berikut belum tersedia:

1. **Customer Self-Service**: Customer tidak dapat login dan melihat rental mereka sendiri. Tidak ada halaman atau dashboard untuk customer.
2. **Password Reset**: Tidak ada route atau controller untuk forgot password dan reset password.
3. **Email Verification**: Tidak ada fitur verifikasi email.
4. **API/JSON Endpoints**: `routes/api.php` menyediakan endpoint worker broadcast (opt-out, daily limit, update status); ini bukan API umum untuk seluruh aplikasi.
5. **Multi-language**: Sistem hanya mendukung bahasa Indonesia.
6. **Unit/Integration Tests**: Test files tersedia, tetapi keberadaannya tidak membuktikan suite lulus. Pada audit ini hasil runtime test belum dapat dinyatakan lulus; migrasi pengujian sebelumnya terhenti pada konflik index/FK notifikasi.

---

### 3.10 Kendala

Berdasarkan analisis source code Sewajas, terdapat beberapa kendala yang teridentifikasi. Kendala ini dikelompokkan berdasarkan kategori untuk memudahkan pembahasan.

**Perbaikan dan Perilaku yang Sudah Tercermin pada Source Saat Ini**

Poin berikut bukan daftar perubahan yang dilakukan pada tugas dokumentasi ini; semuanya diverifikasi sebagai perilaku kode saat audit:

1. **Activity Log**: event model mengambil file service melalui container dan melaporkan kegagalan penulisan file. Ini tidak menelan kegagalan pembuatan record database.
2. **Cabang User**: form Admin Toko meminta cabang utama; akun lama tanpa `branch_id` perlu diperbarui melalui edit user. Sales memakai akses `specific`. Admin Toko disetel `all`, sehingga query tidak boleh diasumsikan selalu satu cabang.
3. **Kode Produk**: server menghasilkan kode; form create menampilkannya sebagai non-editable dan form edit tidak menyediakan perubahan kode. Unique index melindungi duplikasi tersimpan, tetapi collision concurrent masih dapat membuat request gagal.
4. **Stok Per Ukuran**: UI Sales menampilkan produk, ukuran, stok tersedia/total dan mengirim `size_id`; backend memastikan ukuran tersebut bagian dari produk, mengubah hanya ukuran terpilih, menyinkronkan agregat, dan mencatat ukuran pada activity log.
5. **Notifikasi**: form HTML mengembalikan redirect/flash, sedangkan fetch/AJAX memakai JSON yang ditangani JavaScript; record dibatasi ke pemilik.
6. **Logout**: konfirmasi memakai modal custom. Batal tidak mengirim request; persetujuan mempertahankan POST, CSRF, route, dan controller logout yang sudah ada.

Poin-poin tersebut diverifikasi dari source code, bukan diuji terhadap hosting atau database production.

#### 3.10.1 Kendala Teknis

**1. Payment Number Race Condition (masih terbuka pada sebagian jalur)**

Status audit source 2026-09-28: `RentalService::generatePaymentNumber()` memakai penguncian/urutan dalam alur pembayaran rental, tetapi `PaymentService::create()` dan jalur timestamp lain membuat nomor `PAY-` tanpa mekanisme sequence yang sama. Unique index pada `payments.payment_number` mencegah duplikasi tersimpan, namun tabrakan concurrent masih dapat menyebabkan kegagalan request.

Bagian sistem yang terdampak: Jalur pembuatan pembayaran yang memakai nomor berbasis timestamp.

Penyebab: Generator nomor pembayaran tidak diseragamkan di semua service/controller.

Dampak: Potensi unique constraint error pada request bersamaan.

Solusi yang disarankan: Gunakan satu generator nomor terurut/terkunci untuk seluruh jalur pembayaran.

**2. Invoice Number Race Condition (sudah ditangani di source saat ini)**

Masalah lama: Nomor invoice sebelumnya dianggap hanya dijaga di application level.

Bagian sistem yang terdampak: Tabel `rentals`, kolom `invoice_number`.

Status saat ini: `invoice_number` memiliki unique index database dan generator invoice menggunakan mekanisme lock/retry.

Dampak/status: Temuan lama ini tidak lagi berlaku pada source yang diaudit. Kondisi database production tetap tidak dapat dipastikan hanya dari repository.

Solusi lama telah tercermin dalam source/migration saat ini; tidak ada perubahan migration pada tugas dokumentasi ini.

**3. Late Fee Formula Inconsistency (masih terbuka)**

Masalah: `RentalService::processReturn()` menghitung late fee berdasarkan subtotal/durasi dan faktor 0,5, sedangkan `RentalController::updateStatus()` memakai `config('app.late_fee_per_day', 0)`.

Bagian sistem yang terdampak: Perhitungan denda keterlambatan.

Penyebab: Formula bisnis berbeda antara service dan jalur controller.

Dampak: Nilai late fee dapat berbeda menurut jalur return.

Solusi yang disarankan: Samakan formula dan satu sumber konfigurasi perhitungan.

**4. RentalItem Size Serialization (masih terbuka pada jalur tertentu)**

Masalah: `RentalItem` menyimpan ukuran di `product_size` dan tidak memiliki accessor `size`, sementara beberapa payload controller membaca `$item->size`.

Bagian sistem yang terdampak: Tampilan detail rental, khususnya ukuran item.

Penyebab: Jalur serialisasi memakai property yang tidak tersedia, bukan kolom `product_size`.

Dampak: Ukuran item tidak ditampilkan di UI atau menampilkan nilai null.

Solusi yang disarankan: Serialisasikan `product_size` secara eksplisit atau tambahkan accessor dan pastikan kontrak view/API konsisten.

**5. confirmReturnAjax Membaca Attribute yang Tidak Ada (masih terbuka)**

Masalah: `RentalController::confirmReturnAjax()` membentuk field `size` dan `price` dari `$item->size` dan `$item->price`, sedangkan model menyimpan `product_size` dan `price_per_day` tanpa accessor untuk nama singkat tersebut.

Bagian sistem yang terdampak: Panel pengembalian di halaman detail rental.

Penyebab: Mismatch property model saat membangun payload.

Dampak: Field ukuran/harga pada payload berisiko kosong/null; kontrak aktual perlu dites di browser.

Solusi yang disarankan: Ambil nilai dari `product_size` dan `price_per_day`, kemudian verifikasi payload dan panel return.

**6. External Image Fallbacks**

Masalah: Model `Product` menggunakan Unsplash URL sebagai fallback foto, dan model `User` menggunakan `ui-avatars.com` sebagai fallback avatar. Jika layanan tersebut offline, gambar akan broken.

Bagian sistem yang terdampak: Tampilan produk dan avatar user.

Penyebab: Hardcoded external dependency.

Dampak: Gambar tidak muncul jika layanan eksternal tidak available.

Solusi: Menggunakan placeholder image lokal atau SVG data URI.

#### 3.10.2 Kendala Arsitektur dan Kode

**7. Activity Log Database Masih Dibuat di Banyak Pemanggil**

Masalah: Sejumlah controller/service masih membuat record melalui `ActivityLog::create([...])` secara langsung. Source saat ini sudah memiliki `ActivityLogFormatter` dan `ActivityLogFileService`, serta model event yang meminta file service menulis setelah record dibuat; service file bukan pengganti satu API terpusat untuk seluruh pembuatan record.

Bagian sistem yang terdampak: Semua controller yang melakukan logging.

Penyebab: Formatter dan file writer tersedia, tetapi pemanggilan pembuatan record database masih tersebar.

Dampak: Inkonsistensi format log, sulit mengubah format, duplikasi kode.

Solusi yang disarankan: Pertimbangkan satu API pencatatan database bila diperlukan, tanpa menghilangkan perilaku audit yang ada.

**8. Authorization Logic Tersebar antara Route dan Controller**

Masalah: Policies terdaftar di `AuthServiceProvider`, tetapi sebagian besar akses route menggunakan `CheckRole` dan sejumlah aksi memakai pemeriksaan inline; tidak semua aksi memakai policy.

Bagian sistem yang terdampak: Semua controllers.

Penyebab: Authorization logic tersebar dan tidak konsisten.

Dampak: Aturan akses tersebar dan perlu dicocokkan antara middleware, controller, dan scope query. Ini bukan bukti bypass dengan sendirinya.

Solusi yang disarankan: Pastikan seluruh route dan query memiliki aturan akses yang konsisten; pemakaian policy dapat dipertimbangkan sebagai pola.

**9. Rental Return Table Tidak Digunakan**

Masalah: Tabel `rental_returns` dibuat melalui migrasi, tetapi alur return aktif tidak membuat record di tabel tersebut. Dashboard `DashboardService` mengambil metrik pengembalian tertentu dari tabel ini.

Bagian sistem yang terdampak: Dashboard widget "Pengembalian Hari Ini", tabel `rental_returns`.

Penyebab: Business logic di-refactor untuk menyimpan return data di `rental_items` dan `rentals`, tetapi query dashboard tidak diperbarui.

Dampak: Widget/statistik yang bergantung pada tabel tersebut dapat nol atau tidak mencerminkan return yang tercatat di `rentals`/`rental_items`.

Solusi: Membuat `RentalReturn` record di `processReturn()`, atau mengganti query dashboard untuk menghitung rentals dengan `rental_status = 'returned'` dan `actual_return_date = today()`.

**10. Duplicate Route Definitions (temuan lama, sudah tidak ada di source saat ini)**

Masalah lama: Audit sebelumnya menandai route `products.update` serta laporan stok/return sebagai duplikat. Pemeriksaan source saat ini menemukan satu definisi untuk tiap route tersebut.

Bagian sistem yang terdampak: Routing.

Penyebab/status: Temuan berasal dari versi source yang lebih lama dan tidak terkonfirmasi pada route saat audit ini.

Dampak/status: Risiko duplikasi yang dicatat sebelumnya tidak terkonfirmasi lagi pada source yang diaudit.

Solusi: Tidak diperlukan perubahan berdasarkan kondisi source saat ini.

#### 3.10.3 Kendala UI dan Konsistensi

**11. Inconsistent Card Classes**

Masalah: Berbagai views menggunakan kelas card yang berbeda: `ds-card`, `card`, `card-container`, `stat-card` dengan bayangan, border, dan radius yang berbeda-beda.

Bagian sistem yang terdampak: Semua views.

Penyebab: Tidak ada standar UI yang ditegakkan.

Dampak: Tampilan tidak konsisten di seluruh aplikasi.

Solusi: Menstandarisasi penggunaan `ds-card` dengan kelas yang konsisten.

**12. Inconsistent Button Classes**

Masalah: Beberapa views menggunakan `btn-primary` / `btn-secondary`, beberapa menggunakan inline Tailwind classes, dan beberapa menggunakan Blade component `components.btn`.

Bagian sistem yang terdampak: Semua views.

Penyebab: Tidak ada standar button component.

Dampak: Tampilan button tidak konsisten.

Solusi: Menstandarisasi penggunaan button component.

**13. Dashboard Polling Every 10 Seconds**

Masalah: Dashboard melakukan polling ke `/dashboard/data` setiap 10 detik; implementasi tidak menunjukkan exponential backoff.

Bagian sistem yang terdampak: Server resources, database queries.

Penyebab: Aggressive polling strategy.

Dampak: Beban tambahan mungkin terjadi saat banyak sesi bersamaan, tetapi risiko overload belum dibuktikan dengan load test.

Solusi: Menggunakan SSE/WebSocket, atau minimal pause polling saat error 5xx.

**14. Product Code Race Condition**

Masalah: `ProductController::generateCode()` menghasilkan kode produk tanpa `lockForUpdate()`. Kolom `products.code` memiliki unique index sehingga tabrakan tidak seharusnya tersimpan sebagai duplikat, tetapi request bersamaan dapat gagal pada constraint.

Bagian sistem yang terdampak: Manajemen produk.

Penyebab: Tidak ada pessimistic lock.

Dampak: Potensi error unique constraint pada pembuatan bersamaan, bukan duplikasi diam-diam yang lolos constraint.

Solusi yang disarankan: Gunakan sequence atomik atau retry yang menangani unique-constraint collision; unique index tetap menjadi perlindungan akhir.

#### 3.10.4 Kendala Keamanan

**15. SearchController Includes Soft-Deleted Customers Without Indication**

Masalah: `SearchController::index()` menggunakan `Customer::withTrashed()`, sehingga customer soft-deleted dapat ikut dicari. Query tetap memiliki scope role/ownership; Sales dibatasi ke customer miliknya, sedangkan akses Admin Toko dapat lintas cabang karena `branch_access_type = all`.

Bagian sistem yang terdampak: Pencarian global.

Penyebab: Record terhapus tetap dimasukkan ke query pencarian; cakupan cabang bergantung pada query role.

Dampak: Hasil dapat memuat customer nonaktif tanpa penanda yang memadai; audit ini tidak menyimpulkan bahwa semua role melihat seluruh cabang.

Solusi: Memberikan scope `withTrashed()` by branch, dan menandai hasil deleted secara visual.

**16. Hardcoded "Jas%" Filter**

Masalah: `RentalController::edit()` memfilter produk hanya untuk kategori yang dimulai dengan "Jas". Filter ini mencegah edit rental dengan produk non-Jas.

Bagian sistem yang terdampak: Edit rental.

Penyebab: Hardcoded business rule legacy.

Dampak: Rental dengan produk non-Jas tidak dapat diedit.

Solusi: Menghapus hardcoded filter.

#### 3.10.5 Kendala Data

**17. Phantom Rental Statuses**

Masalah: `Rental::statusLabels()` masih menyediakan label `booked` dan `completed`, sementara alur utama memakai `waiting`, `active`, `overdue`, `returned`, dan `cancelled`.

Bagian sistem yang terdampak: Tampilan status badge.

Penyebab: Label status mencakup status yang tidak digunakan.

Dampak: Label tambahan dapat membingungkan jika dipakai untuk nilai status yang tidak dihasilkan alur utama.

Solusi yang disarankan: Selaraskan label model dengan status yang didukung atau dokumentasikan label tambahan. Tidak ada kebutuhan migration database yang terbukti dari temuan ini.

**18. Payment Retention Policy (kebutuhan belum dipastikan)**

Kondisi source: Model `Payment` tidak menggunakan trait `SoftDeletes`; aksi `destroy()` melakukan hard delete.

Bagian sistem yang terdampak: Data pembayaran.

Penyebab: SoftDeletes tidak diimplementasikan pada model Payment.

Dampak: Data pembayaran yang dihapus tidak dapat dipulihkan. Apakah ini pelanggaran audit trail bergantung pada kebijakan retensi bisnis yang belum dapat dibuktikan dari kode.

Tindak lanjut: Pastikan kebijakan retensi pembayaran terlebih dahulu. Penambahan `SoftDeletes` dan migration bukan kebutuhan yang dapat disimpulkan dari UI/source saja.

---

### 3.11 Catatan Tambahan

#### 3.11.1 Deployment

Sewajas memiliki konfigurasi deployment untuk Railway.app yang didefinisikan dalam file `railway.json`. Konfigurasi ini menunjukkan:

- **Build Command**: `composer install --no-dev --optimize-autoloader && npm install && npm run build`
- **Start Command**: `php artisan migrate --force && php artisan storage:link && php artisan serve --host 0.0.0.0 --port $PORT`
- **Restart Policy**: ON_FAILURE dengan maksimal 10 retries

Berdasarkan audit source code, konfigurasi deployment untuk Railway.app telah disiapkan. Namun, tidak terdapat bukti bahwa aplikasi telah berhasil di-deploy ke lingkungan production. Status deployment Sewajas tidak dapat dipastikan berdasarkan source code yang tersedia.

#### 3.11.2 Batasan Audit

Audit ini dilakukan dengan menganalisis source code Sewajas yang tersedia pada direktori `C:\xampp82\htdocs\rental-jas-main`. Informasi pada BAB ini direkonsiliasi terhadap source code per tanggal 28 September 2026.

Informasi yang tidak dapat dipastikan dari source code meliputi:
- Apakah aplikasi telah di-deploy ke production
- Apakah migration di repository telah dijalankan pada database production
- Apakah asset build saat ini tersedia pada hosting
- Pengalaman pribadi pengguna selama penggunaan
- Data aktual yang dimasukkan ke database
- Hasil pengujian menyeluruh, khususnya pada environment production
- Tanggal pengerjaan proyek
- Anggota tim dan peran masing-masing

---

### DAFTAR SCREENSHOT YANG PERLU DISISIPKAN

| No | Bagian | Screenshot | Sumber/Fungsi |
| -- | ------ | ---------- | ------------- |
| 3.1 | 3.4.2 | Tampilan Halaman Login Sewajas | `resources/views/auth/login.blade.php` - Form login dengan branding aplikasi |
| 3.2 | 3.7.1 | Dashboard Super Admin | `resources/views/dashboard/super-admin.blade.php` - Overview sistem multi-cabang |
| 3.3 | 3.7.2 | Dashboard Admin Toko | `resources/views/dashboard/admin.blade.php` - Dashboard Admin Toko |
| 3.4 | 3.7.3 | Dashboard Sales | `resources/views/dashboard/sales.blade.php` - Overview pribadi sales |
| 3.5 | 3.6.2 | Daftar Customer | `resources/views/customers/index.blade.php` - Tabel customer dengan search dan filter |
| 3.6 | 3.6.2 | Detail Customer | `resources/views/customers/show.blade.php` - Info customer + riwayat rental |
| 3.7 | 3.6.3 | Daftar Produk | `resources/views/products/index.blade.php` - Grid produk dengan filter |
| 3.8 | 3.6.3 | Detail Produk | `resources/views/products/show.blade.php` - Info produk + stok + QR code |
| 3.9 | 3.6.4 | Daftar Rental | `resources/views/rentals/index.blade.php` - Tabel rental dengan filter status |
| 3.10 | 3.6.4 | Form Buat Rental | `resources/views/rentals/create.blade.php` - Form pemilihan customer, produk, jaminan |
| 3.11 | 3.6.4 | Detail Rental | `resources/views/rentals/show.blade.php` - Info lengkap rental |
| 3.12 | 3.6.6 | Panel Pengembalian | `resources/views/rentals/show.blade.php` - Form cek kondisi barang |
| 3.13 | 3.6.4 | Scan QR | `resources/views/rentals/scan.blade.php` - Halaman scan QR dengan kamera |
| 3.14 | 3.6.4 | Hasil Scan QR | `resources/views/rentals/scan-result.blade.php` - Detail rental dari QR scan |
| 3.15 | 3.6.11 | Laporan Pendapatan | `resources/views/reports/revenue.blade.php` - Grafik dan tabel pendapatan |
| 3.16 | 3.6.11 | Laporan Transaksi | `resources/views/reports/transactions.blade.php` - Daftar transaksi dengan filter |
| 3.17 | 3.6.8 | Broadcast Campaigns | `resources/views/broadcast_campaigns/index.blade.php` - Daftar campaign |
| 3.18 | 3.6.8 | Form Broadcast | `resources/views/broadcast_campaigns/create.blade.php` - Form buat campaign |
| 3.19 | 3.6.9 | Points Leaderboard | `resources/views/points/index.blade.php` - Peringkat sales |
| 3.20 | 3.6.12 | Profile | `resources/views/profile/edit.blade.php` - Edit profile dan theme |
| 3.21 | 3.6.13 | Settings | `resources/views/settings/index.blade.php` - Pengaturan aplikasi |
| 3.22 | 3.5 | Manajemen User | `resources/views/users/index.blade.php` - Daftar user |
| 3.23 | 3.5 | Manajemen Cabang | `resources/views/branches/index.blade.php` - Daftar cabang |
| 3.24 | 3.6.2 | Customer Blacklist | `resources/views/customers/index.blade.php` - Filter blacklist |

---

### DAFTAR SCREENSHOT CODE YANG PERLU DISISIPKAN

| No | Nama Gambar | File | Bagian Code | Penjelasan |
| -- | ----------- | ---- | ----------- | ----------- |
| 3.1 | Potongan Kode generateInvoiceNumber | `app/Services/RentalService.php` | Baris 260-310 | Menunjukkan race-condition safe invoice generation dengan lockForUpdate() |
| 3.2 | Potongan Kode createRental | `app/Services/RentalService.php` | Baris 325-467 | Menunjukkan validasi stok, stock locking, guarantee creation, dan QR generation |
| 3.3 | Potongan Kode processPayment | `app/Services/RentalService.php` | Baris 469-524 | Menunjukkan logika pembayaran dan recalculation payment status |
| 3.4 | Potongan Kode processReturn | `app/Services/RentalService.php` | Baris 526-588 | Menunjukkan logika pengembalian, late fee, stock update, dan points |
| 3.5 | Potongan Kode Role Constants | `app/Models/User.php` | Baris 37-49 | Menunjukkan 4 role yang tersedia dalam sistem |
| 3.6 | Potongan Kode CheckRole Middleware | `app/Http/Middleware/CheckRole.php` | Seluruh file | Menunjukkan otorisasi berbasis role |
| 3.7 | Potongan Kode EnsureBranchScope Middleware | `app/Http/Middleware/EnsureBranchScope.php` | Seluruh file | Menunjukkan pemeriksaan status akun dan akses cabang; filter data diterapkan oleh query fitur |
| 3.8 | Potongan Kode Rental Status Constants | `app/Models/Rental.php` | Baris 42-56 | Menukkan status rental, payment, dan fine |
| 3.9 | Potongan Kode Activity Log Model | `app/Models/ActivityLog.php` | Seluruh file | Menunjukkan struktur log aktivitas |
| 3.10 | Potongan Kode Broadcast Campaign Store | `app/Http/Controllers/BroadcastCampaignController.php` | Baris 82-166 | Menunjukkan pembuatan campaign broadcast dengan targeting |
| 3.11 | Potongan Kode Report Revenue | `app/Http/Controllers/ReportController.php` | Baris 81-136 | Menunjukkan laporan pendapatan dengan filter dan export |
| 3.12 | Potongan Kode Customer normalizePhone | `app/Http/Controllers/CustomerController.php` | Baris 431-442 | Menunjukkan normalisasi nomor telepon |
| 3.13 | Potongan Kode Route Definition | `routes/web.php` | Baris 37-222 | Menunjukkan struktur route dengan middleware role |
| 3.14 | Potongan Kode Dashboard Payload | `app/Services/DashboardService.php` | Baris 64-418 | Menunjukkan aggregate queries untuk dashboard |

---

## 4. DOKUMENTASI TEKNIS LENGKAP SEWAJAS

### 4.1 Informasi Project

**Nama Project:** Sewajas (Sewa Jas Rental)  
**Nama Aplikasi:** RentalJas  
**Framework:** Laravel v12.62.0  
**PHP Requirement:** ^8.2  
**Database:** MySQL  
**Frontend Stack:**
- Tailwind CSS v4
- Vite v7 (bundler)
- AlpineJS (interactive components)
- Chart.js (dashboard charts)
- html5-qrcode (QR scanner)

**Packages Utama:**
- `spatie/laravel-permission` — Role & Permission management
- `maatwebsite/excel` — Export Excel (Maatwebsite Excel)
- `barryvdh/laravel-dompdf` — Export PDF
- `simplesoftwareio/simple-qrcode` — QR Code generation
- `html5-qrcode` — QR Code scanning di browser

**Deployment:** Konfigurasi untuk Railway.app (`railway.json`)  
**Build Command:** `composer install --no-dev --optimize-autoloader && npm install && npm run build`  
**Start Command:** `php artisan migrate --force && php artisan storage:link && php artisan serve --host 0.0.0.0 --port $PORT`

---

### 4.2 Struktur Folder Lengkap

```
rental-jas-main/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── AuditPaymentsCommand.php
│   │       ├── AuditQrCommand.php
│   │       ├── RegenerateRentalQrsCommand.php
│   │       └── Kernel.php
│   ├── Events/
│   ├── Exports/
│   │   ├── CustomerExport.php
│   │   └── ReportsExport.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── AuthenticatedSessionController.php
│   │   │   │   ├── RegisteredUserController.php
│   │   │   │   └── AuthController.php
│   │   │   ├── BroadcastCampaignController.php
│   │   │   ├── BroadcastController.php
│   │   │   ├── BranchController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── CustomerController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── NotificationController.php
│   │   │   ├── PaymentController.php
│   │   │   ├ PointsController.php
│   │   │   ├── ProductController.php
│   │   │   ├── ProfileController.php
│   │   │   ├── RentalController.php
│   │   │   ├── RentalReceiptController.php
│   │   │   ├── ReportController.php
│   │   │   ├── SearchController.php
│   │   │   ├── SettingsController.php
│   │   │   ├── TransactionController.php
│   │   │   ├── UserController.php
│   │   │   └── Controller.php
│   │   ├── Middleware/
│   │   │   ├── CheckRole.php
│   │   │   ├── EnsureBranchAccess.php
│   │   │   ├── EnsureBranchScope.php
│   │   │   └── RoleMiddleware.php
│   │   └── Requests/
│   │       └── StoreRentalRequest.php
│   ├── Jobs/
│   │   └── SendBroadcastMessage.php
│   ├── Models/
│   │   ├── ActivityLog.php
│   │   ├── Branch.php
│   │   ├── Category.php
│   │   ├── Chat.php
│   │   ├── Customer.php
│   │   ├── Guarantee.php
│   │   ├── Laundry.php
│   │   ├── Message.php
│   │   ├── Notification.php
│   │   ├── Payment.php
│   │   ├── Product.php
│   │   ├── Rental.php
│   │   ├── RentalItem.php
│   │   ├── RentalReturn.php
│   │   ├── StatusHistory.php
│   │   ├── User.php
│   │   └── Broadcast/
│   │       ├── BroadcastCampaign.php
│   │       ├── BroadcastLog.php
│   │       └── BroadcastTemplate.php
│   ├── Policies/
│   │   ├── BranchPolicy.php
│   │   ├── CategoryPolicy.php
│   │   ├── CustomerPolicy.php
│   │   └── ProductPolicy.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   ├── AuthServiceProvider.php
│   │   └── RouteServiceProvider.php
│   └── Services/
│       ├── ActivityLogFileService.php
│       ├── ActivityLogFormatter.php
│       ├── DashboardService.php
│       ├── NotificationService.php
│       ├── PaymentService.php
│       └── RentalService.php
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/ (58 migration files pada source yang diaudit)
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── DemoDataSeeder.php
│       ├── ErpDataSeeder.php
│       ├── FixAllDataSeeder.php
│       ├── GenerateAllQrSeeder.php
│       ├── ProductPhotoSeeder.php
│       ├── RegenerateAllRentalQrSeeder.php
│       ├── RentalDemoSeeder.php
│       └── RolePermissionSeeder.php
├── public/
├── resources/
│   ├── css/
│   ├── js/
│   └── views/ (88 blade files)
├── routes/
│   ├── api.php
│   └── web.php
├── storage/
├── tests/
├── composer.json
├── package.json
├── vite.config.js
├── tailwind.config.js
├── postcss.config.js
└── railway.json
```

---

### 4.3 Daftar Seluruh Route

#### 4.3.1 Routes Web (`routes/web.php`)

**Auth Routes:**
- `GET /login` → `AuthenticatedSessionController@create` (name: `login`)
- `POST /login` → `AuthenticatedSessionController@store` (name: `login.post`)
- `POST /logout` → `AuthenticatedSessionController@destroy` (name: `logout`)

**Dashboard Routes:**
- `GET /dashboard` → `DashboardController@index` (name: `dashboard`)
- `GET /dashboard/data` → `DashboardController@data` (name: `dashboard.data`)
- `GET /dashboard/activity-log/download` → `DashboardController@downloadActivityLog` (name: `dashboard.activity-log.download`)

**Search Routes:**
- `GET /search` → `SearchController@index` (name: `search.index`)

**Profile Routes:**
- `GET /profile` → `ProfileController@edit` (name: `profile.edit`)
- `PATCH /profile` → `ProfileController@update` (name: `profile.update`)
- `DELETE /profile` → `ProfileController@destroy` (name: `profile.destroy`)
- `PUT /profile/theme` → `ProfileController@updateTheme` (name: `profile.theme`)
- `PUT /profile/password` → `ProfileController@updatePassword` (name: `profile.password`)

**Notification Routes:**
- `GET /notifications` → `NotificationController@index` (name: `notifications.index`)
- `GET /notifications/data` → `NotificationController@data`
- `GET /notifications/count` → `NotificationController@count`
- `POST /notifications/read-all` → `NotificationController@markAllRead` (name: `notifications.read-all`)
- `POST /notifications/{id}/read` → `NotificationController@markRead` (name: `notifications.read`)
- `DELETE /notifications/{id}` → `NotificationController@destroy` (name: `notifications.destroy`)
- `GET /notifications/{id}` → `NotificationController@show` (name: `notifications.show`)

**Broadcast Routes:**
- `GET /broadcasts` → `BroadcastController@index` (name: `broadcasts.index`)
- `POST /broadcasts` → `BroadcastController@store` (name: `broadcasts.store`)
- `DELETE /broadcasts/{broadcast}` → `BroadcastController@destroy` (name: `broadcasts.destroy`)

**Broadcast Campaign Routes:**
- `GET /broadcast-campaigns` → `BroadcastCampaignController@index` (name: `broadcast-campaigns.index`)
- `GET /broadcast-campaigns/create` → `BroadcastCampaignController@create` (name: `broadcast-campaigns.create`)
- `POST /broadcast-campaigns` → `BroadcastCampaignController@store` (name: `broadcast-campaigns.store`)
- `GET /broadcast-campaigns/{campaign}` → `BroadcastCampaignController@show` (name: `broadcast-campaigns.show`)
- `POST /broadcast-campaigns/{campaign}/send/{customer}` → `BroadcastCampaignController@sendToCustomer` (name: `broadcast-campaigns.send-to-customer`)
- `POST /broadcast-campaigns/{campaign}/message` → `BroadcastCampaignController@updateMessage` (name: `broadcast-campaigns.update-message`)
- `DELETE /broadcast-campaigns/{campaign}` → `BroadcastCampaignController@destroy` (name: `broadcast-campaigns.destroy`)

**Customer Routes:**
- `GET /customers` → `CustomerController@index` (name: `customers.index`)
- `GET /customers/search` → `CustomerController@search` (name: `customers.search`)
- `GET /customers/create` → `CustomerController@create` (name: `customers.create`)
- `POST /customers` → `CustomerController@store` (name: `customers.store`)
- `GET /customers/export` → `CustomerController@export` (name: `customers.export`)
- `GET /customers/{customer}/edit` → `CustomerController@edit` (name: `customers.edit`)
- `PATCH /customers/{customer}` → `CustomerController@update` (name: `customers.update`)
- `PATCH /customers/{customer}/blacklist` → `CustomerController@toggleBlacklist` (name: `customers.blacklist`)
- `GET /customers/{customer}` → `CustomerController@show` (name: `customers.show`)
- `POST /customers/{customer}/restore` → `CustomerController@restore` (name: `customers.restore`)
- `DELETE /customers/{customer}` → `CustomerController@destroy` (name: `customers.destroy`)
- `DELETE /customers/{customer}/force-destroy` → `CustomerController@forceDestroy` (name: `customers.force-destroy`)

**Rental Routes:**
- `GET /rentals/scan` → `RentalController@scanPage` (name: `rentals.scan`)
- `GET /rentals/scan/{invoice}` → `RentalController@scanQr` (name: `rentals.scan.show`)
- `GET /rentals` → `RentalController@index` (name: `rentals.index`)
- `GET /rentals/activity-log` → `RentalController@activityLog` (name: `rentals.activity-log`)
- `GET /rentals/activity-log/export/excel` → `RentalController@exportActivityLogExcel` (name: `rentals.activity-log.export.excel`)
- `GET /rentals/activity-log/export/pdf` → `RentalController@exportActivityLogPdf` (name: `rentals.activity-log.export.pdf`)
- `GET /rentals/create` → `RentalController@create` (name: `rentals.create`)
- `POST /rentals` → `RentalController@store` (name: `rentals.store`)
- `GET /rentals/{rental}` → `RentalController@show` (name: `rentals.show`)
- `POST /rentals/{rental}/payment` → `RentalController@processPayment` (name: `rentals.payment`)
- `PATCH /rentals/{rental}/payments/{payment}` → `RentalController@paymentUpdate` (name: `rentals.payment.update`)
- `DELETE /rentals/{rental}/payments/{payment}` → `RentalController@paymentDestroy` (name: `rentals.payment.destroy`)
- `POST /rentals/{rental}/payments/{payment}/refund` → `RentalController@paymentRefund` (name: `rentals.payment.refund`)
- `POST /rentals/{rental}/mark-refund-given` → `RentalController@markRefundGiven` (name: `rentals.mark-refund-given`)
- `POST /rentals/{rental}/payments/{payment}/void` → `RentalController@paymentVoid` (name: `rentals.payment.void`)
- `POST /rentals/{rental}/return` → `RentalController@processReturn` (name: `rentals.return`)
- `PATCH /rentals/{rental}/cancel-return` → `RentalController@cancelReturn` (name: `rentals.cancel-return`)
- `PATCH /rentals/{rental}/handover` → `RentalController@handoverRental` (name: `rentals.handover`)
- `PATCH /rentals/{rental}/update-status` → `RentalController@updateStatus` (name: `rentals.update-status`)
- `PATCH /rentals/{rental}/confirm-return-ajax` → `RentalController@confirmReturnAjax` (name: `rentals.confirm-return-ajax`)
- `GET /rentals/{rental}/thermal` → `RentalController@thermalPrint` (name: `rentals.thermal`)
- `GET /rentals/{rental}/pdf` → `RentalController@exportPdf` (name: `rentals.pdf`)
- `GET /rentals/{rental}/whatsapp` → `RentalController@whatsapp` (name: `rentals.whatsapp`)
- `GET /rentals/{rental}/reminder` → `RentalController@sendReminder` (name: `rentals.reminder`)
- `GET /rentals/{rental}/receipt` → `RentalReceiptController@show` (name: `rentals.receipt.show`)
- `GET /rentals/{rental}/receipt/pdf` → `RentalReceiptController@pdf` (name: `rentals.receipt.pdf`)
- `GET /rentals/{rental}/receipt/qr` → `RentalReceiptController@qr` (name: `rentals.receipt.qr`)
- `GET /rentals/{rental}/receipt/whatsapp` → `RentalReceiptController@whatsapp` (name: `rentals.receipt.whatsapp`)
- `PATCH /rentals/{rental}/cancel` → `RentalController@cancel` (name: `rentals.cancel`)
- `GET /rentals/{rental}/edit` → `RentalController@edit` (name: `rentals.edit`)
- `PATCH /rentals/{rental}` → `RentalController@update` (name: `rentals.update`)
- `DELETE /rentals/{rental}` → `RentalController@destroy` (name: `rentals.destroy`)
- `GET /rentals/{rental}/activity-log/download` → `RentalController@downloadActivityLog` (name: `rentals.activity-log.download`)

**Product Routes:**
- `GET /products` → `ProductController@index` (name: `products.index`)
- `GET /products/create` → `ProductController@create` (name: `products.create`)
- `POST /products` → `ProductController@store` (name: `products.store`)
- `GET /products/{product}/edit` → `ProductController@edit` (name: `products.edit`)
- `PUT /products/{product}` → `ProductController@update` (name: `products.update`)
- `DELETE /products/{product}` → `ProductController@destroy` (name: `products.destroy`)
- `GET /products/{product}` → `ProductController@show` (name: `products.show`)
- `PATCH /products/{product}/update-stock` → `ProductController@updateStock` (name: `products.update-stock`)

**Category Routes:**
- `GET /categories` → `CategoryController@index` (name: `categories.index`)
- `GET /categories/create` → `CategoryController@create` (name: `categories.create`)
- `POST /categories` → `CategoryController@store` (name: `categories.store`)
- `GET /categories/{category}/edit` → `CategoryController@edit` (name: `categories.edit`)
- `PATCH /categories/{category}` → `CategoryController@update` (name: `categories.update`)
- `DELETE /categories/{category}` → `CategoryController@destroy` (name: `categories.destroy`)

**Branch Routes:**
- `GET /branches` → `BranchController@index` (name: `branches.index`)
- `GET /branches/create` → `BranchController@create` (name: `branches.create`)
- `POST /branches` → `BranchController@store` (name: `branches.store`)
- `GET /branches/{branch}` → `BranchController@show` (name: `branches.show`)
- `GET /branches/{branch}/edit` → `BranchController@edit` (name: `branches.edit`)
- `PATCH /branches/{branch}` → `BranchController@update` (name: `branches.update`)
- `DELETE /branches/{branch}` → `BranchController@destroy` (name: `branches.destroy`)

**User Routes:**
- `GET /users` → `UserController@index` (name: `users.index`)
- `GET /users/create` → `UserController@create` (name: `users.create`)
- `POST /users` → `UserController@store` (name: `users.store`)
- `GET /users/{user}/edit` → `UserController@edit` (name: `users.edit`)
- `PATCH /users/{user}` → `UserController@update` (name: `users.update`)
- `PATCH /users/{user}/toggle` → `UserController@toggle` (name: `users.toggle`)
- `DELETE /users/{user}` → `UserController@destroy` (name: `users.destroy`)
- `GET /users/{user}` → `UserController@show` (name: `users.show`)

**Settings Routes:**
- `GET /settings` → `SettingsController@index` (name: `settings.index`)
- `POST /settings` → `SettingsController@update` (name: `settings.update`)

**Report Routes:**
- `GET /reports/revenue` → `ReportController@revenue` (name: `reports.revenue`)
- `GET /reports/transactions` → `ReportController@transactions` (name: `reports.transactions`)
- `GET /reports/returns` → `ReportController@returns` (name: `reports.returns`)
- `GET /reports/stock` → `ReportController@stock` (name: `reports.stock`)
- `GET /reports/customers` → `ReportController@customers` (name: `reports.customers`)
- `GET /reports/payments` → `ReportController@payments` (name: `reports.payments`)
- `GET /reports/activity-log` → `ReportController@activityLog` (name: `reports.activity-log`)
- `GET /reports/activity-log/export/csv` → `ReportController@exportActivityLog` (name: `reports.activity-log.export.csv`)
- `GET /reports/activity-log/export/pdf` → `ReportController@exportActivityLogPdf` (name: `reports.activity-log.export.pdf`)
- `GET /reports/revenue/export/excel` → `ReportController@exportRevenueExcel` (name: `reports.export.revenue.excel`)
- `GET /reports/revenue/export/pdf` → `ReportController@exportRevenuePdf` (name: `reports.export.revenue.pdf`)
- `GET /reports/transactions/export/excel` → `ReportController@exportTransactionsExcel` (name: `reports.export.transactions.excel`)
- `GET /reports/transactions/export/pdf` → `ReportController@exportTransactionsPdf` (name: `reports.export.transactions.pdf`)
- `GET /reports/returns/export/excel` → `ReportController@exportReturnsExcel` (name: `reports.export.returns.excel`)
- `GET /reports/returns/export/pdf` → `ReportController@exportReturnsPdf` (name: `reports.export.returns.pdf`)
- `GET /reports/stock/export/excel` → `ReportController@exportStockExcel` (name: `reports.export.stock.excel`)
- `GET /reports/stock/export/pdf` → `ReportController@exportStockPdf` (name: `reports.export.stock.pdf`)
- `GET /reports/customers/export/excel` → `ReportController@exportCustomersExcel` (name: `reports.export.customers.excel`)
- `GET /reports/customers/export/pdf` → `ReportController@exportCustomersPdf` (name: `reports.export.customers.pdf`)
- `GET /reports/payments/export/excel` → `ReportController@exportPaymentsExcel` (name: `reports.export.payments.excel`)
- `GET /reports/payments/export/pdf` → `ReportController@exportPaymentsPdf` (name: `reports.export.payments.pdf`)
- `GET /reports/export/excel` → `ReportController@exportExcel` (name: `reports.export.excel`)
- `GET /reports/export/pdf` → `ReportController@exportPdf` (name: `reports.export.pdf`)

**Super Admin Payment/Transaction Routes:**
- `POST /payments/{invoice}/store` → `PaymentController@store` (name: `payments.store`)
- `PATCH /payments/{invoice}/{payment}` → `PaymentController@update` (name: `payments.update`)
- `DELETE /payments/{invoice}/{payment}` → `PaymentController@destroy` (name: `payments.destroy`)
- `PATCH /payments/{invoice}/{payment}/void` → `PaymentController@void` (name: `payments.void`)
- `PATCH /payments/{invoice}/{payment}/refund` → `PaymentController@refund` (name: `payments.refund`)
- `GET /transactions` → `TransactionController@index` (name: `transactions.index`)
- `GET /transactions/{transaction}` → `TransactionController@show` (name: `transactions.show`)
- `GET /transactions/{transaction}/edit` → `TransactionController@edit` (name: `transactions.edit`)
- `PATCH /transactions/{transaction}` → `TransactionController@update` (name: `transactions.update`)
- `DELETE /transactions/{transaction}` → `TransactionController@destroy` (name: `transactions.destroy`)

**Points Routes:**
- `GET /points` → `PointsController@index` (name: `points.index`)

#### 4.3.2 Routes API (`routes/api.php`)

**Worker Routes (prefix: `/api/worker`):**
- `POST /api/worker/opt-out` → `BroadcastCampaignController@handleOptOut` (name: `worker.opt-out`)
- `GET /api/worker/daily-limit` → `BroadcastCampaignController@checkDailyLimit` (name: `worker.daily-limit`)
- `POST /api/worker/update-status` → `BroadcastCampaignController@updateMessageStatus` (name: `worker.update-status`)

---

### 4.4 Daftar Seluruh Model

| No | Model | File | Tabel | Fitur Utama |
| -- | ----- | ---- | ----- | ----------- |
| 4.1 | User | `app/Models/User.php` | `users` | Role constants, SoftDeletes, accessor `isSuperAdmin()`, `canManage()`, `isAdminToko()`, `isSales()` |
| 4.2 | Branch | `app/Models/Branch.php` | `branches` | SoftDeletes, relasi ke User/Product/Customer/Rental |
| 4.3 | Category | `app/Models/Category.php` | `categories` | Fillable: name, slug, icon, sort_order, is_active |
| 4.4 | Product | `app/Models/Product.php` | `products` | SoftDeletes, accessor `qr_code_url`, scope `available()` |
| 4.5 | Customer | `app/Models/Customer.php` | `customers` | SoftDeletes, scope `active()`, accessor `formatted_phone` |
| 4.6 | Rental | `app/Models/Rental.php` | `rentals` | Status constants (rental_status, payment_status, fine_status), relasi ke RentalItem/Payment/Guarantee/RentalReturn/ActivityLog |
| 4.7 | RentalItem | `app/Models/RentalItem.php` | `rental_items` | Fillable: rental_id, product_id, product_name, product_size, product_color, quantity, price_per_day, duration_days, subtotal, return_condition, damage_fee, is_returned, returned_at |
| 4.8 | RentalReturn | `app/Models/RentalReturn.php` | `rental_returns` | Fillable: rental_id, returned_at, late_days, late_fee, condition, return_notes |
| 4.9 | Payment | `app/Models/Payment.php` | `payments` | Fillable: rental_id, received_by, payment_number, amount, method, type, paid_at, notes |
| 4.10 | Guarantee | `app/Models/Guarantee.php` | `guarantees` | Fillable: rental_id, type, id_number, id_name, deposit_amount, status, returned_at |
| 4.11 | Laundry | `app/Models/Laundry.php` | `laundries` | SoftDeletes, relasi ke Rental/Product, accessor `status_label` > **Catatan:** Model ini ada di codebase, tetapi belum ada UI/route/controller yang menggunakannya. Disiapkan untuk pengembangan masa depan. |
| 4.12 | ActivityLog | `app/Models/ActivityLog.php` | `activity_logs` | Cast: old_values/new_values (array), booted event untuk file logging |
| 4.13 | Notification | `app/Models/Notification.php` | `notifications` | Fillable: notifiable_type, notifiable_id, branch_id, type, title, message, icon, color, action_url, meta, is_read, read_at |
| 4.14 | Message | `app/Models/Message.php` | `messages` | Relasi ke Chat |
| 4.15 | Chat | `app/Models/Chat.php` | `chats` | Relasi ke Message |
| 4.16 | StatusHistory | `app/Models/StatusHistory.php` | `status_histories` | Relasi ke Rental |
| 4.17 | BroadcastCampaign | `app/Models/Broadcast/BroadcastCampaign.php` | `broadcast_campaigns` | Cast: target_filters, channels, custom_message, scheduled_at, started_at, completed_at |
| 4.18 | BroadcastLog | `app/Models/Broadcast/BroadcastLog.php` | `broadcast_logs` | Cast: sent_at (datetime), attempt_count (integer) |
| 4.19 | BroadcastTemplate | `app/Models/Broadcast/BroadcastTemplate.php` | `broadcast_templates` | SoftDeletes |
| 4.20 | ProductSize | `app/Models/ProductSize.php` | `product_sizes` | Ukuran dan stok per produk; relasi ke Product |

---

### 4.5 Struktur Database Lengkap

#### 4.5.1 Tabel `users`
- `id` BIGINT UNSIGNED PK AI
- `name` VARCHAR(255)
- `email` VARCHAR(255) UNIQUE
- `email_verified_at` TIMESTAMP NULL
- `password` VARCHAR(255)
- `phone` VARCHAR(20) NULL
- `role` VARCHAR(50) DEFAULT 'sales'
- `branch_id` BIGINT UNSIGNED FK NULL
- `branch_access_type` ENUM('all', 'specific') DEFAULT 'specific'
- `is_active` TINYINT(1) DEFAULT 1
- `remember_token` VARCHAR(100) NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP
- `deleted_at` TIMESTAMP NULL (SoftDeletes)

Cabang utama disimpan pada `branch_id`; relasi cabang spesifik user disimpan terpisah pada pivot `user_branch_access`.

#### 4.5.2 Tabel `branches`
- `id` BIGINT UNSIGNED PK AI
- `name` VARCHAR(255)
- `code` VARCHAR(50) UNIQUE
- `address` TEXT NULL
- `phone` VARCHAR(20) NULL
- `email` VARCHAR(255) NULL
- `city` VARCHAR(100) NULL
- `province` VARCHAR(100) NULL
- `is_active` TINYINT(1) DEFAULT 1
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP
- `deleted_at` TIMESTAMP NULL (SoftDeletes)

#### 4.5.3 Tabel `categories`
- `id` BIGINT UNSIGNED PK AI
- `name` VARCHAR(255)
- `slug` VARCHAR(255) UNIQUE
- `icon` VARCHAR(100) NULL
- `sort_order` INT DEFAULT 0
- `is_active` TINYINT(1) DEFAULT 1
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

#### 4.5.4 Tabel `products`
- `id` BIGINT UNSIGNED PK AI
- `branch_id` BIGINT UNSIGNED FK
- `category_id` BIGINT UNSIGNED FK
- `code` VARCHAR(50) UNIQUE
- `name` VARCHAR(255)
- `size` VARCHAR(20) NULL (legacy; current size inventory is in `product_sizes`)
- `color` VARCHAR(50) NULL
- `brand` VARCHAR(100) NULL
- `rental_price` DECIMAL(10,2)
- `deposit_price` DECIMAL(10,2) NULL
- `stock_total` INT DEFAULT 0
- `stock_available` INT DEFAULT 0
- `condition` VARCHAR(50) NULL
- `status` VARCHAR(50) DEFAULT 'available'
- `photo` VARCHAR(255) NULL
- `qr_code` VARCHAR(255) NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP
- `deleted_at` TIMESTAMP NULL (SoftDeletes)

Variasi ukuran/stok berada pada `product_sizes` (`product_id`, `size`, `stock_total`, `stock_available`), dengan unique gabungan produk dan ukuran. Nilai stock di `products` merupakan agregat tersinkronisasi.

#### 4.5.5 Tabel `customers`
- `id` BIGINT UNSIGNED PK AI
- `branch_id` BIGINT UNSIGNED FK
- `user_id` BIGINT UNSIGNED FK NULL
- `name` VARCHAR(255)
- `phone` VARCHAR(20) UNIQUE
- `email` VARCHAR(255) NULL
- `address` TEXT NULL
- `is_blacklisted` TINYINT(1) DEFAULT 0
- `opt_out` TINYINT(1) DEFAULT 0
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP
- `deleted_at` TIMESTAMP NULL (SoftDeletes)

#### 4.5.6 Tabel `rentals`
- `id` BIGINT UNSIGNED PK AI
- `invoice_number` VARCHAR(100) UNIQUE
- `branch_id` BIGINT UNSIGNED FK
- `customer_id` BIGINT UNSIGNED FK
- `created_by` BIGINT UNSIGNED FK
- `rental_date` DATE
- `return_due_date` DATE
- `actual_return_date` DATE NULL
- `duration_days` INT
- `subtotal` DECIMAL(12,2)
- `discount` DECIMAL(12,2) DEFAULT 0
- `late_fee` DECIMAL(12,2) DEFAULT 0
- `total_amount` DECIMAL(12,2)
- `paid_amount` DECIMAL(12,2) DEFAULT 0
- `change_amount` DECIMAL(12,2) DEFAULT 0
- `payment_status` VARCHAR(50) DEFAULT 'unpaid' (unpaid/partial/paid)
- `rental_status` VARCHAR(50) DEFAULT 'waiting' (waiting/active/overdue/returned/cancelled)
- `qr_code` VARCHAR(255) NULL
- `notes` TEXT NULL
- `returned_at` DATETIME NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

#### 4.5.7 Tabel `rental_items`
- `id` BIGINT UNSIGNED PK AI
- `rental_id` BIGINT UNSIGNED FK
- `product_id` BIGINT UNSIGNED FK NULL
- `product_name` VARCHAR(255)
- `product_size` VARCHAR(20) NULL
- `product_color` VARCHAR(50) NULL
- `quantity` INT DEFAULT 1
- `price_per_day` DECIMAL(10,2)
- `duration_days` INT
- `subtotal` DECIMAL(12,2)
- `return_condition` VARCHAR(50) NULL (baik/kurang_baik/rusak_ringan/rusak_berat/hilang)
- `damage_fee` DECIMAL(10,2) DEFAULT 0
- `is_returned` TINYINT(1) DEFAULT 0
- `returned_at` DATETIME NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

#### 4.5.8 Tabel `rental_returns`
- `id` BIGINT UNSIGNED PK AI
- `rental_id` BIGINT UNSIGNED FK
- `returned_at` DATE
- `late_days` INT DEFAULT 0
- `late_fee` DECIMAL(12,2) DEFAULT 0
- `condition` VARCHAR(50) NULL
- `return_notes` TEXT NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

#### 4.5.9 Tabel `payments`
- `id` BIGINT UNSIGNED PK AI
- `rental_id` BIGINT UNSIGNED FK
- `received_by` BIGINT UNSIGNED FK
- `payment_number` VARCHAR(100) UNIQUE
- `amount` DECIMAL(12,2)
- `method` VARCHAR(50) (cash/transfer/qris)
- `type` VARCHAR(50) (rental/deposit/late_fee)
- `paid_at` DATETIME
- `notes` TEXT NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

#### 4.5.10 Tabel `guarantees`
- `id` BIGINT UNSIGNED PK AI
- `rental_id` BIGINT UNSIGNED FK
- `type` VARCHAR(50) (ktp/sim/deposit)
- `id_number` VARCHAR(50) NULL
- `id_name` VARCHAR(255) NULL
- `deposit_amount` DECIMAL(12,2) NULL
- `status` VARCHAR(50) DEFAULT 'held' (held/returned)
- `returned_at` DATETIME NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

#### 4.5.11 Tabel `laundries`
- `id` BIGINT UNSIGNED PK AI
- `rental_id` BIGINT UNSIGNED FK
- `product_id` BIGINT UNSIGNED FK NULL
- `status` VARCHAR(50) DEFAULT 'pending' (pending/in_progress/completed)
- `notes` TEXT NULL
- `completed_at` DATETIME NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP
- `deleted_at` TIMESTAMP NULL (SoftDeletes)

#### 4.5.12 Tabel `activity_logs`
- `id` BIGINT UNSIGNED PK AI
- `user_id` BIGINT UNSIGNED FK NULL
- `branch_id` BIGINT UNSIGNED FK NULL
- `action` VARCHAR(100)
- `model_type` VARCHAR(255)
- `model_id` BIGINT UNSIGNED
- `description` TEXT NULL
- `old_values` JSON NULL
- `new_values` JSON NULL
- `ip_address` VARCHAR(45) NULL
- `user_agent` TEXT NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP NULL (ActivityLog uses `UPDATED_AT = null`)

#### 4.5.13 Tabel `notifications`
- `id` BIGINT UNSIGNED PK AI
- `notifiable_type` VARCHAR(255)
- `notifiable_id` BIGINT UNSIGNED
- `branch_id` BIGINT UNSIGNED FK NULL
- `type` VARCHAR(100)
- `title` VARCHAR(255)
- `message` TEXT
- `icon` VARCHAR(50) NULL
- `color` VARCHAR(50) NULL
- `action_url` VARCHAR(255) NULL
- `meta` JSON NULL
- `is_read` TINYINT(1) DEFAULT 0
- `read_at` TIMESTAMP NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

#### 4.5.14 Tabel `messages`
- `id` BIGINT UNSIGNED PK AI
- `chat_id` BIGINT UNSIGNED FK
- `sender_id` BIGINT UNSIGNED FK
- `sender_type` VARCHAR(255)
- `message` TEXT
- `is_read` TINYINT(1) DEFAULT 0
- `read_at` TIMESTAMP NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

#### 4.5.15 Tabel `chats`
- `id` BIGINT UNSIGNED PK AI
- `user_id` BIGINT UNSIGNED FK
- `customer_id` BIGINT UNSIGNED FK NULL
- `last_message` TEXT NULL
- `last_message_at` TIMESTAMP NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

#### 4.5.16 Tabel `status_histories`
- `id` BIGINT UNSIGNED PK AI
- `rental_id` BIGINT UNSIGNED FK
- `old_status` VARCHAR(50) NULL
- `new_status` VARCHAR(50)
- `changed_by` BIGINT UNSIGNED FK NULL
- `notes` TEXT NULL
- `created_at` TIMESTAMP

#### 4.5.17 Tabel `broadcast_campaigns`
- `id` BIGINT UNSIGNED PK AI
- `name` VARCHAR(255)
- `type` VARCHAR(50)
- `trigger_event` VARCHAR(100) NULL
- `template_id` BIGINT UNSIGNED FK NULL
- `custom_message` JSON NULL
- `message_template` TEXT NULL
- `target_type` VARCHAR(50)
- `recipient_type` VARCHAR(50)
- `target_filters` JSON NULL
- `provider` VARCHAR(50) DEFAULT 'whatsapp'
- `channels` JSON NULL
- `status` VARCHAR(50) DEFAULT 'draft'
- `total_target` INT DEFAULT 0
- `total_success` INT DEFAULT 0
- `total_failed` INT DEFAULT 0
- `scheduled_at` DATETIME NULL
- `started_at` DATETIME NULL
- `completed_at` DATETIME NULL
- `created_by` BIGINT UNSIGNED FK
- `branch_id` BIGINT UNSIGNED FK NULL
- `delay_seconds` INT DEFAULT 0
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

#### 4.5.18 Tabel `broadcast_logs`
- `id` BIGINT UNSIGNED PK AI
- `campaign_id` BIGINT UNSIGNED FK
- `recipient_type` VARCHAR(255)
- `recipient_id` BIGINT UNSIGNED
- `phone` VARCHAR(20)
- `rendered_message` TEXT
- `provider` VARCHAR(50)
- `provider_message_id` VARCHAR(255) NULL
- `status` VARCHAR(50) DEFAULT 'pending'
- `error_message` TEXT NULL
- `attempt_count` INT DEFAULT 0
- `sent_at` DATETIME NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

#### 4.5.19 Tabel `broadcast_templates`
- `id` BIGINT UNSIGNED PK AI
- `name` VARCHAR(255)
- `content` TEXT
- `type` VARCHAR(50)
- `is_active` TINYINT(1) DEFAULT 1
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP
- `deleted_at` TIMESTAMP NULL (SoftDeletes)

#### 4.5.20 Tabel `points`
- `id` BIGINT UNSIGNED PK AI
- `user_id` BIGINT UNSIGNED FK
- `rental_id` BIGINT UNSIGNED FK NULL
- `points` INT
- `type` VARCHAR(50)
- `description` TEXT NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

#### 4.5.21 Tabel `settings`
- `id` BIGINT UNSIGNED PK AI
- `key` VARCHAR(100) UNIQUE
- `value` TEXT NULL
- `type` VARCHAR(50) DEFAULT 'string'
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

#### 4.5.22 Relasi Antar Tabel

```
User (id) → Branch (id)
  - User.branch_id → Branch.id (Many to One)
  - User.branchAccess → Branch (Many to Many via user_branch_access)

User (id) → Customer (user_id)
  - User hasMany Customer

User (id) → Rental (created_by)
  - User hasMany Rental (as creator)

User (id) → Payment (received_by)
  - User hasMany Payment

Branch (id) → Product (branch_id)
  - Branch hasMany Product

Branch (id) → Customer (branch_id)
  - Branch hasMany Customer

Branch (id) → Rental (branch_id)
  - Branch hasMany Rental

Category (id) → Product (category_id)
  - Category hasMany Product

Product (id) → ProductSize (product_id)
  - Product hasMany ProductSize; each row stores one size and its stock

Rental (id) → RentalItem (rental_id)
  - Rental hasMany RentalItem

Rental (id) → RentalReturn (rental_id)
  - Rental hasOne RentalReturn

Rental (id) → Payment (rental_id)
  - Rental hasMany Payment

Rental (id) → Guarantee (rental_id)
  - Rental hasMany Guarantee

Rental (id) → Laundry (rental_id)
  - Rental hasMany Laundry
  > **Catatan:** Relasi ini ada di codebase, tetapi tidak ada UI/route/controller yang memanfaatkannya. Disiapkan untuk pengembangan masa depan.

Rental (id) → StatusHistory (rental_id)
  - Rental hasMany StatusHistory

Rental (id) → ActivityLog (model_id)
  - Rental hasMany ActivityLog (morph-like via model_type/model_id)

BroadcastCampaign (id) → BroadcastLog (campaign_id)
  - BroadcastCampaign hasMany BroadcastLog

BroadcastCampaign (id) → BroadcastTemplate (template_id)
  - BroadcastCampaign belongsTo BroadcastTemplate

BroadcastCampaign (id) → Branch (branch_id)
  - BroadcastCampaign belongsTo Branch

BroadcastCampaign (id) → User (created_by)
  - BroadcastCampaign belongsTo User (as creator)
```

#### 4.5.23 Tabel Pendukung Ukuran dan Akses Cabang

**`product_sizes`** menyimpan satu baris per kombinasi produk/ukuran dengan kolom `id`, `product_id`, `size`, `stock_total`, `stock_available`, timestamps, serta unique constraint pada (`product_id`, `size`).

**`user_branch_access`** menyimpan relasi user-cabang dengan kolom `id`, `user_id`, `branch_id`, timestamps, serta unique constraint pada (`user_id`, `branch_id`). Kolom `users.branch_access_type` memilih cakupan `all` atau `specific`; nilai role yang ditetapkan saat ini dijelaskan pada subbab 3.5.

---

### 4.6 Daftar Seluruh Controller

| No | Controller | File | Method Utama |
| -- | ---------- | ---- | ------------ |
| 4.1 | AuthenticatedSessionController | `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | create(), store(), destroy() |
| 4.2 | RegisteredUserController | `app/Http/Controllers/Auth/RegisteredUserController.php` | create(), store() |
| 4.3 | AuthController | `app/Http/Controllers/Auth/AuthController.php` | — |
| 4.4 | DashboardController | `app/Http/Controllers/DashboardController.php` | index(), data(), downloadActivityLog() |
| 4.5 | SearchController | `app/Http/Controllers/SearchController.php` | index() |
| 4.6 | ProfileController | `app/Http/Controllers/ProfileController.php` | edit(), update(), destroy(), updateTheme(), updatePassword() |
| 4.7 | NotificationController | `app/Http/Controllers/NotificationController.php` | index(), data(), count(), markAllRead(), markRead(), destroy(), show() |
| 4.8 | BroadcastController | `app/Http/Controllers/BroadcastController.php` | index(), store(), destroy() |
| 4.9 | BroadcastCampaignController | `app/Http/Controllers/BroadcastCampaignController.php` | index(), create(), store(), show(), sendToCustomer(), updateMessage(), destroy(), handleOptOut(), checkDailyLimit(), updateMessageStatus() |
| 4.10 | CustomerController | `app/Http/Controllers/CustomerController.php` | index(), search(), create(), store(), export(), edit(), update(), toggleBlacklist(), show(), restore(), destroy(), forceDestroy() |
| 4.11 | RentalController | `app/Http/Controllers/RentalController.php` | scanPage(), scanQr(), index(), activityLog(), exportActivityLogExcel(), exportActivityLogPdf(), create(), store(), show(), processPayment(), paymentUpdate(), paymentDestroy(), paymentRefund(), markRefundGiven(), paymentVoid(), processReturn(), cancelReturn(), handoverRental(), updateStatus(), confirmReturnAjax(), thermalPrint(), exportPdf(), whatsapp(), sendReminder(), cancel(), edit(), update(), destroy(), downloadActivityLog() |
| 4.12 | RentalReceiptController | `app/Http/Controllers/RentalReceiptController.php` | show(), pdf(), qr(), whatsapp() |
| 4.13 | ProductController | `app/Http/Controllers/ProductController.php` | index(), create(), store(), edit(), update(), destroy(), show(), updateStock(), generateQrCode() |
| 4.14 | CategoryController | `app/Http/Controllers/CategoryController.php` | index(), create(), store(), edit(), update(), destroy() |
| 4.15 | BranchController | `app/Http/Controllers/BranchController.php` | index(), create(), store(), show(), edit(), update(), destroy() |
| 4.16 | UserController | `app/Http/Controllers/UserController.php` | index(), create(), store(), edit(), update(), toggle(), destroy(), show() |
| 4.17 | SettingsController | `app/Http/Controllers/SettingsController.php` | index(), update() |
| 4.18 | ReportController | `app/Http/Controllers/ReportController.php` | revenue(), transactions(), returns(), stock(), customers(), payments(), activityLog(), exportActivityLog(), exportActivityLogPdf(), exportRevenueExcel(), exportRevenuePdf(), exportTransactionsExcel(), exportTransactionsPdf(), exportReturnsExcel(), exportReturnsPdf(), exportStockExcel(), exportStockPdf(), exportCustomersExcel(), exportCustomersPdf(), exportPaymentsExcel(), exportPaymentsPdf(), exportExcel(), exportPdf() |
| 4.19 | PaymentController | `app/Http/Controllers/PaymentController.php` | store(), update(), destroy(), void(), refund() |
| 4.20 | TransactionController | `app/Http/Controllers/TransactionController.php` | index(), show(), edit(), update(), destroy() |
| 4.21 | PointsController | `app/Http/Controllers/PointsController.php` | index() |

---

### 4.7 Daftar Seluruh View (Blade)

#### 4.7.1 Layouts
- `Layouts/app.blade.php` — Layout utama aplikasi
- `Layouts/navbar.blade.php` — Komponen navbar/navigasi

#### 4.7.2 Auth
- `auth/login.blade.php` — Halaman login

#### 4.7.3 Dashboard
- `dashboard/_dashboard_common.blade.php` — Shared dashboard components
- `dashboard/super-admin.blade.php` — Dashboard super admin
- `dashboard/admin.blade.php` — Dashboard admin cabang
- `dashboard/sales.blade.php` — Dashboard sales

#### 4.7.4 Branches
- `branches/index.blade.php` — Daftar cabang
- `branches/create.blade.php` — Form buat cabang
- `branches/edit.blade.php` — Form edit cabang
- `branches/show.blade.php` — Detail cabang

#### 4.7.5 Users
- `users/index.blade.php` — Daftar user
- `users/create.blade.php` — Form buat user
- `users/edit.blade.php` — Form edit user
- `users/show.blade.php` — Detail user

#### 4.7.6 Categories
- `categories/index.blade.php` — Daftar kategori
- `categories/create.blade.php` — Form buat kategori
- `categories/edit.blade.php` — Form edit kategori

#### 4.7.7 Products
- `products/index.blade.php` — Daftar produk
- `products/create.blade.php` — Form buat produk
- `products/edit.blade.php` — Form edit produk
- `products/show.blade.php` — Detail produk + QR code

#### 4.7.8 Customers
- `customers/index.blade.php` — Daftar customer
- `customers/create.blade.php` — Form buat customer
- `customers/edit.blade.php` — Form edit customer
- `customers/show.blade.php` — Detail customer + riwayat rental

#### 4.7.9 Rentals
- `rentals/index.blade.php` — Daftar rental
- `rentals/create.blade.php` — Form buat rental
- `rentals/edit.blade.php` — Form edit rental
- `rentals/show.blade.php` — Detail rental + panel pengembalian
- `rentals/scan.blade.php` — Halaman scan QR dengan kamera
- `rentals/scan-result.blade.php` — Hasil scan QR
- `rentals/pdf.blade.php` — Export PDF rental
- `rentals/receipt.blade.php` — Struk rental (web)
- `rentals/receipt_pdf.blade.php` — Struk rental (PDF)
- `rentals/activity-log.blade.php` — Activity log rental

#### 4.7.10 Rentals Partials (17 files)
- `rentals/partials/doc-action-buttons-web.blade.php` — Tombol aksi web
- `rentals/partials/doc-audit-trail.blade.php` — Audit trail
- `rentals/partials/doc-cards-customer-rental.blade.php` — Kartu customer rental
- `rentals/partials/doc-close-html.blade.php` — Penutup HTML dokumen
- `rentals/partials/doc-customer-info.blade.php` — Info customer
- `rentals/partials/doc-item-list.blade.php` — Daftar item
- `rentals/partials/doc-notes.blade.php` — Catatan
- `rentals/partials/doc-parts-receipt.blade.php` — Bagian struk
- `rentals/partials/doc-parts-receipt-notes-only.blade.php` — Bagian struk catatan
- `rentals/partials/doc-payment-info.blade.php` — Info pembayaran
- `rentals/partials/doc-payments-history.blade.php` — Riwayat pembayaran
- `rentals/partials/doc-price-summary.blade.php` — Ringkasan harga
- `rentals/partials/doc-qr-card.blade.php` — Kartu QR
- `rentals/partials/doc-rental-info-summary.blade.php` — Ringkasan info rental
- `rentals/partials/doc-status-badge.blade.php` — Badge status
- `rentals/partials/doc-transaction-info.blade.php` — Info transaksi
- `rentals/partials/premium-doc-head.blade.php` — Header premium dokumen

#### 4.7.11 Reports
- `reports/revenue.blade.php` — Laporan pendapatan
- `reports/transactions.blade.php` — Laporan transaksi
- `reports/returns.blade.php` — Laporan pengembalian
- `reports/stock.blade.php` — Laporan stok
- `reports/customers.blade.php` — Laporan customer
- `reports/payments.blade.php` — Laporan pembayaran
- `reports/activity-log.blade.php` — Laporan activity log

#### 4.7.12 Reports Partials
- `reports/partials/filter-bar.blade.php` — Filter bar
- `reports/partials/revenue-content.blade.php` — Konten pendapatan
- `reports/partials/transactions-content.blade.php` — Konten transaksi
- `reports/partials/returns-content.blade.php` — Konten pengembalian
- `reports/partials/stock-content.blade.php` — Konten stok
- `reports/partials/activity-log-content.blade.php` — Konten activity log

#### 4.7.13 Reports PDF
- `reports/pdf/revenue.blade.php` — PDF pendapatan
- `reports/pdf/transactions.blade.php` — PDF transaksi
- `reports/pdf/returns.blade.php` — PDF pengembalian
- `reports/pdf/stock.blade.php` — PDF stok
- `reports/pdf/customers.blade.php` — PDF customer
- `reports/pdf/payments.blade.php` — PDF pembayaran
- `reports/pdf/rental-activity-log.blade.php` — PDF activity log rental

#### 4.7.14 Broadcast Campaigns
- `broadcast_campaigns/index.blade.php` — Daftar campaign
- `broadcast_campaigns/create.blade.php` — Form buat campaign
- `broadcast_campaigns/show.blade.php` — Detail campaign

#### 4.7.15 Broadcasts
- `broadcasts/index.blade.php` — Daftar broadcast

#### 4.7.16 Points
- `points/index.blade.php` — Leaderboard points

#### 4.7.17 Search
- `search/index.blade.php` — Pencarian global

#### 4.7.18 Notifications
- `notifications/index.blade.php` — Daftar notifikasi

#### 4.7.19 Profile
- `profile/edit.blade.php` — Edit profile + theme

#### 4.7.20 Settings
- `settings/index.blade.php` — Pengaturan aplikasi

#### 4.7.21 Transactions
- `transactions/index.blade.php` — Daftar transaksi
- `transactions/edit.blade.php` — Form edit transaksi
- `transactions/show.blade.php` — Detail transaksi

#### 4.7.22 Components
- `components/flash-messages.blade.php` — Flash messages
- `components/notification-bell.blade.php` — Notifikasi bell
- `components/pagination.blade.php` — Pagination custom
- `components/show.blade.php` — Komponen show

**Total: 89 file Blade pada source yang diaudit**

---

### 4.8 Sistem Role dan Permission

#### 4.8.1 Role yang Tersedia

| Role | Deskripsi berdasarkan source saat ini |
| ---- | --------- |
| super_admin | Mengakses fitur administrasi dan data lintas cabang sesuai route. |
| admin_toko | Cabang utama wajib dipilih; `branch_access_type` saat ini disetel `all`, sehingga cakupan data mengikuti query tiap fitur dan tidak selalu satu cabang. |
| sales | Akses operasional dengan cabang spesifik; beberapa daftar juga dibatasi pada data yang dibuat Sales. Dapat update stok berdasarkan ukuran. |
| customer | Konstanta role tersedia, tetapi belum memiliki route dashboard/self-service yang berfungsi. |

Role didefinisikan di `app/Models/User.php` sebagai konstanta:
- `ROLE_SUPER_ADMIN = 'super_admin'`
- `ROLE_ADMIN_TOKO = 'admin_toko'`
- `ROLE_SALES = 'sales'`
- `ROLE_CUSTOMER = 'customer'`

Accessor methods:
- `isSuperAdmin()` — true jika role === super_admin
- `isAdminToko()` — true jika role === admin_toko
- `isSales()` — true jika role === sales
- `canManage()` — true jika super_admin atau admin_toko

#### 4.8.2 Ringkasan Hak Akses Route

Project memiliki `RolePermissionSeeder`, policies, Gates, dan middleware, tetapi `User` tidak menggunakan trait Spatie `HasRoles`. Karena itu tabel berikut merangkum route/controller aktual, bukan menyatakan bahwa seeder menjadi satu-satunya sumber otorisasi.

| Area | super_admin | admin_toko | sales |
| ---- | ----------- | ---------- | ----- |
| Dashboard | Dashboard Super Admin | Dashboard Admin | Dashboard Sales |
| Cabang, user, kategori, settings | Kelola | Tidak diizinkan oleh route | Tidak diizinkan oleh route |
| Produk | CRUD dan update stok | CRUD dan update stok | Baca dan update stok per ukuran |
| Customer | Lihat/tambah/edit/hapus; blacklist khusus Super Admin | Lihat/tambah/edit/soft delete | Lihat/tambah sesuai ownership; tidak edit/hapus/blacklist |
| Rental | Alur operasional lengkap sesuai route | Alur operasional; edit/cancel dibatasi ke admin | Buat/lihat sesuai scope, bayar, return, scan |
| Laporan dan campaign | Diizinkan | Diizinkan oleh route | Tidak diizinkan oleh route umum |
| Points dan notifikasi | Akses sesuai halaman | Akses sesuai halaman | Points pribadi; notifikasi milik user |

Scope cabang bukan konsekuensi role saja: controller/query memakai `accessible_branch_ids`, `branch_id`, atau ownership. Admin Toko disetel `all`, sementara Sales memakai `specific`.

#### 4.8.3 Implementasi Authorization

**1. Middleware Role Check (`app/Http/Middleware/CheckRole.php`)**
- Usage: `middleware(CheckRole::class . ':super_admin,admin_toko')`
- Method: `handle(Request $request, Closure $next, string ...$roles)`
- Logic: Cek `auth()->user()->role` in array roles, abort 403 jika tidak cocok

**2. Middleware Branch Scope (`app/Http/Middleware/EnsureBranchScope.php`)**
- Usage: `middleware(EnsureBranchScope::class)`
- Logic: Memeriksa status aktif lebih dahulu; akun nonaktif dikeluarkan dan diarahkan ke login. Akses cabang ditentukan bersama role/`branch_access_type`; ini tidak otomatis menambahkan kondisi cabang pada semua query.

**3. Middleware Branch Access (`app/Http/Middleware/EnsureBranchAccess.php`)**
- Alias `branch.access` didaftarkan di `bootstrap/app.php`. Middleware dapat memeriksa branch dari request, tetapi audit route saat ini tidak menemukan pemakaian alias tersebut pada route fitur; jangan menganggapnya sebagai proteksi global.

**4. Policies (`app/Policies/`)**
`ProductPolicy`, `CustomerPolicy`, `CategoryPolicy`, dan `BranchPolicy` dipetakan ke model di `AuthServiceProvider`. Pemetaan policy tidak membuktikan bahwa semua controller memanggil policy; banyak izin tetap dikendalikan oleh route `CheckRole` dan pemeriksaan inline.

**5. Gates (`app/Providers/AuthServiceProvider.php`)**
- `Gate::before()`: Super admin bypass semua gate
- `laporan.view-all`: super_admin only
- `laporan.export-pdf`: super_admin only
- `laporan.export-excel`: super_admin only
- `rental.create`: super_admin + admin_toko + sales
- `rental.edit`: super_admin + admin_toko
- `rental.delete`: super_admin + admin_toko
- `rental.cancel`: super_admin + admin_toko
- `branch.manage`: super_admin only
- `user.manage`: super_admin only
- `activity.view-all`: super_admin only
- `laundry.update-status`: super_admin + admin_toko + sales > **Catatan:** Gate ini didefinisikan di `AuthServiceProvider`, tetapi belum ada route/controller/view yang memanfaatkannya. Disiapkan untuk pengembangan fitur laundry di masa depan.

**6. Role Permission Seeder**
`database/seeders/RolePermissionSeeder.php` tersedia, tetapi model `User` tidak menggunakan trait `HasRoles`. Untuk mendeskripsikan akses efektif, gunakan route, middleware, pemeriksaan controller, dan scope query yang aktif.

---

### 4.9 Fitur-Fitur Teknis Khusus

#### 4.9.1 Soft Delete

Model yang menggunakan trait `SoftDeletes`:
- `app/Models/User.php` — Kolom `deleted_at`
- `app/Models/Branch.php` — Kolom `deleted_at`
- `app/Models/Product.php` — Kolom `deleted_at`
- `app/Models/Customer.php` — Kolom `deleted_at`
- `app/Models/Laundry.php` — Kolom `deleted_at`
- `app/Models/Broadcast/BroadcastTemplate.php` — Kolom `deleted_at`

Implementasi:
- Route `customers.show` menggunakan `withTrashed()` untuk bisa menampilkan customer yang dihapus
- Route `products.show` menggunakan `withTrashed()`
- CustomerController memiliki method `restore()` untuk restore soft-deleted customer
- CustomerController memiliki method `forceDestroy()` untuk hard delete (hanya super_admin)

#### 4.9.2 Export Data

**Export Excel (Maatwebsite Excel):**
- `app/Exports/CustomerExport.php` — Export customer dengan styling (header biru, border, alternating row colors, freeze pane, auto filter)
- `app/Exports/ReportsExport.php` — Export rental laporan ke CSV streaming
- Routes export laporan meliputi Excel/CSV sesuai implementasi; activity log tersedia sebagai CSV dan PDF, bukan Excel.

**Export PDF (barryvdh/laravel-dompdf):**
- Views PDF di `resources/views/reports/pdf/`:
  - `revenue.blade.php`
  - `transactions.blade.php`
  - `returns.blade.php`
  - `stock.blade.php`
  - `customers.blade.php`
  - `payments.blade.php`
  - `rental-activity-log.blade.php`
- Views PDF rental:
  - `rentals/pdf.blade.php`
  - `rentals/receipt_pdf.blade.php`
- Routes export PDF di `ReportController` dan `RentalController`

#### 4.9.3 Scan QR Code

**QR Code Generation:**
- Package: `simplesoftwareio/simple-qrcode`
- Generated untuk: Product dan Rental
- Format: SVG, disimpan di `storage/app/public/qrcodes/`
- Product QR: Mengarah ke `route('products.show', $product)`
- Rental QR: Mengarah ke `route('rentals.scan', $rental->invoice_number)`
- Method: `RentalService@generateQrCode()`, `ProductController@generateQrCode()`

**QR Code Scanning:**
- Package: `html5-qrcode`
- Halaman: `resources/views/rentals/scan.blade.php`
- Route: `GET /rentals/scan` (scanPage), `GET /rentals/scan/{invoice}` (scanQr)
- Flow: User scan QR → redirect ke `rentals.scan.show` → tampilkan detail rental

**QR-related Commands:**
- `php artisan rental:regenerate-qrs` — Regenerate semua rental QR
- `php artisan audit:qr` — Audit QR codes

**QR-related Seeders:**
- `GenerateAllQrSeeder.php`
- `RegenerateAllRentalQrSeeder.php`
- `ForceGenerateQrSeeder.php`

#### 4.9.4 Broadcast & Notifications

**Broadcast Campaign:**
- Model: `BroadcastCampaign`, `BroadcastLog`, `BroadcastTemplate`
- Channels: WhatsApp (via external worker), In-App Notification
- Targeting: By branch, by recipient type (customer/user/both), by specific IDs
- Inter-message delay: Konfigurasi `delay_seconds` pada campaign
- Daily limit: `env('BROADCAST_DAILY_LIMIT', 50)`
- Status tracking: pending → submitted → sent → delivered → read
- Worker API: `routes/api.php` (opt-out, daily-limit, update-status)

**In-App Notification:**
- Model: `Notification` (morphable)
- Service: `NotificationService`
- Types: rental_new, rental_return, rental_late, payment, reminder
- Features: unread count, paging/filter, mark as read, mark all read, delete, show
- Component: `components/notification-bell.blade.php`
- Ownership: controller membatasi notifikasi pada user yang sedang login (`notifiable_type` dan `notifiable_id`)
- Request HTML biasa: redirect dengan flash message; request fetch/AJAX: JSON yang dikonsumsi `resources/js/notifications.js`
- Route: `/notifications` dan route web pendukung data/count/actions; endpoint worker broadcast berada terpisah di `routes/api.php`

**Broadcast Job:**
- `app/Jobs/SendBroadcastMessage.php` — Queue job untuk mengirim pesan broadcast

#### 4.9.5 Activity Log

- Model: `ActivityLog`
- Fillable: user_id, branch_id, action, model_type, model_id, description, old_values, new_values, ip_address, user_agent
- Casts: old_values, new_values (array)
- Event: `booted()` saat created mengambil `ActivityLogFileService` melalui container, lalu memanggil `write($log)` di dalam try/catch
- Kegagalan penulisan file dilaporkan ke Laravel log dan tidak menggagalkan aksi hanya karena file writer gagal; kegagalan `ActivityLog::create()` sendiri tidak otomatis ditelan oleh event ini
- Service: `ActivityLogFileService` — mencoba menulis ke file log
- Service: `ActivityLogFormatter` — format log untuk display
- Actions yang dicatat: create_rental, update_rental, process_payment, process_return, cancel_rental, dll
- Export: CSV dan PDF via `ReportController`

#### 4.9.6 Points System

- Model: `Point` (tabel `points`)
- Route: `GET /points` → `PointsController@index`
- View: `resources/views/points/index.blade.php`
- Fitur: Leaderboard sales; pembayaran pertama memberi 3 poin kepada Sales pembuat rental, pengembalian memberi 5 poin kepada Sales yang diproses, dengan flag untuk mencegah pemberian ganda

#### 4.9.8 WhatsApp Integration

- Button "Kirim WA" di halaman rental
- Auto-download PDF receipt + open WhatsApp chat
- Broadcast campaign via WhatsApp worker

---

### 4.10 Seed Data

#### 4.10.1 `database/seeders/DatabaseSeeder.php`
- Seeder utama yang dipanggil oleh `php artisan db:seed`
- Membuat 2 cabang: Toko Pusat Yogyakarta (YGY01), Cabang Solo (SLO01)
- Membuat 5 user: Super Administrator, Admin Pusat Yogya, Admin Cabang Solo, Budi Santoso (sales), Andi Pratama (sales)
- Membuat 7 kategori: Jas Formal, Tuxedo, Jas Wisuda, Kebaya, Rompi, Sepatu, Aksesoris
- Memanggil `ErpDataSeeder::class`

#### 4.10.2 `database/seeders/RolePermissionSeeder.php`
- Membuat 34 permissions
- Membuat 3 roles:
  - super_admin: semua permissions (34)
  - admin_toko: 21 permissions
  - sales: 10 permissions

#### 4.10.3 `database/seeders/ErpDataSeeder.php`
- Membuat produk per cabang (3 produk per cabang)
- Membuat customer per sales (3 customer per sales)
- Membuat rental & related data (3 rental per sales):
  - RentalItem, Payment, Guarantee, RentalReturn, ActivityLog
- Payment methods: cash, transfer, qris
- Rental statuses: waiting, active, returned

#### 4.10.4 `database/seeders/DemoDataSeeder.php`
- Update produk cabang Solo dengan data baru (Jas Wisuda, Kebaya, Rompi)
- Menambahkan 6 customer tambahan
- Membuat 80 rental demo dengan weighted random status (60% returned, 25% active, 10% waiting, 5% overdue)
- Payment status weighted (75% paid, 25% partial)

#### 4.10.5 `database/seeders/RentalDemoSeeder.php`
- Membuat 20 transaksi sewa demo
- Rental statuses: active, returned, overdue
- Payment statuses: paid, partial, unpaid
- Generate Guarantee (KTP/SIM/deposit)
- Generate Payment

#### 4.10.6 `database/seeders/FixAllDataSeeder.php`
- Memperbaiki foto produk dengan URL Unsplash
- Generate QR code untuk semua rental yang belum punya QR

#### 4.10.7 `database/seeders/GenerateAllQrSeeder.php`
- Generate QR codes untuk semua produk dan rental

#### 4.10.8 `database/seeders/RegenerateAllRentalQrSeeder.php`
- Regenerate semua QR code rental dengan format scan-result URL baru

#### 4.10.9 `database/seeders/ForceGenerateQrSeeder.php`
- Force regenerate QR code (overwrite existing)

#### 4.10.10 `database/seeders/ProductPhotoSeeder.php`
- Seed foto produk dari URL eksternal

**Cara menjalankan seeder:**
```bash
php artisan db:seed
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=ErpDataSeeder
php artisan db:seed --class=DemoDataSeeder
```

---

### 4.11 Konfigurasi Environment

Berdasarkan file `.env` yang digunakan:

```env
APP_NAME=RentalJas
APP_ENV=local
APP_KEY=base64:2b+4x2u5y6z7a8b9c0d1e2f3g4h5i6j7k8l9m0n1o2p=
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rental_jas
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_APP_NAME="${APP_NAME}"
```

**Variabel penting khusus Sewajas:**
- `BROADCAST_DAILY_LIMIT=50` — Limit broadcast WhatsApp per hari
- `BROADCAST_WORKER_URL` — URL worker untuk broadcast (dikomentari di .env.example)
- `BROADCAST_WORKER_TOKEN` — Token autentikasi worker (dikomentari di .env.example)
- `QUEUE_CONNECTION=database` — Menggunakan database queue untuk background jobs
- `SESSION_DRIVER=file` — Session disimpan di file (bisa diganti database untuk production)

---

### 4.12 Riwayat Perubahan Signifikan

Berdasarkan git log (`git log --oneline -30`):

| No | Commit | Jenis | Deskripsi |
| -- | ------ | ----- | --------- |
| 1 | `6339c46` | chore | Remove demo accounts from login page |
| 2 | `ce9c48a` | feat | Broadcast WhatsApp semi-manual, rental payment netting fix, customer force delete improvements |
| 3 | `f21b4d5` | fix | Restore missing @endif for KTP/SIM guarantee block in rentals/edit.blade.php |
| 4 | `c1e1133` | feat | Restrict rental edit access to super_admin and admin_toko only |
| 5 | `d11489a` | fix | Remove corrupted DSML artifacts from rentals/edit.blade.php |
| 6 | `e192b0f` | fix | Rename custom_message to custom_message_single to avoid field collision |
| 7 | `0012278` | fix | Broadcast campaign renderTemplate TypeError and empty variant handling |
| 8 | `0da4e13` | fix | Broadcast progress tracking and campaign completion logic |
| 9 | `89fc0a5` | feat | Add inter-message delay for WhatsApp broadcast campaigns |
| 10 | `bd2c33a` | fix | Dashboard chart proportions and Y-axis scaling |
| 11 | `7765a54` | fix | Remove border-radius from login blue panel |
| 12 | `29d2ba7` | fix | Notification morph keys and in-app validation |
| 13 | `a184304` | fix | Login page as centered blue card with white margins |
| 14 | `8390683` | fix | Login page background full-screen + cleanup |
| 15 | `a2fef5f` | fix | Broadcast campaign delete flow and blade escaping bugs |
| 16 | `b940a26` | fix | Enforce table-layout fixed on elegant-table and ds-table to prevent column misalignment |
| 17 | `5605d22` | fix | Restore missing /products/create route causing 404 error |
| 18 | `091e034` | fix | Keep dashboard sparkles icon and replace map-pin with navigation icon in user/product pages |
| 19 | `fbbeb29` | fix | Replace star-like sparkles icon with layout-dashboard and activity icons on dashboard |
| 20 | `2e36302` | feat | Add Total Poin display on sales profile page |
| 21 | `4679f49` | feat | Add Total Poin column to users index for sales role |
| 22 | `9fb84d7` | feat | Replace sales commission system with points system |
| 23 | `d99c8d7` | chore | Remove deleted thermal print view files from git tracking |
| 24 | `207878e` | feat | Remove thermal print feature and related routes/views |
| 25 | `39e7405` | fix | Align Receipt PDF status badge with right column values |
| 26 | `06ca3ab` | fix | Push Receipt PDF status badge further right to align with values above |
| 27 | `4d2776d` | fix | Show logo in Receipt PDF header and adjust status badge alignment |
| 28 | `d9b6133` | fix | Resolve critical layout regression on login page |
| 29 | `7dd638c` | feat | Change WA Receipt button to auto-download PDF and open WhatsApp chat |
| 30 | `ccdca13` | fix | Resolve messy Receipt PDF layout (missing header, excess spacing) |

**Kategori perubahan terakhir:**
- **Fitur Baru:** Broadcast WhatsApp semi-manual, points system, auto-download PDF receipt, inter-message delay
- **Bug Fixes:** QR scan, login page layout, dashboard charts, notification morph keys, table layout, products route 404, receipt PDF alignment
- **Keamanan:** Rental edit access restriction, customer force delete improvements
- **Refactoring:** Remove thermal print feature, remove corrupted artifacts, rename field collision

---

### DAFTAR SCREENSHOT YANG PERLU DISISIPKAN

| No | Bagian | Screenshot | Sumber/Fungsi |
| -- | ------ | ---------- | ------------- |
| 3.1 | 3.4.2 | Tampilan Halaman Login Sewajas | `resources/views/auth/login.blade.php` - Form login dengan branding aplikasi |
| 3.2 | 3.7.1 | Dashboard Super Admin | `resources/views/dashboard/super-admin.blade.php` - Overview sistem multi-cabang |
| 3.3 | 3.7.2 | Dashboard Admin Toko | `resources/views/dashboard/admin.blade.php` - Dashboard Admin Toko |
| 3.4 | 3.7.3 | Dashboard Sales | `resources/views/dashboard/sales.blade.php` - Overview pribadi sales |
| 3.5 | 3.6.2 | Daftar Customer | `resources/views/customers/index.blade.php` - Tabel customer dengan search dan filter |
| 3.6 | 3.6.2 | Detail Customer | `resources/views/customers/show.blade.php` - Info customer + riwayat rental |
| 3.7 | 3.6.3 | Daftar Produk | `resources/views/products/index.blade.php` - Grid produk dengan filter |
| 3.8 | 3.6.3 | Detail Produk | `resources/views/products/show.blade.php` - Info produk + stok + QR code |
| 3.9 | 3.6.4 | Daftar Rental | `resources/views/rentals/index.blade.php` - Tabel rental dengan filter status |
| 3.10 | 3.6.4 | Form Buat Rental | `resources/views/rentals/create.blade.php` - Form pemilihan customer, produk, jaminan |
| 3.11 | 3.6.4 | Detail Rental | `resources/views/rentals/show.blade.php` - Info lengkap rental |
| 3.12 | 3.6.6 | Panel Pengembalian | `resources/views/rentals/show.blade.php` - Form cek kondisi barang |
| 3.13 | 3.6.4 | Scan QR | `resources/views/rentals/scan.blade.php` - Halaman scan QR dengan kamera |
| 3.14 | 3.6.4 | Hasil Scan QR | `resources/views/rentals/scan-result.blade.php` - Detail rental dari QR scan |
| 3.15 | 3.6.11 | Laporan Pendapatan | `resources/views/reports/revenue.blade.php` - Grafik dan tabel pendapatan |
| 3.16 | 3.6.11 | Laporan Transaksi | `resources/views/reports/transactions.blade.php` - Daftar transaksi dengan filter |
| 3.17 | 3.6.8 | Broadcast Campaigns | `resources/views/broadcast_campaigns/index.blade.php` - Daftar campaign |
| 3.18 | 3.6.8 | Form Broadcast | `resources/views/broadcast_campaigns/create.blade.php` - Form buat campaign |
| 3.19 | 3.6.9 | Points Leaderboard | `resources/views/points/index.blade.php` - Peringkat sales |
| 3.20 | 3.6.12 | Profile | `resources/views/profile/edit.blade.php` - Edit profile dan theme |
| 3.21 | 3.6.13 | Settings | `resources/views/settings/index.blade.php` - Pengaturan aplikasi |
| 3.22 | 3.5 | Manajemen User | `resources/views/users/index.blade.php` - Daftar user |
| 3.23 | 3.5 | Manajemen Cabang | `resources/views/branches/index.blade.php` - Daftar cabang |
| 3.24 | 3.6.2 | Customer Blacklist | `resources/views/customers/index.blade.php` - Filter blacklist |

---

### DAFTAR SCREENSHOT CODE YANG PERLU DISISIPKAN

| No | Nama Gambar | File | Bagian Code | Penjelasan |
| -- | ----------- | ---- | ----------- | ----------- |
| 3.1 | Potongan Kode generateInvoiceNumber | `app/Services/RentalService.php` | Baris 260-310 | Menunjukkan race-condition safe invoice generation dengan lockForUpdate() |
| 3.2 | Potongan Kode createRental | `app/Services/RentalService.php` | Baris 325-467 | Menunjukkan validasi stok, stock locking, guarantee creation, dan QR generation |
| 3.3 | Potongan Kode processPayment | `app/Services/RentalService.php` | Baris 469-524 | Menunjukkan logika pembayaran dan recalculation payment status |
| 3.4 | Potongan Kode processReturn | `app/Services/RentalService.php` | Baris 526-588 | Menunjukkan logika pengembalian, late fee, stock update, dan points |
| 3.5 | Potongan Kode Role Constants | `app/Models/User.php` | Baris 37-49 | Menunjukkan 4 role yang tersedia dalam sistem |
| 3.6 | Potongan Kode CheckRole Middleware | `app/Http/Middleware/CheckRole.php` | Seluruh file | Menunjukkan otorisasi berbasis role |
| 3.7 | Potongan Kode EnsureBranchScope Middleware | `app/Http/Middleware/EnsureBranchScope.php` | Seluruh file | Menunjukkan pemeriksaan status akun dan akses cabang; filter data diterapkan oleh query fitur |
| 3.8 | Potongan Kode Rental Status Constants | `app/Models/Rental.php` | Baris 42-56 | Menunjukkan status rental, payment, dan fine |
| 3.9 | Potongan Kode Activity Log Model | `app/Models/ActivityLog.php` | Seluruh file | Menunjukkan struktur log aktivitas |
| 3.10 | Potongan Kode Broadcast Campaign Store | `app/Http/Controllers/BroadcastCampaignController.php` | Baris 82-166 | Menunjukkan pembuatan campaign broadcast dengan targeting |
| 3.11 | Potongan Kode Report Revenue | `app/Http/Controllers/ReportController.php` | Baris 81-136 | Menunjukkan laporan pendapatan dengan filter dan export |
| 3.12 | Potongan Kode Customer normalizePhone | `app/Http/Controllers/CustomerController.php` | Baris 431-442 | Menunjukkan normalisasi nomor telepon |
| 3.13 | Potongan Kode Route Definition | `routes/web.php` | Baris 37-222 | Menunjukkan struktur route dengan middleware role |
| 3.14 | Potongan Kode Dashboard Payload | `app/Services/DashboardService.php` | Baris 64-418 | Menunjukkan aggregate queries untuk dashboard |
| 3.15 | Potongan Kode SoftDeletes pada Model | `app/Models/Customer.php` | Baris 7, 14 | Menunjukkan implementasi SoftDeletes |
| 3.16 | Potongan Kode Export Excel | `app/Exports/CustomerExport.php` | Seluruh file | Menunjukkan export Excel dengan styling |
| 3.17 | Potongan Kode BroadcastCampaign Model | `app/Models/Broadcast/BroadcastCampaign.php` | Baris 75-132 | Menunjukkan queueMessages() dan resolveRecipients() |
| 3.18 | Potongan Kode Notification Model | `app/Models/Notification.php` | Seluruh file | Menunjukkan struktur notifikasi dengan icon dan color |
| 3.19 | Potongan Kode AuthServiceProvider Gates | `app/Providers/AuthServiceProvider.php` | Baris 22-62 | Menunjukkan definisi Gate untuk otorisasi |
| 3.20 | Potongan Kode RolePermissionSeeder | `database/seeders/RolePermissionSeeder.php` | Seluruh file | Menunjukkan definisi 34 permissions dan 3 roles |
| 3.21 | Potongan Kode EnsureBranchAccess Middleware | `app/Http/Middleware/EnsureBranchAccess.php` | Seluruh file | Menunjukkan validasi akses branch dari route/body/query |
| 3.22 | Potongan Kode QR Code Generation | `app/Services/RentalService.php` | Baris 590-608 | Menunjukkan generate QR code untuk rental |
| 3.23 | Potongan Kode Product QR Generation | `app/Http/Controllers/ProductController.php` | Baris 297-309 | Menunjukkan generate QR code untuk produk |
