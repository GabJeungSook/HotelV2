<?php

Route::prefix('admin')
    ->middleware(['auth', 'role:admin|superadmin'])
    ->group(function () {
        Route::get('/dashboard', function () {
            return view('admin.index');
        })->name('admin.dashboard');
        Route::get('/types', function () {
            return view('admin.manage.type');
        })->name('admin.type');
        Route::get('/manage-frontdesk', function () {
            return view('admin.manage-frontdesk');
        })->name('admin.manage-frontdesk');
        Route::get('/rates', function () {
            return view('admin.manage.rate');
        })->name('admin.rate');
        Route::get('/floors', function () {
            return view('admin.manage.floor');
        })->name('admin.floor');
        Route::get('/rooms', function () {
            return view('admin.manage.room');
        })->name('admin.room');
        Route::get('/frontdesk-kitchen', function () {
                return view('admin.manage.kitchen-inventory');
        })->name('admin.food-inventory');
        Route::get('/stock-movements', function () {
                return view('admin.manage.stock-movements');
        })->name('admin.stock-movements');
        Route::get('/price-changes', function () {
                return view('admin.manage.price-changes');
        })->name('admin.price-changes');
        Route::get('/food/category', function () {
                return view('frontdesk.food.category');
        })->name('frontdesk.food-category');
        Route::get('/food/menu', function () {
                return view('frontdesk.food.menu');
        })->name('frontdesk.food-menu');
        Route::get('/food/inventory/{record}', fn($record) => view('frontdesk.food.inventory', compact('record')))
            ->name('frontdesk.food-inventories');
        Route::get('/users', function () {
            return view('admin.manage.user');
        })->name('admin.user');
        Route::get('/discounts', function () {
            return view('admin.manage.discount');
        })->name('admin.discount');
        Route::get('/extension-rates', function () {
            return view('admin.manage.extension-rate');
        })->name('admin.extension-rate');
        Route::get('/damage-charges', function () {
            return view('admin.manage.charges');
        })->name('admin.charges');
        Route::get('/amenities', function () {
            return view('admin.manage.amenities');
        })->name('admin.amenities');
        Route::get('/manage-settings', function () {
            return view('admin.settings');
        })->name('admin.settings');

        Route::get('/roomboy-designation', function () {
            return view('admin.roomboy-designation');
        })->name('admin.roomboy-designation');
        Route::get('/priority-room', function () {
            return view('admin.priority-room');
        })->name('admin.priority-room');
        Route::get('/reservation', function () {
            return view('admin.manage.reservation');
        })->name('admin.reservation');
        Route::get('/activity-logs', function () {
            return view('superadmin.activity-logs');
        })->name('admin.activity-logs');
        Route::get('/check-in-co', function () {
            return view('admin.check-in-co-admin');
        })->name('admin.check-in-co');
         Route::get('/manage-cash-drawers', function () {
            return view('admin.manage.cash-drawers');
        })->name('admin.cash-drawers');

        Route::get('/unresolved-check-ins', \App\Http\Livewire\Admin\UnresolvedCheckIns::class)
            ->name('admin.unresolved-check-ins');

        Route::get('/ghost-rooms', \App\Http\Livewire\Admin\GhostRooms::class)
            ->name('admin.ghost-rooms');
    });
