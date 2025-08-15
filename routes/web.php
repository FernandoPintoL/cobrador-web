<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    /*Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // Rutas del Dashboard
    Route::get('dashboard/map', function () {
        return Inertia::render('dashboard/map-view');
    })->name('dashboard.map');

    Route::get('dashboard/cash-reconciliation', function () {
        return Inertia::render('dashboard/cash-reconciliation');
    })->name('dashboard.cash-reconciliation');

    // Rutas de Usuarios
    Route::get('users', function () {
        return Inertia::render('users/index');
    })->name('users.index');

    Route::get('users/create', function () {
        return Inertia::render('users/create');
    })->name('users.create');

    Route::get('users/{user}', function ($user) {
        return Inertia::render('users/show', ['user' => $user]);
    })->name('users.show');

    Route::get('users/{user}/edit', function ($user) {
        return Inertia::render('users/edit', ['user' => $user]);
    })->name('users.edit');

    // Rutas de Rutas de Cobro
    Route::get('routes', function () {
        return Inertia::render('routes/index');
    })->name('routes.index');

    Route::get('routes/create', function () {
        return Inertia::render('routes/create');
    })->name('routes.create');

    Route::get('routes/{route}', function ($route) {
        return Inertia::render('routes/show', ['route' => $route]);
    })->name('routes.show');

    Route::get('routes/{route}/edit', function ($route) {
        return Inertia::render('routes/edit', ['route' => $route]);
    })->name('routes.edit');

    // Rutas de Créditos
    Route::get('credits', function () {
        return Inertia::render('credits/index');
    })->name('credits.index');

    Route::get('credits/create', function () {
        return Inertia::render('credits/create');
    })->name('credits.create');

    Route::get('credits/{credit}', function ($credit) {
        return Inertia::render('credits/show', ['credit' => $credit]);
    })->name('credits.show');

    Route::get('credits/{credit}/edit', function ($credit) {
        return Inertia::render('credits/edit', ['credit' => $credit]);
    })->name('credits.edit');

    // Rutas de Pagos
    Route::get('payments', function () {
        return Inertia::render('payments/index');
    })->name('payments.index');

    Route::get('payments/create', function () {
        return Inertia::render('payments/create');
    })->name('payments.create');

    Route::get('payments/{payment}', function ($payment) {
        return Inertia::render('payments/show', ['payment' => $payment]);
    })->name('payments.show');

    Route::get('payments/{payment}/edit', function ($payment) {
        return Inertia::render('payments/edit', ['payment' => $payment]);
    })->name('payments.edit');

    // Rutas de Arqueo de Caja
    Route::get('cash-balances', function () {
        return Inertia::render('cash-balances/index');
    })->name('cash-balances.index');

    Route::get('cash-balances/create', function () {
        return Inertia::render('cash-balances/create');
    })->name('cash-balances.create');

    Route::get('cash-balances/{cashBalance}', function ($cashBalance) {
        return Inertia::render('cash-balances/show', ['cashBalance' => $cashBalance]);
    })->name('cash-balances.show');

    Route::get('cash-balances/{cashBalance}/edit', function ($cashBalance) {
        return Inertia::render('cash-balances/edit', ['cashBalance' => $cashBalance]);
    })->name('cash-balances.edit');

    // Rutas de Notificaciones
    Route::get('notifications', function () {
        return Inertia::render('notifications/index');
    })->name('notifications.index');

    Route::get('notifications/create', function () {
        return Inertia::render('notifications/create');
    })->name('notifications.create');

    Route::get('notifications/{notification}', function ($notification) {
        return Inertia::render('notifications/show', ['notification' => $notification]);
    })->name('notifications.show');

    Route::get('notifications/{notification}/edit', function ($notification) {
        return Inertia::render('notifications/edit', ['notification' => $notification]);
    })->name('notifications.edit');*/
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
