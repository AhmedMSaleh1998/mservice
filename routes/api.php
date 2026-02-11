<?php

use App\Http\Controllers\Api\BlogsController;
use App\Http\Controllers\Api\AdRequestsController;
use App\Http\Controllers\Api\AdSpacesController;
use App\Http\Controllers\Api\CertificateRequestController;
use App\Http\Controllers\Api\CertificatesController;
use App\Http\Controllers\Api\ChangePasswordController;
use App\Http\Controllers\Api\ChangePhoneController;
use App\Http\Controllers\Api\CoursesController;
use App\Http\Controllers\Api\ContactUsController;
use App\Http\Controllers\Api\GradesController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\LanguagesController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\MedicalUniversitiesController;
use App\Http\Controllers\Api\MedicalGuideController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\NationalitiesController;
use App\Http\Controllers\Api\NewRegisterController;
use App\Http\Controllers\Api\OtpSendController;
use App\Http\Controllers\Api\PaymentMethodsController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProvincesController;
use App\Http\Controllers\Api\ProceduresController;
use App\Http\Controllers\Api\ReligionsController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\RestUnitsController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SupportTicketsController;
use App\Http\Controllers\Api\UserAddressController;
use App\Http\Controllers\ServicesController;
use App\Http\Middleware\ValidateHeadersMiddleware;
use Illuminate\Support\Facades\Route;


Route::middleware(ValidateHeadersMiddleware::class)->prefix('v1')->group(function () {
    Route::get('nationalities', [NationalitiesController::class, 'index']);
    Route::get('provinces', [ProvincesController::class, 'index']);
    Route::get('medical-universities', [MedicalUniversitiesController::class, 'index']);
    Route::get('grades', [GradesController::class, 'index']);
    Route::get('languages', [LanguagesController::class, 'index']);
    Route::get('payment-methods', [PaymentMethodsController::class, 'index']);
    Route::get('religions', [ReligionsController::class, 'index']);
    Route::post('register-request', [NewRegisterController::class, 'register']);

    Route::controller(OtpSendController::class)->prefix('otp')->group(function () {
        Route::post('send', 'send');
        Route::post('verify', 'verify');
        Route::post('resend', 'resend');
    });

    Route::prefix('auth')->group(function () {
        Route::controller(RegisterController::class)->group(function () {
            Route::post('register', 'register');
        });

        Route::controller(LoginController::class)->group(function () {
            Route::post('login', 'login');
            Route::post('logout', 'logout')->middleware('auth:sanctum');
        });
    });

    // Authed Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('settings', [SettingsController::class, 'update']);
        Route::post('profile/update', [ProfileController::class, 'update']);
        Route::post('auth/change-password', ChangePasswordController::class);
        Route::post('auth/change-phone', [ChangePhoneController::class, 'change']);
        Route::post('auth/change-phone-verify', [ChangePhoneController::class, 'verify']);

        Route::post('support-tickets', [SupportTicketsController::class, 'store']);

        Route::prefix('services')->group(function () {
            Route::controller(ServicesController::class)->group(function () {
                Route::get('/', 'index');
            });

            Route::get('/rest-units', [RestUnitsController::class, 'index']);
            Route::get('/rest-units/{id}', [RestUnitsController::class, 'show']);
            Route::post('rest-units/booking', [RestUnitsController::class, 'booking']);
        });

        Route::post('membership/request', [MembershipController::class, 'store']);

        Route::apiResource('user-addresses', UserAddressController::class)->only(['index', 'store']);
        Route::post('user-addresses/{userAddress}/update', [UserAddressController::class, 'update']);
        Route::post('user-addresses/{userAddress}/delete', [UserAddressController::class, 'destroy']);

        Route::get('procedures', [ProceduresController::class, 'index']);
        Route::get('procedures/{procedure}', [ProceduresController::class, 'show']);

        Route::get('certificates', [CertificatesController::class, 'index']);
        Route::post('certificate/request', [CertificateRequestController::class, 'store']);

        Route::prefix('ads')->group(function () {
            Route::get('/', [AdRequestsController::class, 'approved']);
            Route::get('spaces', [AdSpacesController::class, 'index']);
            Route::post('/', [AdRequestsController::class, 'store']);
            Route::get('{adRequest}', [AdRequestsController::class, 'show']);
            Route::post('{adRequest}/pay', [AdRequestsController::class, 'pay']);
        });
    });


    Route::prefix('news')->group(function () {
        Route::controller(BlogsController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/{slug}', 'show');
        });
    });

    Route::prefix('courses')->group(function () {
        Route::controller(CoursesController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/{course}', 'show');
        });
    });

    Route::prefix('medical-guides')->group(function () {
        Route::controller(MedicalGuideController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/{medicalGuide}', 'show');
        });
    });

    Route::get('contact-us', [ContactUsController::class, 'show']);
    Route::get('home', [HomeController::class, 'index']);
});
