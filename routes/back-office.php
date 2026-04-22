<?php

Route::prefix('back-office')
    ->middleware(['auth', 'role:back_office'])
    ->group(function () {
        Route::get('/dashboard', function () {
            return view('back-office.index');
        })->name('back-office.dashboard');
        Route::get('/sales', function () {
            return view('back-office.sales');
        })->name('back-office.sales');
        Route::get('/shift-reports', function () {
            return view('back-office.frontdesk-shift-table');
        })->name('back-office.frontdesk-shift-table');
         Route::get('/shift-report-form', function () {
            return view('back-office.frontdesk-shift-form');
        })->name('back-office.frontdesk-shift-form');

        Route::get('/shift-report-form-edit/{id}', function ($id) {
            return view('back-office.frontdesk-shift-edit', compact('id'));
        })->name('back-office.frontdesk-shift-edit');

        // Route::get('/expenses', function () {
        //     return view('back-office.expenses');
        // })->name('back-office.expenses');
        // Route::get('/expense-report', function () {
        //     return view('back-office.expense-report');
        // })->name('back-office.expense-report');
        Route::get('/sales-report', function () {
            return view('back-office.sales-report');
        })->name('back-office.sales-report');
         Route::get('/inventory-report', function () {
            return view('back-office.inventory-report');
        })->name('back-office.inventory-report');
        Route::get('/report-hub', function () {
            return view('back-office.report-hub');
        })->name('back-office.report-hub');
        Route::get('/reports', function () {
            return view('back-office.reports');
        })->name('back-office.reports');
        Route::get('/frontdesk-report', function () {
            return view('back-office.Reports.frontdesk-report');
        })->name('back-office.frontdesk-report');
        Route::get('/frontdesk-report-v2', function () {
            return view('back-office.frontdesk-report-v2');
        })->name('back-office.frontdesk-report-v2');
        Route::get('/gust-extension-report', function () {
            return view('back-office.Reports.extended-guest');
        })->name('back-office.extended-guest-report');
        Route::get('/archives', function () {
            return view('back-office.archives');
        })->name('back-office.archives');

        Route::get('/roomboy-penalty-report', function () {
            return view('back-office.roomboy-penalty-report');
        })->name('back-office.roomboy-penalty-report');
    });
