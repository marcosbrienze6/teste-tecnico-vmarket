<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/fornecedores', function () {
        return Inertia::render('Suppliers/Index');
    })->name('suppliers.page');

    Route::get('/produtos', function () {
        return Inertia::render('Products/Index');
    })->name('products.page');

    Route::get('/vinculos', function () {
        return Inertia::render('ProductSuppliers/Index');
    })->name('product-suppliers.page');

    Route::get('/pedidos', function () {
        return Inertia::render('OrdersIndex');
    })->name('orders.index.page');

    Route::get('/pedidos/{order}', function (int $order) {
        return Inertia::render('OrdersShow', [
            'orderId' => $order,
        ]);
    })->name('orders.details.page');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
