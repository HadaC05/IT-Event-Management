<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_id_number_unique');
            $table->dropUnique('users_email_unique');
            $table->boolean('must_change_password')->default(false)->after('password');
        });
        Schema::create('sbo_officer_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('student_id');
            $table->foreignId('officer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('position', 100);
            $table->string('term', 100);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ended_at')->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
            $table->index(['student_id', 'term', 'status'], 'officer_student_term_status_index');
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('student_id')->nullable()->after('subject_user_id');
            $table->foreignId('officer_assignment_id')->nullable()->after('student_id')->constrained('sbo_officer_assignments')->nullOnDelete();
            $table->string('acting_role', 50)->nullable()->after('action');
        });
        Schema::create('account_password_reset_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_password_reset_tokens');
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('officer_assignment_id');
            $table->dropColumn(['student_id', 'acting_role']);
        });
        Schema::dropIfExists('sbo_officer_assignments');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
            $table->unique('id_number');
            $table->unique('email');
        });
    }
};
