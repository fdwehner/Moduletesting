<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OrgDesignerExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('org-designer.index');
    }

    return view('welcome');
})->name('home');

Route::post('/locale/{locale}', [LocaleController::class, 'update'])
    ->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::view('/register', 'auth.register')->name('register');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('org-designer.index'))->name('dashboard');
    Route::view('/org-designer', 'org-designer.index')->name('org-designer.index');
    Route::get('/org-designer/export/excel', [OrgDesignerExportController::class, 'excel'])
        ->name('org-designer.export.excel');
    Route::get('/org-designer/export/json', [OrgDesignerExportController::class, 'json'])
        ->name('org-designer.export.json');
});
