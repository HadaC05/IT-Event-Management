<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['name' => 'SBO Officer'],
            ['created_at' => now(), 'updated_at' => now()],
        );

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('officer_team_id')->nullable()->after('year_level')->constrained('teams')->nullOnDelete();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('morning_in_at')->nullable()->after('end_at');
            $table->dateTime('morning_out_at')->nullable()->after('morning_in_at');
            $table->dateTime('afternoon_in_at')->nullable()->after('morning_out_at');
            $table->dateTime('afternoon_out_at')->nullable()->after('afternoon_in_at');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->timestamp('morning_in_at')->nullable()->after('checked_in_at');
            $table->timestamp('morning_out_at')->nullable()->after('morning_in_at');
            $table->timestamp('afternoon_in_at')->nullable()->after('morning_out_at');
            $table->timestamp('afternoon_out_at')->nullable()->after('afternoon_in_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['morning_in_at', 'morning_out_at', 'afternoon_in_at', 'afternoon_out_at']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['morning_in_at', 'morning_out_at', 'afternoon_in_at', 'afternoon_out_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('officer_team_id');
        });

    }
};
