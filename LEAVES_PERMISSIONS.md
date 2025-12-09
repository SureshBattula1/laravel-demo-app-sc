# Leaves Module Permissions

## Overview
This document describes the permissions created for the Leaves module in the School Management System.

## Module Details
- **Module Name**: Leaves
- **Module Slug**: `leaves`
- **Route**: `/leaves`
- **Icon**: `event_busy`
- **Order**: 16

## Permissions Created

The following permissions have been created for the Leaves module:

| Permission Slug | Action | Description | Controller Method |
|----------------|--------|-------------|-------------------|
| `leaves.view` | view | View leave records (list and details) | `index()`, `show()`, `getStudentLeaves()`, `getTeacherLeaves()` |
| `leaves.create` | create | Create new leave applications | `store()` |
| `leaves.edit` | edit | Edit existing leave records | `update()` |
| `leaves.delete` | delete | Delete leave records | `destroy()` |
| `leaves.approve` | approve | Approve leave applications | `update()` (with status='Approved') |
| `leaves.reject` | reject | Reject leave applications | `update()` (with status='Rejected') |

## Permission Assignment by Role

### Super Admin
- **All permissions**: `leaves.view`, `leaves.create`, `leaves.edit`, `leaves.delete`, `leaves.approve`, `leaves.reject`
- Full access to manage all leave records across all branches

### Branch Admin
- **All permissions**: `leaves.view`, `leaves.create`, `leaves.edit`, `leaves.delete`, `leaves.approve`, `leaves.reject`
- Full access to manage leave records within their branch and descendant branches

### Teacher
- **Permissions**: `leaves.view`, `leaves.create`
- Can view leave records and create their own leave applications
- Cannot approve/reject or edit/delete other users' leaves

### Staff
- **Permissions**: `leaves.view`, `leaves.create`, `leaves.edit`, `leaves.approve`, `leaves.reject`
- Can view, create, edit, approve, and reject leave applications
- Typically handles leave management for students and teachers

### Accountant
- **No specific leaves permissions** (focused on financial modules)
- Can access leaves if they have other roles assigned

### Student
- **Permissions**: `leaves.view`, `leaves.create`
- Can view their own leave records and create leave applications
- Cannot approve/reject or edit/delete leaves

## Controller Endpoints Mapping

| Endpoint | Method | Permission Required | Description |
|----------|--------|---------------------|-------------|
| `/api/leaves` | GET | `leaves.view` | List all leave records (student/teacher) |
| `/api/leaves` | POST | `leaves.create` | Create new leave application |
| `/api/leaves/{id}` | GET | `leaves.view` | Get single leave record |
| `/api/leaves/{id}` | PUT | `leaves.edit` / `leaves.approve` / `leaves.reject` | Update leave (edit/approve/reject) |
| `/api/leaves/{id}` | DELETE | `leaves.delete` | Delete leave record |
| `/api/leaves/student/{studentId}` | GET | `leaves.view` | Get student's leave records |
| `/api/leaves/teacher/{teacherId}` | GET | `leaves.view` | Get teacher's leave records |

## Implementation Notes

1. **Permission Checking**: The permissions are created in the database and can be checked using the `CheckPermission` middleware or the `hasPermission()` method on the User model.

2. **Branch Filtering**: The LeaveController already implements branch filtering using `getAccessibleBranchIds()`, which respects the user's branch access.

3. **Status Updates**: The `update()` method handles both editing leave details and changing status (approve/reject). The permission check should verify:
   - `leaves.edit` for updating leave details (dates, reason, etc.)
   - `leaves.approve` for setting status to 'Approved'
   - `leaves.reject` for setting status to 'Rejected'

4. **Self-Service**: Students and Teachers can create and view their own leaves, but cannot approve/reject them.

## Database Seeder

The permissions are created automatically when running:
```bash
php artisan db:seed --class=DatabaseSeeder
```

Or specifically:
```bash
php artisan db:seed
```

## Next Steps

To implement permission checking in the LeaveController:

1. Add permission middleware to routes in `routes/api.php`:
```php
Route::middleware(['auth:sanctum', 'permission:leaves.view'])->get('leaves', [LeaveController::class, 'index']);
Route::middleware(['auth:sanctum', 'permission:leaves.create'])->post('leaves', [LeaveController::class, 'store']);
// etc.
```

2. Or add permission checks directly in controller methods:
```php
if (!$request->user()->hasPermission('leaves.view', $branchId)) {
    return response()->json(['message' => 'Unauthorized'], 403);
}
```

3. Update the UI to show/hide buttons based on user permissions.


