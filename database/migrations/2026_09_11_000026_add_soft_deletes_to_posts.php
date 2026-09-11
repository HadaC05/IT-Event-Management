<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('posts', 'deleted_at')) {
            Schema::table('posts', fn (Blueprint $table) => $table->softDeletes());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('posts', 'deleted_at')) {
            Schema::table('posts', fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};
