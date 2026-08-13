<?php

use App\Http\Controllers\Admin\DivisionController;
use App\Http\Controllers\Admin\DocumentController as AdminDocumentController;
use App\Http\Controllers\Admin\RetentionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\DocumentTypeController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentExportController;
use App\Http\Controllers\DocumentShareController;
use App\Http\Controllers\JoditController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LanguageController;
use Illuminate\Support\Facades\Route;

Route::get('/lang/{locale}', [LanguageController::class, 'switch'])->name('language.switch');

Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\SignatureController;

Route::middleware(['auth', 'signature.required'])->group(function () {
// Public QR Code verification route
Route::get('/d/{token}', [DocumentController::class, 'viewByHash'])->name('documents.hash');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Jodit image upload
    Route::post('/jodit-upload', [JoditController::class, 'upload'])->name('jodit.upload');

    // Signatures
    Route::get('/profile/signature', [SignatureController::class, 'show'])->name('profile.signature.show');
    Route::post('/profile/signature', [SignatureController::class, 'store'])->name('profile.signature.store');
    Route::delete('/profile/signature', [SignatureController::class, 'destroy'])->name('profile.signature.destroy');
    Route::get('/signatures/users', [SignatureController::class, 'availableUsers'])->name('signatures.users');
    Route::get('/signature-requests', [SignatureController::class, 'requestsIndex'])->name('signatures.requests.index');
    Route::post('/signature-requests/{signatureRequest}/approve', [SignatureController::class, 'approve'])->name('signatures.requests.approve');
    Route::post('/signature-requests/{signatureRequest}/reject', [SignatureController::class, 'reject'])->name('signatures.requests.reject');

    // Documents
    Route::resource('documents', DocumentController::class)->except(['edit', 'update']);
    Route::get('/document-numbers/preview', [DocumentController::class, 'nextNumber'])->name('documents.next-number');
    Route::get('/documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
    Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::post('/documents/{document}/summarize', [DocumentController::class, 'summarize'])->name('documents.summarize');
    Route::get('/documents/{document}/summary-status', [DocumentController::class, 'summaryStatus'])->name('documents.summary-status');
    Route::get('/documents/{document}/preview-content', [DocumentController::class, 'previewContent'])->name('documents.preview-content');
    Route::get('/documents/{document}/versions/{version}/preview', [DocumentController::class, 'previewVersion'])->name('documents.preview-version');
    Route::get('/documents/{document}/versions/{version}/file', [DocumentController::class, 'file'])->name('documents.file');
    Route::get('/documents/{document}/qrcode', [DocumentController::class, 'qrCode'])->name('documents.qrcode');
    Route::put('/documents/{document}/save', [DocumentController::class, 'save'])->name('documents.save');
    Route::put('/documents/{document}/save-draft', [DocumentController::class, 'saveDraft'])->name('documents.save-draft');
    Route::post('/documents/{document}/versions/upload', [DocumentController::class, 'uploadVersion'])->name('documents.upload-version');
    Route::patch('/documents/{document}/visibility', [DocumentController::class, 'updateVisibility'])->name('documents.update-visibility');
    Route::post('/documents/{document}/discard', [DocumentController::class, 'discard'])->name('documents.discard');
    Route::post('/documents/{document}/toggle-public', [DocumentController::class, 'togglePublic'])->name('documents.toggle-public');

    // Approvals
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/documents/{document}/versions/{version}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/documents/{document}/versions/{version}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
    Route::post('/documents/{document}/versions/{version}/rollback', [ApprovalController::class, 'rollback'])->name('approvals.rollback');
    Route::post('/documents/{document}/rollback-request/approve', [ApprovalController::class, 'approveRollback'])->name('approvals.rollback-request.approve');
    Route::post('/documents/{document}/rollback-request/reject', [ApprovalController::class, 'rejectRollback'])->name('approvals.rollback-request.reject');

    // Document Shares (Google Docs model)
    Route::post('/documents/{document}/shares', [DocumentShareController::class, 'store'])->name('shares.store');
    Route::patch('/documents/{document}/shares/{share}', [DocumentShareController::class, 'updateUserShare'])->name('shares.update');
    Route::delete('/documents/{document}/shares/{share}', [DocumentShareController::class, 'destroyUserShare'])->name('shares.destroy');
    Route::patch('/documents/{document}/division-shares/{divisionShare}', [DocumentShareController::class, 'updateDivisionShare'])->name('shares.division.update');
    Route::delete('/documents/{document}/division-shares/{divisionShare}', [DocumentShareController::class, 'destroyDivisionShare'])->name('shares.division.destroy');
    Route::patch('/documents/{document}/general-access', [DocumentShareController::class, 'updateGeneralAccess'])->name('shares.general-access.update');
    Route::post('/documents/{document}/regenerate-token', [DocumentShareController::class, 'regenerateToken'])->name('shares.regenerate-token');
    Route::get('/documents/{document}/share-data', [DocumentShareController::class, 'shareData'])->name('shares.data');
    Route::get('/documents/{document}/search-sharees', [DocumentShareController::class, 'searchSharees'])->name('shares.search');

    // PDF Export
    Route::post('/documents/{document}/export-pdf', [DocumentExportController::class, 'export'])
        ->name('documents.export-pdf');

    // Admin
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('divisions', DivisionController::class);
        Route::resource('users', UserController::class);
        Route::get('/retention', [RetentionController::class, 'edit'])->name('retention.edit');
        Route::put('/retention', [RetentionController::class, 'update'])->name('retention.update');
        Route::resource('document-types', DocumentTypeController::class);
        Route::get('/documents', [AdminDocumentController::class, 'index'])->name('documents.index');
        Route::delete('/documents/{document}', [AdminDocumentController::class, 'destroy'])->name('documents.destroy');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});

// Share-token link access (Google Docs model)
Route::get('/shared/{token}', [DocumentShareController::class, 'accessByToken'])->name('documents.shared');

require __DIR__.'/auth.php';
