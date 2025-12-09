# Import Module Permissions

## Overview
This document describes the permissions created for the Import module in the School Management System.

## Module Details
- **Module Name**: Import
- **Module Slug**: `import`
- **Route**: `/imports`
- **Icon**: `upload_file`
- **Order**: 17

## Permissions Created

The following permissions have been created for the Import module:

| Permission Slug | Action | Description | Controller Method |
|----------------|--------|-------------|-------------------|
| `import.view` | view | View import modules, history, and preview data | `getModules()`, `history()`, `preview()` |
| `import.upload` | upload | Upload Excel/CSV files for import | `upload()` |
| `import.validate` | validate | Validate imported data before committing | `validate()` |
| `import.commit` | commit | Commit validated data to production | `commit()` |
| `import.cancel` | cancel | Cancel/delete import batches | `cancel()` |
| `import.template` | template | Download Excel templates for import | `downloadTemplate()` |

## Permission Assignment by Role

### Super Admin
- **All permissions**: `import.view`, `import.upload`, `import.validate`, `import.commit`, `import.cancel`, `import.template`
- Full access to manage all import operations across all branches

### Branch Admin
- **All permissions**: `import.view`, `import.upload`, `import.validate`, `import.commit`, `import.cancel`, `import.template`
- Full access to manage import operations within their branch and descendant branches
- Can import students and teachers for their branches

### Teacher
- **Permissions**: `import.view`, `import.template`
- Can view import modules and download templates
- Cannot upload, validate, commit, or cancel imports

### Staff
- **Permissions**: `import.view`, `import.upload`, `import.validate`, `import.commit`, `import.cancel`, `import.template`
- Full access to manage import operations
- Typically handles bulk imports of students and teachers

### Accountant
- **No specific import permissions** (focused on financial modules)
- Can access imports if they have other roles assigned

### Student
- **No import permissions**
- Students cannot perform import operations

## Controller Endpoints Mapping

| Endpoint | Method | Permission Required | Description |
|----------|--------|---------------------|-------------|
| `/api/imports/modules` | GET | `import.view` | Get available import modules |
| `/api/imports/history` | GET | `import.view` | Get import history |
| `/api/imports/template/{entity}` | GET | `import.template` | Download Excel template |
| `/api/imports/{entity}/upload` | POST | `import.upload` | Upload Excel/CSV file |
| `/api/imports/{entity}/validate/{batchId}` | POST | `import.validate` | Validate imported data |
| `/api/imports/{entity}/preview/{batchId}` | GET | `import.view` | Preview validation results |
| `/api/imports/{entity}/commit/{batchId}` | POST | `import.commit` | Commit import to production |
| `/api/imports/{entity}/cancel/{batchId}` | DELETE | `import.cancel` | Cancel import batch |

## Import Workflow

1. **View Modules** (`import.view`) - User views available import modules (Students, Teachers)
2. **Download Template** (`import.template`) - User downloads Excel template
3. **Upload File** (`import.upload`) - User uploads filled Excel/CSV file
4. **Validate Data** (`import.validate`) - System validates uploaded data
5. **Preview Results** (`import.view`) - User previews valid/invalid records
6. **Commit Import** (`import.commit`) - User commits valid records to production
7. **Cancel** (`import.cancel`) - User can cancel import at any stage

## Implementation Notes

1. **Permission Checking**: The permissions are created in the database and can be checked using the `CheckPermission` middleware or the `hasPermission()` method on the User model.

2. **Branch Filtering**: The ImportController should implement branch filtering to ensure users can only import data for their accessible branches.

3. **Rate Limiting**: Import routes are rate-limited (30 requests per minute) to prevent abuse.

4. **File Types**: Supports Excel (.xlsx, .xls) and CSV (.csv) file formats.

5. **Staging Tables**: Uses staging tables (`student_imports`, `teacher_imports`) to validate data before committing to production.

6. **Batch Processing**: Each import operation is tracked with a unique `batch_id` for monitoring and rollback capabilities.

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

To implement permission checking in the ImportController:

1. Add permission middleware to routes in `routes/api.php`:
```php
Route::middleware(['auth:sanctum', 'permission:import.view'])->get('imports/modules', [ImportController::class, 'getModules']);
Route::middleware(['auth:sanctum', 'permission:import.upload'])->post('imports/{entity}/upload', [ImportController::class, 'upload']);
Route::middleware(['auth:sanctum', 'permission:import.validate'])->post('imports/{entity}/validate/{batchId}', [ImportController::class, 'validate']);
Route::middleware(['auth:sanctum', 'permission:import.commit'])->post('imports/{entity}/commit/{batchId}', [ImportController::class, 'commit']);
Route::middleware(['auth:sanctum', 'permission:import.cancel'])->delete('imports/{entity}/cancel/{batchId}', [ImportController::class, 'cancel']);
Route::middleware(['auth:sanctum', 'permission:import.template'])->get('imports/template/{entity}', [ImportController::class, 'downloadTemplate']);
```

2. Or add permission checks directly in controller methods:
```php
if (!$request->user()->hasPermission('import.upload', $branchId)) {
    return response()->json(['message' => 'Unauthorized'], 403);
}
```

3. Update the UI to show/hide buttons based on user permissions.


