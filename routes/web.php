<?php

use Illuminate\Support\Facades\Route;

Route::view('/register', 'portal.register');
Route::view('/register/success', 'portal.register-success')->name('portal.register.success');

Route::get('/', function () {
    return view('welcome');
});
