# Hotel Reservation System

A simple PHP/MySQL hotel reservation system with a public booking site and an admin dashboard for managing rooms, services, bookings, and invoices.

## Features
- Public site to browse rooms, check availability, and book
- Admin dashboard to manage rooms, services, and bookings
- PDF invoice generation using `dompdf/dompdf`
- Image uploads for rooms (`public/uploads`)
- GST calculation on bookings

## Tech Stack
- PHP (mysqli)
- MySQL
- Composer (`dompdf/dompdf`)
- Vanilla HTML/CSS/JS

## Requirements
- PHP 7.4+ (PHP 8.x recommended)
- MySQL 5.7+/8.x
- PHP extensions: `mbstring`, `gd` (images)
- `curl` enabled or `allow_url_fopen=true` for remote assets in PDF
- Write permission to `public/uploads`

## Project Structure
- `public/` — public site entry (`index.php`), assets, uploads
- `admin/` — admin dashboard pages, migrations, API
- `install.sql` — database schema with seed data
- `config.php` — application configuration (database, uploads, GST)
- `db.php` — database connection bootstrap
- `vendor/` — Composer dependencies (dompdf)

## Quick Start (XAMPP on Windows)
1. Copy this folder to `c:\xampp\htdocs\hotel-app`.
2. Start Apache and MySQL in XAMPP.
3. Create a database named `hotel_app`.
4. Import `install.sql` into the `hotel_app` database.
5. Apply migrations (adds new columns used by the UI):
   - Run `admin/migrate_add_room_image.sql` on `hotel_app`.
   - Run `admin/migrate_add_room_quantity.sql` on `hotel_app`.
   - Run `admin/migrate_add_identity_card.sql` on `hotel_app`.
6. Open `config.php` and update database credentials if needed:
   - `db.host`, `db.user`, `db.pass`, `db.name`, `db.port`
7. Ensure `public/uploads` exists and is writable.
8. If `vendor/` is missing, install dependencies:
   - Open a terminal in the project folder and run `composer install`.

## Running
- Public site: `http://localhost/hotel-app/public/`
- Admin login: `http://localhost/hotel-app/admin/login.php`

## Default Admin Credentials
- Username: `admin`
- Password: `xxxxxxx`

Credentials are seeded by `install.sql` and validated in the app (`admin/login.php`). You can change them by updating the `admins` table or the fallback values in `config.php`.

## Configuration
Key settings in `config.php`:
- `db` — MySQL connection details
- `admin_user`, `admin_pass` — fallback admin credentials
- `upload_dir` — relative path for uploads
- `default_gst` — default GST rate used in bookings

## Invoices
Invoices are generated using `dompdf`.
- If remote images/styles are needed in PDFs, enable `curl` or set `allow_url_fopen=true` in `php.ini`.

## Troubleshooting
- Database connection errors: confirm `config.php` values and that MySQL is running.
- Broken room images: verify files exist under `public/uploads` and that the `rooms.image` column contains a correct relative path.
- Missing columns (`image`, `quantity`, `identity_card`): apply the SQL migration files in `admin/` after `install.sql`.
- Composer not found: install from https://getcomposer.org and run `composer install` in the project directory.

## Notes
- This is a reference implementation intended for local use with XAMPP.
- For production, harden configuration, move non-public files outside web root, and enforce authentication and input validation throughout.
