<?php

namespace Modules\Users\Services;

use App\Support\RegistrationRequestDocuments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Users\Models\RegistrationRequest;
use RuntimeException;
use Throwable;

class UpdateRegistrationRequestService
{
    private const UPDATABLE_FIELDS = [
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
    ];

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $documents
     */
    public function update(RegistrationRequest $registrationRequest, array $data, array $documents): RegistrationRequest
    {
        $existingDocuments = is_array($registrationRequest->documents) ? $registrationRequest->documents : [];
        $newUploadedPaths = [];
        $replacedPaths = [];

        try {
            DB::transaction(function () use ($registrationRequest, $data, $documents, $existingDocuments, &$newUploadedPaths, &$replacedPaths) {
                $mergedDocuments = $existingDocuments;
                $documentsDirectory = "documents/{$registrationRequest->id}";

                foreach (RegistrationRequestDocuments::requiredDocumentKeys() as $key) {
                    $file = $documents[$key] ?? null;

                    if (! $file instanceof UploadedFile) {
                        continue;
                    }

                    $filename = time() . "_{$key}_" . $file->getClientOriginalName();
                    $path = $file->storeAs($documentsDirectory, $filename, 'public');

                    if ($path === false) {
                        throw new RuntimeException("Failed to store document [{$key}].");
                    }

                    $newUploadedPaths[$key] = $path;

                    $previousPath = $mergedDocuments[$key] ?? null;
                    if (is_string($previousPath) && $previousPath !== '' && $previousPath !== $path) {
                        $replacedPaths[] = $previousPath;
                    }

                    $mergedDocuments[$key] = $path;
                }

                $registrationRequest->fill(Arr::only($data, self::UPDATABLE_FIELDS));
                $registrationRequest->documents = $mergedDocuments;
                $registrationRequest->save();
            });
        } catch (Throwable $exception) {
            if ($newUploadedPaths !== []) {
                Storage::disk('public')->delete(array_values($newUploadedPaths));
            }

            throw $exception;
        }

        if ($replacedPaths !== []) {
            Storage::disk('public')->delete(array_values(array_unique($replacedPaths)));
        }

        return $registrationRequest->fresh();
    }
}
