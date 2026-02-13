<?php

namespace Modules\Users\Services;

use App\Support\CountryCodeOptions;
use Modules\Core\Enums\GenderEnum;
use Modules\Core\Models\Grade;
use Modules\Core\Models\Language;
use Modules\Core\Models\MedicalUniversity;
use Modules\Core\Models\Nationality;
use Modules\Core\Models\Province;
use Modules\Core\Models\Religion;
use Modules\Users\Models\RegistrationRequest;
use Omaralalwi\Gpdf\Gpdf;
use RuntimeException;
use Illuminate\Support\Str;

class RegistrationRequestPdfService
{
    public function __construct(
        private readonly Gpdf $gpdf
    ) {
    }

    public function generate(RegistrationRequest $request): array
    {
        $data = $this->buildViewData($request);
        $html = view('pdf.registration-request', $data)->render();
        $pdfContent = $this->gpdf->generate($html);

        if (! $this->looksLikePdf($pdfContent)) {
            $preview = Str::limit(trim(strip_tags($pdfContent)), 200);
            $message = $preview !== ''
                ? 'Generated PDF content is invalid: ' . $preview
                : 'Generated PDF content is invalid.';
            throw new RuntimeException($message);
        }

        return [
            'content' => $pdfContent,
            'fileName' => $this->buildFileName($request),
        ];
    }

    private function buildViewData(RegistrationRequest $request): array
    {
        $locale = app()->getLocale();

        return [
            'locale' => $locale,
            'request' => $request,
            'labels' => [
                'gender' => GenderEnum::labelFor($request->gender),
                'nationality' => $this->getLookupName($request->nationality, Nationality::class, $locale),
                'religion' => $this->getLookupName($request->religion, Religion::class, $locale),
                'governorate' => $this->getLookupName($request->governorate, Province::class, $locale),
                'birth_governorate' => $this->getLookupName($request->birth_governorate, Province::class, $locale),
                'residence_governorate' => $this->getLookupName($request->residence_governorate, Province::class, $locale),
                'university' => $this->getLookupName($request->university, MedicalUniversity::class, $locale),
                'grade' => $this->getLookupName($request->grade, Grade::class, $locale),
                'first_foreign_language' => $this->getLookupName($request->first_foreign_language, Language::class, $locale),
                'second_foreign_language' => $this->getLookupName($request->second_foreign_language, Language::class, $locale),
                'residence_mobile_1_country_code' => $this->formatCountryCode($request->residence_mobile_1_country_code),
                'residence_mobile_2_country_code' => $this->formatCountryCode($request->residence_mobile_2_country_code),
            ],
            'documents' => $request->documents ?? [],
            'documentLabels' => $this->documentLabels(),
        ];
    }

    private function buildFileName(RegistrationRequest $request): string
    {
        $code = $request->reg_code ?: (string) $request->id;
        $safeCode = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $code);
        $safeCode = trim((string) $safeCode, '-');

        if ($safeCode === '') {
            $safeCode = (string) $request->id;
        }

        return 'registration-request-' . $safeCode . '.pdf';
    }

    private function looksLikePdf(string $content): bool
    {
        return str_starts_with($content, '%PDF');
    }

    private function getLookupName($id, string $modelClass, string $locale): ?string
    {
        if (! $id) {
            return null;
        }

        $model = $modelClass::query()->find($id);

        if (! $model) {
            return (string) $id;
        }

        if (method_exists($model, 'getTranslation')) {
            return $model->getTranslation('name', $locale);
        }

        return $model->name ?? (string) $id;
    }

    private function formatCountryCode(?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        return CountryCodeOptions::label($code);
    }

    private function documentLabels(): array
    {
        return [
            'personal_image' => __('Personal Photo'),
            'national_id_image' => __('National ID Photo'),
            'graduation_certificate_image' => __('Graduation Certificate'),
            'internship_certificate_image' => __('Internship Certificate'),
            'criminal_record_certificate_image' => __('Criminal Record Certificate'),
            'dob_image' => __('Date of Birth Certificate'),
        ];
    }
}
