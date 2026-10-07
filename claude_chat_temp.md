Option A is done. The Laravel app runs fully in Docker at http://localhost:8090 with Vue 3 + Inertia, Tailwind, MySQL and Redis. Authentication, 2FA, RBAC and audit logging are in place, and the first real feature, alumni registration and verification, works end to end.

Test results

56 PHPUnit tests pass. They run against a separate test database and cover every critical security case in SRS §104 except 10–11, which are about file uploads and wait for the upload feature.
A real-browser test passes with zero console errors or Content Security Policy (CSP) violations. It walks the whole admin 2FA flow: forced setup, password confirmation, QR code, one-time recovery codes, then signing in again with a code.
The email queue delivers to Mailpit. I sent two test emails and both arrived.
composer audit and npm audit found no vulnerabilities.
What's built

Docker: php-fpm 8.3, nginx (it sees only public/), MySQL 8.4 (reachable only from this machine), Redis, a queue worker, a scheduler and Mailpit. Host ports differ from your admissions project, so both can run at once.
Auth and security:
Sign-up, email verification and password reset.
Authenticator-app 2FA, mandatory for every admin role.
Recovery codes are shown only once.
Accounts lock after 10 failed attempts. Failed 2FA codes count too.
Rate limits on every sign-in and sign-up endpoint.
Users can list and sign out their devices.
Admins get a 30-minute idle and 8-hour absolute session limit.
A strict CSP with no inline code.
An audit log that can't be edited and strips secrets.
A request ID on every response.
Roles: the 12 SRS roles with fine-grained permissions. Nobody can change their own roles, grant permissions they don't hold, or manage someone more senior.
Alumni:
Registration auto-verifies against the institute's records. Anything that doesn't match goes to a review queue.
Officers can't approve their own claim.
Profiles have per-field privacy, and directory search can't be used to uncover hidden fields.
Admin pages: dashboard (statistics plus security panel), users and roles, verification queue, audit log.
Gaps I fixed along the way

Fortify's default routes don't rate-limit sign-up or password reset at all.
Fortify lets anyone switch off their own 2FA, which would break the "mandatory for admins" rule.
Fortify lets users view their recovery codes again at any time; the SRS says once.
A trust-all-proxies setting I first wrote would have let anyone fake their IP address and get past rate limits.
Changes from the SRS

Passkeys (WebAuthn) are off. Fortify supports them, but they need extra frontend work, and the SRS lists them as future.
"Connections only" privacy behaves like "Only me" until the Connect feature exists.
Programme and department seed data is my best guess and needs checking against the academic section's records.
Not built yet: file uploads (§71–73), SSO/OAuth, sign-in alerts for admins, CSV import and export, and every module after this one (feed, events, mentoring, jobs and so on). Permissions for those already exist.

To try it

Your login: admin@iiitm.ac.in / ChangeMe-Alumni-2026. Have an authenticator app ready; you'll be asked to set up 2FA first.
Demo accounts: password Demo-Password-2026, e.g. student@, verification@, alumni.office@iiitm.ac.in.
Docs: README.md has setup and commands. docs/security.md maps each SRS section to its code. CLAUDE.md replaces Laravel's stock version with project conventions; AGENTS.md is an identical copy.
The folder isn't a git repo yet. Should I run git init and make the first commit?