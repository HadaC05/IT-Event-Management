<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_statuses')) {
            return;
        }

        $activeId = DB::table('event_statuses')->where('label', 'active')->value('id');
        if (! $activeId) {
            return;
        }

        $upcomingId = DB::table('event_statuses')->where('label', 'upcoming')->value('id');
        if ($upcomingId) {
            DB::table('events')->where('event_status_id', $activeId)->update(['event_status_id' => $upcomingId]);
            DB::table('event_statuses')->where('id', $activeId)->delete();

            return;
        }

        DB::table('event_statuses')->where('id', $activeId)->update(['label' => 'upcoming']);
    }

    public function down(): void
    {
    }
};
