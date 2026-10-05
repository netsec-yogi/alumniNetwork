# ABV-IIITM Alumni Connect: agent guide

Laravel 13 + Inertia 3 (Vue 3, TS) + Tailwind 4 + MySQL 8.4 + Redis, all in Docker. The SRS is the source of requirements. Section numbers in comments (e.g. "SRS 77") refer to it.

## Running things
- No host PHP. Use `docker compose exec -T app php artisan …` or the Makefile (`make test`, `make lint`, `make smoke`).
- Node runs in a throwaway container: `docker run --rm -u 1000:1000 -v "$PWD":/app -w /app node:22-alpine npm run build`.
- TypeScript is pinned to 5.x: TS 7 (native) has no JS API, and vue-tsc needs one.
- Tests use the separate `alumni_connect_testing` DB (phpunit.xml). Never point them at the dev DB.

## Conventions
- Models declare `#[Fillable]`. Status, verification, role and lockout fields are never fillable. Set them with `forceFill` in the owning service.
- Business logic lives in `app/Services`, controllers stay thin, and every admin action calls `$this->authorize()`.
- Anything security-relevant goes through `AuditLogger::record()`. Never log secrets. Add new sensitive keys to `config/security.php` `redact`.
- Reads of alumni data for other users go through `ProfileVisibility`.
- CSP is nonce-only: no inline scripts or styles in Blade/HTML. Vue `:style` bindings are fine (CSSOM).
- `route()` is available in templates and `<script setup>`.
- UI composes components from `resources/js/Components`. Don't repeat long utility strings.
