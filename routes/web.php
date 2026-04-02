<?php

use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        if (
            auth()
                ->user()
                ->hasRole('admin')
        ) {
            return redirect()->route('admin.dashboard');
        }
        if (
            auth()
                ->user()
                ->hasRole('frontdesk')
        ) {
            return redirect()->route('frontdesk.dashboard');
        }

        if (
            auth()
                ->user()
                ->hasRole('kiosk')
        ) {
            return redirect()->route('kiosk.house-rules');
        }

        if (
            auth()
                ->user()
                ->hasRole('roomboy')
        ) {
            return redirect()->route('roomboy.dashboard');
        }

        if (
            auth()
                ->user()
                ->hasRole('kitchen')
        ) {
            return redirect()->route('kitchen.dashboard');
        }

        if (
            auth()
                ->user()
                ->hasRole('superadmin')
        ) {
            return redirect()->route('superadmin.dashboard');
        }

        if (
            auth()
                ->user()
                ->hasRole('back_office')
        ) {
            return redirect()->route('back-office.dashboard');
        }
        if (
            auth()
                ->user()
                ->hasRole('pub_kitchen')
        ) {
            return redirect()->route('pub_kitchen.dashboard');
        }
    })->name('dashboard');
    
});
