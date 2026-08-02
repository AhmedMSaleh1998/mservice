<?php

use App\Http\Controllers\Api\BlogsController;
use App\Http\Controllers\Api\AdRequestsController;
use App\Http\Controllers\Api\AdSpacesController;
use App\Http\Controllers\Api\BannersController;
use App\Http\Controllers\Api\CertificateRequestController;
use App\Http\Controllers\Api\CertificatesController;
use App\Http\Controllers\Api\ChangePasswordController;
use App\Http\Controllers\Api\ChangePhoneController;
use App\Http\Controllers\Api\CommunitySmsDlrController;
use App\Http\Controllers\Api\CourseBookingsController;
use App\Http\Controllers\Api\CoursesController;
use App\Http\Controllers\Api\ContactUsController;
use App\Http\Controllers\Api\GradesController;
use App\Http\Controllers\Api\DoctorMedicalGuideController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\LanguagesController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\MedicalUniversitiesController;
use App\Http\Controllers\Api\MedicalGuideController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\MyCoursesController;
use App\Http\Controllers\Api\NationalitiesController;
use App\Http\Controllers\Api\NewRegisterController;
use App\Http\Controllers\Api\OrdersController;
use App\Http\Controllers\Api\OtpSendController;
use App\Http\Controllers\Api\PagesController;
use App\Http\Controllers\Api\PaymentsController;
use App\Http\Controllers\Api\PaymentMethodsController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProvincesController;
use App\Http\Controllers\Api\ProceduresController;
use App\Http\Controllers\Api\ReligionsController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\RestUnitsController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SupportTicketsController;
use App\Http\Controllers\Api\TravelsController;
use App\Http\Controllers\Api\UserAddressController;
use App\Http\Controllers\ServicesController;
use App\Http\Middleware\ValidateHeadersMiddleware;
use Illuminate\Support\Facades\Route;
use Modules\Users\Services\RegistrationRequestPdfService;

Route::match(['GET', 'POST'], 'sms/community/dlr', CommunitySmsDlrController::class)
    ->name('api.sms.community.dlr');

Route::middleware(ValidateHeadersMiddleware::class)->prefix('v1')->group(function () {
    Route::get('nationalities', [NationalitiesController::class, 'index']);
    Route::get('banners', [BannersController::class, 'index']);
    Route::get('provinces', [ProvincesController::class, 'index']);
    Route::get('medical-universities', [MedicalUniversitiesController::class, 'index']);
    Route::get('grades', [GradesController::class, 'index']);
    Route::get('languages', [LanguagesController::class, 'index']);
    Route::get('payment-methods', [PaymentMethodsController::class, 'index']);
    Route::get('religions', [ReligionsController::class, 'index']);
    Route::post('register-request', [NewRegisterController::class, 'register']);
    Route::post('register-request/{reg_code}/update', [NewRegisterController::class, 'update'])
        ->middleware('signed')
        ->where('reg_code', 'EMS\\d+')
        ->name('register-request.update');
    Route::post('register-request/retrieve-documents', [NewRegisterController::class, 'retrieveDocuments'])
        ->middleware('throttle:5,1')
        ->name('register-documents-retrieve');
    Route::get('register-request/{reg_code}/pdf', [NewRegisterController::class, 'downloadPdf'])
        ->middleware('signed')
        ->where('reg_code', 'EMS\\d+')
        ->name('register-pdf');
    Route::get('register-request/{reg_code}/pdf/{document}', [NewRegisterController::class, 'downloadPdf'])
        ->middleware('signed')
        ->where('reg_code', 'EMS\\d+')
        ->where('document', RegistrationRequestPdfService::DOCUMENT_REGISTRATION_REQUEST . '|' . RegistrationRequestPdfService::DOCUMENT_LICENSE_REQUEST)
        ->name('register-pdf-document');
    Route::get('{reg_code}/pdf', [NewRegisterController::class, 'downloadPdf'])
        ->middleware('signed')
        ->where('reg_code', 'EMS\\d+');

    Route::prefix('payments/fawry/orders')->group(function () {
        Route::get('return', [OrdersController::class, 'handleFawryReturn'])->name('api.orders.fawry.return');
        Route::get('result', [OrdersController::class, 'showFawryResult'])->name('api.orders.fawry.result');
        Route::post('notification', [OrdersController::class, 'handleFawryNotification'])->name('api.orders.fawry.notification');
    });

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
        Route::get('profile/show', [ProfileController::class, 'show']);
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
            Route::get('rest-units/bookings/{restUnitBooking}', [RestUnitsController::class, 'showBooking'])
                ->name('api.services.rest-units.bookings.show');
        });

        Route::post('membership/request', [MembershipController::class, 'store']);
        Route::prefix('my-courses')->group(function () {
            Route::get('/', [MyCoursesController::class, 'index'])->name('api.my-courses.index');
            Route::get('{courseBooking}', [MyCoursesController::class, 'show'])->name('api.my-courses.show');
        });
        Route::prefix('travels')->group(function () {
            Route::controller(TravelsController::class)->group(function () {
                Route::get('/', 'index');
                Route::get('/{travel}', 'show');
            });
        });
        Route::post('travels/{travel}/booking', [TravelsController::class, 'booking']);
        Route::get('travel-bookings/{travelBooking}', [TravelsController::class, 'showBooking']);
        Route::prefix('payments')->group(function () {
            Route::get('/', [PaymentsController::class, 'index'])->name('api.payments.index');
            Route::get('{order}', [PaymentsController::class, 'show'])->name('api.payments.show');
        });
        Route::prefix('orders')->group(function () {
            Route::post('{order}/pay', [OrdersController::class, 'pay'])->name('api.orders.pay');
            Route::post('{order}/sync-payment-status', [OrdersController::class, 'syncPaymentStatus'])
                ->name('api.orders.sync-payment');
            Route::post('{order}/confirm-payment', [OrdersController::class, 'confirmPayment'])
                ->name('api.orders.confirm-payment');
        });

        Route::apiResource('user-addresses', UserAddressController::class)->only(['index', 'store']);
        Route::post('user-addresses/{userAddress}/update', [UserAddressController::class, 'update']);
        Route::delete('user-addresses/{userAddress}', [UserAddressController::class, 'destroy']);

        Route::get('procedures', [ProceduresController::class, 'index']);
        Route::get('procedures/{procedure}', [ProceduresController::class, 'show']);

        Route::get('certificates', [CertificatesController::class, 'index']);
        Route::post('certificate/request', [CertificateRequestController::class, 'store']);

        Route::prefix('medical-guides/me')->group(function () {
            Route::get('/', [DoctorMedicalGuideController::class, 'show']);
            Route::post('toggle', [DoctorMedicalGuideController::class, 'toggle']);
            Route::post('clinics/{clinic}/toggle', [DoctorMedicalGuideController::class, 'toggleClinic']);
        });

        Route::prefix('ads')->group(function () {
            Route::get('/', [AdRequestsController::class, 'approved']);
            Route::get('spaces', [AdSpacesController::class, 'index']);
            Route::post('/', [AdRequestsController::class, 'store']);
            Route::post('{adRequest}/cancel', [AdRequestsController::class, 'cancel']);
            Route::get('{adRequest}', [AdRequestsController::class, 'show']);
        });

        Route::post('courses/{course}', [CourseBookingsController::class, 'store']);
        Route::post('courses/{course}/booking', [CourseBookingsController::class, 'store']);
        Route::get('course-bookings/{courseBooking}', [CourseBookingsController::class, 'show']);
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

    Route::prefix('pages')->group(function () {
        Route::get('/{slug}', [PagesController::class, 'show']);
    });
});
