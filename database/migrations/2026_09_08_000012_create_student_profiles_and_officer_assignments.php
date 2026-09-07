<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('student_id')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->unique();
            $table->foreignId('year_level_id')->nullable()->constrained('year_levels')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_id_number_unique');
            $table->dropUnique('users_email_unique');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('id_number')->nullable()->change();
            $table->string('first_name')->nullable()->change();
            $table->string('last_name')->nullable()->change();
            $table->string('email')->nullable()->change();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('student_profile_id')->nullable()->after('id')->constrained('student_profiles')->nullOnDelete();
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->unique(['student_profile_id', 'role_id'], 'users_student_profile_role_unique');
        });

        $studentRoleIds = DB::table('roles')->whereIn('name', ['Student', 'SBO Officer'])->pluck('id');
        DB::table('users')->whereIn('role_id', $studentRoleIds)->orderBy('id')->each(function ($user) {
            if (blank($user->id_number) || blank($user->email)) {
                return;
            }
            $profileId = DB::table('student_profiles')->where('student_id', $user->id_number)->orWhere('email', $user->email)->value('id');
            $profileId ??= DB::table('student_profiles')->insertGetId([
                'student_id' => $user->id_number,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'year_level_id' => $user->year_level,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('users')->where('id', $user->id)->update(['student_profile_id' => $profileId]);
        });
        DB::table('users')->whereIn('role_id', $studentRoleIds)->whereNotNull('student_profile_id')->update([
            'id_number' => null, 'first_name' => null, 'middle_name' => null,
            'last_name' => null, 'email' => null, 'year_level' => null,
        ]);

        Schema::create('sbo_officer_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
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
            $table->index(['student_profile_id', 'term', 'status'], 'officer_profile_term_status_index');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('student_profile_id')->nullable()->after('subject_user_id')->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('officer_assignment_id')->nullable()->after('student_profile_id')->constrained('sbo_officer_assignments')->nullOnDelete();
            $table->string('acting_role', 50)->nullable()->after('action');
        });

        $officerRoleId = DB::table('roles')->where('name', 'SBO Officer')->value('id');
        $activeStatusId = DB::table('user_statuses')->where('label', 'active')->value('id');
        DB::table('users')->where('role_id', $officerRoleId)->whereNotNull('student_profile_id')->whereNotNull('officer_team_id')->orderBy('id')->each(function ($officer) use ($activeStatusId) {
            DB::table('sbo_officer_assignments')->insert([
                'student_profile_id' => $officer->student_profile_id, 'officer_user_id' => $officer->id,
                'team_id' => $officer->officer_team_id, 'position' => 'SBO Officer', 'term' => 'Legacy assignment',
                'assigned_at' => $officer->created_at ?? now(), 'status' => $officer->status === $activeStatusId ? 'Active' : 'Inactive',
                'created_at' => now(), 'updated_at' => now(),
            ]);
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
            $table->dropConstrainedForeignId('student_profile_id');
            $table->dropColumn('acting_role');
        });
        Schema::dropIfExists('sbo_officer_assignments');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_student_profile_role_unique');
            $table->dropConstrainedForeignId('student_profile_id');
            $table->dropColumn('must_change_password');
            $table->unique('id_number');
            $table->unique('email');
        });
        Schema::dropIfExists('student_profiles');
    }
};
