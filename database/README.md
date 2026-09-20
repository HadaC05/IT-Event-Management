# MySQL database

The application uses the MySQL/MariaDB database `event_db` through
`api/db_connect.php`. The connection defaults match a standard local XAMPP setup:

- Host: `127.0.0.1`
- Port: `3306`
- Database: `event_db`
- User: `root`
- Password: empty

Override any default with the `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and
`DB_PASS` environment variables.

Application tables use the `tbl_` prefix, such as `tbl_users`, `tbl_roles`, and
`tbl_posts`.

Import `database/2026_09_21_add_activity_catalog.sql` once to create the
activity catalog (`tbl_activities`) grouped by event type. It migrates existing
event activities to the catalog, removes the legacy event-activity description,
adds the required `activity_id` reference, and restricts both activity statuses
to the `active`/`inactive` enum values. Every existing event activity must have
an event type before importing it.

Import `database/2026_09_20_add_event_academic_periods_and_membership_snapshots.sql`
after the score-finalization migration. It gives every event an explicit
school-year/semester period, freezes its eligible student/team membership, and
makes SBO score-sheet finalization team-scoped.

Then import
`database/2026_09_20_separate_officer_home_tribe_and_scanner_scope.sql` once.
It preserves `team_id` as the officer's home tribe and stores attendance
scanner permission separately in `scanner_team_id`, so changing scanner scope
cannot silently move the officer to another tribe.

Import `database/2026_09_20_separate_attendance_corrections_and_finalize_scores.sql`
once to keep scanner attendance evidence immutable while storing Adviser
corrections as an explicit overlay, and to make only finalized score sheets
official in Leaderboard, Reports, dashboards, Faculty, and Student views. Draft
scores remain available in the Adviser and SBO Officer scoring workspaces. Run
`php api/test-operation-atomicity.php` to verify correction/evidence separation,
Draft/Finalized publication, reopen behavior, rollback, and retry safety in an
isolated temporary database.

Import `database/2026_09_19_add_admin_role.sql` once to add the explicit Admin
role used by the shared media feed. Existing legacy `SBO` administrator accounts
remain supported. The feed reuses `tbl_posts`, `tbl_post_audits`,
`tbl_post_comments`, `tbl_post_reactions`, and the carousel fields on
`tbl_events`; no role-specific media tables are created.

Import `database/2026_09_19_link_legacy_score_categories_to_activities.sql`
once to attach legacy event-level scoring criteria to an event's sole activity
and allow criterion names to be reused across different activities. Adviser
activity scoring continues to reuse `tbl_event_activities`,
`tbl_score_categories`, `tbl_scores`, and `tbl_teams`. Run
`php api/test-activity-scoring.php` to verify activity isolation; the test uses
a transaction and rolls its temporary records back.

Import `database/2026_09_19_simplify_post_reactions.sql` once to preserve older
reaction records while converting them to the feed's single heart-based Like
reaction.

To rename an existing database without removing its records, import
`2026_09_14_prefix_tables.sql` once through phpMyAdmin.

To create or refresh MySQL from a SQLite migration source, run this command from
the project root:

```powershell
php api\migrate_sqlite_to_mysql.php --fresh
```

Omit `--fresh` to make the command refuse to overwrite a non-empty target
database.

Alternatively, import `database/event_db.sql` with phpMyAdmin. The
dump contains the converted schema, indexes, foreign keys, and existing data.

For an existing prefixed MySQL database, import
`database/2026_09_14_add_sbo_module.sql` once to add the SBO event-assignment,
attendance-entry, scoring, activity, and media relationships.

Import `database/2026_09_14_add_location_geofence.sql` once to add latitude,
longitude, and square-geofence radius fields to existing locations without
removing current records.

Then import `database/2026_09_15_link_event_locations.sql` once to relate
specific locations to a general location and link events to `tbl_locations`
without removing the existing event location text.

Import `database/2026_09_18_add_event_locations.sql` once to allow an event
to contain multiple saved locations. The existing `tbl_events.location_id`
continues to identify the primary venue used by attendance GPS.

Then import `database/2026_09_18_add_scan_detected_venue.sql` so attendance
scans can save the matched or nearest event venue and its boundary snapshot.

For SBO QR attendance, import `database/2026_09_15_add_sbo_qr_attendance.sql`
once. It adds an event-level Off/Warning/Strict location policy, scan-specific
GPS audit fields, a student/event-day/session unique scan key, and per-officer
rate-limit state. Before importing, check that existing attendance entries have
no duplicate `(attendance_id,event_schedule_id,session_code)` groups. Existing
QR-token, assignment, team, schedule, location, and attendance tables are reused.

The attendance QR belongs to each student and is scoped to that student's event
and session. The same code is scanned by an assigned SBO officer or a Faculty
member assigned to that student's team and event for both time
in and time out; the officer chooses the checkpoint before scanning. Time out
requires a prior time in, and both timestamps appear in the student's attendance
history. Time in opens 30 minutes before the session and closes at session end;
time out opens 30 minutes before session end and closes 30 minutes after. The
adviser manages event dates and times but does not issue an event attendance QR.
Editing an event schedule retains attendance records and student QR tokens.

For Faculty scanning, import
`database/2026_09_17_allow_faculty_scans_in_attendance_entries.sql` once after
the SBO attendance migrations. Faculty scans reuse `tbl_attendances` and
`tbl_attendance_entries`; only the SBO assignment and activity references are
nullable for these entries. Assign the Faculty member to a team in User
Management and to the event through the existing event assignment control.
Faculty receive no attendance QR and can scan only Student QRs for their team.
Run `php api/test-faculty-scanner.php` to verify this in a temporary database.

For mobile event scanning, serve the XAMPP site through Apache HTTPS using a
certificate trusted by each officer's device and open the HTTPS URL using a
hostname covered by that certificate. A self-signed certificate that the phone
has not trusted is not sufficient for reliable camera/geolocation access. The
scanner disables camera access on an insecure origin; the redirect in
`.htaccess` sends non-localhost HTTP attendance-page requests to HTTPS.
The two Nimiq scanner files and QR-generator module are self-hosted in
`js/vendor/`, so QR scanning/display do not depend on an event-day CDN.

Run `php api/test-sbo-qr-attendance.php` to verify the scan rules in a
temporary isolated MySQL database. The test database has a random name and is
removed by the script after the checks; the live `event_db` records are not
modified.
Import `database/2026_09_20_resolve_account_identity_and_normalize_academic_labels.sql`
once to restore imported students that were left as orphan SBO Officer accounts,
give real Officer logins their own unique email alias, enforce unique account
emails, and normalize the current school year and semester labels. New roster
imports parse school year and semester separately and reports display one
canonical academic-period label.
