# Deployment checklist

## Before local setup
- Import `database/schema.sql`.
- Configure `app/config.php` for local DB.
- Set a unique setup key; create the first administrator with `public/setup.php`.
- Delete `public/setup.php` after setup.

## Before production
- Use a currently supported PHP version and patched OS/web server.
- Serve only the `public/` directory as the document root.
- Use HTTPS only; set `NDMS_APP_ENV=production`.
- Configure `NDMS_DB_USER` and `NDMS_DB_PASS` for a dedicated least-privilege account.
- Move logs and backups outside the document root; restrict file permissions.
- Store secrets in environment/secret management, not source control.
- Configure admin MFA and verified email/password recovery.
- Add reverse-proxy/WAF throttling, monitoring and alerting.
- Test authorization boundaries, IDOR, SQL injection, XSS, CSRF, uploads, and privilege escalation.
- Run daily encrypted backups to a separate location and perform restoration drills.
- Review privacy, retention, consent, incident escalation, and official alert publication policies.
- Establish data agreements with official information providers before showing any feed as official.
- Conduct independent security testing and a disaster exercise before operational launch.

## HTTPS
The application does not automatically provision TLS certificates. Configure HTTPS at the production web server/load balancer and redirect HTTP to HTTPS. HSTS is enabled only when `NDMS_APP_ENV=production`; do not enable it locally unless HTTPS works correctly.

## Windows daily backup
Use Windows Task Scheduler with a protected batch/PowerShell script invoking `mysqldump.exe`, gzip/7-Zip, and a protected destination. Store DB credentials outside the script or use a secured credential mechanism. Verify the generated backup and test restoring it.
