<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    private const EVENT_DESCRIPTION = 'Generated IT GAMES attendance test fixture.';

    private const STUDENT_IDS = [
        '02-2026-100001',
        '02-2026-100002',
        '02-2026-100003',
        '02-2026-100004',
        '02-2026-100005',
        '02-2026-100006',
    ];

    private const STUDENT_USERNAMES = [
        'itgames.student1',
        'itgames.student2',
        'itgames.student3',
        'itgames.student4',
        'itgames.student5',
        'itgames.student6',
    ];

    private const OFFICER_USERNAMES = [
        'itgames.officer1',
        'itgames.officer2',
    ];

    public function up(): void
    {
        // This is interactive demo data and must never be inserted in production
        // or into the isolated database used by the automated test suite.
        if (! app()->environment('local')) {
            return;
        }

        DB::transaction(function () {
            $now = now();
            $studentRoleId = $this->lookupId('roles', 'name', 'Student');
            $officerRoleId = $this->lookupId('roles', 'name', 'SBO Officer');
            $activeStatusId = $this->lookupId('user_statuses', 'label', 'active');
            $activeEventStatusId = $this->lookupId('event_statuses', 'label', 'active');
            $yearLevelId = $this->lookupId('year_levels', 'label', 'First Year');
            $eventTypeId = $this->lookupId('event_types', 'label', 'IT Days');
            $schoolYearId = $this->lookupId('school_years', 'label', '2026-2027 IT GAMES Test');

            $teamIds = [
                $this->teamId($schoolYearId, 'Team 1', '#397565'),
                $this->teamId($schoolYearId, 'Team 2', '#2F3AE0'),
            ];

            $students = [
                ['Alex', 'Rivera', 'alex.rivera.itgames@example.test'],
                ['Bea', 'Santos', 'bea.santos.itgames@example.test'],
                ['Carlo', 'Mendoza', 'carlo.mendoza.itgames@example.test'],
                ['Dana', 'Cruz', 'dana.cruz.itgames@example.test'],
                ['Evan', 'Garcia', 'evan.garcia.itgames@example.test'],
                ['Faith', 'Reyes', 'faith.reyes.itgames@example.test'],
            ];

            $studentUserIds = [];
            $profileIds = [];

            foreach ($students as $index => [$firstName, $lastName, $email]) {
                $profileId = DB::table('student_profiles')->insertGetId([
                    'student_id' => self::STUDENT_IDS[$index],
                    'first_name' => $firstName,
                    'middle_name' => null,
                    'last_name' => $lastName,
                    'email' => $email,
                    'year_level_id' => $yearLevelId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $studentUserId = DB::table('users')->insertGetId([
                    'student_profile_id' => $profileId,
                    'role_id' => $studentRoleId,
                    'id_number' => null,
                    'first_name' => null,
                    'middle_name' => null,
                    'last_name' => null,
                    'username' => self::STUDENT_USERNAMES[$index],
                    'year_level' => null,
                    'officer_team_id' => null,
                    'password' => Hash::make('Student@2026'),
                    'must_change_password' => false,
                    'email' => null,
                    'status' => $activeStatusId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $teamId = $index < 3 ? $teamIds[0] : $teamIds[1];
                DB::table('team_user')->insert([
                    'team_id' => $teamId,
                    'user_id' => $studentUserId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $profileIds[] = $profileId;
                $studentUserIds[] = $studentUserId;
            }

            foreach ([0 => $teamIds[0], 3 => $teamIds[1]] as $studentIndex => $teamId) {
                $officerNumber = $studentIndex === 0 ? 1 : 2;
                $officerUserId = DB::table('users')->insertGetId([
                    'student_profile_id' => $profileIds[$studentIndex],
                    'role_id' => $officerRoleId,
                    'id_number' => null,
                    'first_name' => null,
                    'middle_name' => null,
                    'last_name' => null,
                    'username' => self::OFFICER_USERNAMES[$officerNumber - 1],
                    'year_level' => null,
                    'officer_team_id' => $teamId,
                    'password' => Hash::make('Officer@2026'),
                    'must_change_password' => false,
                    'email' => null,
                    'status' => $activeStatusId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('sbo_officer_assignments')->insert([
                    'student_profile_id' => $profileIds[$studentIndex],
                    'officer_user_id' => $officerUserId,
                    'team_id' => $teamId,
                    'position' => 'Team Attendance Officer',
                    'term' => 'IT GAMES Test Event',
                    'assigned_by' => null,
                    'assigned_at' => $now,
                    'ended_by' => null,
                    'ended_at' => null,
                    'status' => 'Active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $eventDay = $now->hour >= 19
                ? $now->copy()->addDay()->startOfDay()
                : $now->copy()->startOfDay();

            $eventId = DB::table('events')->insertGetId([
                'title' => 'IT GAMES',
                'description' => self::EVENT_DESCRIPTION,
                'location' => 'CITE Activity Center',
                'audience_type' => 'selected_tribes',
                'poster_path' => null,
                'start_at' => $eventDay->copy()->setTime(7, 0),
                'morning_in_at' => $eventDay->copy()->setTime(7, 0),
                'morning_out_at' => $eventDay->copy()->setTime(11, 0),
                'afternoon_in_at' => $eventDay->copy()->setTime(13, 0),
                'afternoon_out_at' => $eventDay->copy()->setTime(17, 0),
                'end_at' => $eventDay->copy()->setTime(19, 0),
                'event_type_id' => $eventTypeId,
                'event_status_id' => $activeEventStatusId,
                'created_by' => DB::table('users')
                    ->where('role_id', DB::table('roles')->where('name', 'SBO Adviser')->value('id'))
                    ->value('id'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($teamIds as $teamId) {
                DB::table('event_team')->insert([
                    'event_id' => $eventId,
                    'team_id' => $teamId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $eventIds = DB::table('events')
                ->where('description', self::EVENT_DESCRIPTION)
                ->pluck('id');
            $studentUserIds = DB::table('users')
                ->whereIn('username', self::STUDENT_USERNAMES)
                ->pluck('id');
            $officerUserIds = DB::table('users')
                ->whereIn('username', self::OFFICER_USERNAMES)
                ->pluck('id');
            $profileIds = DB::table('student_profiles')
                ->whereIn('student_id', self::STUDENT_IDS)
                ->pluck('id');

            DB::table('activity_logs')
                ->whereIn('event_id', $eventIds)
                ->orWhereIn('actor_id', $officerUserIds)
                ->orWhereIn('subject_user_id', $studentUserIds->merge($officerUserIds))
                ->orWhereIn('student_profile_id', $profileIds)
                ->delete();

            DB::table('events')->whereIn('id', $eventIds)->delete();
            DB::table('sbo_officer_assignments')
                ->where('term', 'IT GAMES Test Event')
                ->whereIn('officer_user_id', $officerUserIds)
                ->delete();
            DB::table('users')
                ->whereIn('username', array_merge(self::STUDENT_USERNAMES, self::OFFICER_USERNAMES))
                ->delete();

            $schoolYearId = DB::table('school_years')
                ->where('label', '2026-2027 IT GAMES Test')
                ->value('id');
            DB::table('teams')
                ->where('school_year_id', $schoolYearId)
                ->whereIn('name', ['Team 1', 'Team 2'])
                ->delete();
            DB::table('student_profiles')->whereIn('id', $profileIds)->delete();
            DB::table('school_years')->where('id', $schoolYearId)->delete();
        });
    }

    private function lookupId(string $table, string $column, string $value): int
    {
        DB::table($table)->updateOrInsert(
            [$column => $value],
            ['updated_at' => now(), 'created_at' => now()],
        );

        return (int) DB::table($table)->where($column, $value)->value('id');
    }

    private function teamId(int $schoolYearId, string $name, string $color): int
    {
        return (int) DB::table('teams')->insertGetId([
            'school_year_id' => $schoolYearId,
            'name' => $name,
            'color' => $color,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
