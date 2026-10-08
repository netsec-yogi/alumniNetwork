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

**Demo accounts** (`make demo`, password `Demo-Password-2026`): `verification@`, `alumni.office@`, `events@`, `chapter@`, `giving@`, `faculty@`, `student@iiitm.ac.in`, and 66 random alumni (60 verified, 15 of them mentors) with events, jobs, groups and posts. Staff roles must enrol in 2FA on first sign-in.

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

## What is built (SRS §113 MVP, §114 Phase 2, §115 Phase 3)

| Area | Features |
|---|---|
| **Security** | Registration, email verification, login, password reset, TOTP 2FA (mandatory for admin roles) with one-time recovery codes, RBAC with privilege ceilings, lockout, rate limits, device sessions, admin session timeouts, nonce-only CSP, audit log, request IDs. See [docs/security.md](docs/security.md) |
| **Alumni** | Registration with automatic verification against institute records, manual verification queue, profiles with per-field privacy (public / alumni / connections / private), privacy-aware directory |
| **Connect** | Connection requests, follow, block, report, mutual connections, "people you may know" |
| **Feed & groups** | Posts (plain text + link, achievements, announcements), likes, comments, saves, pinning, infinite scroll; communities, chapters and automatic **batch groups**; group moderators; abuse reports and a permission-scoped moderation queue |
| **Events** | Public/member events, capacity with guests, strict FIFO waitlist, QR tickets that open the staff check-in desk, duplicate-proof check-in, 24h reminders, cancellation notices, .ics, attendee CSV; chapter admins host only for their chapters. **Reunions**: paid tickets (seat held 30 min during checkout), batch invitations, attendee photo gallery |
| **Career** | Jobs & internships with moderation (edits to live posts are re-reviewed), expiry, referral requests decided by the poster |
| **Mentoring** | Mentor profiles, rule-based matching with configurable weights (`config/mentoring.php`) and a score breakdown, capacity limits, request → accept → complete |
| **Notifications** | In-app notification centre + queued email |
| **Messaging** | 1:1 conversations, message requests for non-connections, attachments, block-aware, reportable; no administrative read path |
| **Uploads** | Profile photos and attachments: content-sniffed types, images re-encoded (metadata stripped), PDFs by magic bytes, ClamAV scanning (optional, fail-closed when required), private storage, authorised downloads |
| **Recognition** | Achievements (submit → review → publish), distinguished alumni, staff-written stories (safe Markdown) — public pages |
| **Network** | Startup directory with alumni founders, research-collaboration board, speaker network with invitations |
| **Volunteering** | Opportunities, sign-ups, hours logged and approved by organisers |
| **Communications** | Segmented email/in-app campaigns, live audience count, scheduling, consent-respecting email, signed one-click unsubscribe |
| **Giving** | Donations by category via hosted checkout (Razorpay Payment Links; test gateway for dev), signed webhooks with replay protection, gap-free 80G receipts per financial year (PDF, emailed), refunds, exports |
| **Fundraising** | Campaigns proposed by alumni or staff, reviewed before going live, with goals, matching pledges, updates and a Giving Day leaderboard by batch and chapter |
| **Surveys** | Targeted or event-attendee surveys, anonymous or named, 6 question types incl. NPS, results and CSV export |
| **Analytics** | 12-month trends, retention, cohorts, and top countries, cities, industries and employers |
| **AI (optional)** | Natural-language directory search, AI re-ranking of mentor matches with reasons, and an assistant whose read-only tools run as the signed-in user. Off unless `AI_ENABLED=true` and `ANTHROPIC_API_KEY` are set |
| **Administration** | KPI + security dashboard, alumni 360° view (audited), CSV import of institute records (validate → preview → queued import), capped & audited CSV export, programmes/departments, user & role management, engagement reports with CASE breakdown and score (`config/engagement.php`), audit log |

Every module writes `engagement_activities` (SRS 52); scores are always computed from them.

Sign in with Google or LinkedIn works once `GOOGLE_*` / `LINKEDIN_*` keys are set (link the account under Security first). Admins can be limited to campus IPs with `ADMIN_ALLOWED_IPS`, and get an email on sign-in from a new browser (`LOGIN_ALERTS`).

Members can add **passkeys** under Security and sign in with fingerprint, face or device PIN (Fortify's built-in passkeys on `laravel/passkeys`); for production, `APP_URL` must be the real https origin, because passkeys are bound to that domain.

Not yet built: OpenSearch (the directory uses MySQL).

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

## UI design system

A social-network look for the member app: a sticky top bar (search, Create, theme, messages, notifications, avatar menu), a navigation rail on desktop (full labels on wide screens, icons with tooltips/flyouts on laptops) and a bottom tab bar with a "Menu" sheet on phones. Navigation is context-aware: inside `/admin` the rail shows the admin sections and a way back. The home page is a feed (greeting, events strip, composer, announcements, posts with comment previews) with a "for you" sidebar; profiles have a cover, stats, actions and About/Posts tabs. Navigation is defined once in `resources/js/lib/navigation.ts` and filtered by permission; breadcrumbs derive from it.

- **Tokens** (`resources/css/app.css`): pages use semantic colours (`bg-surface`, `bg-canvas`, `text-ink`, `text-muted`, `ring-line`…) rather than raw greys, so a theme is a change of CSS variables. Light, dark or system theme is chosen from the top bar or the account menu (`public/js/theme.js` applies it before first paint). Tint ramps flip in the dark theme; solid brand surfaces use the fixed `deep-*` colours.
- **Components** (`resources/js/Components`): `AppButton` (pill buttons: primary / secondary / outline / accent / danger / success / ghost, icons, icon-only, loading), `CardPanel`, `StatCard`, `DataTable` + the `.data-table` style, `PaginationNav`, `FormField` with `TextInput` / `SelectInput` / `TextArea` / `CheckboxInput` / `RadioGroup` / `MultiSelect` / `FileUpload`, `StatusBadge`, `AlertBox`, toasts (`lib/toast.ts`, fed by flash messages), `ModalDialog`, `ConfirmDialog` (via `ask()` in `lib/confirm.ts` for every destructive action), `DrawerPanel`, `DropdownMenu`, `TabsNav`, `Breadcrumbs`, `EmptyState` (illustrated), `SkeletonBlock`, `AvatarImage` / `AvatarGroup`, `AppTooltip`, `StepIndicator` (multi-step forms such as registration), `CountUp`, `LightboxViewer`, and the `v-reveal` scroll animation (`lib/reveal.ts`). Tables become stacked cards below 640px. Motion respects `prefers-reduced-motion`.
- **Icons**: Lucide only (`lucide-vue-next`). **Font**: Plus Jakarta Sans, self-hosted (no third-party requests, CSP unchanged).

## Public landing page

`/` is a database-driven landing page (`LandingController` → `App\Services\LandingPageService`): live statistics, public upcoming events, news & announcements and alumni stories (the stories module, `news`/`announcement` types), distinguished alumni, a photo gallery with lightbox, chapters, a global-network breakdown, and join/give calls to action. Nothing is hard-coded; empty sections show an empty state and zero statistics are hidden. Admins control it under Administration → Content: **Landing page** (section order/visibility, which statistics show) and **Gallery** (draft/published/archived, featured, order, date window); stories, distinguished alumni and events have *featured* and ordering options, events a cover image, and chapters a city/country/public coordinator and a "show on landing page" switch. The payload is cached for 10 minutes and refreshed when curated content changes. SEO tags (description, canonical, Open Graph, Twitter, Organization JSON-LD) are rendered server-side.

## Media

- **Image galleries**: events (Admin → event page → *Event images*) and news/stories (story editor → *Images*) take multiple images: drag-and-drop upload, reorder, replace, delete, and one ★ featured image that is used on cards and the landing page; the detail pages show the full gallery with a lightbox. Events reuse `event_photos` (`is_official` images vs. attendee uploads); news uses `story_images`.
- **Profile photos**: members upload their own (preview before saving). Members-only by default; shown publicly only while the alumnus is featured on the landing page.
- **Size limits**: Administration → System → *Media settings* (defaults: event 300 KB, news 300 KB, profile 200 KB; 50–2048 KB). `FileUploadService::storeOptimizedImage` checks the real content type and dimensions, resizes, re-encodes to WebP (no metadata), steps quality/size down until the image fits, and rejects it otherwise. Originals are not kept.
- **Admin password management** (`users.password.manage`, super administrators by default): Users → *Change password* (admin sets one under the password policy; the user must choose their own at next sign-in) or *Reset password* (the old password stops working and the user is emailed a reset link — nobody ever sees a password). Both sign the user out everywhere, notify them, need a reason and password re-confirmation, are rate-limited, and are audited (`password_changed_by_admin` / `password_reset_by_admin`, including refused and failed attempts). Audit → *Password changes* lists them.
- **Admin email correction**: Administration → People → Users → *Email* (password confirmation; re-verification required; the old address is notified; audited).

## Payments

`PAYMENT_GATEWAY=fake` (default) gives a local test checkout with “simulate success/failure”. For real donations set `PAYMENT_GATEWAY=razorpay`, the `RAZORPAY_*` keys, point a Razorpay webhook (`payment_link.paid`) at `/webhooks/payments/razorpay`, and fill the `RECEIPT_*` values (institute PAN, 80G registration) used on receipts. Online giving is INR-only: foreign contributions need FCRA registration.

## AI features

Set `AI_ENABLED=true` and `ANTHROPIC_API_KEY` to turn them on (model: `AI_MODEL`, default `claude-opus-5-5`). Smart search sends only the query plus the programme and interest lists. Mentor re-ranking sends anonymous candidates with what the mentee can already see. The assistant's tools run as the user, so it can't see anything they couldn't. Requests are limited to 6 a minute and 60 a day per user, and the conversation lives in the session only.

## Production notes

Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES=<proxy IPs>`, real mail credentials, and strong DB/Redis passwords. Run `php artisan config:cache route:cache view:cache`. Start ClamAV (`docker compose --profile scan up -d clamav`) and set `CLAMAV_HOST=clamav`, `UPLOADS_SCAN_REQUIRED=true`. Back up MySQL nightly and keep binlogs for point-in-time recovery (enabled in `docker/mysql/my.cnf`), and test restores.
