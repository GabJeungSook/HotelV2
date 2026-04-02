<?php

Route::prefix('kiosk')
    ->middleware(['auth', 'role:kiosk'])
    ->group(function () {
        Route::get('/house-rules', function () {
            return view('kiosk.house-rules');
        })->name('kiosk.house-rules');
        Route::get('/dashboard', function () {
            return view('kiosk.index');
        })->name('kiosk.dashboard');
        Route::get('/check-in', function () {
            return view('kiosk.check-in');
        })->name('kiosk.check-in');
        Route::get('/check-out', function () {
            return view('kiosk.check-out');
        })->name('kiosk.check-out');
        Route::get('/check-out/success', function () {
            return view('kiosk.partials-checkout.check-out-success');
        })->name('kiosk.check-out-success');
    });
