# Security implementation

How the SRS security requirements map to code. Policy values live in `config/security.php`.

| SRS | Requirement | Implementation |
|---|---|---|
| 9–14 | Auth, TOTP 2FA, recovery codes | Fortify. Secrets and recovery codes are stored encrypted. Codes are shown once, right after generation (`SecurityController`), and the read-back endpoint is shadowed in `routes/web.php`. TOTP replay is blocked via cache. |
| 11 | Mandatory 2FA by role | `security.two_factor_required_roles` + `EnsureTwoFactorEnrolled`. Owners cannot disable it (`Actions/Fortify/DisableTwoFactorAuthentication`). Admins reset it for lost devices (audited, needs password confirmation). |
| 15 | Password policy | 12–128 chars, no composition rules. Breached-password check (HIBP k-anonymity) in production. Change/reset sends a notification. |
| 16 | Sessions | Database driver, encrypted payload, HttpOnly, SameSite=Lax. Users can list and revoke devices (`SessionManager`). Admins get a 30 min idle and 8 h absolute limit (`EnforceSessionTimeouts`). Password change or reset revokes other sessions and rotates the remember token. |
| 17–18, 79, 112 | RBAC, least privilege, no escalation | Permissions in `App\Enums\Permission`, roles seeded by `RolesAndPermissionsSeeder`. `RoleAssignment`: no self-changes, can only grant roles whose permissions you hold, cannot manage more-privileged users. Role changes require password confirmation and are audited from the package's own events. |
| 20 | Verification | `AlumniVerificationService`: auto-verifies only on exact roll number + programme + year plus a fuzzy name match on an unclaimed record. Everything else goes to manual review. Officers cannot decide their own claim. |
| 22 | Privacy | `ProfileVisibility` filters every read path, including directory filters, so a hidden field cannot be inferred by searching on it. "Connections only" acts as private until Connect ships. |
| 58–61 | OWASP / injection / XSS / CSRF | Eloquent and bound parameters only, LIKE input escaped. Vue escaping (the one `v-html` is the server-generated QR SVG). Profile URLs must be https (no `javascript:`). Laravel CSRF on the web group. |
| 62–64 | Headers + CSP | `SecurityHeaders`: nonce-based script/style CSP with no `unsafe-inline`/`unsafe-eval`, `frame-ancestors 'self'`, nosniff, Referrer-Policy, Permissions-Policy, HSTS when `APP_FORCE_HTTPS`. `CSP_REPORT_ONLY=true` for trials. Ziggy routes are shared as a prop, not an inline script. |
| 66–68 | Rate limits, lockout | Login 5/min per email+IP and 30/min per IP. 2FA 5/min. Registration, reset and password-confirm limited (`ThrottleAuthEndpoints`, since Fortify leaves those unthrottled). Directory search limited. Account locks after 10 failures across password *and* 2FA (`AccountLockout`); admins can unlock. |
| 75–76 | Mass assignment, IDOR | `#[Fillable]` on every model, with verification/status/role fields excluded. Policies on every admin action. Session revocation uses hashed keys, never raw session ids. |
| 77 | Re-authentication | Email/mobile change needs the current password. Role changes, user creation and 2FA reset/disable need password confirmation. |
| 80–82 | Audit + security logs | `AuditLogger` (append-only model, recursive redaction of `security.redact` keys), plus a separate `security` log channel. Pruned after `SECURITY_AUDIT_RETENTION_DAYS` (default 730) by the scheduler. |
| 81 | Correlation | `AssignRequestId`: accepts well-formed upstream `X-Request-ID`, echoes it, adds it to log context and audit rows. Shown on error pages. |
| 83 | Error handling | Outside local/testing, errors render the Inertia `Error` page with the request id, never traces. |
| 87 | DB exposure | MySQL bound to `127.0.0.1` on the host. App uses a non-root user. |
| — | Trusted proxies | Only `TRUSTED_PROXIES`. Never `*`, which would let clients spoof IPs past rate limits. |

## Module-level controls

| Area | Control |
|---|---|
| Connect | Blocks hide both members from each other's directory, profile, feed and mentoring. A blocked member gets the same generic refusal as anyone else, so they can't tell they were blocked. Connection requests are rate-limited (30/hour, 80/day). |
| Privacy | "Connections only" fields are visible to accepted connections, including in directory filters. Mentor matching ignores location the mentor hasn't made visible to the mentee. Contact details are exchanged only when a mentorship is accepted. |
| Feed | Plain text only (no stored HTML). Links rendered client-side, https-only, with `rel="nofollow ugc noopener"`. Member-only group posts never leave the group. Post rate limits. |
| Moderation | Reports are routed by permission: posts and comments → community moderators, jobs → career admins, profiles → user managers. Removal is soft and audited. Group moderators can't act on admins. Batch-group re-enrolment never lifts a ban. |
| Events | Seat allocation under a row lock. Check-in is one atomic update (no double admission). Tickets are 40-char bearer codes, hidden from staff lists. Online links are shown only to confirmed registrants. CSV exports neutralise spreadsheet formulas. Chapter admins can only host for groups they administer. |
| Career | Non-staff posts are moderated, and editing a live post sends it back for review (no bait-and-switch). Application links must be https. |
| Imports | CSV MIME type is sniffed from content, 5 MB / 20k row caps, stored on the private disk, deleted after import. Nothing is written before an explicit confirm by the uploader. Rows are validated individually. |
| Exports | Alumni export needs `alumni.export`, a fresh password confirmation and ≤10k rows, and is audited. Engagement export needs `reports.export` and password confirmation. |
| Staff access | The alumni 360° view shows fields hidden from members, so each view is audited. |
| Notifications | Stored links are relative paths. The open endpoint follows only local paths (no open redirect). |

## Tests

`tests/Feature` covers the SRS 104 critical cases: private fields (1), admin access (2), editing others' profiles (3), disabled users (4), session invalidation (5), 2FA disable re-auth (6), single-use recovery codes (7), TOTP replay (8), rate limits (9), URL-id tampering (12), self-assigned admin (13), secrets in logs (14), and production error output (15). Cases 10–11 (uploads) come with the upload module. Each feature module has its own suite under `tests/Feature` (115 tests in total).

## Not yet built

File uploads (MIME sniffing, re-encoding, malware scanning, private disk), passkeys/WebAuthn, SSO/OAuth, login alerts for admins, IP allow-lists for admin, and the data export/import controls. The permissions and config hooks for these exist.
