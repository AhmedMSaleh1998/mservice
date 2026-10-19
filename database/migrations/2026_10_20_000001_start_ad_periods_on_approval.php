<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // An ad's display period now starts when an admin approves it. Paid ads
        // still awaiting review drop the period they were given at payment, so
        // it cannot run out (and mark them completed) before they are shown.
        DB::table('ad_requests')
            ->where('status', 'paid_successfully')
            ->update(['starts_at' => null, 'ends_at' => null]);
    }

    public function down(): void
    {
        // The cleared periods were never shown, so there is nothing to restore.
    }
};
