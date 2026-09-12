<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_official')->default(false)->after('category');
            $table->index(['is_official', 'status', 'created_at'], 'posts_official_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_official_status_created_index');
            $table->dropColumn('is_official');
        });
    }
};
