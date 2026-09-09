<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['morning_in_at', 'morning_out_at', 'afternoon_in_at', 'afternoon_out_at']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('morning_in_at')->nullable()->after('end_at');
            $table->dateTime('morning_out_at')->nullable()->after('morning_in_at');
            $table->dateTime('afternoon_in_at')->nullable()->after('morning_out_at');
            $table->dateTime('afternoon_out_at')->nullable()->after('afternoon_in_at');
        });
    }
};
