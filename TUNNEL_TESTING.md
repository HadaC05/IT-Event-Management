# Temporary HTTPS scanner testing

This is a testing tunnel, not router port forwarding or production hosting.
Only one laptop hosts the app and database; all testers use its public link.

Prerequisites: XAMPP Apache on port 80, MySQL on port 3306, Node.js, and this
project at `http://localhost/ITEventManagement/`. Apply missing database upgrades
before testing; do not overwrite existing data with the complete SQL dump.

From PowerShell in the project folder:

```powershell
powershell -ExecutionPolicy Bypass -File .\start-test-tunnel.ps1
```

The launcher downloads the official Cloudflare Windows x64 executable, verifies
its release checksum, and starts a localhost-only restricted gateway on port
8787. It prints a temporary HTTPS address. Executables, logs and process state
stay in ignored `.test-tunnel/`, not GitHub. The link changes on restart.

The gateway exposes only app pages, static assets and approved API endpoints.
It blocks phpMyAdmin, other XAMPP projects, SQL dumps, backups, Git files,
database connection files, migrations and development tests. Existing API role
checks and CSRF protection remain active; session cookies are marked Secure.
This does not replace a full security audit: use test accounts/data and share
the link only with your team. Requests traverse Cloudflare's infrastructure.

## Phone test

1. Keep the host laptop awake, connected, with XAMPP running.
2. Prepare a current event with separate Time In/Time Out windows, an active
   officer attendance responsibility, and eligible students in the assigned tribe.
3. Turn **off Wi-Fi** on the officer phone and use mobile data.
4. Open the public HTTPS link and sign in using the existing officer account.
5. Allow camera and location access. Display a student's live QR on a second
   device signed into an eligible student account using the same public link.
6. Scan Time In, confirm the saved record, then test duplicate rejection. Wait
   for the configured Time Out window and display the student's new Time Out QR.
7. Check saved attendance/location audit in the adviser portal.

Warning location policy allows testing away from campus. Strict geofence tests
need accurate venue coordinates/radius and a tester at that venue. Do not invent
campus coordinates or treat a successful home scan as proof of boundary enforcement.

Optional public gateway checks:

```powershell
node js/shared/test-tunnel-check.cjs https://YOUR-LINK.trycloudflare.com
```

These checks validate reachability, blocked files and session/CSRF behavior;
they do not simulate a phone's camera or GPS.

## Stop

```powershell
powershell -ExecutionPolicy Bypass -File .\stop-test-tunnel.ps1
```

The gateway/tunnel also stop automatically after two hours. XAMPP and existing
records remain running. Test events and attendance are local MySQL data and
must be archived/excluded from official reporting when testing is finished.
