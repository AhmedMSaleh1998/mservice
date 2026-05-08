<?php

namespace App\Services\Oracle;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Province;
use Modules\MedicalGuide\Models\MedicalGuide;
use Modules\MedicalGuide\Models\MedicalGuidePlace;
use Modules\MedicalGuide\Models\MedicalSpecialty;
use PDO;
use RuntimeException;

class OracleMedicalGuideSyncService
{
    private array $provinceIdsByCode = [];

    private array $specialtyIdsByName = [];

    public function __construct(
        private readonly OracleConnectionService $oracleConnectionService,
    ) {
    }

    public function sync(?int $limit = null): array
    {
        $connection = $this->oracleConnectionService->make();
        $driver = $connection instanceof PDO ? 'pdo_oci' : 'oci8';
        $syncedAt = now();

        Log::info('Oracle medical guide sync started.', [
            'driver' => $driver,
            'limit' => $limit,
        ]);

        $rows = $connection instanceof PDO
            ? $this->fetchWithPdo($connection, $limit)
            : $this->fetchWithOci8($connection, $limit);

        $stats = [
            'fetched' => count($rows),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'duplicates_skipped' => 0,
            'invalid_skipped' => 0,
        ];

        $seenRegisterNumbers = [];

        foreach ($rows as $rawPayload) {
            $payload = $this->decodePayload($rawPayload);
            $regNumber = $this->normalizeRegisterNumber($payload['REGISTER_NO'] ?? null);

            if ($regNumber === '') {
                $stats['invalid_skipped']++;
                continue;
            }

            if (isset($seenRegisterNumbers[$regNumber])) {
                $stats['duplicates_skipped']++;
                continue;
            }

            $seenRegisterNumbers[$regNumber] = true;
            $result = $this->syncPayload($payload, $regNumber, $syncedAt);
            $stats[$result]++;
        }

        Log::info('Oracle medical guide sync completed.', $stats);

        return $stats;
    }

    private function syncPayload(array $payload, string $regNumber, mixed $syncedAt): string
    {
        return DB::transaction(function () use ($payload, $regNumber, $syncedAt): string {
            $guide = MedicalGuide::query()
                ->where('reg_number', $regNumber)
                ->first();

            $attributes = $this->mapGuideAttributes($payload, $regNumber, $syncedAt);
            $placeAttributes = $this->mapPrimaryPlaceAttributes($payload);

            if (! $guide) {
                $guide = MedicalGuide::query()->create($attributes + [
                    'is_active' => false,
                    'is_featured' => false,
                ]);

                if ($placeAttributes !== null) {
                    $guide->places()->create($placeAttributes + [
                        'is_active' => false,
                    ]);
                }

                $this->logSync($guide, 'created', array_keys($attributes), [], $attributes, $syncedAt);

                return 'created';
            }

            $changes = $this->diffAttributes($guide, $attributes);
            $placeChanges = [];

            if ($placeAttributes !== null) {
                $place = $guide->places()->oldest('id')->first();

                if ($place instanceof MedicalGuidePlace) {
                    $placeChanges = $this->diffAttributes($place, $placeAttributes);

                    if ($placeChanges !== []) {
                        $place->forceFill(Arr::only($placeAttributes, array_keys($placeChanges)))->save();
                    }
                } else {
                    $guide->places()->create($placeAttributes + [
                        'is_active' => false,
                    ]);

                    $placeChanges = array_fill_keys(array_keys($placeAttributes), [
                        'old' => null,
                        'new' => null,
                    ]);
                }
            }

            if ($changes !== [] || $placeChanges !== []) {
                $guide->forceFill(Arr::only($attributes, array_keys($changes)) + [
                    'oracle_synced_at' => $syncedAt,
                ]);
                $guide->oracle_last_changed_at = $syncedAt;
                $guide->save();

                $oldValues = collect($changes)->map(fn (array $change) => $change['old'])->all();
                $newValues = collect($changes)->map(fn (array $change) => $change['new'])->all();

                if ($placeChanges !== []) {
                    $oldValues['primary_place'] = collect($placeChanges)->map(fn (array $change) => $change['old'])->all();
                    $newValues['primary_place'] = collect($placeChanges)->map(fn (array $change) => $change['new'])->all();
                }

                $this->logSync(
                    $guide,
                    'updated',
                    array_merge(array_keys($changes), array_map(fn (string $field) => 'primary_place.' . $field, array_keys($placeChanges))),
                    $oldValues,
                    $newValues,
                    $syncedAt,
                );

                return 'updated';
            }

            DB::table($guide->getTable())
                ->where($guide->getKeyName(), $guide->getKey())
                ->update([
                    'oracle_synced_at' => $syncedAt,
                ]);

            return 'unchanged';
        });
    }

    private function mapGuideAttributes(array $payload, string $regNumber, mixed $syncedAt): array
    {
        $specialtyName = $this->extractSpecialtyName($payload);
        $provinceId = $this->resolveProvinceId($payload['GOV'] ?? null);

        return [
            'reg_number' => $regNumber,
            'title' => $this->translation($this->normalizeNullableString($payload['doctor_name'] ?? null) ?? $regNumber),
            'description' => $this->translation($specialtyName ?? ''),
            'specialty_id' => $this->resolveSpecialtyId($specialtyName),
            'province_id' => $provinceId,
            'oracle_payload' => $payload,
            'oracle_synced_at' => $syncedAt,
        ];
    }

    private function mapPrimaryPlaceAttributes(array $payload): ?array
    {
        $address = $this->normalizeNullableString($payload['address'] ?? null);
        $phones = array_values(array_filter([
            $this->normalizeNullableString($payload['mobile'] ?? null),
            $this->normalizeNullableString($payload['telephone'] ?? null),
        ]));

        if ($address === null && $phones === []) {
            return null;
        }

        return [
            'name' => $this->translation(__('Clinic')),
            'address' => $this->translation($address ?? ''),
            'phones' => $phones,
        ];
    }

    private function fetchWithPdo(PDO $connection, ?int $limit): array
    {
        $sql = 'SELECT DOCTOR_JSON_DATA FROM API.VW_DOC_CLINC';

        if ($limit !== null) {
            $sql .= ' WHERE ROWNUM <= :limit';
        }

        $statement = $connection->prepare($sql);

        if ($limit !== null) {
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        }

        $statement->execute();

        return array_map(
            fn (array $row) => $this->readOracleValue($row['DOCTOR_JSON_DATA'] ?? $row['doctor_json_data'] ?? null),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /**
     * @param resource|object $connection
     */
    private function fetchWithOci8($connection, ?int $limit): array
    {
        $sql = 'SELECT DOCTOR_JSON_DATA FROM API.VW_DOC_CLINC';

        if ($limit !== null) {
            $sql .= ' WHERE ROWNUM <= :limit';
        }

        $statement = @oci_parse($connection, $sql);

        if ($statement === false) {
            $this->throwOciError($connection, 'Oracle medical guide sync failed while preparing statement.');
        }

        if ($limit !== null && ! @oci_bind_by_name($statement, ':limit', $limit, -1, SQLT_INT)) {
            $this->throwOciError($statement, 'Oracle medical guide sync failed while binding limit.');
        }

        if (! @oci_execute($statement)) {
            $this->throwOciError($statement, 'Oracle medical guide sync failed while executing statement.');
        }

        $rows = [];

        while (($row = oci_fetch_assoc($statement)) !== false) {
            $rows[] = $this->readOracleValue($row['DOCTOR_JSON_DATA'] ?? null);
        }

        oci_free_statement($statement);

        return $rows;
    }

    private function decodePayload(mixed $payload): array
    {
        $payload = trim((string) $this->readOracleValue($payload));

        if ($payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Oracle medical guide row returned invalid JSON.');
        }

        return $decoded;
    }

    private function readOracleValue(mixed $value): mixed
    {
        if (is_object($value) && method_exists($value, 'load')) {
            return $value->load();
        }

        return $value;
    }

    private function diffAttributes(object $model, array $attributes): array
    {
        $changes = [];

        foreach ($attributes as $key => $newValue) {
            if ($key === 'oracle_synced_at') {
                continue;
            }

            $oldValue = $model->{$key};

            if ($oldValue instanceof \DateTimeInterface) {
                $oldValue = $oldValue->format('Y-m-d H:i:s');
            }

            if ($newValue instanceof \DateTimeInterface) {
                $newValue = $newValue->format('Y-m-d H:i:s');
            }

            if ($this->normalizeComparableValue($oldValue) !== $this->normalizeComparableValue($newValue)) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    private function normalizeComparableValue(mixed $value): mixed
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (is_array($value)) {
            ksort($value);

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $value;
    }

    private function logSync(MedicalGuide $guide, string $action, array $changedFields, array $oldValues, array $newValues, mixed $syncedAt): void
    {
        DB::table('medical_guide_oracle_sync_logs')->insert([
            'medical_guide_id' => $guide->id,
            'reg_number' => (string) $guide->reg_number,
            'action' => $action,
            'changed_fields' => json_encode(array_values($changedFields), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'old_values' => $oldValues === [] ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'new_values' => $newValues === [] ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'synced_at' => $syncedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resolveProvinceId(mixed $govCode): ?int
    {
        if (! is_numeric($govCode)) {
            return null;
        }

        $code = (int) $govCode;

        if (array_key_exists($code, $this->provinceIdsByCode)) {
            return $this->provinceIdsByCode[$code];
        }

        return $this->provinceIdsByCode[$code] = Province::query()
            ->where('code', (int) $govCode)
            ->value('id');
    }

    private function resolveSpecialtyId(?string $specialtyName): ?int
    {
        if ($specialtyName === null || $specialtyName === '') {
            return null;
        }

        if (array_key_exists($specialtyName, $this->specialtyIdsByName)) {
            return $this->specialtyIdsByName[$specialtyName];
        }

        $specialty = MedicalSpecialty::query()
            ->get()
            ->first(fn (MedicalSpecialty $specialty) => $specialty->getTranslation('name', 'ar') === $specialtyName);

        if ($specialty instanceof MedicalSpecialty) {
            return $this->specialtyIdsByName[$specialtyName] = $specialty->id;
        }

        return $this->specialtyIdsByName[$specialtyName] = MedicalSpecialty::query()->create([
            'name' => $this->translation($specialtyName),
            'is_active' => true,
        ])->id;
    }

    private function extractSpecialtyName(array $payload): ?string
    {
        $specialization = is_array($payload['specialization'] ?? null)
            ? $payload['specialization']
            : [];

        return $this->normalizeNullableString(
            $specialization['detail']
                ?? $specialization['main_sub']
                ?? $specialization['general']
                ?? null
        );
    }

    private function translation(string $value): array
    {
        return [
            'ar' => $value,
            'en' => $value,
        ];
    }

    private function normalizeRegisterNumber(mixed $value): string
    {
        $normalized = strtr(trim((string) $value), [
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
        ]);

        return preg_replace('/\D+/', '', $normalized) ?? '';
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param resource|object $errorSource
     */
    private function throwOciError($errorSource, string $prefix): never
    {
        $error = oci_error($errorSource);
        $message = is_array($error) ? ($error['message'] ?? 'Unknown OCI8 error.') : 'Unknown OCI8 error.';

        throw new RuntimeException(sprintf('%s %s', $prefix, trim((string) $message)));
    }
}
