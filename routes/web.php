<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OrgDesignerExportController;
use App\Models\OrgProject;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $published = OrgProject::query()
        ->published()
        ->with('user:id,name')
        ->latest('published_at')
        ->limit(8)
        ->get();

    return view('welcome', ['publishedCharts' => $published]);
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

Route::view('/org-charts', 'org-designer.public-index')->name('org-charts.public-index');
Route::get('/org-charts/{orgProject:slug}', function (OrgProject $orgProject) {
    abort_unless($orgProject->isPublished(), 404);

    return view('org-designer.public', ['orgProject' => $orgProject]);
})->name('org-charts.show');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('org-designer.index'))->name('dashboard');
    Route::view('/org-designer', 'org-designer.index')->name('org-designer.index');
    Route::get('/org-designer/{orgProject}', function (OrgProject $orgProject) {
        abort_unless(auth()->user()?->can('update', $orgProject), 403);

        return view('org-designer.edit', ['orgProject' => $orgProject]);
    })->name('org-designer.edit');
    Route::get('/org-designer/{orgProject}/export/excel', [OrgDesignerExportController::class, 'excel'])
        ->name('org-designer.export.excel');
});
