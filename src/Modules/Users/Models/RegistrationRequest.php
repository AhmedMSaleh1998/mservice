<?php

namespace Modules\Users\Models;

use Modules\Core\Builders\OtpQueryBuilder;
use Modules\Core\Models\CustomModel;
use Modules\Users\Models\Concerns\HasRegistrationRequestCreation;

class RegistrationRequest extends CustomModel
{
    use HasRegistrationRequestCreation;

    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_PENDING_FINAL_APPROVAL = 'pending_final_approval';
    public const STATUS_APPROVED = 'approved';

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            if (blank($request->status)) {
                $request->status = self::STATUS_PENDING_REVIEW;
            }
        });

        static::saving(function (self $request): void {
            $documents = is_array($request->documents) ? $request->documents : [];

            if (filled($request->license_image)) {
                $documents['license_image'] = $request->license_image;
            } elseif (array_key_exists('license_image', $documents)) {
                $request->license_image = $documents['license_image'];
            }

            if (array_key_exists('license_image', $documents) && blank($documents['license_image'])) {
                unset($documents['license_image']);
            }

            $request->documents = empty($documents) ? null : $documents;
            $request->license_image = $documents['license_image'] ?? $request->license_image;
        });
    }

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
        'license_number',
        'license_date',
        'license_image',
        'status',
        'reg_code',
        'oracle_register_no',
        'active',
        'documents',
    ];

    protected $casts = [
        'active' => 'boolean',
        'documents' => 'array',
        'birth_date' => 'date',
        'license_date' => 'date',
    ];

    public function newEloquentBuilder($query): OtpQueryBuilder
    {
        return new OtpQueryBuilder($query);
    }
}
