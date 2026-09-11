<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('poster_path');
            $table->unsignedInteger('featured_order')->nullable()->after('is_featured');
            $table->timestamp('featured_until')->nullable()->after('featured_order');
            $table->index(['is_featured', 'featured_order']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['is_featured', 'featured_order']);
            $table->dropColumn(['is_featured', 'featured_order', 'featured_until']);
        });
    }
};
