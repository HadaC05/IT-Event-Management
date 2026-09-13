# Project Technology Requirements

Always build and maintain this system using the following stack:

- HTML for page structure.
- Vanilla JavaScript only for frontend behavior.
- Axios for all frontend HTTP/API requests.
- PHP with object-oriented, class-based architecture.
- PDO with prepared statements for all database access.
- Tailwind CSS for styling.

Do not introduce Laravel, React, Vue, Angular, jQuery, or a PHP ORM unless the user explicitly changes these requirements.

Keep responsibilities separated:

- `pages/` contains HTML files only. Do not place PHP files in this directory.
- `api/` contains all PHP files, including API endpoints and class-based backend code.
- Keep all PHP class and endpoint files directly inside the flat `api/` directory.
- `database/` contains the SQLite database, schema, migrations, or database-related files.
- `js/` contains vanilla JavaScript and Axios. Create a subdirectory for every page, such as `js/home/`, and keep third-party scripts in `js/vendor/`.
- Group role-specific JavaScript under `js/adviser/` (SBO Adviser), `js/sbo/` (SBO), `js/officer/` (SBO Officer), `js/faculty/`, and `js/student/`.
- Keep authentication code in `js/auth/`, public homepage code in `js/home/`, and reusable application code in `js/shared/`.
- `css/` contains Tailwind source and compiled CSS.
- `assets/` contains images, icons, fonts, and other static files.

The application must run through XAMPP Apache on localhost. Preserve the current SQLite data during conversion unless the user explicitly requests a database migration.
