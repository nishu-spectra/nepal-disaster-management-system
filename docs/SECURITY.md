# Security design and known MVP limitations

Implemented in starter:
- PDO prepared statements, server-side validation, output escaping
- Password hashing using PHP password_hash / password_verify
- CSRF tokens for POST forms
- Role checks on operations/admin pages
- Owner-scoped queries for personal incident and assistance lists
- Session ID regeneration on login, HttpOnly and SameSite cookies
- Basic email-keyed login attempt throttling and hashed IP identifiers
- Security events and audit records for selected actions
- Production security headers and generic error messages

Required before production:
- Multi-factor authentication for privileged roles
- Email verification, secure password reset, account recovery and session management UI
- Robust IP + account rate limiting at reverse proxy/WAF level
- Stronger throttling storage/atomic counters and cleanup job
- File upload pipeline with malware scanning if uploads are enabled
- Detailed RBAC/organization scoping for all future organization and volunteer features
- Formal privacy/retention policy and access review
- Automated unit/integration/security test suite and independent penetration test
- Tested daily encrypted off-site backups and restoration process
- Alert feed source validation and authorized publication governance

No system can be guaranteed fully secure. Do not expose this starter directly to the public for real emergency operations before completing the items above.
