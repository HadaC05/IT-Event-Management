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

For SBO QR attendance, import `database/2026_09_15_add_sbo_qr_attendance.sql`
once. It adds an event-level Off/Warning/Strict location policy, scan-specific
GPS audit fields, a student/event-day/session unique scan key, and per-officer
rate-limit state. Before importing, check that existing attendance entries have
no duplicate `(attendance_id,event_schedule_id,session_code)` groups. Existing
QR-token, assignment, team, schedule, location, and attendance tables are reused.

The attendance QR belongs to each student and is scoped to that student's event
and session. The same code is scanned by the assigned SBO officer for both time
in and time out; the officer chooses the checkpoint before scanning. Time out
requires a prior time in, and both timestamps appear in the student's attendance
history. Time in opens 30 minutes before the session and closes at session end;
time out opens 30 minutes before session end and closes 30 minutes after. The
adviser manages event dates and times but does not issue an event attendance QR.
Editing an event schedule retains attendance records and student QR tokens.

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
