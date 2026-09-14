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
