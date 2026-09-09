<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session', 12);
            $table->string('token', 48)->unique();
            $table->timestamps();

            $table->unique(['event_id', 'user_id', 'session']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_qr_tokens');
    }
};
