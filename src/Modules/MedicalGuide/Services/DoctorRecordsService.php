<?php

namespace Modules\MedicalGuide\Services;

use App\Support\NationalIdentifier;
use Illuminate\Support\Carbon;
use Modules\MedicalGuide\Models\MedicalGuide;
use Modules\MedicalGuide\Resources\MedicalGuidePlaceResource;
use Modules\Users\Models\User;
use Throwable;

/**
 * A doctor's clinics and registered specialties, read from the medical guide
 * record synced from the syndicate database (API.VW_DOC_CLINC) rather than from
 * Oracle on every request.
 */
class DoctorRecordsService
{
    /**
     * @return array{clinics: array<int, array>, specialties: array<int, array>, membership_status: ?string}
     */
    public function forUser(User $user): array
    {
        $records = [
            'clinics' => [],
            'specialties' => [],
            // The syndicate data the app receives carries no membership type yet.
            'membership_status' => null,
        ];

        $regNumber = preg_replace('/\D+/', '', NationalIdentifier::normalize((string) $user->reg_number)) ?? '';

        if ($regNumber === '') {
            return $records;
        }

        $guide = MedicalGuide::query()
            ->where('reg_number', $regNumber)
            ->with('places')
            ->first();

        if (! $guide instanceof MedicalGuide) {
            return $records;
        }

        $records['clinics'] = MedicalGuidePlaceResource::collection($guide->places)->resolve(request());
        $records['specialties'] = $this->specialties($guide->oracle_payload ?? []);

        return $records;
    }

    /**
     * @return array<int, array{name: string, degree: ?string, date: ?string}>
     */
    private function specialties(array $payload): array
    {
        $specialization = $payload['specialization'] ?? null;

        if (! is_array($specialization)) {
            return [];
        }

        $items = array_is_list($specialization) ? $specialization : [$specialization];
        $specialties = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            // The most specific level the syndicate records for the specialty.
            $name = $this->filled($item['detail'] ?? null)
                ?? $this->filled($item['main_sub'] ?? null)
                ?? $this->filled($item['general'] ?? null);

            if ($name === null) {
                continue;
            }

            $specialties[] = [
                'name' => $name,
                'degree' => $this->filled($item['consultant'] ?? null),
                'date' => $this->formatDate($item['reg_year'] ?? null),
            ];
        }

        return $specialties;
    }

    /**
     * The syndicate sends "2017-08-07T00:00:00"; the web page shows 07/08/2017.
     */
    private function formatDate(mixed $value): ?string
    {
        $value = $this->filled($value);

        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (Throwable) {
            return null;
        }
    }

    private function filled(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }
}
