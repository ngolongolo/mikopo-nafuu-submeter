<?php

use App\Http\Controllers\SupplierApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/supplier')->middleware(['supplier.api', 'throttle:60,1'])->group(function () {
    Route::post('/customers/onboard', [SupplierApiController::class, 'onboardCustomer']);
    Route::get('/loans/details', [SupplierApiController::class, 'loanDetails']);
    Route::post('/repayments', [SupplierApiController::class, 'submitRepayment']);
    Route::get('/meters/status', [SupplierApiController::class, 'meterStatus']);
});
