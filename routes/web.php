<?php
// routes/web.php
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\CarbonCreditController;

Route::get('/', function () {
    return view('welcome');
});

// Auth routes (Using Laravel Breeze/UI)
require __DIR__.'/auth.php';

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth')->name('dashboard');

Route::get('/post', [DashboardController::class, 'post'])->middleware(['auth', 'admin']);

Route::middleware(['auth'])->group(function () {

    // Emission Monitoring Routes
    Route::get('/emission-monitoring', [DashboardController::class, 'emissionMonitoring'])->name('emission.monitoring');
    Route::get('/api/emission-data', [DashboardController::class, 'getEmissionData'])->name('api.emission.data');

    // Device Management Routes
    Route::get('/devices', [App\Http\Controllers\DeviceController::class, 'index'])->name('devices.index');
    Route::get('/devices/create/{carbonCredit}', [App\Http\Controllers\DeviceController::class, 'create'])->name('devices.create');
    Route::post('/devices/{carbonCredit}', [App\Http\Controllers\DeviceController::class, 'store'])->name('devices.store');
    Route::get('/devices/{carbonCredit}', [App\Http\Controllers\DeviceController::class, 'show'])->name('devices.show');
    Route::get('/devices/{carbonCredit}/edit', [App\Http\Controllers\DeviceController::class, 'edit'])->name('devices.edit');
    Route::patch('/devices/{carbonCredit}', [App\Http\Controllers\DeviceController::class, 'update'])->name('devices.update');
    Route::delete('/devices/{carbonCredit}', [App\Http\Controllers\DeviceController::class, 'destroy'])->name('devices.destroy');
    Route::get('/devices/{carbonCredit}/qr-code', [App\Http\Controllers\DeviceController::class, 'generateQrCode'])->name('devices.qr-code');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/carbon-credits/{carbonCredit}/request-sale', [CarbonCreditController::class, 'requestSale'])
            ->name('carbon-credits.request-sale');
    Route::patch('/carbon-credits/{carbonCredit}/request-sale', [CarbonCreditController::class, 'submitSaleRequest'])
        ->name('carbon-credits.submit-sale-request');

    // Dashboard
    // Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth')->name('dashboard');
    
    // New route for vehicles list
    Route::get('/carbon-credits/vehicles', [CarbonCreditController::class, 'vehicles'])->name('carbon-credits.vehicles');

    // Carbon Credits routes (semua user bisa mengelola kuota karbon mereka)
    Route::resource('carbon-credits', CarbonCreditController::class);
    
    // Admin only routes untuk approval kuota karbon
    Route::middleware([RoleMiddleware::class.':admin'])->group(function () {
        Route::patch('/carbon-credits/{carbonCredit}/approve', [CarbonCreditController::class, 'approve'])
            ->name('carbon-credits.approve');
        Route::patch('/carbon-credits/{carbonCredit}/reject', [CarbonCreditController::class, 'reject'])
            ->name('carbon-credits.reject');
        Route::patch('/carbon-credits/{carbonCredit}/set-available', [CarbonCreditController::class, 'setAvailable'])
            ->name('carbon-credits.set-available');

        
        // Route untuk approve sale request
        Route::patch('/carbon-credits/{carbonCredit}/approve-sale-request', [CarbonCreditController::class, 'approveSaleRequest'])
            ->name('carbon-credits.approve-sale-request');

        // Route untuk admin reject sale request
        Route::patch('/carbon-credits/{carbonCredit}/reject-sale-request', [CarbonCreditController::class, 'rejectSaleRequest'])
            ->name('carbon-credits.reject-sale-request');
        // Route untuk melihat pending sale requests (admin only)
        Route::get('/carbon-credits/pending-sale-requests', [CarbonCreditController::class, 'pendingSaleRequests'])
            ->name('carbon-credits.pending-sale-requests');

        // Admin marketplace route
        Route::get('/admin/marketplace', [MarketplaceController::class, 'adminIndex'])->name('admin.marketplace');
    });
    
    // Marketplace routes (semua user bisa akses marketplace)
    Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace');
    Route::get('/marketplace/{carbonCredit}', [MarketplaceController::class, 'show'])->name('marketplace.show');
    
    // Transaction routes (semua user bisa bertransaksi)
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::get('/transactions/create/{carbonCredit}', [TransactionController::class, 'create'])->name('transactions.create');
    Route::post('/transactions/{carbonCredit}', [TransactionController::class, 'store'])->name('transactions.store');
    Route::get('/transactions/{transaction}/payment', [TransactionController::class, 'showPayment'])->name('transactions.payment');
    
    // Payout routes (semua user bisa lihat payout mereka)
    Route::get('/payouts', [PayoutController::class, 'index'])->name('payouts.index');
    Route::get('/payouts/{payout}', [PayoutController::class, 'show'])->name('payouts.show');

    // Create payout (step 1) - bisa diakses oleh user untuk payout mereka sendiri
    Route::post('/payouts/{payout}/create', [PayoutController::class, 'create'])->name('payouts.create');
    
    // Approve payout (step 2) - bisa diakses oleh user untuk payout mereka sendiri
    Route::get('/payouts/{payout}/approve', [PayoutController::class, 'showApproveForm'])->name('payouts.approve.form');
    Route::post('/payouts/{payout}/approve', [PayoutController::class, 'approve'])->name('payouts.approve');
    
    // Admin only untuk mengelola payout
    Route::middleware([RoleMiddleware::class.':admin'])->group(function () {
        // Process payout (create + approve in one step)
        Route::post('/payouts/{payout}/process', [PayoutController::class, 'process'])->name('payouts.process');
        
        // Check payout status manually
        Route::post('/payouts/{payout}/check-status', [PayoutController::class, 'checkStatus'])->name('payouts.check-status');

        // Admin payout management page
        Route::get('/admin/payouts', [PayoutController::class, 'adminIndex'])->name('payouts.admin.index');
    });
});

// Notification URLs yang akan dipanggil oleh Midtrans
Route::post('/payment-notification', [TransactionController::class, 'handlePaymentNotification'])
    ->name('payment.notification');
Route::post('/payout-notification', [PayoutController::class, 'handlePayoutNotification'])
    ->name('payout.notification');

// Device setup route (untuk teknisi, tanpa auth)
Route::get('/device-setup/{deviceId}', [App\Http\Controllers\DeviceController::class, 'setup'])->name('devices.setup');

// API routes untuk webhook (tanpa CSRF protection)
Route::middleware('api')->group(function () {
    Route::post('/api/payment-callback', [TransactionController::class, 'handlePaymentNotification']);
    Route::post('/api/payout-callback', [PayoutController::class, 'handlePayoutNotification']);
});
