# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Village profile website for Desa Mpanau (Kec. Sigi Biromaru, Kab. Sigi, Sulawesi Tengah), built against a PRD (`docs/DEVIASI.md` documents every deviation from it, with rationale — read it before assuming an implementation choice is accidental). All commit messages, code comments, and docs are in Indonesian; match that when editing existing files.

**Architecture: Laravel + Inertia.js monolith** (Laravel 12, PHP 8.2+, React 19). The project started as a decoupled Laravel REST API with two separate React SPAs and was merged into a single Inertia monolith in August 2026 — see `docs/MIGRASI-MONOLIT.md` for the full history. Key consequence for anyone touching this code:

- `routes/web.php` is the single source of truth for pages. It replaced three previously-separate route maps (API routes + two client-side React routers).
- Public pages and CMS controllers render Inertia responses directly (`Inertia::render('Publik/...')`, `Inertia::render('Admin/...')`) with data passed as page props — **not** fetched client-side.
- `routes/api.php` and `app/Http/Controllers/Api/**` still exist and are still live, but **only because the admin dashboard screens haven't finished migrating off them** (they still fetch via `@tanstack/react-query` + axios, listed in "Fase 4" of the migration doc). Do not add new API-first patterns; new admin data should go through controller → Inertia props → `useForm`, matching what public pages already do.
- `frontend/admin-app/` and `frontend/public-app/` are the leftover pre-migration SPAs. They are dead code scheduled for deletion in migration "Fase 5" — do not build on them.
- When the API layer's migration finishes, `routes/api.php`, `app/Http/Controllers/Api/`, `resources/js/lib/api.ts`, `axios`, `@tanstack/react-query`, `statefulApi()`, CORS/Sanctum SPA config, and `frontend/` are all meant to disappear together (see the "Fase 5" checklist in `docs/MIGRASI-MONOLIT.md`).

### Directory map (current, post-migration)

```
app/Http/Controllers/Publik/    Public page controllers (Inertia::render)
app/Http/Controllers/Api/V1/    Legacy REST API — admin dashboard XHR only, being phased out
app/Http/Controllers/Auth/      Session-based login (LoginController)
app/Services/                   Domain logic shared across controllers (see below)
resources/js/Pages/Publik/      Public-facing Inertia pages
resources/js/Pages/Admin/       CMS dashboard Inertia pages
resources/js/Layouts/           LayoutPublik, LayoutAdmin (persistent Inertia layouts)
resources/js/lib/api.ts         Axios client — only for admin screens still on the old API
```

### Route grouping

`routes/web.php` deliberately keeps two groups separate, per the "public never touches personal data" rule below:

- Public routes (no auth) live under `catat.kunjungan` middleware, which counts page visits — new public pages should join this group.
- `/admin/*` routes each carry their **own** `permission:` middleware. The admin sidebar menu filters by permission too, but hiding a menu item is not access control — every route must be gated itself (PRD 12.2).
- A handful of public POST endpoints (bansos check, PPID request, complaint submission, letter request) sit outside `catat.kunjungan` (they aren't page views) and each has a named rate limiter defined in `AppServiceProvider::boot()` — always use a **named** limiter for a new form endpoint, not inline `throttle:N,1`, otherwise it silently shares its bucket with every other unnamed-throttle route on the same IP.

## Binding principles (apply to every new module)

From the PRD, restated in `README.md` and reaffirmed for the Inertia rewrite in `docs/MIGRASI-MONOLIT.md`:

1. **Public endpoints never touch personal data.** Sensitive modules (population/kependudukan, bansos, APBDes realization) only expose aggregates publicly; anything identifying goes behind an admin route with permission middleware.
2. **Every write/read of sensitive data is logged** via `ActivityLogger`, which auto-scrubs PII fields so the audit trail itself never becomes a shadow table of NIKs.
3. **Every public module needs an informative empty state** — title, explanation of purpose, "Belum Ada Data" — never a blank page or raw error.
4. **Shared page props aren't refetched per page.** `pengaturan` (site settings) and `statistik_kunjungan` are already injected into every public Inertia response by `HandleInertiaRequests` — don't have a controller send them again.
5. **Logic used in two places gets promoted to a service**, not copy-pasted (see `PengaturanSitus`, `ProfilDesa` as examples already done).

## PII handling (Kependudukan / population data)

NIK and No. KK are stored encrypted (Laravel encrypted cast) for display, **plus** a separate deterministic HMAC-SHA256 hash column (`*_hash`) for indexed search/deduplication, because Laravel's encryption is non-deterministic and can't be indexed directly. See `docs/DEVIASI.md` §C1.

- The HMAC key is `PII_HASH_KEY` in `.env` — **separate from `APP_KEY`**, never commit it, and rotating it invalidates every existing hash (searches silently stop matching until data is reindexed). Generate with `php artisan pii:key`.
- `app/Services/PiiCipher.php` is the only place that should compute these hashes.
- The bansos public-search feature only hashes and matches the *last 4 digits* of NIK (`nik4_hash`), never the full value — see `docs/DEVIASI.md` §C6 before touching that matching logic.

## Commands

```bash
# One-time setup
composer install
cp .env.example .env
php artisan key:generate
php artisan pii:key          # separate HMAC key, required for Kependudukan module
# create the database first: CREATE DATABASE desa_mpanau;
php artisan migrate --seed
npm install

# Development — two processes (Laravel serve + Vite), not three
npm run dev

# Build / lint / typecheck (frontend)
npm run build
npm run lint                 # oxlint
npm run typecheck            # tsc -b --noEmit

# Tests
php artisan test                                    # full backend suite
php artisan test tests/Feature/Fase6/PengaduanTest.php   # single file
php artisan test --filter=nama_metode_test               # single test by name/method
npm test                                             # frontend smoke tests (Vitest)

# Fresh DB
php artisan migrate:fresh --seed
php artisan optimize:clear   # "bersih" — clear all caches
```

Backend tests run against a **real MySQL database** (`desa_mpanau_test`), configured in `phpunit.xml` — not SQLite in-memory. This is deliberate (`docs/DEVIASI.md` §C4): the schema relies on JSON columns and composite/hash indexes whose behavior differs between engines. This means:

- MySQL must be running locally and `desa_mpanau_test` must exist before tests pass.
- The suite is comparatively slow (~5s+); don't reach for SQLite as a "faster" alternative in test setup.
- `tests/TestCase.php` sends an `Origin` header on every request — required because Sanctum's stateful-SPA detection reads that header, and the still-live admin XHR paths depend on it.

## Seeded dev accounts

Created by `AdminUserSeeder`, non-production environments only. Password via `SEED_ADMIN_PASSWORD` env (default `password`).

| Email | Role | Scope |
|---|---|---|
| `superadmin@desa.test` | Super Admin | Everything, via `Gate::before` in `AppServiceProvider` (bypasses permission checks entirely — new permissions are automatically covered without reseeding) |
| `admin@desa.test` | Admin Utama | Everything including personal data (`view-*-pii` permissions) |
| `konten@desa.test` | Operator Konten | Berita, galeri, potensi, wisata, produk, POI, pengaduan |
| `ppid@desa.test` | Operator PPID | PPID module + information requests only (no pengaduan access — see `docs/DEVIASI.md` §C15 for why this separation matters) |

Permissions are managed via `spatie/laravel-permission`; `manage-*` (can edit a record) and `view-*-pii` (can see raw NIK) are intentionally separate abilities so an operator can be given work access without personal-data exposure.

## Known sharp edges

- **CAPTCHA is fail-closed.** `App\Services\CaptchaVerifier` (Cloudflare Turnstile) is wired into the bansos-check, PPID-request, and pengaduan forms. If `TURNSTILE_SECRET_KEY` is empty, verification is skipped entirely (fine for local dev); once it's set, all three forms require a real Turnstile token or they 422 — the frontend widget must be in place before setting the key in any shared environment.
- **No Redis.** Cache/queue/session all use the `database` driver (`docs/DEVIASI.md` §A3). Rate limiting (e.g. bansos search) is therefore per-process/per-server — fine for the current single-server deployment, but don't assume it holds if the app is ever scaled horizontally.
- **CSV import for Kependudukan runs synchronously**, not queued, capped at 10MB uploads (`docs/DEVIASI.md` §C1b).
- **Complaint (`pengaduan`) attachments live on a private disk**, never `storage/app/public`: filenames are randomized (UUID), MIME is sniffed from file content (not extension/header), and files are served only through a permission-gated admin download endpoint with `X-Content-Type-Options: nosniff`. See `docs/DEVIASI.md` §C13 before changing anything in `LampiranPengaduanService`.
- **Letter requests (`Surat Pengantar`)** generate a PDF and push a Telegram notification to the RT chief on every submission (`app/Services/NotifikasiSuratTelegram.php`, `TelegramBot.php`) — this is why its rate limiter (`kirim-surat`) is stricter than other public forms, and why the Telegram webhook route is excluded from CSRF and uses its own named limiter (`telegram-webhook`) so webhook traffic can't eat into the 3/minute form quota.
- API error responses (`routes/api.php` paths) and page-render error responses (everything else) are handled by two different branches in `bootstrap/app.php` — API errors always return the `ApiResponse::error()` JSON contract; page errors (403/404/419/500/503) render as an Inertia `Galat` page with the site chrome, except in debug mode.
