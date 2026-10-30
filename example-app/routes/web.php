<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureLocalEnvironment;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', EntryController::class)->name('home');
Route::get('/dashboard', EntryController::class)->name('entry');
Route::inertia('/no-store-access', 'no-store-access')->middleware('auth')->name('no-store-access');
Route::prefix('{current_team}')->middleware(['auth', 'verified', EnsureTeamMembership::class.':owner'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});
Route::middleware('auth')->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});
require __DIR__.'/settings.php';
if (app()->environment('local')) {
    Route::middleware([EnsureLocalEnvironment::class, 'auth', 'verified'])->group(function () {
        Route::get('/_dev/dashboard-reference', fn () => view('dashboard.index'))->name('dashboard.preview');
        Route::post('/_dev/dashboard-reference/theme', function (Request $request) {
            $request->session()->put('theme', $request->session()->get('theme', 'light') === 'light' ? 'dark' : 'light');

            return to_route('dashboard.preview');
        })->name('theme.toggle');
        Route::inertia('/_dev/{current_team}/pos-shell', 'dev/pos-shell')->middleware(EnsureTeamMembership::class.':owner')->name('dev.pos-shell');
    });
}
