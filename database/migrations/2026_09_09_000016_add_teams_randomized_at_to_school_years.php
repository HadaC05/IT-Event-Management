<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_years', function (Blueprint $table) {
            $table->timestamp('teams_randomized_at')->nullable()->after('label');
        });

        if (Schema::hasTable('activity_logs')) {
            DB::table('activity_logs')
                ->where('action', 'team_members_randomized')
                ->orderByDesc('created_at')
                ->get(['description', 'created_at'])
                ->each(function (object $log): void {
                    if (preg_match('/\bSY (.+)\.$/u', $log->description, $matches) !== 1) {
                        return;
                    }

                    DB::table('school_years')
                        ->where('label', $matches[1])
                        ->whereNull('teams_randomized_at')
                        ->update(['teams_randomized_at' => $log->created_at]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('school_years', function (Blueprint $table) {
            $table->dropColumn('teams_randomized_at');
        });
    }
};
