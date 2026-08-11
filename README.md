# TutorLagbe

A PHP + MySQL starter platform for finding trusted tutors in Bangladesh.

## Run locally

1. Put this folder in XAMPP's `htdocs` directory and start Apache and MySQL.
2. Import `database/tutorlagbe.sql` through phpMyAdmin (it creates the `tutorlagbe` database).
3. Update the constants or environment variables in `config/db.php` when your MySQL credentials differ.
4. Open `http://localhost/TutorLagbe2.0/`. The project detects that path automatically; set `TUTORLAGBE_BASE_URL` only when using a custom virtual host.

## Admin access

After importing the schema, visit `http://localhost/TutorLagbe2.0/admin/login.php`.
The initial account is `admin@tutorlagbe.com` with password `Admin@123`; change this password before deploying anywhere public.

Tutor registrations are held for approval. Student registrations are approved immediately.

## Structure

- `config/` PDO connection
- `includes/` shared layout and security helpers
- `auth/` registration, login, logout, password-reset placeholder and Google OAuth placeholder
- `assets/` styles and browser-side validation
- `database/` importable schema

Google sign-in is intentionally a safe placeholder. Add your Google OAuth client ID and secret as environment variables and implement the callback token verification in `auth/google_login.php` before enabling it in production.
