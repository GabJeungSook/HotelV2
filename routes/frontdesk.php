<?php

Route::prefix('frontdesk')
    ->middleware(['auth', 'role:frontdesk'])
    ->group(function () {
        Route::get('/dashboard', function () {
            if (auth()->user()->cash_drawer_id != null) {
                return view('frontdesk.index');
            } else {
                return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.dashboard');
        Route::get('/room-monitoring', function () {
            if (auth()->user()->cash_drawer_id != null) {
                return view('frontdesk.monitoring.room-monitorings');
            } else {
                return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.room-monitoring');
        Route::get('/check-in-from-kiosk/{record}', function ($record) {
            if (auth()->user()->cash_drawer_id != null) {
            return view('frontdesk.monitoring.check-in-from-kiosk', ['record' => $record]);
            } else {
            return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.check-in-from-kiosk');
         Route::get('/scan-qr', function () {
            if (auth()->user()->cash_drawer_id != null) {
                return view('frontdesk.monitoring.scan-qr-code');
            } else {
                return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.scan-qr-code');
        Route::get('/check-out-guest/{record}', function ($record) {
            if (auth()->user()->cash_drawer_id != null) {
            return view('frontdesk.guest-transactions.check-out-guest', ['record' => $record]);
            } else {
            return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.check-out-guest');
        // Route::get('/food-inventory', function () {
        //     if (auth()->user()->assigned_frontdesks != null) {
        //         return view('frontdesk.food-inventory');
        //     } else {
        //         return view('frontdesk.select-frontdesk');
        //     }
        // })->name('frontdesk.food-inventory');
        // Route::get('/food/category', function () {
        //     if (auth()->user()->assigned_frontdesks != null) {
        //         return view('frontdesk.food.category');
        //     } else {
        //         return view('frontdesk.select-frontdesk');
        //     }
        // })->name('frontdesk.food-category');
        // Route::get('/food/menu', function () {
        //     if (auth()->user()->assigned_frontdesks != null) {
        //         return view('frontdesk.food.menu');
        //     } else {
        //         return view('frontdesk.select-frontdesk');
        //     }
        // })->name('frontdesk.food-menu');
        // Route::get('/food/inventory', function () {
        //     if (auth()->user()->assigned_frontdesks != null) {
        //         return view('frontdesk.food.inventory');
        //     } else {
        //         return view('frontdesk.select-frontdesk');
        //     }
        // })->name('frontdesk.food-inventories');
        Route::get('/priority-room', function () {
            return view('frontdesk.priority-room');
        })->name('frontdesk.priority-room');
        Route::get('/manage-guest/{id}', function () {
            if (auth()->user()->cash_drawer_id != null) {
                return view('frontdesk.monitoring.manage-guest');
            } else {
                return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.manage-guest');

        Route::get('/guest-transaction/{id}', function () {
            return view('frontdesk.monitoring.guest-transaction');
        })->name('frontdesk.guest-transaction');
         Route::get('/extend-guest/{record}', function ($record) {
            if (auth()->user()->cash_drawer_id != null) {
            return view('frontdesk.monitoring.extend-guest', ['record' => $record]);
            } else {
            return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.extend-guest');
         Route::get('/transfer-room/{record}', function ($record) {
            if (auth()->user()->cash_drawer_id != null) {
            return view('frontdesk.monitoring.transfer-room', ['record' => $record]);
            } else {
            return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.transfer-room');
        Route::get('/check-in-co', function () {
            if (auth()->user()->cash_drawer_id != null) {
                return view('frontdesk.check-in-co-frontdesk');
            } else {
                return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.check-in-co-frontdesk');
        Route::get('/cash-on-hand', function () {
            if (auth()->user()->cash_drawer_id != null) {
                return view('frontdesk.cash-on-hand');
            } else {
                return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.cash-on-hand');
        Route::get('/beginning-cash', function () {
            if (auth()->user()->cash_drawer_id != null) {
                return view('frontdesk.beginning-cash');
            } else {
                return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.beginning-cash');
        Route::get('/frontdesk-extension', function () {
            if (auth()->user()->cash_drawer_id != null) {
                return view('frontdesk.frontdesk-extension');
            } else {
                return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.frontdesk-extension');
        Route::get('/frontdesk-expenses', function () {
            if (auth()->user()->cash_drawer_id != null) {
                return view('back-office.expenses');
            } else {
                return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.expenses');
        Route::get('/frontdesk-expense-report', function () {
            if (auth()->user()->cash_drawer_id != null) {
                return view('back-office.expense-report');
            } else {
                return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.expense-report');
         Route::get('/frontdesk-remittance', function () {
            if (auth()->user()->cash_drawer_id != null) {
                return view('frontdesk.remittance');
            } else {
                return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.remittance');
        Route::get('/frontdesk-payment-on-short', function () {
            if (auth()->user()->cash_drawer_id != null) {
                return view('frontdesk.payment-on-short');
            } else {
                return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.payment-on-short');
        Route::get('/frontdesk-remittance-report', function () {
            if (auth()->user()->cash_drawer_id != null) {
                return view('frontdesk.remittance-report');
            } else {
                return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.remittance-report');
        Route::get('/frontdesk-point-of-sale', function () {
            if (auth()->user()->cash_drawer_id != null) {
                return view('frontdesk.point-of-sale');
            } else {
                return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.point-of-sale');
        Route::get('/stock-in', function () {
            if (auth()->user()->cash_drawer_id != null) {
                return view('frontdesk.stock-in');
            } else {
                return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.stock-in');
        Route::get('/override-requests', function () {
            if (auth()->user()->cash_drawer_id != null) {
                return view('frontdesk.override-requests');
            } else {
                return view('frontdesk.select-frontdesk');
            }
        })->name('frontdesk.override-requests');
    });
