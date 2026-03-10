<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\RegistrationRequestEditLinkService;
use App\Support\RegistrationRequestDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Grade;
use Modules\Core\Models\MedicalUniversity;
use Modules\Core\Models\Nationality;
use Modules\Core\Models\Province;
use Modules\Users\Models\RegistrationRequest;

class RegistrationRequestPortalController extends Controller
{
    public function __construct(
        private readonly RegistrationRequestEditLinkService $registrationRequestEditLinkService,
    ) {
    }

    public function edit(Request $request, string $reg_code)
    {
        $locale = in_array($request->query('lang'), ['ar', 'en'], true) ? $request->query('lang') : null;
        if (is_string($locale)) {
            app()->setLocale($locale);
        }

        $registrationRequest = RegistrationRequest::query()
            ->where('reg_code', $reg_code)
            ->firstOrFail();

        abort_unless(
            $this->registrationRequestEditLinkService->consumePortalEditLink($registrationRequest, $request->query('token')),
            403,
            app()->getLocale() === 'ar'
                ? 'تم استخدام هذا الرابط من قبل أو انتهت صلاحيته.'
                : 'This link has already been used or has expired.'
        );

        return view('portal.register', [
            'mode' => 'edit',
            'submitUrl' => $this->registrationRequestEditLinkService->apiUpdateUrl($registrationRequest),
            'prefillData' => $this->buildPrefillData($registrationRequest),
            'missingDocumentKeys' => RegistrationRequestDocuments::missingRequiredDocumentKeys($registrationRequest),
            'existingDocumentKeys' => array_keys(RegistrationRequestDocuments::existingRequiredDocuments($registrationRequest)),
            'documentLabels' => RegistrationRequestDocuments::requiredDocuments(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPrefillData(RegistrationRequest $registrationRequest): array
    {
        return [
            'full_name_ar' => $registrationRequest->full_name_ar,
            'full_name_en' => $registrationRequest->full_name_en,
            'gender' => $registrationRequest->gender,
            'nationality' => $this->normalizeLookupCodeToId($registrationRequest->nationality, Nationality::class),
            'religion' => $registrationRequest->religion,
            'national_id' => $registrationRequest->national_id,
            'issued_from' => $registrationRequest->issued_from,
            'governorate' => $this->normalizeLookupCodeToId($registrationRequest->governorate, Province::class),
            'birth_governorate' => $this->normalizeLookupCodeToId($registrationRequest->birth_governorate, Province::class),
            'birth_date' => optional($registrationRequest->birth_date)->format('Y-m-d'),
            'residence_house_number' => $registrationRequest->residence_house_number,
            'residence_street' => $registrationRequest->residence_street,
            'residence_center' => $registrationRequest->residence_center,
            'residence_governorate' => $this->normalizeLookupCodeToId($registrationRequest->residence_governorate, Province::class),
            'residence_phone' => $registrationRequest->residence_phone,
            'residence_mobile_1_country_code' => $registrationRequest->residence_mobile_1_country_code,
            'residence_mobile_1' => $registrationRequest->residence_mobile_1,
            'residence_mobile_2_country_code' => $registrationRequest->residence_mobile_2_country_code,
            'residence_mobile_2' => $registrationRequest->residence_mobile_2,
            'email' => $registrationRequest->email,
            'faculty' => $registrationRequest->faculty,
            'graduation_month' => (string) $registrationRequest->graduation_month,
            'graduation_year' => $registrationRequest->graduation_year,
            'university' => $this->normalizeLookupCodeToId($registrationRequest->university, MedicalUniversity::class),
            'grade' => $this->normalizeLookupCodeToId($registrationRequest->grade, Grade::class),
            'first_foreign_language' => $registrationRequest->first_foreign_language,
            'second_foreign_language' => $registrationRequest->second_foreign_language,
        ];
    }

    private function normalizeLookupCodeToId(mixed $value, string $modelClass): mixed
    {
        if (! is_numeric($value)) {
            return $value;
        }

        $id = (int) $value;

        /** @var Model $model */
        $model = new $modelClass();
        $table = $model->getTable();

        if ($modelClass::query()->whereKey($id)->exists()) {
            return $id;
        }

        if (! Schema::hasColumn($table, 'code')) {
            return $value;
        }

        return $modelClass::query()
            ->where('code', $id)
            ->value($model->getKeyName()) ?? $value;
    }
}
