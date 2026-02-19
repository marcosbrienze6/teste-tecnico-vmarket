<?php

use App\Http\Controllers\Api\BatchOperationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductSupplierController;
use App\Http\Controllers\Api\SupplierController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('products', ProductController::class);

    Route::get('products/{product}/suppliers', [ProductSupplierController::class, 'index']);
    Route::post('products/{product}/suppliers', [ProductSupplierController::class, 'store']);
    Route::delete('products/{product}/suppliers/{supplier}', [ProductSupplierController::class, 'destroy']);
    Route::post('products/{product}/suppliers/bulk-link', [ProductSupplierController::class, 'bulkStore']);
    Route::post('products/{product}/suppliers/bulk-unlink', [ProductSupplierController::class, 'bulkDestroy']);
    Route::get('suppliers/{supplier}/products', [ProductSupplierController::class, 'products']);
    Route::get('batch-operations/{batchOperation}', [BatchOperationController::class, 'show']);

    Route::apiResource('orders', OrderController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('orders/{order}/items', [OrderController::class, 'addItem']);
    Route::delete('orders/{order}/items/{item}', [OrderController::class, 'removeItem']);
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);
});
