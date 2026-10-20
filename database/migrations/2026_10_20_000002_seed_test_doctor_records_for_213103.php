<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TEMPORARY test data so the mobile team can see clinics and specialties in
 * GET /api/v1/profile/show for registration number 213103. Remove it once the
 * screen is built (rollback this migration, or delete the rows marked
 * "_seeded_for_testing").
 *
 * The guide and its clinics are inactive, so the test doctor never appears in
 * the public medical guide, and nothing is written when a real record for this
 * registration number already exists.
 */
return new class extends Migration {
    private const REG_NUMBER = '213103';

    public function up(): void
    {
        $exists = DB::table('medical_guides')
            ->where('reg_number', self::REG_NUMBER)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return;
        }

        $now = now();

        $guideId = DB::table('medical_guides')->insertGetId([
            'reg_number' => self::REG_NUMBER,
            'title' => $this->translation('مروان محمد مصطفي المصري احمد'),
            'description' => $this->translation('طب حالات حرجة'),
            'is_featured' => false,
            'is_active' => false,
            'oracle_payload' => json_encode([
                '_seeded_for_testing' => true,
                'REGISTER_NO' => (int) self::REG_NUMBER,
                'doctor_name' => 'مروان محمد مصطفي المصري احمد',
                'address' => '15 ش التحرير، الدقي، الجيزة',
                'GOV' => 1002,
                'mobile' => '01000000001',
                'telephone' => '0233000001',
                'specialization' => [
                    [
                        'general' => 'طب حالات حرجة',
                        'main_sub' => 'طب حالات حرجة',
                        'detail' => 'طب حالات حرجة',
                        'consultant' => 'إستشاري',
                        'reg_year' => '2021-03-15T00:00:00',
                    ],
                    [
                        'general' => 'الأمراض الباطنة',
                        'main_sub' => 'الأمراض الباطنة',
                        'detail' => 'الباطنة العامة',
                        'consultant' => 'أخصائي',
                        'reg_year' => '2016-09-01T00:00:00',
                    ],
                ],
                'clinics' => null,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('medical_guide_places')->insert([
            [
                'medical_guide_id' => $guideId,
                'name' => $this->translation('عيادة الدقي'),
                'address' => $this->translation('15 ش التحرير، الدقي، الجيزة'),
                'phones' => json_encode(['01000000001', '0233000001']),
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'medical_guide_id' => $guideId,
                'name' => $this->translation('عيادة مدينة نصر'),
                'address' => $this->translation('8 ش عباس العقاد، مدينة نصر، القاهرة'),
                'phones' => json_encode(['01000000002']),
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        $guideIds = DB::table('medical_guides')
            ->where('reg_number', self::REG_NUMBER)
            ->get(['id', 'oracle_payload'])
            ->filter(fn (object $guide): bool => (json_decode((string) $guide->oracle_payload, true)['_seeded_for_testing'] ?? false) === true)
            ->pluck('id');

        DB::table('medical_guide_places')->whereIn('medical_guide_id', $guideIds)->delete();
        DB::table('medical_guides')->whereIn('id', $guideIds)->delete();
    }

    private function translation(string $value): string
    {
        return json_encode(['ar' => $value, 'en' => $value], JSON_UNESCAPED_UNICODE);
    }
};
