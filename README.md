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

**Demo accounts** (`make demo`, password `Demo-Password-2026`): `verification@`, `alumni.office@`, `events@`, `chapter@`, `faculty@`, `student@iiitm.ac.in`, and 66 random alumni (60 verified, 15 of them mentors) with events, jobs, groups and posts. Staff roles must enrol in 2FA on first sign-in.

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

## What is built (SRS §113 MVP)

| Area | Features |
|---|---|
| **Security** | Registration, email verification, login, password reset, TOTP 2FA (mandatory for admin roles) with one-time recovery codes, RBAC with privilege ceilings, lockout, rate limits, device sessions, admin session timeouts, nonce-only CSP, audit log, request IDs. See [docs/security.md](docs/security.md) |
| **Alumni** | Registration with automatic verification against institute records, manual verification queue, profiles with per-field privacy (public / alumni / connections / private), privacy-aware directory |
| **Connect** | Connection requests, follow, block, report, mutual connections, "people you may know" |
| **Feed & groups** | Posts (plain text + link, achievements, announcements), likes, comments, saves, pinning, infinite scroll; communities, chapters and automatic **batch groups**; group moderators; abuse reports and a permission-scoped moderation queue |
| **Events** | Public/member events, capacity with guests, strict FIFO waitlist, QR tickets that open the staff check-in desk, duplicate-proof check-in, 24h reminders, cancellation notices, .ics, attendee CSV; chapter admins host only for their chapters |
| **Career** | Jobs & internships with moderation (edits to live posts are re-reviewed), expiry, referral requests decided by the poster |
| **Mentoring** | Mentor profiles, rule-based matching with configurable weights (`config/mentoring.php`) and a score breakdown, capacity limits, request → accept → complete |
| **Notifications** | In-app notification centre + queued email |
| **Administration** | KPI + security dashboard, alumni 360° view (audited), CSV import of institute records (validate → preview → queued import), capped & audited CSV export, programmes/departments, user & role management, engagement reports with CASE breakdown and score (`config/engagement.php`), audit log |

Every module writes `engagement_activities` (SRS 52); scores are always computed from them.

Not yet built (Phase 2/3 in the SRS): messaging, file/photo uploads, donations & fundraising, stories, achievements approval workflow, startups, research, volunteering, surveys, SSO, AI features.

## Layout

```
app/Actions/Fortify   auth actions (registration, login checks, password changes)
app/Services          one service per domain: ConnectionService, EventRegistrationService,
                      JobService, MentorMatchingService, CommunityService, PostService,
                      AlumniRecordImporter, EngagementScore, AuditLogger, ProfileVisibility …
config/mentoring.php  matching weights; config/engagement.php score weights
app/Policies          per-model authorisation
app/Http/Middleware   security headers, request id, 2FA enforcement, timeouts
config/security.php   2FA policy, timeouts, lockout, redaction list
resources/js          Pages/, Layouts/, Components/ (design system)
docker/               PHP image, nginx, MySQL config
```

## Production notes

Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES=<proxy IPs>`, real mail credentials, and strong DB/Redis passwords. Run `php artisan config:cache route:cache view:cache`. Back up MySQL nightly and keep binlogs for point-in-time recovery (enabled in `docker/mysql/my.cnf`), and test restores.
