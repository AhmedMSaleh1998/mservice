<?php

use App\Http\Controllers\Admin\MissingRegistrationDocumentsExportController;
use App\Http\Controllers\Portal\RegistrationRequestPortalController;
use App\Http\Controllers\Web\PageController;
use Illuminate\Support\Facades\Route;

Route::view('/register', 'portal.register');
Route::view('/register/success', 'portal.register-success')->name('portal.register.success');
Route::view('/register/retrieve-documents', 'portal.register-retrieve-documents')->name('portal.register.retrieve');
Route::get('/register/edit/{reg_code}', [RegistrationRequestPortalController::class, 'edit'])
    ->middleware('signed')
    ->where('reg_code', 'EMS\\d+')
    ->name('portal.register.edit');

Route::get('/manage/registration-requests/missing-documents/export', MissingRegistrationDocumentsExportController::class)
    ->name('manage.registration-requests.missing-documents.export');

Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');

Route::get('/', function () {
    return view('welcome');
});
