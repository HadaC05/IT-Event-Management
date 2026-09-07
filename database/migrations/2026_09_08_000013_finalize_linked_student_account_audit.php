<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('activity_logs', 'student_profile_id')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->foreignId('student_profile_id')->nullable()->after('subject_user_id')->constrained('student_profiles')->nullOnDelete();
                $table->foreignId('officer_assignment_id')->nullable()->after('student_profile_id')->constrained('sbo_officer_assignments')->nullOnDelete();
                $table->string('acting_role', 50)->nullable()->after('action');
            });
        }

        $linkedRoles = DB::table('roles')->whereIn('name', ['Student', 'SBO Officer'])->pluck('id');
        DB::table('users')->whereIn('role_id', $linkedRoles)->whereNotNull('student_profile_id')->update([
            'id_number' => null, 'first_name' => null, 'middle_name' => null,
            'last_name' => null, 'email' => null, 'year_level' => null,
        ]);

        $officerRoleId = DB::table('roles')->where('name', 'SBO Officer')->value('id');
        $activeStatusId = DB::table('user_statuses')->where('label', 'active')->value('id');
        DB::table('users')->where('role_id', $officerRoleId)->whereNotNull('student_profile_id')->whereNotNull('officer_team_id')->orderBy('id')->each(function ($officer) use ($activeStatusId) {
            if (DB::table('sbo_officer_assignments')->where('officer_user_id', $officer->id)->exists()) return;
            DB::table('sbo_officer_assignments')->insert([
                'student_profile_id' => $officer->student_profile_id, 'officer_user_id' => $officer->id,
                'team_id' => $officer->officer_team_id, 'position' => 'SBO Officer', 'term' => 'Legacy assignment',
                'assigned_at' => $officer->created_at ?? now(), 'status' => $officer->status === $activeStatusId ? 'Active' : 'Inactive',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Canonical profile data is intentionally not copied back into login accounts.
    }
};
