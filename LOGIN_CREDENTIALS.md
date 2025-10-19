# 🔐 MySchool Management System - Login Credentials

## 📋 Table of Contents
- [Default Admin Accounts](#default-admin-accounts)
- [Seeded User Passwords](#seeded-user-passwords)
- [How to Find User Emails](#how-to-find-user-emails)
- [Password Policy](#password-policy)
- [Quick Login Guide](#quick-login-guide)

---

## 🎯 Default Admin Accounts

### Super Administrator
```
Email:    admin@myschool.com
Password: Admin@123
Role:     SuperAdmin
Access:   Full system access, all branches
```

### Branch Manager
```
Email:    manager@myschool.com
Password: Manager@123
Role:     BranchAdmin
Access:   Branch-specific administration
```

### Sample Teacher
```
Email:    teacher@myschool.com
Password: Teacher@123
Role:     Teacher
Access:   Class management, attendance, grades
```

### Sample Student
```
Email:    student@myschool.com
Password: Student@123
Role:     Student
Access:   View attendance, grades, assignments
```

### Sample Parent
```
Email:    parent@myschool.com
Password: Parent@123
Role:     Parent
Access:   View children's records, fees, reports
```

---

## 👥 Seeded User Passwords (Comprehensive Seeder)

When you run the **RealtimeComprehensiveSeeder**, it creates 13,350+ users:

### All Teachers (150 users)
```
Password: Teacher@123
Email Format: firstname.lastnameN@globaledu.com

Examples:
- james.smith0@globaledu.com
- mary.johnson1@globaledu.com
- john.williams2@globaledu.com
```

### All Students (13,200 users)
```
Password: Student@123
Email Format: firstname.lastname.STUXXXXXX@student.globaledu.com

Examples:
- james.smith.STU000001@student.globaledu.com
- mary.johnson.STU000002@student.globaledu.com
- john.williams.STU000003@student.globaledu.com
```

### All Parents (if created)
```
Password: Parent@123
Email Format: firstname.lastname.parent@globaledu.com
```

---

## 🔍 How to Find User Emails

### Method 1: Using Tinker (Recommended)

```bash
cd backend
php artisan tinker
```

Then run these commands:

```php
// Get 10 teacher emails
DB::table('users')->where('role', 'Teacher')->limit(10)->get(['id', 'first_name', 'last_name', 'email']);

// Get 10 student emails  
DB::table('users')->where('role', 'Student')->limit(10)->get(['id', 'first_name', 'last_name', 'email']);

// Search for specific user
DB::table('users')->where('email', 'like', '%james%')->first();

// Get user by name
DB::table('users')->where('first_name', 'James')->where('last_name', 'Smith')->first();

// Get all users from a branch
DB::table('users')->where('branch_id', 1)->where('role', 'Teacher')->get(['email']);
```

### Method 2: Direct Database Query

```sql
-- Get teachers
SELECT id, first_name, last_name, email, role 
FROM users 
WHERE role = 'Teacher' 
LIMIT 10;

-- Get students  
SELECT id, first_name, last_name, email, role 
FROM users 
WHERE role = 'Student' 
LIMIT 10;

-- Search by name
SELECT id, CONCAT(first_name, ' ', last_name) as name, email, role
FROM users
WHERE first_name LIKE '%James%' OR last_name LIKE '%Smith%';
```

### Method 3: API Endpoint (If logged in as Admin)

```bash
# Get all users
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:8001/api/users

# Get teachers only
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:8001/api/users?role=Teacher

# Get students only
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:8001/api/users?role=Student
```

---

## 🛡️ Password Policy

All seeded passwords follow this pattern:

- **Minimum Length**: 8 characters
- **Uppercase**: ✅ At least 1 (e.g., 'T' in Teacher@123)
- **Lowercase**: ✅ At least 1 (e.g., 'eacher' in Teacher@123)
- **Number**: ✅ At least 1 (e.g., '123')
- **Special Character**: ✅ At least 1 (e.g., '@')

### Production Recommendation
⚠️ **IMPORTANT**: Change all default passwords before deploying to production!

```php
// In Laravel Tinker:
$user = User::find(1);
$user->password = Hash::make('NewSecurePassword@2024');
$user->save();
```

---

## 🚀 Quick Login Guide

### Step 1: Start the Backend
```bash
cd backend
php artisan serve --port=8001
```

### Step 2: Start the Frontend
```bash
cd MySchool
ng serve -o
```

### Step 3: Access the Application
```
URL: http://localhost:4200
```

### Step 4: Login
```
Use any credentials from above
Example: admin@myschool.com / Admin@123
```

---

## 📊 User Statistics (After Full Seeding)

```
Role          | Count  | Password
------------- | ------ | --------------
SuperAdmin    | 1      | Admin@123
BranchAdmin   | 1      | Manager@123
Teachers      | 150+   | Teacher@123
Students      | 13,200+| Student@123
Parents       | 5      | Parent@123
------------- | ------ | --------------
TOTAL         | 13,357 |
```

---

## 🔄 Reset Password via Command

### For a Specific User:
```bash
php artisan tinker
```

```php
$user = User::where('email', 'admin@myschool.com')->first();
$user->password = Hash::make('NewPassword@123');
$user->save();
```

### For All Teachers:
```php
User::where('role', 'Teacher')->update([
    'password' => Hash::make('NewTeacherPass@123')
]);
```

### For All Students:
```php
User::where('role', 'Student')->update([
    'password' => Hash::make('NewStudentPass@123')
]);
```

---

## 🎓 Branch-wise User Distribution

After running comprehensive seeder:

```
Branch 1 (HQ - New York):
  Teachers:  25
  Students:  ~2,200

Branch 2 (Downtown - New York):
  Teachers:  25
  Students:  ~2,200

Branch 3 (Westside - Los Angeles):
  Teachers:  25
  Students:  ~2,200

Branch 4 (Lakeside - Chicago):
  Teachers:  25
  Students:  ~2,200

Branch 5 (Sunrise - Houston):
  Teachers:  25
  Students:  ~2,200

Branch 6 (Valley View - Phoenix):
  Teachers:  25
  Students:  ~2,200
```

---

## 📱 Testing Different Roles

### As SuperAdmin (Full Access)
```
Login: admin@myschool.com / Admin@123
Can: Manage all branches, users, settings
```

### As Branch Admin (Branch Access)
```
Login: manager@myschool.com / Manager@123
Can: Manage specific branch data
```

### As Teacher (Class Access)
```
Login: Any teacher email / Teacher@123
Can: View/edit class data, mark attendance, enter grades
```

### As Student (Self Access)
```
Login: Any student email / Student@123
Can: View own attendance, grades, assignments
```

### As Parent (Children Access)
```
Login: parent@myschool.com / Parent@123
Can: View children's records, pay fees
```

---

## 🔐 Security Best Practices

### Before Production:

1. **Change all default passwords**
   ```bash
   php artisan tinker
   User::whereIn('email', ['admin@myschool.com', 'manager@myschool.com'])->each(function($user) {
       $user->password = Hash::make('SecurePassword'.rand(1000,9999).'!');
       $user->save();
   });
   ```

2. **Enable 2FA** (if implemented)

3. **Set password expiration policy**

4. **Enforce strong password requirements**

5. **Enable login attempt limits**

6. **Review user access logs**

---

## 📞 Support

If you can't login:
1. Verify backend is running: `http://localhost:8001/api/health`
2. Check browser console for errors (F12)
3. Verify email/password combination
4. Reset password using Tinker if needed

---

**Last Updated**: October 2024  
**System Version**: 1.0  
**Auth System**: Laravel Sanctum (Token-based)

---

## 🎉 Quick Reference Card

```
┌─────────────────────────────────────────────────────┐
│  ADMIN LOGIN                                        │
│  Email: admin@myschool.com                          │
│  Pass:  Admin@123                                   │
├─────────────────────────────────────────────────────┤
│  ALL TEACHERS                                       │
│  Pass:  Teacher@123                                 │
├─────────────────────────────────────────────────────┤
│  ALL STUDENTS                                       │
│  Pass:  Student@123                                 │
└─────────────────────────────────────────────────────┘
```

**Remember**: All seeded accounts use their role name + @123 as password! 🔑

