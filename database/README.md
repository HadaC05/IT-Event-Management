# MySQL database

The application now uses the MySQL/MariaDB database `it_event_management` through
`api/db_connect.php`. The connection defaults match a standard local XAMPP setup:

- Host: `127.0.0.1`
- Port: `3306`
- Database: `it_event_management`
- User: `root`
- Password: empty

Override any default with the `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and
`DB_PASS` environment variables.

The original `database.sqlite` file is retained as the migration source and data
backup. To create or refresh MySQL from that file, run this command from the
project root:

```powershell
php api\migrate_sqlite_to_mysql.php --fresh
```

Omit `--fresh` to make the command refuse to overwrite a non-empty target
database.

Alternatively, import `database/it_event_management.sql` with phpMyAdmin. The
dump contains the converted schema, indexes, foreign keys, and existing data.
