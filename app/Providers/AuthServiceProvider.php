<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\ContactInfo;
use App\Models\RegistrationRequest;
use App\Models\Role;
use App\Policies\AdminPolicy;
use App\Policies\AdRequestPolicy;
use App\Policies\AdSpacePolicy;
use App\Policies\BannerPolicy;
use App\Policies\BlogPolicy;
use App\Policies\CertificatePolicy;
use App\Policies\ContactInfoPolicy;
use App\Policies\CoursePolicy;
use App\Policies\GradePolicy;
use App\Policies\LanguagePolicy;
use App\Policies\MedicalGuidePolicy;
use App\Policies\MedicalUniversityPolicy;
use App\Policies\NationalityPolicy;
use App\Policies\PaymentMethodPolicy;
use App\Policies\ProcedurePolicy;
use App\Policies\ProvincePolicy;
use App\Policies\RegistrationRequestPolicy;
use App\Policies\ReligionPolicy;
use App\Policies\RestUnitBookingPolicy;
use App\Policies\RestUnitPolicy;
use App\Policies\RolePolicy;
use App\Policies\ServicePolicy;
use App\Policies\ServiceTypePolicy;
use App\Policies\SmsMessagePolicy;
use App\Policies\SupportTicketPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Models\AdSpace;
use Modules\Blog\Models\Blog;
use Modules\Certificates\Models\Certificate;
use Modules\Core\Models\Banner;
use Modules\Core\Models\Grade;
use Modules\Core\Models\Language;
use Modules\Core\Models\MedicalUniversity;
use Modules\Core\Models\Nationality;
use Modules\Core\Models\PaymentMethod;
use Modules\Core\Models\Province;
use Modules\Core\Models\Religion;
use Modules\Core\Models\SmsMessage;
use Modules\Courses\Models\Course;
use Modules\MedicalGuide\Models\MedicalGuide;
use Modules\Procedures\Models\Procedure;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitBooking;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceType;
use Modules\Support\Models\SupportTicket;
use Modules\Users\Models\User;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Admin::class => AdminPolicy::class,
        AdRequest::class => AdRequestPolicy::class,
        AdSpace::class => AdSpacePolicy::class,
        Banner::class => BannerPolicy::class,
        Blog::class => BlogPolicy::class,
        Certificate::class => CertificatePolicy::class,
        ContactInfo::class => ContactInfoPolicy::class,
        Course::class => CoursePolicy::class,
        Grade::class => GradePolicy::class,
        Language::class => LanguagePolicy::class,
        MedicalGuide::class => MedicalGuidePolicy::class,
        MedicalUniversity::class => MedicalUniversityPolicy::class,
        Nationality::class => NationalityPolicy::class,
        PaymentMethod::class => PaymentMethodPolicy::class,
        Procedure::class => ProcedurePolicy::class,
        Province::class => ProvincePolicy::class,
        RegistrationRequest::class => RegistrationRequestPolicy::class,
        Religion::class => ReligionPolicy::class,
        RestUnit::class => RestUnitPolicy::class,
        RestUnitBooking::class => RestUnitBookingPolicy::class,
        Role::class => RolePolicy::class,
        Service::class => ServicePolicy::class,
        ServiceType::class => ServiceTypePolicy::class,
        SmsMessage::class => SmsMessagePolicy::class,
        SupportTicket::class => SupportTicketPolicy::class,
        User::class => UserPolicy::class,
    ];
}
