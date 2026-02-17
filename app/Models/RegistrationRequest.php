<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationRequest extends Model
{
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
        'active',
        'documents',
        'reg_code',
        'oracle_register_no',
    ];


    protected $casts = [
        'documents' => 'array',
        'active' => 'boolean',
        'birth_date' => 'date',
        'license_date' => 'date',
    ];

    // Helper method to get all document types
    public function getDocumentTypes(): array
    {
        return [
            'personal_image' => 'Personal Photo',
            'national_id_image' => 'National ID Photo',
            'graduation_certificate_image' => 'Graduation Certificate',
            'internship_certificate_image' => 'Internship Certificate',
            'criminal_record_certificate_image' => 'Criminal Record Certificate',
            'dob_image' => 'Date of Birth Certificate',
            'license_image' => 'License Image',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING_REVIEW => __('Pending'),
            self::STATUS_PENDING_FINAL_APPROVAL => __('Pending Final Approval'),
            self::STATUS_APPROVED => __('Approved'),
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return static::statusOptions()[$status] ?? (string) $status;
    }
}
