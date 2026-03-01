<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const REG_CODE_PREFIX = 'EMS';
    private const REG_CODE_MIN = 100000000000;
    private const REG_CODE_MAX = 999999999999;

    public function up(): void
    {
        $this->normalizeRegCodes();

        if (Schema::hasIndex('registration_requests', 'registration_requests_reg_code_index')) {
            Schema::table('registration_requests', function (Blueprint $table) {
                $table->dropIndex('registration_requests_reg_code_index');
            });
        }

        if (! Schema::hasIndex('registration_requests', 'registration_requests_reg_code_unique')) {
            Schema::table('registration_requests', function (Blueprint $table) {
                $table->unique('reg_code', 'registration_requests_reg_code_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('registration_requests', 'registration_requests_reg_code_unique')) {
            Schema::table('registration_requests', function (Blueprint $table) {
                $table->dropUnique('registration_requests_reg_code_unique');
            });
        }

        if (! Schema::hasIndex('registration_requests', 'registration_requests_reg_code_index')) {
            Schema::table('registration_requests', function (Blueprint $table) {
                $table->index('reg_code', 'registration_requests_reg_code_index');
            });
        }
    }

    private function normalizeRegCodes(): void
    {
        $seenCodes = [];

        DB::table('registration_requests')
            ->select(['id', 'reg_code'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$seenCodes): void {
                foreach ($rows as $row) {
                    $id = (int) $row->id;
                    $currentCode = trim((string) ($row->reg_code ?? ''));

                    if ($currentCode === '' || isset($seenCodes[$currentCode])) {
                        $newCode = $this->generateUniqueRegCode($seenCodes, $id);

                        DB::table('registration_requests')
                            ->where('id', $id)
                            ->update(['reg_code' => $newCode]);

                        $seenCodes[$newCode] = true;
                        continue;
                    }

                    $seenCodes[$currentCode] = true;
                }
            }, 'id');
    }

    private function generateUniqueRegCode(array $seenCodes, int $exceptId): string
    {
        while (true) {
            $newCode = self::REG_CODE_PREFIX . (string) random_int(self::REG_CODE_MIN, self::REG_CODE_MAX);

            if (isset($seenCodes[$newCode])) {
                continue;
            }

            $exists = DB::table('registration_requests')
                ->where('reg_code', $newCode)
                ->where('id', '!=', $exceptId)
                ->exists();

            if (! $exists) {
                return $newCode;
            }
        }
    }
};
