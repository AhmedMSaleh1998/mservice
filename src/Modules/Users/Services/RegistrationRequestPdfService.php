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
use Illuminate\Support\Str;
use RuntimeException;

class RegistrationRequestPdfService
{
    public const DOCUMENT_REGISTRATION_REQUEST = 'registration-request';
    public const DOCUMENT_LICENSE_REQUEST = 'license-request';

    public function __construct(
        private readonly Gpdf $gpdf
    ) {
    }

    public function generate(
        RegistrationRequest $request,
        string $document = self::DOCUMENT_REGISTRATION_REQUEST
    ): array
    {
        $documentConfig = $this->resolveDocumentConfig($document, $request);
        $data = $this->buildViewData($request);
        $html = view($documentConfig['view'], $data)->render();
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
            'fileName' => $documentConfig['fileName'],
        ];
    }

    private function resolveDocumentConfig(string $document, RegistrationRequest $request): array
    {
        return match ($document) {
            self::DOCUMENT_REGISTRATION_REQUEST => [
                'view' => 'pdf.registration-request',
                'fileName' => $this->buildFileName($request, self::DOCUMENT_REGISTRATION_REQUEST),
            ],
            self::DOCUMENT_LICENSE_REQUEST => [
                'view' => 'pdf.registration-license-request',
                'fileName' => $this->buildFileName($request, self::DOCUMENT_LICENSE_REQUEST),
            ],
            default => throw new RuntimeException("Unsupported PDF document type: {$document}"),
        };
    }

    private function buildViewData(RegistrationRequest $request): array
    {
        $locale = app()->getLocale();

        return [
            'locale' => $locale,
            'request' => $request,
            'generatedAt' => ($request->created_at ?? now())->timezone(config('app.timezone'))->format('d/m/Y H:i:s'),
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

    private function buildFileName(RegistrationRequest $request, string $document): string
    {
        $code = $request->reg_code ?: (string) $request->id;
        $safeCode = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $code);
        $safeCode = trim((string) $safeCode, '-');

        if ($safeCode === '') {
            $safeCode = (string) $request->id;
        }

        $prefix = match ($document) {
            self::DOCUMENT_REGISTRATION_REQUEST => 'registration-request',
            self::DOCUMENT_LICENSE_REQUEST => 'license-request',
            default => 'registration-document',
        };

        return $prefix . '-' . $safeCode . '.pdf';
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
