# ABV-IIITM Alumni Connect

Secure alumni relationship and engagement platform for ABV-IIITM Gwalior.

**Stack:** Laravel 13 · Inertia 3 + Vue 3 + TypeScript · Tailwind CSS 4 · MySQL 8.4 · Redis · Fortify (auth + TOTP 2FA) · spatie/laravel-permission (RBAC)

Everything runs in Docker. You need Docker, `make`, and (for the browser smoke test only) a local Chromium.

## Quick start

```bash
make install     # build image, install deps, migrate + seed, build assets
make demo        # optional: 60 verified alumni, pending claims, staff accounts
```

| What | Where |
|---|---|
| Portal | http://localhost:8090 |
| Mail catcher (Mailpit) | http://localhost:8027 |
| MySQL (host access) | `127.0.0.1:33063` (alumni / secret) |

**First sign-in:** `admin@iiitm.ac.in` / `ChangeMe-Alumni-2026` (from `.env`). Admin roles must enrol in 2FA before any admin page opens, so have an authenticator app ready.

**Demo accounts** (`make demo`, password `Demo-Password-2026`): `verification@`, `alumni.office@`, `events@`, `faculty@`, `student@iiitm.ac.in`, and 60 random verified alumni.

## Everyday commands

```bash
make up / down     # start / stop
make dev           # Vite hot reload (make dev-stop to return to compiled assets)
make test          # PHPUnit, against a separate alumni_connect_testing database
make smoke         # real browser over the main journeys; fails on any console/CSP error
make lint          # Pint + vue-tsc
make audit         # composer audit + npm audit
make fresh demo    # reset data
```

## Services (compose.yaml)

`app` (php-fpm 8.3) · `nginx` (sees only `public/`) · `queue` (Redis worker: mail, notifications) · `scheduler` (`schedule:work`) · `mysql` (bound to localhost) · `redis` · `mailpit` · `node` (dev profile only)

## What is built (MVP foundation)

- **Auth:** registration, email verification, login, password reset, TOTP 2FA with one-time recovery codes, password confirmation for sensitive actions
- **Security:** RBAC with privilege ceilings, mandatory 2FA by role, account lockout, rate limits, session listing/revocation, idle/absolute admin timeouts, nonce-based CSP, audit log, request IDs. See [docs/security.md](docs/security.md)
- **Alumni:** registration with automatic verification against institute records, manual verification queue, profiles with per-field privacy, privacy-aware directory search
- **Admin:** KPI + security dashboard, user/role management, verification queue, audit log viewer

Schema groundwork for engagement analytics (`engagement_activities`, SRS 52) and consent records (SRS 110) is in place.

## Layout

```
app/Actions/Fortify   auth actions (registration, login checks, password changes)
app/Services          AuditLogger, AlumniVerificationService, ProfileVisibility,
                      RoleAssignment, SessionManager, AccountLockout
app/Policies          per-model authorisation
app/Http/Middleware   security headers, request id, 2FA enforcement, timeouts
config/security.php   2FA policy, timeouts, lockout, redaction list
resources/js          Pages/, Layouts/, Components/ (design system)
docker/               PHP image, nginx, MySQL config
```

## Production notes

Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES=<proxy IPs>`, real mail credentials, and strong DB/Redis passwords. Run `php artisan config:cache route:cache view:cache`. Back up MySQL nightly and keep binlogs for point-in-time recovery (enabled in `docker/mysql/my.cnf`), and test restores.
