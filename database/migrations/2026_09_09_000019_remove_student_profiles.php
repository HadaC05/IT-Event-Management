<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_profiles')) {
            return;
        }
        DB::table('users')->whereNotNull('student_profile_id')->orderBy('id')->each(function ($user) {
            $profile = DB::table('student_profiles')->where('id', $user->student_profile_id)->first();
            if ($profile) {
                DB::table('users')->where('id', $user->id)->update(['id_number' => $profile->student_id, 'first_name' => $profile->first_name, 'middle_name' => $profile->middle_name, 'last_name' => $profile->last_name, 'email' => $profile->email, 'year_level' => $profile->year_level_id]);
            }
        });
        Schema::table('sbo_officer_assignments', fn (Blueprint $table) => $table->string('student_id')->nullable()->after('id'));
        DB::table('sbo_officer_assignments')->orderBy('id')->each(fn ($row) => DB::table('sbo_officer_assignments')->where('id', $row->id)->update(['student_id' => DB::table('student_profiles')->where('id', $row->student_profile_id)->value('student_id')]));
        Schema::table('activity_logs', fn (Blueprint $table) => $table->string('student_id')->nullable()->after('subject_user_id'));
        DB::table('activity_logs')->whereNotNull('student_profile_id')->orderBy('id')->each(fn ($row) => DB::table('activity_logs')->where('id', $row->id)->update(['student_id' => DB::table('student_profiles')->where('id', $row->student_profile_id)->value('student_id')]));
        Schema::table('activity_logs', fn (Blueprint $table) => $table->dropConstrainedForeignId('student_profile_id'));
        Schema::table('sbo_officer_assignments', function (Blueprint $table) {
            $table->dropIndex('officer_profile_term_status_index');
            $table->dropConstrainedForeignId('student_profile_id');
            $table->index(['student_id', 'term', 'status'], 'officer_student_term_status_index');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_student_profile_role_unique');
            $table->dropConstrainedForeignId('student_profile_id');
        });
        Schema::dropIfExists('student_profiles');
    }

    public function down(): void {}
};
