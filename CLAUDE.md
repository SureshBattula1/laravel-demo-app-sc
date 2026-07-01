# CLAUDE.md — School Management API

Guidance for Claude Code when working in this repository.

## What this is

REST/JSON backend for a **multi-tenant School Management SaaS**. It serves the
Angular SPA in the sibling `../ui` repo. There is no server-rendered UI here —
every endpoint returns JSON and is authenticated with Sanctum bearer tokens.

## Stack

- **PHP 8.2+ / Laravel 12**
- **MySQL** (default; `DB_DATABASE=school_management`). SQLite is supported for quick local runs.
- **Laravel Sanctum 4** — API token auth (`auth:sanctum`)
- **barryvdh/laravel-dompdf** — server-side PDF (invoices, reports)
- **maatwebsite/excel** — Excel/CSV import & export (bulk student/teacher import)
- **Twilio SDK 8** — SMS & WhatsApp (bulk messaging, status webhooks)
- Queues, cache, and sessions all use the **database** driver by default (see `.env.example`)

## Commands

```bash
composer install
cp .env.example .env && php artisan key:generate   # first-time setup
php artisan migrate                                 # apply schema (~75 migrations)
php artisan db:seed                                 # seeders (see below)

composer dev        # runs server + queue worker + log tailer + vite concurrently
php artisan serve   # API only, http://localhost:8000

composer test       # config:clear then php artisan test (PHPUnit 11)
php artisan test --filter StudentTest
./vendor/bin/pint   # code style (Laravel Pint)
```

Seeders of note: `CompleteSchoolSystemSeeder` (full demo school), `CompanyPortalSeeder`
(SaaS/company-portal data), `AssignSuperAdminPermissions`. Entry point is `DatabaseSeeder`.

## Running with Docker

A full-stack Docker setup lives in the **parent directory** (`../docker-compose.yml`),
covering this API, the `../ui` Angular app, and a dedicated MySQL. Run all `docker`
commands from **inside WSL2 Ubuntu** (Docker Engine CLI — no Docker Desktop needed):

```bash
cd /mnt/c/.../Projects        # the folder containing docker-compose.yml
docker compose up --build -d  # build + start db, api, ui
docker compose logs -f api    # watch API logs
docker compose down           # stop (keeps DB volume)
docker compose down -v        # stop AND wipe the DB (fresh seed next boot)
```

Endpoints when running: UI `http://localhost:8080`, API `http://localhost:8000`,
MySQL `127.0.0.1:3309` (root/root, visible in Workbench).

Key files (this repo): [Dockerfile](Dockerfile) (PHP 8.4-fpm + extensions + Composer),
[docker/entrypoint.sh](docker/entrypoint.sh) (waits for DB → `migrate --force` → seeds
**only on a fresh DB** → `artisan serve`), [.env.docker](.env.docker) (container env;
`DB_HOST=db`). The image bakes in code, so **rebuild (`up --build`) after code changes**.

Notes:
- The compose MySQL uses host port **3309** to avoid clashing with any pre-existing
  `mysql8` container on 3308. It is a separate database from local (non-Docker) runs.
- `.dockerignore` excludes `vendor`, `.env`, and the SQLite file; the image runs
  `composer install` itself. `entrypoint.sh` must keep **LF** line endings.

## Architecture & conventions

**Multi-tenancy hierarchy — this is the most important concept:**
```
Company → School → Branch → (students, teachers, classes, sections, ...)
```
Almost every domain table carries `school_id` and `branch_id`. Many also carry
`academic_year_id`. **When writing any query that reads tenant data, scope it by
school/branch** — a missing scope is a tenant data leak, the highest-severity bug
class in this codebase.

- **School scoping:** controllers call `getCurrentSchoolId($request)`; respect it.
- **Academic year:** injected via the `AcademicYearContext` service (constructor
  injection) and the `SetAcademicYearContext` middleware. The UI sends the active
  year on every request.
- **Cross-branch access** is permission-gated — see `User::hasCrossBranchAccess()`.

**Authorization is a custom RBAC system, not Laravel Gates/Policies:**
- Tables: `roles`, `permissions`, `role_permissions`, `user_roles`, `user_permissions`.
- Permission check logic lives in `app/Models/User.php` (`hasPermission`,
  `hasAnyPermission`, `getAllPermissions`). **Priority: user-specific override > role permission.**
- Permissions are slug-based (e.g. `students.view`, `fees.collect`).
- Routes enforce them with `->middleware('permission:students.view')`.
- There is **no SuperAdmin bypass** — SuperAdmin must have permissions assigned to its role.

**User types** (platform layer, distinct from `role`): `CompanyAdmin`, `SupportStaff`,
`SchoolUser`. Company admins can **impersonate** school users (`ImpersonationController`).

**Routing:**
- `routes/api.php` — main app API, all under `auth:sanctum` + `throttle:180,1`.
- `routes/company-portal.php` — SaaS owner portal (companies, schools, impersonation).
- `routes/api_class_group.php` — class/group endpoints.
- Public routes: `/login`, `/register`, `/forgot-password`, `/reset-password`,
  `/health`, and the Twilio status webhook.

**Controller style (follow the existing pattern):**
- Controllers are thin-ish but currently do **inline validation** via the `Validator`
  facade. Only `StoreStudentRequest` / `StoreBranchRequest` use Form Requests so far —
  prefer creating a Form Request for new validation rather than adding more inline rules.
- Performance-sensitive list endpoints use the **query builder (`DB::table`)** with
  explicit joins and column selection instead of Eloquent eager loading. See
  `StudentController::index`.
- List endpoints use the `PaginatesAndSorts` trait for server-side pagination/sorting.
- Migrations and some queries use defensive `Schema::hasTable()/hasColumn()` checks
  because the schema evolved incrementally — keep this style when touching them.
- **Soft deletes** (`SoftDeletes`) are used widely; remember to account for `deleted_at`.

> Note: `OptimizedStudentController` and `EnhancedBranchController` exist alongside the
> base controllers. Confirm which is actually routed before editing — they may be
> parallel/legacy implementations.

## New feature checklist

Before shipping a new endpoint or controller method, check all of these — the
`DepartmentController` gap (branch scoping fixed on `index`/`store` but missing on
`show`/`update`/`destroy`/`toggleStatus`) is the cautionary example for #1:

1. **Tenant scoping on every single-record method, not just `index()`.** Any model holding
   tenant data should use `BelongsToTenant`, but that's defense-in-depth, not a substitute for
   an explicit `canAccessBranch($request, $record->branch_id)` (or equivalent) check in
   `show`/`update`/`destroy`/custom actions. `AdmissionController` and `StudentGroupController`
   are the reference implementations — every method checks, not just the list endpoint.
2. **Validation via Form Request**, not another inline `Validator::make()` block — the codebase
   is mid-migration to Form Requests (`StoreStudentRequest`, `StoreBranchRequest`); add to that
   pattern rather than the inline one.
3. **Performance-sensitive list endpoints** use the query builder (`DB::table`) with explicit
   joins/column selection, and the `PaginatesAndSorts` trait for pagination/sorting — see
   `StudentController::index`.
4. **Multi-table writes go in a DB transaction** (`DB::beginTransaction`/`commit`/`rollBack`),
   matching `StudentPromotionService` and `ImportService`.
5. **Route it behind `permission:<module>.<action>`** with a slug that matches exactly what the
   UI's route `data.permissions` and `*hasPermission` directive expect — a mismatch silently
   hides the feature client-side rather than erroring.
6. **Add a feature test.** Coverage is thin — this is the main safety net against regressions
   like the tenant-scoping gap above.
7. **Response shape** should match existing conventions: the standard paginated envelope, and
   hashid-encoded IDs if `HASHIDS_ENABLED=true` (don't return raw ints on one endpoint and
   hashids on another).

## Gotchas

- `SMS_BULK_DISPATCH_SYNC=true` runs bulk SMS jobs inside the HTTP request (no worker
  needed in dev). Set `false` in production with a real queue worker.
- Twilio WhatsApp status callbacks need a public HTTPS URL; localhost is skipped automatically.
- Test coverage is currently minimal (`StudentTest`, `SmsTemplateTagRendererTest` + stubs).
  When adding features, add feature tests — they're the safety net this repo lacks.
