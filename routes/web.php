<?php

use App\Http\Controllers\Admin\DivisionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\JoditController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShareLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn() => view('dashboard'))->name('dashboard');

    // Jodit image upload
    Route::post('/jodit-upload', [JoditController::class, 'upload'])->name('jodit.upload');

    // Documents
    Route::resource('documents', DocumentController::class)->except(['edit', 'update']);
    Route::get('/documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
    Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::put('/documents/{document}/save', [DocumentController::class, 'save'])->name('documents.save');
    Route::post('/documents/{document}/toggle-public', [DocumentController::class, 'togglePublic'])->name('documents.toggle-public');

    // Approvals
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/documents/{document}/versions/{version}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/documents/{document}/versions/{version}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
    Route::post('/documents/{document}/versions/{version}/rollback', [ApprovalController::class, 'rollback'])->name('approvals.rollback');

    // Share Links
    Route::post('/documents/{document}/links', [ShareLinkController::class, 'store'])->name('links.store');
    Route::delete('/documents/{document}/links/{link}', [ShareLinkController::class, 'destroy'])->name('links.destroy');

    // Admin
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('divisions', DivisionController::class);
        Route::resource('users', UserController::class);
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Shared link access (no auth required)
Route::get('/share/{token}', [ShareLinkController::class, 'access'])->name('shared.documents');
Route::post('/share/{token}/save', [ShareLinkController::class, 'save'])->name('shared.documents.save');

require __DIR__.'/auth.php';
