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
- UI composes components from `resources/js/Components`. Don't repeat long utility strings. Containers use the `card` utility; tables use `<table class="data-table">`.
- Colours are semantic tokens (`bg-surface`, `text-ink`, `text-muted`, `ring-line`…), never raw `slate-*`/`white`, so themes (incl. the prepared `.dark` palette) keep working. Icons come from `lucide-vue-next` only.
- Destructive actions confirm through `ask()` (`lib/confirm.ts`), never `window.confirm`. Tables go in `DataTable` (filters in the `toolbar` slot, pagination in `footer`).
- Landing-page text lives in `App\Services\Content\LandingCopy::fields()` (stable keys, defaults). Never hard-code visible copy in `Welcome.vue`; add a field and read it with `t('…')`. Logos come from `Branding` via the shared `branding` prop and `<AppLogo place=…>`.
- The public landing page reads only `LandingPageService::payload()`. Add public data there, field by field, from published/public records only — never pass models or member-visible data to `/`.
- New pages go in `lib/navigation.ts` so the sidebar and breadcrumbs pick them up. Visual check: `node tests/Browser/shots.mjs` (see its header).
- Event/news/profile images use `FileUploadService::storeOptimizedImage` with a `MediaSettings` limit; galleries go through `App\Services\Media\ImageGallery`. Content-derived public access to files belongs in `App\Services\PublicMedia`.
- Files enter only through `FileUploadService` and are served only through `FileController` (`StoredFilePolicy`).
- Every polymorphic model needs an entry in `Relation::enforceMorphMap` (AppServiceProvider). Missing entries fail at runtime.
- Strict mode is on outside production: no lazy loading, and `fill()` with unknown keys throws. Eager-load and use `Arr::except` when the payload carries non-model fields.
- Reportable content implements `App\Contracts\Moderatable` and is listed in `ReportController::REPORTABLE` and `ModerationController::TYPE_PERMISSIONS`.
- Engagement is recorded through `EngagementRecorder` (idempotent per alumnus, type and entity). Never store scores.
- Anything paid online implements `App\Contracts\Payable` and goes through `PaymentGateway`. Never add a second checkout path.
- AI calls go through `App\Services\Ai\LanguageModel` (bind `Tests\Support\FakeLanguageModel` in tests). Assistant tools must stay read-only and run as the user. Never send data the user couldn't see.
