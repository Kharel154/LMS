# Learning Management System (LMS)

A lightweight PHP-based Learning Management System for small schools, training centers, or personal projects. This repository contains a role-based LMS with Admin, Teacher, and Student interfaces, plus a simple REST-like API for integration and uploads.

## Key Features
- Role-based dashboards: Admin, Teacher, Student
- Course management: create, update, publish courses and lessons
- Quizzes and grading with attempts and progress tracking
- File uploads for lesson media and student assignments
- Simple API endpoints for integration or mobile apps

## Requirements
- PHP 7.4+ (or compatible PHP 8.x)
- MySQL / MariaDB
- Apache (XAMPP/LAMP) or another web server
- Enabled PHP extensions: `mysqli`, `pdo_mysql`, `fileinfo` (for uploads)

## Quick Setup (XAMPP / LAMP)
1. Clone or copy the project into your webroot (example uses XAMPP):

	`sudo cp -r /path/to/LLM /opt/lampp/htdocs/LLM`

2. Create a database and import the schema (via terminal or phpMyAdmin):

	`mysql -u root -p` then inside MySQL:
	`CREATE DATABASE lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
	`exit`

	Import schema:

	`mysql -u root -p lms < database/schema.sql`

	Or use phpMyAdmin to import `database/schema.sql`.

3. Configure database connection: edit [config/database.php](config/database.php) with your DB credentials.

4. Ensure `uploads/` directories are writable by the web server:

	`sudo chown -R www-data:www-data uploads/` (or `daemon:daemon` / `nobody` depending on distro)
	`sudo chmod -R 775 uploads/`

5. Open the app in your browser: `http://localhost/LLM` (or appropriate server host).

## Directory Overview
- `admin/` — Admin dashboard pages (manage users, roles, reports, system config).
- `student/` — Student-facing pages (catalogue, course view, lessons, quizzes).
- `teacher/` — Teacher tools (course builder, upload lessons, grading hub).
- `api/` — Backend endpoints used by AJAX or external clients (auth, courses, lessons, grades, uploads).
- `config/` — Configuration files (database connection, env settings).
- `includes/` — Shared header/footer, auth helper, and utility functions.
- `assets/` — CSS and JS for frontend UI.
- `database/` — Database schema and optional seed data.

## API Endpoints (quick map)
See the files in `api/` for available endpoints and parameters. Examples:
- `api/auth.php` — login, logout, session handling
- `api/courses.php` — list and fetch course data
- `api/lessons.php` — lesson retrieval and metadata
- `api/grades.php` — submit and fetch grades
- `api/upload.php` — file upload handling

When building integrations, prefer POST requests for create/update operations and validate responses for JSON structure.

## Usage Notes
- Create at least one Admin account in the `users` table (or register via UI if available) to configure the system.
- Teachers can upload lessons and assignments (check `uploads/` folder permissions).
- Students can attempt quizzes and upload assignments; their progress is tracked under `progress.php`.

## Development
- Follow the coding patterns used in `includes/functions.php` and the modular file structure in `admin/`, `teacher/`, and `student/`.
- If adding composer packages, include a `composer.json` and update the README with setup steps.

Local development tips:

`php -S localhost:8000 -t .`

This runs PHP's built-in server for quick testing (ensure `config/database.php` uses local DB credentials).

## Troubleshooting
- Blank pages / errors: enable `display_errors` in `php.ini` (development only) or check Apache/PHP error logs.
- Database connection failures: verify credentials in [config/database.php](config/database.php) and that the DB server is running.
- Uploads failing: confirm `uploads/` permissions and PHP `post_max_size` / `upload_max_filesize` settings.

## Contributing
Contributions are welcome. Suggested workflow:
1. Fork the repo
2. Create a feature branch
3. Open a pull request with a clear description of the change

Please include tests or manual verification steps for functional changes.

## License
Add your preferred license here (e.g., MIT, Apache-2.0). If you want me to add a license file, tell me which one.

## Contact / Support
If you need help customizing or deploying this LMS, open an issue or contact the project owner.

---

File references:
- Main config: [config/database.php](config/database.php)
- DB schema: [database/schema.sql](database/schema.sql)
- Updated README: [README.md](README.md)

If you want, I can also add sample admin credentials, a seed script, or a quick installer.

