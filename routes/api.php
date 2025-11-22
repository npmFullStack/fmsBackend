<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContainerTypeController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\PortController;
use App\Http\Controllers\TruckCompController;
use App\Http\Controllers\ShipRouteController;
use App\Http\Controllers\ShippingLineController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CargoMonitoringController;
use App\Http\Controllers\AccountsPayableController;
use App\Http\Controllers\AccountsReceivableController;
use App\Http\Controllers\PaymentController;


Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');
});


// Category Route Group
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::post('/', [CategoryController::class, 'store']);
    Route::get('/{id}', [CategoryController::class, 'show']);
    Route::put('/{id}', [CategoryController::class, 'update']);
    Route::delete('/{id}', [CategoryController::class, 'destroy']);
    Route::post('/bulk-delete', [CategoryController::class, 'bulkDestroy']);
    Route::post('/{id}/restore', [CategoryController::class, 'restore']);
});

// Container Type Route Group
Route::prefix('container-types')->group(function () {
    Route::get('/', [ContainerTypeController::class, 'index']);
    Route::post('/', [ContainerTypeController::class, 'store']);
    Route::get('/{id}', [ContainerTypeController::class, 'show']);
    Route::put('/{id}', [ContainerTypeController::class, 'update']);
    Route::delete('/{id}', [ContainerTypeController::class, 'destroy']);
    Route::post('/bulk-delete', [ContainerTypeController::class, 'bulkDestroy']);
    Route::post('/{id}/restore', [ContainerTypeController::class, 'restore']);
});


// Ports Route Group
Route::prefix('ports')->group(function () {
    Route::get('/', [PortController::class, 'index']);
    Route::post('/', [PortController::class, 'store']);
    Route::get('/{id}', [PortController::class, 'show']);
    Route::put('/{id}', [PortController::class, 'update']);
    Route::delete('/{id}', [PortController::class, 'destroy']);
    Route::post('/bulk-delete', [PortController::class, 'bulkDestroy']);
    Route::post('/{id}/restore', [PortController::class, 'restore']);
});



//  Truck Companies Route Group
Route::prefix('truck-comps')->group(function () {
    Route::get('/', [TruckCompController::class, 'index']);
    Route::post('/', [TruckCompController::class, 'store']);
    Route::get('/{id}', [TruckCompController::class, 'show']);
    Route::put('/{id}', [TruckCompController::class, 'update']);
    Route::delete('/{id}', [TruckCompController::class, 'destroy']);
    Route::post('/bulk-delete', [TruckCompController::class, 'bulkDestroy']);
    Route::post('/{id}/restore', [TruckCompController::class, 'restore']);
});

// Shipping Lines Route Group
Route::prefix('shipping-lines')->group(function () {
    Route::get('/', [ShippingLineController::class, 'index']);
    Route::post('/', [ShippingLineController::class, 'store']);
    Route::get('/{id}', [ShippingLineController::class, 'show']);
    Route::put('/{id}', [ShippingLineController::class, 'update']);
    Route::delete('/{id}', [ShippingLineController::class, 'destroy']);
    Route::post('/bulk-delete', [ShippingLineController::class, 'bulkDestroy']);
    Route::post('/{id}/restore', [ShippingLineController::class, 'restore']);
    Route::get('/{id}/routes', [ShippingLineController::class, 'getRoutes']);
});

// Ship Routes Route Group
Route::prefix('ship-routes')->group(function () {
    Route::get('/', [ShipRouteController::class, 'index']);
    Route::post('/', [ShipRouteController::class, 'store']);
    Route::get('/{id}', [ShipRouteController::class, 'show']);
    Route::put('/{id}', [ShipRouteController::class, 'update']);
    Route::delete('/{id}', [ShipRouteController::class, 'destroy']);
    Route::post('/bulk-delete', [ShipRouteController::class, 'bulkDestroy']);
    Route::post('/{id}/restore', [ShipRouteController::class, 'restore']);
    Route::get('/find/route', [ShipRouteController::class, 'getRouteBetweenPorts']);
});

// Quotes Route Group
Route::prefix('quotes')->group(function () {
  Route::get('/', [QuoteController::class, 'index']);
  Route::post('/', [QuoteController::class, 'store']);
  Route::get('/{id}', [QuoteController::class, 'show']);
  Route::delete('/{id}', [QuoteController::class, 'destroy']);
  Route::post('/{id}/send', [QuoteController::class, 'sendQuote']);
});

// Bookings Route Group
Route::prefix('bookings')->group(function () {
    Route::get('/', [BookingController::class, 'index']);
    Route::post('/', [BookingController::class, 'store']); // For regular bookings with user
    Route::get('/{id}', [BookingController::class, 'show']);
    Route::put('/{id}', [BookingController::class, 'update']);
    Route::delete('/{id}', [BookingController::class, 'destroy']);
    Route::post('/bulk-delete', [BookingController::class, 'bulkDestroy']);
    Route::post('/{id}/restore', [BookingController::class, 'restore']);
    // Add approval route
    Route::post('/{id}/approve', [BookingController::class, 'approveBooking']);
});

// Users Route Group
Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::get('/{id}', [UserController::class, 'show']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::delete('/{id}', [UserController::class, 'destroy']);
    Route::post('/bulk-delete', [UserController::class, 'bulkDestroy']);
    Route::post('/{id}/restore', [UserController::class, 'restore']);
    Route::post('/{id}/promote', [UserController::class, 'promote']);
});

// Cargo Monitoring Route Group
Route::prefix('cargo-monitoring')->group(function () {
    Route::get('/', [CargoMonitoringController::class, 'index']); // Add this line
    Route::get('/booking/{bookingId}', [CargoMonitoringController::class, 'getByBooking']);
    Route::put('/{id}/status', [CargoMonitoringController::class, 'updateStatus']);
    Route::get('/{id}', [CargoMonitoringController::class, 'show']);
});


// Accounts Payable Route Group
Route::prefix('accounts-payables')->group(function () {
    Route::get('/', [AccountsPayableController::class, 'index']);
    Route::post('/', [AccountsPayableController::class, 'store']);
    Route::get('/{id}', [AccountsPayableController::class, 'show']);
    Route::put('/{id}', [AccountsPayableController::class, 'update']);
    Route::delete('/{id}', [AccountsPayableController::class, 'destroy']);
    Route::put('/{apId}/{chargeType}/{chargeId}', [AccountsPayableController::class, 'updateChargeStatus']);
Route::get('/booking/{bookingId}', [AccountsPayableController::class,
'getByBooking']);
});


Route::prefix('pay-charges')->group(function () {
    Route::get('/', [AccountsPayableController::class, 'getPayableCharges']);
    Route::get('/booking/{bookingId}', [AccountsPayableController::class, 'getPayableChargesByBooking']);
    Route::post('/mark-paid', [AccountsPayableController::class, 'markChargesAsPaid']);
    Route::post('/mark-multiple-paid', [AccountsPayableController::class, 'markMultipleChargesAsPaid']);
});

// Accounts Receivable Route Group
Route::prefix('accounts-receivables')->group(function () {
    Route::get('/', [AccountsReceivableController::class, 'index']);
    Route::post('/', [AccountsReceivableController::class, 'store']);
    Route::get('/summary', [AccountsReceivableController::class, 'getFinancialSummary']);
    Route::get('/{id}', [AccountsReceivableController::class, 'show']);
    Route::put('/{id}', [AccountsReceivableController::class, 'update']);
    Route::delete('/{id}', [AccountsReceivableController::class, 'destroy']);
    Route::get('/booking/{bookingId}', [AccountsReceivableController::class, 'getByBooking']);
    Route::post('/{id}/mark-paid', [AccountsReceivableController::class, 'markAsPaid']);
    Route::post('/booking/{bookingId}/update-delivery', [AccountsReceivableController::class, 'updateOnDelivery']);
    Route::post('/{id}/process-payment', [AccountsReceivableController::class, 'processPayment']);
    Route::get('/{id}/payment-breakdown', [AccountsReceivableController::class,
    'getPaymentBreakdown']);
Route::post('/{id}/send-payment-email', [AccountsReceivableController::class,
'sendPaymentEmail']);
});



// Payments Route Group
Route::prefix('payments')->group(function () {
    Route::get('/', [PaymentController::class, 'index']);
    Route::post('/', [PaymentController::class, 'store']);
    Route::get('/{id}', [PaymentController::class, 'show']);
    Route::put('/{id}', [PaymentController::class, 'update']);
    Route::delete('/{id}', [PaymentController::class, 'destroy']);
    Route::get('/booking/{bookingId}', [PaymentController::class, 'getByBooking']);
    Route::post('/{id}/process-gcash', [PaymentController::class, 'processGCashPayment']);
});

// Customer-specific routes
Route::prefix('customer')->middleware('auth:sanctum')->group(function () {
    Route::get('/bookings', [BookingController::class, 'getCustomerBookings']);
    Route::get('/bookings/{id}', [BookingController::class, 'getCustomerBooking']);
    Route::post('/bookings/{id}/pay', [PaymentController::class, 'createPayment']);
    Route::get('/accounts-receivables', [AccountsReceivableController::class, 'getCustomerReceivables']);
    Route::post('/payments/paymongo', [PaymongoController::class,
    'createPaymentIntent']);
});

Route::prefix('paymongo')->group(function () {
    Route::post('/create-payment-intent', [PaymongoController::class, 'createPaymentIntent']);
    Route::post('/webhook', [PaymongoController::class, 'handleWebhook']);
});