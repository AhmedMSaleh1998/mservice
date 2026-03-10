<?php

namespace App\Services;

use App\Support\RegistrationRequestDocuments;
use Modules\Users\Models\RegistrationRequest;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

class MissingRegistrationDocumentsExportService
{
    public function __construct(
        private readonly RegistrationRequestEditLinkService $registrationRequestEditLinkService,
    ) {
    }

    /**
     * @return array{download_name: string, path: string}
     */
    public function create(): array
    {
        $path = $this->temporaryFilePath();
        $writer = new Writer();
        $writerOpened = false;

        try {
            $writer->openToFile($path);
            $writerOpened = true;
            $writer->getCurrentSheet()->setName('Missing Documents');

            $writer->addRow(Row::fromValues([
                __('Registration Code'),
                __('Full Name (AR)'),
                __('Full Name (EN)'),
                __('Residence Phone'),
                __('Mobile 1'),
                __('Mobile 2'),
                'Missing Documents',
                'One-Time Edit Link',
            ]));

            foreach (RegistrationRequest::query()->orderBy('id')->lazyById() as $registrationRequest) {
                if (RegistrationRequestDocuments::hasAllRequiredDocuments($registrationRequest)) {
                    continue;
                }

                $writer->addRow(Row::fromValues([
                    $registrationRequest->reg_code,
                    $registrationRequest->full_name_ar,
                    $registrationRequest->full_name_en,
                    $registrationRequest->residence_phone,
                    $this->formatPhone(
                        $registrationRequest->residence_mobile_1_country_code,
                        $registrationRequest->residence_mobile_1,
                    ),
                    $this->formatPhone(
                        $registrationRequest->residence_mobile_2_country_code,
                        $registrationRequest->residence_mobile_2,
                    ),
                    implode('، ', RegistrationRequestDocuments::missingRequiredDocumentLabels($registrationRequest)),
                    $this->registrationRequestEditLinkService->portalEditUrl($registrationRequest),
                ]));
            }
        } catch (Throwable $exception) {
            if ($writerOpened) {
                $writer->close();
            }

            if (is_file($path)) {
                @unlink($path);
            }

            throw $exception;
        }

        $writer->close();

        return [
            'download_name' => 'missing-registration-documents-' . now()->format('Y-m-d-His') . '.xlsx',
            'path' => $path,
        ];
    }

    private function temporaryFilePath(): string
    {
        $basePath = tempnam(sys_get_temp_dir(), 'missing-registration-documents-');

        if ($basePath === false) {
            throw new RuntimeException('Unable to create a temporary file for the missing documents export.');
        }

        $path = $basePath . '.xlsx';

        if (! @rename($basePath, $path)) {
            @unlink($basePath);

            throw new RuntimeException('Unable to prepare the missing documents export file.');
        }

        return $path;
    }

    private function formatPhone(?string $countryCode, ?string $number): string
    {
        $number = trim((string) $number);

        if ($number === '') {
            return '';
        }

        $countryCode = trim((string) $countryCode);

        return $countryCode !== '' ? "{$countryCode} {$number}" : $number;
    }
}
