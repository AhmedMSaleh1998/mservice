<?php

namespace Modules\Users\Models;

use Modules\Core\Builders\OtpQueryBuilder;
use Modules\Core\Models\CustomModel;

class RegistrationRequest extends CustomModel
{
    protected $fillable = [
        'national_id',
        'full_name_ar',
        'full_name_en',
        'gender',
        'nationality',
        'religion',
        'governorate',
        'issued_from',
        'birth_governorate',
        'birth_date',
        'residence_governorate',
        'residence_center',
        'residence_street',
        'residence_house_number',
        'residence_phone',
        'residence_mobile_1_country_code',
        'residence_mobile_1',
        'residence_mobile_2_country_code',
        'residence_mobile_2',
        'email',
        'university',
        'faculty',
        'graduation_year',
        'graduation_month',
        'grade',
        'first_foreign_language',
        'second_foreign_language',
        'reg_code',
        'active',
        'documents',
    ];

    protected $casts = [
        'active' => 'boolean',
        'documents' => 'array',
        'birth_date' => 'date',
    ];

    public function newEloquentBuilder($query): OtpQueryBuilder
    {
        return new OtpQueryBuilder($query);
    }
}
