<?php

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/invoice', [InvoiceController::class, 'index']);
Route::get('/invoice/{invoice_id}', [InvoiceController::class, 'show'])->where('invoice_id', '[0-9]+');
Route::post('/invoice', [InvoiceController::class, 'store']);
Route::post('/invoice/{invoice_id}', [InvoiceController::class, 'update'])->where('invoice_id', '[0-9]+');
Route::delete('/invoice/{invoice_id}', [InvoiceController::class, 'destroy']);
Route::post('/invoice/amount', [InvoiceController::class, 'calculateTotalAmountBetweenTwoDates']);
Route::get('/invoice/status', [InvoiceController::class, 'getInvoicesCountsByStatus']);

Route::get('/payment', [PaymentController::class, 'index']);
Route::get('/payment/{payment_id}', [PaymentController::class, 'show'])->where('payment_id', '[0-9]+');
Route::post('/payment', [PaymentController::class, 'store']);
Route::post('/payment/{payment_id}', [PaymentController::class, 'update'])->where('payment_id', '[0-9]+');
Route::delete('/payment/{payment_id}', [PaymentController::class, 'destroy']);
Route::post('/payment/amount', [PaymentController::class, 'calculateTotalAmountBetweenTwoDates']);