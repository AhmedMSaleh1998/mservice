<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class RegistrationRequestDocuments
{
    public static function requiredDocumentsBilingual(): array
    {
        return [
            'personal_image' => [
                'ar' => 'صورة شخصية',
                'en' => 'Personal Photo',
            ],
            'national_id_image' => [
                'ar' => 'صورة بطاقة الرقم القومي',
                'en' => 'National ID Photo',
            ],
            'graduation_certificate_image' => [
                'ar' => 'شهادة التخرج',
                'en' => 'Graduation Certificate',
            ],
            'internship_certificate_image' => [
                'ar' => 'شهادة الامتياز',
                'en' => 'Internship Certificate',
            ],
            'criminal_record_certificate_image' => [
                'ar' => 'صحيفة الحالة الجنائية',
                'en' => 'Criminal Record Certificate',
            ],
            'dob_image' => [
                'ar' => 'شهادة الميلاد',
                'en' => 'Date of Birth Certificate',
            ],
        ];
    }

    public static function requiredDocuments(): array
    {
        return array_map(
            static fn (array $labels): string => app()->getLocale() === 'ar' ? $labels['ar'] : $labels['en'],
            static::requiredDocumentsBilingual(),
        );
    }

    public static function requiredDocumentKeys(): array
    {
        return array_keys(static::requiredDocuments());
    }

    public static function existingRequiredDocuments(object $registrationRequest): array
    {
        $documents = is_array($registrationRequest->documents ?? null)
            ? $registrationRequest->documents
            : [];

        $existing = [];

        foreach (static::requiredDocumentKeys() as $key) {
            $path = $documents[$key] ?? null;

            if (! is_string($path) || $path === '') {
                continue;
            }

            if (! Storage::disk('public')->exists($path)) {
                continue;
            }

            $existing[$key] = $path;
        }

        return $existing;
    }

    public static function missingRequiredDocumentKeys(object $registrationRequest): array
    {
        return array_values(array_diff(
            static::requiredDocumentKeys(),
            array_keys(static::existingRequiredDocuments($registrationRequest)),
        ));
    }

    public static function missingRequiredDocumentLabels(object $registrationRequest): array
    {
        $labels = static::requiredDocuments();

        return array_values(array_map(
            static fn (string $key): string => $labels[$key],
            static::missingRequiredDocumentKeys($registrationRequest),
        ));
    }

    public static function missingRequiredDocumentBilingualLabels(object $registrationRequest): array
    {
        $labels = static::requiredDocumentsBilingual();

        return array_values(array_map(
            static fn (string $key): array => $labels[$key],
            static::missingRequiredDocumentKeys($registrationRequest),
        ));
    }

    public static function hasAllRequiredDocuments(object $registrationRequest): bool
    {
        return static::missingRequiredDocumentKeys($registrationRequest) === [];
    }
}
