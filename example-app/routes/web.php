<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\StockOperationController;
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
    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('pos/catalog', [PosController::class, 'catalog'])->defaults('preserve_current_team', true)->name('pos.catalog');
    Route::post('pos/quotes', [PosController::class, 'quote'])->defaults('preserve_current_team', true)->name('pos.quotes.store');
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::patch('products/{product}/recipe', [RecipeController::class, 'update'])->name('products.recipe.update');
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('inventory/items', [InventoryController::class, 'store'])->name('inventory.items.store');
    Route::patch('inventory/items/{inventoryItem}', [InventoryController::class, 'update'])->name('inventory.items.update');
    Route::post('inventory/receipts', [StockOperationController::class, 'receipt'])->name('inventory.receipts.store');
    Route::post('inventory/adjustments', [StockOperationController::class, 'adjustment'])->name('inventory.adjustments.store');
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
