<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\BroadcastCampaignController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TransactionController;

use App\Http\Controllers\Auth\AuthenticatedSessionController;

// Redirect root to login
Route::get('/', function () {
    return redirect()->route('login');
});

// ─── AUTH ─────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.post');
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// ─── AUTHENTICATED ROUTES (Super Admin, Admin Cabang, Sales) ─────
Route::middleware(['auth', \App\Http\Middleware\EnsureBranchScope::class])->group(function () {

    // ─── DASHBOARD ───────────────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko,sales')->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->middleware('auth')->name('dashboard.data');
    Route::get('/dashboard/activity-log/download', [DashboardController::class, 'downloadActivityLog'])->middleware(\App\Http\Middleware\CheckRole::class . ':super_admin')->name('dashboard.activity-log.download');
    Route::get('/search', [SearchController::class, 'index'])->middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko,sales')->name('search.index');

    // ─── PROFILE ─────────────────────────────────────────────────────
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::put('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // ─── NOTIFIKASI ──────────────────────────────────────────────────
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::prefix('notifications')->group(function () {
        Route::get('/data', [NotificationController::class, 'data']);
        Route::get('/count', [NotificationController::class, 'count']);
        Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::get('/{id}', [NotificationController::class, 'show'])->name('notifications.show');
    });

    Route::prefix('broadcasts')->name('broadcasts.')->middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko')->group(function () {
        Route::get('/', [BroadcastController::class, 'index'])->name('index');
        Route::post('/', [BroadcastController::class, 'store'])->name('store');
        Route::delete('/{broadcast}', [BroadcastController::class, 'destroy'])->middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko')->name('destroy');
    });

    Route::prefix('broadcast-campaigns')
        ->name('broadcast-campaigns.')
        ->middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko')
        ->group(function () {
            Route::get('/', [BroadcastCampaignController::class, 'index'])->name('index');
            Route::get('/create', [BroadcastCampaignController::class, 'create'])->name('create');
            Route::post('/', [BroadcastCampaignController::class, 'store'])->name('store');
            Route::get('/templates', [\App\Http\Controllers\BroadcastTemplateController::class, 'index'])->name('templates.index');
            Route::get('/templates/create', [\App\Http\Controllers\BroadcastTemplateController::class, 'create'])->name('templates.create');
            Route::post('/templates', [\App\Http\Controllers\BroadcastTemplateController::class, 'store'])->name('templates.store');
            Route::get('/templates/{template}/edit', [\App\Http\Controllers\BroadcastTemplateController::class, 'edit'])->name('templates.edit');
            Route::patch('/templates/{template}', [\App\Http\Controllers\BroadcastTemplateController::class, 'update'])->name('templates.update');
            Route::patch('/templates/{template}/toggle', [\App\Http\Controllers\BroadcastTemplateController::class, 'toggle'])->name('templates.toggle');
            Route::delete('/templates/{template}', [\App\Http\Controllers\BroadcastTemplateController::class, 'destroy'])->name('templates.destroy');
            Route::get('/{campaign}', [BroadcastCampaignController::class, 'show'])->name('show');
            Route::post('/{campaign}/send/{customer}', [BroadcastCampaignController::class, 'sendToCustomer'])->name('send-to-customer');
            Route::post('/{campaign}/confirm-sent/{customer}', [BroadcastCampaignController::class, 'confirmCustomerSent'])->name('confirm-customer-sent');
            Route::post('/{campaign}/message', [BroadcastCampaignController::class, 'updateMessage'])->name('update-message');
            Route::delete('/{campaign}', [BroadcastCampaignController::class, 'destroy'])->middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko')->name('destroy');
        });

    // ─── CUSTOMER (MASTER DATA) ──────────────────────────────────────
    Route::prefix('customers')->name('customers.')->middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko,sales')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::get('/search', [CustomerController::class, 'search'])->name('search');

        Route::middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko,sales')->group(function () {
            Route::get('/create', [CustomerController::class, 'create'])->name('create');
            Route::post('/', [CustomerController::class, 'store'])->name('store');
            Route::get('/export', [CustomerController::class, 'export'])->name('export')->middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko');
            Route::get('/{customer}/edit', [CustomerController::class, 'edit'])->name('edit');
            Route::patch('/{customer}', [CustomerController::class, 'update'])->name('update');
            Route::patch('/{customer}/blacklist', [CustomerController::class, 'toggleBlacklist'])->name('blacklist');
        });

        Route::get('/{customer}', [CustomerController::class, 'show'])->withTrashed()->name('show');
        Route::post('/{customer}/restore', [CustomerController::class, 'restore'])->name('restore')->withTrashed();

        Route::middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko,sales')->delete('/{customer}', [CustomerController::class, 'destroy'])->name('destroy');
        Route::middleware(\App\Http\Middleware\CheckRole::class . ':super_admin')->delete('/{customer}/force-destroy', [CustomerController::class, 'forceDestroy'])->withTrashed()->name('force-destroy');
    });

    // ─── PENYEWAAN (RENTALS) ─────────────────────────────────────────
    Route::prefix('rentals')->name('rentals.')->scopeBindings()->middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko,sales')->group(function () {
        Route::get('/scan', [RentalController::class, 'scanPage'])->name('scan');
        Route::get('/scan/{invoice}', [RentalController::class, 'scanQr'])->name('scan.show')->where('invoice', '.*');
        Route::get('/', [RentalController::class, 'index'])->name('index');
        Route::middleware(\App\Http\Middleware\CheckRole::class . ':super_admin')->group(function () {
            Route::get('/activity-log', [RentalController::class, 'activityLog'])->name('activity-log');
            Route::get('/activity-log/export/excel', [RentalController::class, 'exportActivityLogExcel'])->name('activity-log.export.excel');
            Route::get('/activity-log/export/pdf', [RentalController::class, 'exportActivityLogPdf'])->name('activity-log.export.pdf');
        });
        Route::get('/create', [RentalController::class, 'create'])->name('create');
        Route::post('/', [RentalController::class, 'store'])->name('store');
        Route::middleware('can:view,rental')->group(function () {
        Route::get('/{rental}', [RentalController::class, 'show'])->name('show');
        Route::post('/{rental}/payment', [RentalController::class, 'processPayment'])->name('payment');
        Route::patch('/{rental}/payments/{payment}', [RentalController::class, 'paymentUpdate'])->name('payment.update');
        Route::delete('/{rental}/payments/{payment}', [RentalController::class, 'paymentDestroy'])->name('payment.destroy');
        Route::post('/{rental}/payments/{payment}/refund', [RentalController::class, 'paymentRefund'])->name('payment.refund');
        Route::post('/{rental}/mark-refund-given', [RentalController::class, 'markRefundGiven'])->name('mark-refund-given');
        Route::post('/{rental}/payments/{payment}/void', [RentalController::class, 'paymentVoid'])->name('payment.void');
        Route::post('/{rental}/return', [RentalController::class, 'processReturn'])->name('return');
        Route::patch('/{rental}/cancel-return', [RentalController::class, 'cancelReturn'])->name('cancel-return');
        Route::patch('/{rental}/handover', [RentalController::class, 'handoverRental'])->name('handover');
        Route::patch('/{rental}/update-status', [RentalController::class, 'updateStatus'])->name('update-status');
        Route::patch('/{rental}/confirm-return-ajax', [RentalController::class, 'confirmReturnAjax'])->name('confirm-return-ajax');
        Route::get('/{rental}/thermal', [RentalController::class, 'thermalPrint'])->name('thermal');
        Route::get('/{rental}/pdf', [RentalController::class, 'exportPdf'])->name('pdf');
        Route::get('/{rental}/whatsapp', [RentalController::class, 'whatsapp'])->name('whatsapp');
        Route::get('/{rental}/reminder', [RentalController::class, 'sendReminder'])->name('reminder');
        Route::get('/{rental}/receipt', [\App\Http\Controllers\RentalReceiptController::class, 'show'])->name('receipt.show');
        Route::get('/{rental}/receipt/pdf', [\App\Http\Controllers\RentalReceiptController::class, 'pdf'])->name('receipt.pdf');
        Route::get('/{rental}/receipt/qr', [\App\Http\Controllers\RentalReceiptController::class, 'qr'])->name('receipt.qr');
        Route::get('/{rental}/receipt/whatsapp', [\App\Http\Controllers\RentalReceiptController::class, 'whatsapp'])->name('receipt.whatsapp');
        Route::get('/{rental}/invoice', [\App\Http\Controllers\RentalReceiptController::class, 'invoice'])->name('invoice');

        Route::middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko')->group(function () {
            Route::patch('/{rental}/cancel', [RentalController::class, 'cancel'])->name('cancel');
            Route::get('/{rental}/edit', [RentalController::class, 'edit'])->middleware('branch.access')->name('edit');
            Route::patch('/{rental}', [RentalController::class, 'update'])->name('update');
        });
        Route::middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko')->delete('/{rental}', [RentalController::class, 'destroy'])->name('destroy');
        Route::middleware(\App\Http\Middleware\CheckRole::class . ':super_admin')->get('/{rental}/activity-log/download', [RentalController::class, 'downloadActivityLog'])->name('activity-log.download');
        });
    });

    // ─── RIWAYAT PENYEWAAN (RENTAL HISTORIES) ──────────────────────────
    Route::prefix('rental-histories')->name('rental-histories.')->middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko,sales')->group(function () {
        Route::get('/', [\App\Http\Controllers\RentalHistoryController::class, 'index'])->name('index');
        Route::get('/{rentalHistory}/invoice', [\App\Http\Controllers\RentalHistoryController::class, 'downloadInvoice'])->name('invoice');
        Route::get('/{rentalHistory}', [\App\Http\Controllers\RentalHistoryController::class, 'show'])->name('show');
    });

    // ─── PRODUK ──────────────────────────────────────────────────────
    Route::prefix('products')->name('products.')->group(function () {
        Route::middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko')->group(function () {
            Route::get('/create', [ProductController::class, 'create'])->name('create');
            Route::post('/', [ProductController::class, 'store'])->name('store');
            Route::get('/maintenance', [\App\Http\Controllers\ProductMaintenanceController::class, 'index'])->name('maintenance');
            Route::patch('/maintenance/{maintenance}/restore', [\App\Http\Controllers\ProductMaintenanceController::class, 'restore'])->name('maintenance.restore');
            Route::delete('/maintenance/{maintenance}/dispose', [\App\Http\Controllers\ProductMaintenanceController::class, 'dispose'])->name('maintenance.dispose');
            Route::patch('/{product}/update-stock', [ProductController::class, 'updateStock'])->name('update-stock');
            Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
            Route::put('/{product}', [ProductController::class, 'update'])->name('update');
            Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
        });

        Route::middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko,sales')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::get('/{product}', [ProductController::class, 'show'])->name('show')->withTrashed();
        });
    });

    // ─── KATEGORI (super_admin) ───────────────────────────────────────
    Route::prefix('categories')->name('categories.')->middleware(\App\Http\Middleware\CheckRole::class . ':super_admin')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::get('/create', [CategoryController::class, 'create'])->name('create');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::get('/{category}/edit', [CategoryController::class, 'edit'])->name('edit');
        Route::patch('/{category}', [CategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
    });

    // ─── CABANG (super_admin) ───────────────────────────────────────
    Route::prefix('branches')->name('branches.')->middleware(\App\Http\Middleware\CheckRole::class . ':super_admin')->group(function () {
        Route::get('/', [BranchController::class, 'index'])->name('index');
        Route::get('/create', [BranchController::class, 'create'])->name('create');
        Route::post('/', [BranchController::class, 'store'])->name('store');
        Route::get('/{branch}', [BranchController::class, 'show'])->name('show');
        Route::get('/{branch}/edit', [BranchController::class, 'edit'])->name('edit');
        Route::patch('/{branch}', [BranchController::class, 'update'])->name('update');
        Route::delete('/{branch}', [BranchController::class, 'destroy'])->name('destroy');
    });

    // ─── PENGGUNA (super_admin) ─────────────────────────────────────
    Route::prefix('users')->name('users.')->middleware(\App\Http\Middleware\CheckRole::class . ':super_admin')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::patch('/{user}', [UserController::class, 'update'])->name('update');
        Route::patch('/{user}/toggle', [UserController::class, 'toggle'])->name('toggle');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
        Route::get('/{user}', [UserController::class, 'show'])->name('show');
    });

    // ─── PENGATURAN (super_admin) ───────────────────────────────────
    Route::prefix('settings')->name('settings.')->middleware(\App\Http\Middleware\CheckRole::class . ':super_admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\SettingsController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\SettingsController::class, 'update'])->name('update');
    });

    // ─── LAPORAN ─────────────────────────────────────────────────────
    Route::prefix('reports')->middleware(\App\Http\Middleware\CheckRole::class . ':super_admin,admin_toko')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');
        Route::get('/transactions', [ReportController::class, 'transactions'])->name('reports.transactions');
        Route::get('/returns', [ReportController::class, 'returns'])->name('reports.returns');
        Route::get('/stock', [ReportController::class, 'stock'])->name('reports.stock');
        Route::get('/customers', [ReportController::class, 'customers'])->name('reports.customers');
        Route::get('/payments', [ReportController::class, 'payments'])->name('reports.payments');
        Route::middleware(\App\Http\Middleware\CheckRole::class . ':super_admin')->group(function () {
            Route::get('/activity-log', [ReportController::class, 'activityLog'])->name('reports.activity-log');
            Route::get('/activity-log/export/csv', [ReportController::class, 'exportActivityLog'])->name('reports.activity-log.export.csv');
            Route::get('/activity-log/export/pdf', [ReportController::class, 'exportActivityLogPdf'])->name('reports.activity-log.export.pdf');
        });

        Route::get('/revenue/export/excel', [ReportController::class, 'exportRevenueExcel'])->name('reports.export.revenue.excel');
        Route::get('/revenue/export/pdf', [ReportController::class, 'exportRevenuePdf'])->name('reports.export.revenue.pdf');
        Route::get('/transactions/export/excel', [ReportController::class, 'exportTransactionsExcel'])->name('reports.export.transactions.excel');
        Route::get('/transactions/export/pdf', [ReportController::class, 'exportTransactionsPdf'])->name('reports.export.transactions.pdf');
        Route::get('/returns/export/excel', [ReportController::class, 'exportReturnsExcel'])->name('reports.export.returns.excel');
        Route::get('/returns/export/pdf', [ReportController::class, 'exportReturnsPdf'])->name('reports.export.returns.pdf');
        Route::get('/stock/export/excel', [ReportController::class, 'exportStockExcel'])->name('reports.export.stock.excel');
        Route::get('/stock/export/pdf', [ReportController::class, 'exportStockPdf'])->name('reports.export.stock.pdf');
        Route::get('/customers/export/excel', [ReportController::class, 'exportCustomersExcel'])->name('reports.export.customers.excel');
        Route::get('/customers/export/pdf', [ReportController::class, 'exportCustomersPdf'])->name('reports.export.customers.pdf');
        Route::get('/payments/export/excel', [ReportController::class, 'exportPaymentsExcel'])->name('reports.export.payments.excel');
        Route::get('/payments/export/pdf', [ReportController::class, 'exportPaymentsPdf'])->name('reports.export.payments.pdf');

        Route::get('/export/excel', [ReportController::class, 'exportExcel'])->name('reports.export.excel');
        Route::get('/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');
    });
});

    // ─── SUPER ADMIN MODULES (Payment/Transaction) ─────────────────────
Route::middleware(['auth', \App\Http\Middleware\EnsureBranchScope::class])->group(function () {
    Route::middleware(\App\Http\Middleware\CheckRole::class . ':super_admin')->group(function () {
        Route::prefix('payments')->name('payments.')->scopeBindings()->group(function () {
            Route::post('/{invoice}/store', [PaymentController::class, 'store'])->name('store');
            Route::patch('/{invoice}/{payment}', [PaymentController::class, 'update'])->name('update');
            Route::delete('/{invoice}/{payment}', [PaymentController::class, 'destroy'])->name('destroy');
            Route::patch('/{invoice}/{payment}/void', [PaymentController::class, 'void'])->name('void');
            Route::patch('/{invoice}/{payment}/refund', [PaymentController::class, 'refund'])->name('refund');
        });

        Route::prefix('transactions')->name('transactions.')->group(function () {
            Route::get('/', [TransactionController::class, 'index'])->name('index');
            Route::get('/{transaction}', [TransactionController::class, 'show'])->name('show');
            Route::get('/{transaction}/edit', [TransactionController::class, 'edit'])->name('edit');
            Route::patch('/{transaction}', [TransactionController::class, 'update'])->name('update');
            Route::delete('/{transaction}', [TransactionController::class, 'destroy'])->name('destroy');
        });
    });
});

// ─── POINTS ──────────────────────────────────────────────
Route::middleware(['auth'])->prefix('points')->name('points.')->group(function () {
    Route::get('/', [\App\Http\Controllers\PointsController::class, 'index'])->name('index');
});
