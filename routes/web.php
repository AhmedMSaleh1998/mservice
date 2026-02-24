<?php

use Illuminate\Support\Facades\Route;

Route::view('/register', 'portal.register');
Route::view('/register/success', 'portal.register-success')->name('portal.register.success');
Route::view('/register/retrieve-documents', 'portal.register-retrieve-documents')->name('portal.register.retrieve');

Route::get('/', function () {
    return view('welcome');
});
