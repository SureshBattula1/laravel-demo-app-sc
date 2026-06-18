# Database Seeder Documentation

## Overview

This application uses a **clean, minimal seeding approach** with **automatic role assignment** for students and teachers.

## What Gets Seeded

### ✅ DatabaseSeeder.php (Main Seeder)

When you run `php artisan db:seed`, the following will be created:

#### 1. **6 System Roles**
- **Super Admin** (super-admin) - Level 1 - Full system access
- **Branch Admin** (branch-admin) - Level 2 - Branch-level administration
- **Teacher** (teacher) - Level 3 - Teaching staff access
- **Staff** (staff) - Level 4 - Administrative staff access
- **Accountant** (accountant) - Level 4 - Accounting and finance
- **Student** (student) - Level 5 - Student access

#### 2. **2 Super Admin Users**

| Email | Password | Role |
|-------|----------|------|
| superadmin@school.com | Admin@123 | Super Admin |
| admin@school.com | Admin@123 | Super Admin |

#### 3. **Default Data**
- Grades 1-12 (with categories: Primary, Middle, Secondary, Senior-Secondary)
- Account Categories (Income/Expense types)

### ❌ What is NOT Seeded

- **NO demo teachers** (create via application)
- **NO demo students** (create via application)
- **NO demo branches** (create via application)
- **NO demo fee structures** (create via application)

All other data should be created through the application interface or imports.

---

## 🔄 Automatic Role Assignment

### How It Works

The application uses **Model Observers** to automatically assign roles when teachers or students are created:

#### For Students:
```php
// When a student is created (anywhere in the code)
$student = Student::create([...]);

// ✅ Automatically:
// - Finds the "student" role
// - Assigns it to the student's user account
// - Sets is_primary = true
// - Links to the student's branch
```

#### For Teachers:
```php
// When a teacher is created (anywhere in the code)
$teacher = Teacher::create([...]);

// ✅ Automatically:
// - Finds the "teacher" role
// - Assigns it to the teacher's user account
// - Sets is_primary = true
// - Links to the teacher's branch
```

### Where This Works

✅ **Individual Creation** - StudentController, TeacherController  
✅ **Bulk Import** - CSV/Excel imports for students and teachers  
✅ **API Creation** - Any API endpoint that creates students/teachers  
✅ **Console Commands** - Artisan commands that create students/teachers  
✅ **Seeders** - If you create custom seeders  

### Implementation Details

The automatic role assignment is handled by:

1. **`app/Observers/StudentObserver.php`** - Handles student role assignment
2. **`app/Observers/TeacherObserver.php`** - Handles teacher role assignment
3. **`app/Providers/AppServiceProvider.php`** - Registers the observers

### Safety Features

- ✅ **Duplicate Prevention** - Checks if role is already assigned before adding
- ✅ **Error Handling** - Logs errors without breaking the creation process
- ✅ **Idempotent** - Can run multiple times safely
- ✅ **Branch Linking** - Automatically links role to correct branch

---

## 🚀 Usage

### Fresh Installation

```bash
# 1. Run migrations to create tables
php artisan migrate:fresh

# 2. Run seeder to create roles and super admins
php artisan db:seed

# 3. Log in as Super Admin
# Email: superadmin@school.com
# Password: Admin@123
```

### Development/Testing

```bash
# Fresh database with seed data
php artisan migrate:fresh --seed
```

### Production

```bash
# Run migrations first
php artisan migrate --force

# Then run seeders
php artisan db:seed --force
```

---

## 📝 Creating Additional Users

### Super Admin Users

If you need more super admin users, you can:

1. **Via Tinker:**
```bash
php artisan tinker
```
```php
$user = User::create([
    'first_name' => 'Your',
    'last_name' => 'Name',
    'email' => 'your@email.com',
    'password' => Hash::make('YourPassword'),
    'role' => 'SuperAdmin',
    'user_type' => 'Admin',
    'is_active' => true
]);

$superAdminRole = Role::where('slug', 'super-admin')->first();
$user->roles()->attach($superAdminRole->id, [
    'is_primary' => true,
    'branch_id' => null
]);
```

2. **Via Seeder:** Edit `DatabaseSeeder.php` and add to the `$superAdmins` array.

### Teachers & Students

Create through the application:
- **Teachers:** Dashboard → Teachers → Add New Teacher
- **Students:** Dashboard → Students → Add New Student  
- **Bulk Import:** Dashboard → Import → Upload CSV/Excel

**Roles are assigned automatically** - no manual assignment needed!

---

## 🔧 Manual Role Assignment (if needed)

If for any reason automatic assignment doesn't work, you can manually assign roles:

```php
use App\Models\User;
use App\Models\Role;

$user = User::find($userId);
$role = Role::where('slug', 'teacher')->first();

$user->roles()->attach($role->id, [
    'is_primary' => true,
    'branch_id' => $branchId
]);
```

Or run the fix command:

```bash
php artisan roles:assign-missing
```

---

## 📦 Old Seeders

Old seeders have been moved to `seeders_backup/` for reference:
- They created demo data which should be created via the application
- Kept for reference only
- **Do not use these seeders** unless you specifically need demo data

---

## 🐛 Troubleshooting

### Issue: Roles not assigned after creating student/teacher

**Solution:**
1. Check if observers are registered in `AppServiceProvider.php`
2. Check logs: `storage/logs/laravel.log`
3. Run manual assignment: `php artisan roles:assign-missing`

### Issue: "Role not found" error

**Solution:**
1. Make sure you ran the seeder: `php artisan db:seed`
2. Check if roles exist: `php artisan tinker` → `Role::all()`
3. Re-run seeder if needed

### Issue: Duplicate role assignments

**Solution:**
The observer checks for existing roles, so duplicates shouldn't happen. If they do:
1. Check `user_roles` table for duplicates
2. Remove duplicates manually
3. The observer will prevent new duplicates

---

## 📊 Database Schema

### Roles Table
- `id` - Primary key
- `name` - Display name (Super Admin, Teacher, etc.)
- `slug` - Unique identifier (super-admin, teacher, etc.)
- `description` - Role description
- `level` - Hierarchy level (1 = highest)
- `is_system_role` - Cannot be deleted
- `is_active` - Role is active

### User Roles Pivot Table
- `user_id` - References users table
- `role_id` - References roles table
- `is_primary` - Is this the user's primary role
- `branch_id` - Role specific to branch (null = all branches)

---

## 🎯 Best Practices

1. ✅ **Always use the application** to create teachers and students
2. ✅ **Let automatic role assignment work** - don't manually assign
3. ✅ **Use bulk import** for large numbers of students/teachers
4. ✅ **Keep super admin credentials secure**
5. ✅ **Don't modify system roles** (is_system_role = true)
6. ✅ **Create branch-specific admins** via Branch Admin role

---

## 📞 Support

For issues or questions about seeding and role assignment, check:
- Application logs: `storage/logs/laravel.log`
- Database structure: Check migrations in `database/migrations/`
- Observer code: `app/Observers/`

---

*Last Updated: November 2025*

