<?php

use App\Http\Livewire\Supervisor\Dashboard;

Route::prefix('supervisor')
    ->middleware(['auth', 'role:supervisor'])
    ->group(function () {
        // SUPERVISOR MODULE ON HOLD — all supervisor routes redirect to the
        // "module on hold" placeholder page. Original routes are preserved as
        // comments below; uncomment to restore.
        // ----------------------------------------------------------------------
        // Route::get('/dashboard', Dashboard::class)->name('supervisor.dashboard');
        // Route::get('/archives', function () {
        //     return view('supervisor.archives');
        // })->name('supervisor.archives');
        // Route::get('/report-hub', function () {
        //     return view('supervisor.report-hub');
        // })->name('supervisor.report-hub');

        Route::get('/dashboard', function () {
            return view('supervisor.module-on-hold');
        })->name('supervisor.dashboard');

        Route::get('/archives', function () {
            return redirect()->route('supervisor.dashboard');
        })->name('supervisor.archives');

        Route::get('/report-hub', function () {
            return redirect()->route('supervisor.dashboard');
        })->name('supervisor.report-hub');
    });
