<?php

use App\Http\Livewire\Supervisor\Dashboard;

Route::prefix('supervisor')
    ->middleware(['auth', 'role:supervisor'])
    ->group(function () {
        Route::get('/dashboard', Dashboard::class)->name('supervisor.dashboard');
        Route::get('/archives', function () {
            return view('supervisor.archives');
        })->name('supervisor.archives');
        Route::get('/report-hub', function () {
            return view('supervisor.report-hub');
        })->name('supervisor.report-hub');
    });
