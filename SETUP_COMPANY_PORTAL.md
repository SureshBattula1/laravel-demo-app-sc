# Company Portal Setup Instructions

## Step 1: Run Migrations

First, you need to run all the migrations to create the necessary tables:

```bash
cd laravel-demo-app-sc
php artisan migrate
```

This will create:
- `companies` table
- `schools` table
- `impersonation_sessions` table
- Add `school_id` to all existing tables
- Add `company_id` and `user_type` to users table

## Step 2: Run the Company Portal Seeder

After migrations are complete, run the seeder to create a demo company and company admin user:

```bash
php artisan db:seed --class=CompanyPortalSeeder
```

## Step 3: Login Credentials

After running the seeder, you can login to the company portal with:

**Email:** `company.admin@demo.com`  
**Password:** `Admin@123`

## Step 4: Access the Company Portal

### Frontend (Angular)
Navigate to: `http://localhost:4200/company-portal/login`

### Backend API
The company portal login endpoint is: `POST /api/company-portal/login`

## What Gets Created

1. **Company:**
   - Name: Demo Company
   - Code: DEMO001
   - Email: admin@demo-company.com
   - Status: Active

2. **Company Admin User:**
   - Email: company.admin@demo.com
   - Password: Admin@123
   - User Type: CompanyAdmin
   - Role: CompanyAdmin

## Quick Setup (All in One)

If you want to run everything at once:

```bash
cd laravel-demo-app-sc
php artisan migrate
php artisan db:seed --class=CompanyPortalSeeder
```

## Troubleshooting

If you get an error about tables not existing, make sure you've run migrations first.

If the seeder says the company or user already exists, that's fine - it means they were already created.



