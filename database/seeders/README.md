# Database seeders

## Default (`php artisan db:seed`)

`DatabaseSeeder` runs in two phases:

| Phase | What it creates |
|-------|-----------------|
| **System** | Roles, modules & permissions, role permission matrix, super-admin users, academic years, company portal user |
| **Demo** (when `SEED_DEMO_SCHOOL=true`, default) | Green Valley demo school via `DemoSchoolDatasetSeeder` |

Set `SEED_DEMO_SCHOOL=false` in `.env` for roles/permissions only (no sample school).

## Demo school pipeline

```
DemoSchoolDatasetSeeder
├── DemoOneSchoolSeeder      … school, branch, staff, teachers, students
├── DemoSchoolOpsSeeder      … subjects, exams, leaves, fees, holidays
├── DemoTimetableSeeder      … class period rows (Mon–Fri)
├── LibraryDemoDataSeeder    … books & loans
└── TransportDemoDataSeeder  … routes, vehicles, drivers
```

## Optional (manual)

```bash
# Second campus on the same demo school
php artisan db:seed --class=DemoNewBranchSeeder

# Operations only (school must already exist)
php artisan db:seed --class=DemoSchoolOpsSeeder

# Single slice
php artisan db:seed --class=DemoSchoolHolidaysSeeder
```

## Credentials (local dev only)

| Account | Password |
|---------|----------|
| `superadmin@school.com`, `admin@school.com` | `Admin@123` |
| `company.admin@demo.com` | `Admin@123` |
| `*@greenvalley.demo` (demo school users) | `Admin@123` |