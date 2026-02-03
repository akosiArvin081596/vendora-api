<?php

use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\VendorController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\StoreProductController;
use App\Http\Controllers\Api\StoreStaffController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    // Rate limit: 5 attempts per minute for auth endpoints (brute-force protection)
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me', [UserController::class, 'show'])->name('auth.me');
    });
});

// Public product/category READ endpoints (for e-commerce browsing)
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/sku/{sku}', [ProductController::class, 'showBySku'])->name('products.showBySku');
Route::get('/products/barcode/{code}', [ProductController::class, 'showByBarcode'])->name('products.showByBarcode');

// POS endpoint - returns only authenticated user's products (must be before {product} route)
Route::get('/products/my', [ProductController::class, 'my'])->middleware('auth:sanctum')->name('products.my');

Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'show'])->name('user');

    Route::prefix('dashboard')->group(function () {
        Route::get('/kpis', [DashboardController::class, 'kpis'])->name('dashboard.kpis');
        Route::get('/sales-trend', [DashboardController::class, 'salesTrend'])->name('dashboard.sales-trend');
        Route::get('/orders-by-channel', [DashboardController::class, 'ordersByChannel'])->name('dashboard.orders-by-channel');
        Route::get('/payment-methods', [DashboardController::class, 'paymentMethods'])->name('dashboard.payment-methods');
        Route::get('/top-products', [DashboardController::class, 'topProducts'])->name('dashboard.top-products');
        Route::get('/inventory-health', [DashboardController::class, 'inventoryHealth'])->name('dashboard.inventory-health');
        Route::get('/low-stock-alerts', [DashboardController::class, 'lowStockAlerts'])->name('dashboard.low-stock-alerts');
        Route::get('/pending-orders', [DashboardController::class, 'pendingOrders'])->name('dashboard.pending-orders');
        Route::get('/recent-activity', [DashboardController::class, 'recentActivity'])->name('dashboard.recent-activity');
    });

    // Protected product write operations
    Route::patch('/products/{product}/stock', [ProductController::class, 'updateStock'])->name('products.updateStock');
    Route::post('/products/bulk-stock-decrement', [ProductController::class, 'bulkStockDecrement'])->name('products.bulkStockDecrement');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::patch('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    // Protected category write operations
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::patch('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger.index');
    Route::get('/ledger/summary', [LedgerController::class, 'summary'])->name('ledger.summary');
    Route::post('/ledger', [LedgerController::class, 'store'])->name('ledger.store');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/summary', [InventoryController::class, 'summary'])->name('inventory.summary');
    Route::post('/inventory/adjustments', [InventoryController::class, 'storeAdjustment'])->name('inventory.adjustments.store');
    Route::get('/customers/summary', [CustomerController::class, 'summary'])->name('customers.summary');
    Route::apiResource('customers', CustomerController::class);
    Route::get('/orders/summary', [OrderController::class, 'summary'])->name('orders.summary');
    Route::apiResource('orders', OrderController::class);
    Route::get('/payments/summary', [PaymentController::class, 'summary'])->name('payments.summary');
    Route::apiResource('payments', PaymentController::class);

    // Store Management
    Route::apiResource('stores', StoreController::class);
    Route::get('/stores/{store}/staff', [StoreStaffController::class, 'index'])->name('stores.staff.index');
    Route::post('/stores/{store}/staff', [StoreStaffController::class, 'store'])->name('stores.staff.store');
    Route::patch('/stores/{store}/staff/{user}', [StoreStaffController::class, 'update'])->name('stores.staff.update');
    Route::delete('/stores/{store}/staff/{user}', [StoreStaffController::class, 'destroy'])->name('stores.staff.destroy');
    Route::get('/store-roles', [StoreStaffController::class, 'roles'])->name('stores.roles');

    // Store Products (inventory per store)
    Route::get('/stores/{store}/products', [StoreProductController::class, 'index'])->name('stores.products.index');
    Route::post('/stores/{store}/products', [StoreProductController::class, 'store'])->name('stores.products.store');
    Route::get('/stores/{store}/products/{product}', [StoreProductController::class, 'show'])->name('stores.products.show');
    Route::patch('/stores/{store}/products/{product}', [StoreProductController::class, 'update'])->name('stores.products.update');
    Route::delete('/stores/{store}/products/{product}', [StoreProductController::class, 'destroy'])->name('stores.products.destroy');

    // Admin Routes
    Route::prefix('admin')->group(function () {
        // User Management
        Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('admin.users.show');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
        Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])->name('admin.users.status');

        // Vendor Management
        Route::post('/vendors', [VendorController::class, 'store'])->name('admin.vendors.store');
    });
});
