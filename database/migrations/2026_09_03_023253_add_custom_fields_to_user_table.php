<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('id')->constrained('roles')->nullOnDelete();
            $table->foreignId('year_level')->nullable()->after('last_name')->constrained('year_levels')->nullOnDelete();
            $table->foreignId('status')->nullable()->after('password')->constrained('user_statuses')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status');
            $table->dropConstrainedForeignId('year_level');
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
