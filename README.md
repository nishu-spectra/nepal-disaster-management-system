# Nepal Disaster Management System (NDMS)

A modular PHP + MySQL/MariaDB starter application for XAMPP, designed for Nepal with public incident reporting, help requests, role-based operations, bilingual UI (English/Nepali), and a skeuomorphic forest-green / terracotta / ivory interface.

## Important scope
This is a functional starter/MVP, not a certified emergency dispatch service or a security-audited production system. Do not rely on it as the sole channel for real emergency response. Official alerts, SMS, live weather feeds, dispatch integrations, and operational data agreements must be configured and validated before public launch.

## Requirements
- XAMPP with a currently supported PHP release (PHP 8.2+ recommended) and MySQL/MariaDB
- PDO MySQL extension enabled
- Modern browser; internet connection for optional OpenStreetMap tiles

## Install on XAMPP
1. Extract the `nepal-disaster-management` folder into `C:\xampp\htdocs\`.
2. Start Apache and MySQL from XAMPP.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`.
4. Create a database named `ndms` using `utf8mb4_unicode_ci`.
5. Import `database/schema.sql` into `ndms`.
6. Open `app/config.php` and set DB credentials for your local setup. Default XAMPP credentials are `root` with an empty password; change this for any shared or live environment.
7. Generate a strong setup key (for example, a long random string) and set `NDMS_SETUP_KEY` in your environment, or replace the `CHANGE-ME-SETUP-KEY` value in `app/config.php` for local installation only.
8. Visit `http://localhost/nepal-disaster-management/public/setup.php?key=YOUR_SETUP_KEY` and create the first administrator. This endpoint creates the admin account once. After success, delete `public/setup.php` and remove the setup key from configuration.
9. Visit `http://localhost/nepal-disaster-management/public/`.

If your folder name changes, adjust `BASE_PATH` in `app/config.php` (for example `/ndms/public`).

## First administrator
The setup page creates the first admin from your supplied name, email, and password. It does not ship with a default username or password.

## Main features
- Public home page and incident list
- English/Nepali language switch using session preference
- Registration, login, logout, password hashing, CSRF protection, basic login throttling
- Citizen incident reports and help requests
- Role-aware dashboards for citizen, responder, organization, officer, and administrator
- Admin incident review and status updates
- Server-side record ownership checks
- Prepared PDO statements, validation, output escaping, secure session cookie settings
- OpenStreetMap/Leaflet map panel (optional remote tiles; public tile availability is not guaranteed)
- Security event logging and SQL schema
- Backup script template and deployment checklist

## Security notes before production
- Use HTTPS and set `APP_ENV=production`; configure a supported PHP runtime and web server.
- Set a strong database password and use a dedicated least-privilege database user.
- Keep `app/config.php`, logs, backups and uploaded files outside public web access in production.
- Configure external secrets via environment variables; never commit production secrets.
- Add MFA for administrators, verified official alert sources, email/SMS provider integration, malware scanning for uploads, and a tested off-site backup destination.
- Configure a daily scheduled backup using `scripts/backup-mysql.sh` (Linux) or an equivalent Windows Task Scheduler command.
- Conduct independent security testing, restore drills, privacy review and operational exercises before use in real emergencies.
- The built-in login throttle is an application-level starter control. Add reverse-proxy/WAF rate limiting and monitoring for public deployment.

## Structure
- `app/` core config, auth, helpers, controllers
- `public/` web entry point and static assets
- `database/schema.sql` schema
- `scripts/` backup template
- `docs/` deployment and security checklist

## License
Starter project generated for customization. Verify third-party library and map tile provider terms before deployment.
