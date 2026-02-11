<?php

use Illuminate\Support\Facades\Route;

Route::view('/register', 'portal.register');

Route::get('/', function () {
    return view('welcome');
});
