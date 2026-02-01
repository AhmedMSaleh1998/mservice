<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $types = collect(DB::table('service_types')->orderBy('id')->get());

        if ($types->isEmpty()) {
            $now = now();
            $defaultTypes = [
                [
                    'name' => '{"ar":"\u062e\u062f\u0645\u0627\u062a \u0625\u062f\u0627\u0631\u064a\u0629","en":"Administrative Services"}',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => '{"ar":"\u062e\u062f\u0645\u0627\u062a \u0627\u0644\u0631\u062d\u0644\u0627\u062a","en":"Travel Services"}',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => '{"ar":"\u062e\u062f\u0645\u0627\u062a \u0627\u0644\u0625\u0642\u0627\u0645\u0629","en":"Accommodation Services"}',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ];

            DB::table('service_types')->insert($defaultTypes);
            $types = collect(DB::table('service_types')->orderBy('id')->get());
        }

        $typeIds = $types->pluck('id')->filter()->values()->all();

        if ($typeIds === []) {
            return;
        }

        DB::table('services')
            ->whereNull('service_type_id')
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($services) use ($typeIds) {
                foreach ($services as $service) {
                    $serviceTypeId = $typeIds[array_rand($typeIds)];

                    if ($serviceTypeId) {
                        DB::table('services')->where('id', $service->id)->update([
                            'service_type_id' => $serviceTypeId,
                        ]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data backfill has no safe rollback.
    }
};
