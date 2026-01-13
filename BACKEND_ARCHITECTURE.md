# Backend Architecture Documentation

## Table of Contents

1. [Overview](#overview)
2. [Technology Stack](#technology-stack)
3. [Project Structure](#project-structure)
4. [Architecture Patterns](#architecture-patterns)
5. [Authentication & Authorization](#authentication--authorization)
6. [API Design](#api-design)
7. [Database Architecture](#database-architecture)
8. [Service Layer](#service-layer)
9. [Middleware & Security](#middleware--security)
10. [File Management](#file-management)
11. [Export & Import System](#export--import-system)
12. [Performance Optimizations](#performance-optimizations)
13. [Error Handling](#error-handling)
14. [Testing Strategy](#testing-strategy)

---

## Overview

The backend is a **Laravel 12** RESTful API application that serves as the core business logic layer for a comprehensive School Management System. It follows **MVC (Model-View-Controller)** architecture with additional service layer abstraction for complex business logic.

### Key Characteristics

-   **Framework**: Laravel 12 (PHP 8.2+)
-   **API Type**: RESTful JSON API
-   **Authentication**: Laravel Sanctum (Token-based)
-   **Database**: MySQL (with SQLite for development)
-   **Architecture**: Layered Architecture (Controller → Service → Model)

---

## Technology Stack

### Core Framework

-   **Laravel Framework**: ^12.0
-   **PHP Version**: ^8.2
-   **Laravel Sanctum**: ^4.2 (API Authentication)

### Key Packages

-   **barryvdh/laravel-dompdf**: ^3.1 (PDF Generation)
-   **maatwebsite/excel**: ^3.1 (Excel Import/Export)
-   **laravel/tinker**: ^2.10.1 (REPL for debugging)

### Development Tools

-   **PHPUnit**: ^11.5.3 (Testing)
-   **Laravel Pint**: ^1.24 (Code Style)
-   **Laravel Pail**: ^1.2.2 (Log Viewer)

---

## Project Structure

```
laravel-demo-app-sc/
├── app/
│   ├── Console/Commands/          # Artisan Commands
│   ├── Exceptions/                # Exception Handlers
│   ├── Exports/                   # Excel/CSV Export Classes
│   ├── Http/
│   │   ├── Controllers/           # API Controllers (46 files)
│   │   ├── Middleware/            # Custom Middleware (7 files)
│   │   ├── Requests/              # Form Request Validation
│   │   ├── Resources/             # API Resources (Transformers)
│   │   └── Traits/                # Reusable Traits
│   ├── Mail/                      # Email Templates
│   ├── Models/                    # Eloquent Models (57 files)
│   ├── Observers/                 # Model Observers
│   ├── Providers/                 # Service Providers
│   └── Services/                  # Business Logic Services (14 files)
├── bootstrap/                     # Application Bootstrap
├── config/                        # Configuration Files
├── database/
│   ├── migrations/                # Database Migrations (43 files)
│   ├── seeders/                   # Database Seeders (22 files)
│   └── factories/                 # Model Factories
├── routes/
│   ├── api.php                    # API Routes
│   └── web.php                    # Web Routes
├── storage/                       # File Storage
└── tests/                         # Test Suite
```

---

## Architecture Patterns

### 1. **Layered Architecture**

```
┌─────────────────────────────────────┐
│      API Routes (routes/api.php)    │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│   Controllers (HTTP Layer)          │
│   - Request Validation              │
│   - Response Formatting             │
│   - Error Handling                  │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│   Services (Business Logic Layer)   │
│   - Complex Business Rules          │
│   - Data Processing                 │
│   - External Integrations           │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│   Models (Data Access Layer)        │
│   - Eloquent ORM                    │
│   - Relationships                   │
│   - Query Scopes                    │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│   Database (MySQL/SQLite)           │
└─────────────────────────────────────┘
```

### 2. **Service Layer Pattern**

Business logic is extracted into dedicated service classes:

-   `StudentService` - Student management logic
-   `AttendanceService` - Attendance calculations
-   `FeeService` - Fee calculations and processing
-   `BranchService` - Multi-branch operations
-   `ExportService` - Data export logic
-   `ImportService` - Data import validation

### 3. **Repository Pattern (Partial)**

Models act as repositories with query scopes and relationships, providing a clean interface for data access.

### 4. **Observer Pattern**

Model Observers for automatic actions:

-   `StudentObserver` - Handles student lifecycle events
-   `TeacherObserver` - Handles teacher lifecycle events

---

## Authentication & Authorization

### Authentication System

#### **Laravel Sanctum Token-Based Authentication**

1. **Registration Flow**

    - Email/Phone validation
    - Password strength requirements (uppercase, lowercase, number, special char)
    - Role assignment (SuperAdmin, BranchAdmin, Teacher, Student, Parent, Staff)
    - Branch assignment

2. **Login Flow**

    - Supports both email and phone login
    - Rate limiting (5 attempts per 5 minutes)
    - Token generation (30-day expiry)
    - Last login tracking

3. **Token Management**
    - Stored in `personal_access_tokens` table
    - Bearer token authentication
    - Automatic token expiration

### Authorization System

#### **Role-Based Access Control (RBAC)**

**Roles Hierarchy:**

1. **SuperAdmin** - Full system access, all branches
2. **BranchAdmin** - Branch management, descendant branches access
3. **Teacher** - Class/subject management, attendance, grades
4. **Student** - Personal data access
5. **Parent** - Children's data access
6. **Staff** - Limited administrative access

#### **Permission-Based Access Control (PBAC)**

**Permission Structure:**

-   Module-based permissions (e.g., `students.view`, `students.create`, `students.edit`)
-   Granular control per action
-   Branch-scoped permissions
-   User-level and role-level permissions

#### **Middleware Stack**

1. **`auth:sanctum`** - Verifies authentication token
2. **`throttle:180,1`** - Rate limiting (180 requests/minute)
3. **`CheckPermission`** - Permission verification
4. **`CheckRole`** - Role verification
5. **`BranchAccessMiddleware`** - Branch access control
6. **`EnforceActiveBranch`** - Active branch enforcement

#### **Multi-Branch Access Control**

-   **SuperAdmin**: Access to all branches
-   **BranchAdmin**: Access to assigned branch + all descendant branches
-   **Other Roles**: Access only to assigned branch

---

## API Design

### API Structure

**Base URL**: `http://localhost:8000/api`

### Route Organization

Routes are organized by resource/module:

```php
// Public Routes
POST /api/register
POST /api/login
POST /api/forgot-password
POST /api/reset-password
GET  /api/health

// Protected Routes (auth:sanctum middleware)
GET    /api/me
POST   /api/logout
PUT    /api/profile

// Resource Routes
GET    /api/students
POST   /api/students
GET    /api/students/{id}
PUT    /api/students/{id}
DELETE /api/students/{id}
```

### API Response Format

**Success Response:**

```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... },
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 100,
    "last_page": 10
  }
}
```

**Error Response:**

```json
{
    "success": false,
    "message": "Error message",
    "errors": {
        "field": ["Error details"]
    }
}
```

### Rate Limiting

-   **General API**: 180 requests per minute (3 per second)
-   **Import Endpoints**: 30 requests per minute
-   **Upload Endpoints**: 60 requests per minute
-   **Login**: 5 attempts per 5 minutes

### API Modules

1. **Authentication** (`/api/auth/*`)
2. **Branches** (`/api/branches/*`)
3. **Students** (`/api/students/*`)
4. **Teachers** (`/api/teachers/*`)
5. **Classes & Sections** (`/api/classes/*`, `/api/sections/*`)
6. **Subjects** (`/api/subjects/*`)
7. **Attendance** (`/api/attendance/*`)
8. **Exams** (`/api/exams/*`, `/api/exam-terms/*`, `/api/exam-schedules/*`)
9. **Fees** (`/api/fees/*`, `/api/fee-dues/*`, `/api/fee-reports/*`)
10. **Accounts** (`/api/accounts/*`, `/api/transactions/*`)
11. **Invoices** (`/api/invoices/*`)
12. **Holidays** (`/api/holidays/*`)
13. **Leaves** (`/api/leaves/*`)
14. **Library** (`/api/books/*`)
15. **Transport** (`/api/transport-routes/*`)
16. **Events** (`/api/events/*`)
17. **Timetable** (`/api/timetables/*`)
18. **Dashboard** (`/api/dashboard/*`)
19. **Settings** (`/api/users/*`, `/api/roles/*`, `/api/permissions/*`)
20. **Imports** (`/api/imports/*`)
21. **Uploads** (`/api/uploads/*`)
22. **Admissions** (`/api/admissions/*`)
23. **Communications** (`/api/communications/*`)

---

## Database Architecture

### Database System

-   **Production**: MySQL
-   **Development**: SQLite
-   **Connection**: Configurable via `.env`

### Database Design Principles

1. **Normalization**: 3NF (Third Normal Form)
2. **Soft Deletes**: `deleted_at` timestamp for most tables
3. **Timestamps**: `created_at`, `updated_at` on all tables
4. **Indexes**: Strategic indexes for performance
5. **Foreign Keys**: Referential integrity

### Key Tables

#### **Core Tables**

-   `users` - User accounts and authentication
-   `branches` - Multi-branch hierarchy (self-referencing)
-   `roles` - Role definitions
-   `permissions` - Permission definitions
-   `modules` - System modules

#### **Academic Tables**

-   `grades` - Grade levels
-   `sections` - Class sections
-   `subjects` - Subject catalog
-   `departments` - Department organization
-   `students` - Student records
-   `teachers` - Teacher records
-   `section_subjects` - Section-subject assignments

#### **Attendance & Leave Tables**

-   `attendance` - Daily attendance records
-   `student_leaves` - Student leave applications
-   `teacher_leaves` - Teacher leave applications

#### **Examination Tables**

-   `exam_terms` - Examination terms
-   `exams` - Exam definitions
-   `exam_schedules` - Exam scheduling
-   `exam_marks` - Student marks
-   `exam_results` - Calculated results

#### **Fee Management Tables**

-   `fee_types` - Fee type definitions
-   `fee_structures` - Fee structure templates
-   `fee_payments` - Payment records
-   `fee_dues` - Outstanding dues
-   `fee_audit_logs` - Fee transaction audit

#### **Accounts & Finance Tables**

-   `account_categories` - Income/Expense categories
-   `transactions` - Financial transactions
-   `invoices` - Invoice generation
-   `invoice_items` - Invoice line items

#### **Other Modules**

-   `holidays` - Holiday calendar
-   `events` - School events
-   `books` - Library catalog
-   `book_issues` - Book issue records
-   `transport_routes` - Transport routes
-   `vehicles` - Vehicle management
-   `timetables` - Class timetables
-   `admission_applications` - Admission process
-   `notifications` - System notifications
-   `announcements` - Announcements
-   `circulars` - Circulars

### Relationships

**One-to-Many:**

-   User → Students/Teachers
-   Branch → Users
-   Grade → Sections
-   Section → Students
-   Subject → SectionSubjects

**Many-to-Many:**

-   Users ↔ Roles (via `role_user`)
-   Roles ↔ Permissions (via `permission_role`)
-   Users ↔ Permissions (via `permission_user`)

**Self-Referencing:**

-   Branches (parent_id for hierarchy)

### Database Migrations

-   **43 Migration Files** covering all tables
-   **Performance Indexes** migration for optimization
-   **Soft Delete** migrations for data retention

---

## Service Layer

### Purpose

Services encapsulate complex business logic, keeping controllers thin and models focused on data access.

### Key Services

#### **1. StudentService**

-   Student CRUD operations
-   Student promotion logic
-   Student search and filtering
-   Performance-optimized queries

#### **2. AttendanceService**

-   Attendance marking
-   Attendance calculations
-   Attendance reports
-   Bulk attendance operations

#### **3. FeeDuesService**

-   Fee due calculations
-   Aging analysis
-   Payment application
-   Fee carry-forward logic

#### **4. FeeCarryForwardService**

-   Fee carry-forward during promotion
-   Dues calculation
-   Payment reconciliation

#### **5. BranchService**

-   Branch hierarchy management
-   Branch access control
-   Branch analytics
-   Multi-branch operations

#### **6. ExportService**

-   Data export orchestration
-   Format selection (Excel, PDF, CSV)
-   Large dataset handling

#### **7. ImportService**

-   Data import validation
-   Batch processing
-   Error reporting
-   Template generation

#### **8. AuditService**

-   Activity logging
-   Change tracking
-   Audit trail generation

#### **9. FeeNotificationService**

-   Fee reminder notifications
-   Payment confirmations
-   Due date alerts

#### **10. StudentPromotionService**

-   Bulk student promotion
-   Grade/section updates
-   Fee structure updates
-   History tracking

#### **11. GlobalUploadService**

-   File upload handling
-   File validation
-   Storage management
-   Attachment management

#### **12. PdfExportService**

-   PDF generation
-   Template rendering
-   Document formatting

#### **13. CsvExportService**

-   CSV generation
-   Data formatting
-   Large file handling

#### **14. FeeReportService**

-   Fee report generation
-   Analytics and statistics
-   Custom report queries

---

## Middleware & Security

### Authentication Middleware

**`auth:sanctum`**

-   Validates Bearer token
-   Attaches authenticated user to request
-   Returns 401 if token invalid/expired

### Authorization Middleware

**`CheckPermission`**

-   Verifies user has required permission
-   Branch-scoped permission checking
-   Returns 403 if unauthorized

**`CheckRole`**

-   Verifies user has required role
-   Role hierarchy enforcement
-   Returns 403 if unauthorized

**`BranchAccessMiddleware`**

-   Validates branch access rights
-   Enforces branch hierarchy rules
-   Returns 403 if branch access denied

**`EnforceActiveBranch`**

-   Ensures branch is active
-   Prevents operations on inactive branches
-   Returns 403 if branch inactive

### Security Features

1. **Password Hashing**: bcrypt via Laravel Hash
2. **CSRF Protection**: Enabled for web routes
3. **SQL Injection Prevention**: Eloquent ORM parameter binding
4. **XSS Protection**: Input sanitization
5. **Rate Limiting**: Prevents abuse
6. **Input Validation**: Form Request classes
7. **Token Expiration**: 30-day token expiry
8. **Soft Deletes**: Data retention without permanent deletion

### CORS Configuration

Configured in `config/cors.php` for cross-origin requests from frontend.

---

## File Management

### Storage System

**Laravel Filesystem:**

-   **Local Storage**: `storage/app/public`
-   **Public Access**: `public/storage` (symlink)
-   **Private Storage**: `storage/app/private`

### File Upload Endpoints

**`/api/uploads`** - Global upload handler

-   Single file upload
-   Multiple file upload
-   File validation
-   File info retrieval
-   File existence check

**`/api/attachments`** - Universal attachment system

-   Save attachments to any module
-   Get module attachments
-   Download attachments
-   Delete attachments

### Supported File Types

-   Images: JPG, PNG, GIF, WebP
-   Documents: PDF, DOC, DOCX, XLS, XLSX
-   Archives: ZIP, RAR

### File Size Limits

-   Configurable via `php.ini` and Laravel config
-   Default: 10MB per file

---

## Export & Import System

### Export System

#### **Export Formats**

1. **Excel** (XLSX) - Via Maatwebsite Excel
2. **PDF** - Via DomPDF
3. **CSV** - Native PHP

#### **Export Classes**

Located in `app/Exports/`:

-   `StudentsExport`
-   `TeachersExport`
-   `BranchesExport`
-   `GradesExport`
-   `SectionsExport`
-   `HolidaysExport`
-   `TransactionsExport`
-   `AttendanceExport`

#### **Export Endpoints**

```
GET /api/students/export
GET /api/teachers/export
GET /api/branches/export
GET /api/grades/export
GET /api/sections/export
GET /api/holidays/export
GET /api/transactions/export
GET /api/attendance/export
```

### Import System

#### **Import Workflow**

1. **Upload** - File upload to server
2. **Validate** - Data validation and error detection
3. **Preview** - Show validation results
4. **Commit** - Import validated data
5. **Cancel** - Discard import batch

#### **Import Endpoints**

```
POST /api/imports/{entity}/upload
POST /api/imports/{entity}/validate/{batchId}
GET  /api/imports/{entity}/preview/{batchId}
POST /api/imports/{entity}/commit/{batchId}
DELETE /api/imports/{entity}/cancel/{batchId}
GET  /api/imports/template/{entity}
GET  /api/imports/history
```

#### **Supported Entities**

-   Students
-   Teachers
-   Subjects
-   Grades
-   Sections

#### **Import Features**

-   Batch processing
-   Error reporting
-   Validation rules
-   Template download
-   Import history tracking

---

## Performance Optimizations

### Database Optimizations

1. **Indexes**: Strategic indexes on frequently queried columns

    - Foreign keys
    - Search fields (name, email, phone)
    - Status fields
    - Date fields

2. **Query Optimization**:

    - Eager loading relationships (`with()`)
    - Select specific columns
    - Query caching
    - Pagination for large datasets

3. **Database Indexes Migration**:
    - `2024_performance_indexes.php`
    - `2025_11_07_000000_add_additional_performance_indexes.php`
    - `2025_11_09_000002_add_performance_indexes_to_transactions.php`

### Application Optimizations

1. **Caching**:

    - Permission caching
    - Branch hierarchy caching
    - Query result caching

2. **Lazy Loading Prevention**:

    - Eager loading relationships
    - Query optimization

3. **Pagination**:

    - All list endpoints use pagination
    - Configurable page size

4. **Rate Limiting**:
    - Prevents API abuse
    - Ensures fair resource usage

### Performance Monitoring

-   Query logging in development
-   Laravel Telescope (if installed)
-   Performance indexes tracking

---

## Error Handling

### Exception Handling

**Global Exception Handler**: `app/Exceptions/Handler.php`

### Error Response Format

```json
{
    "success": false,
    "message": "Error message",
    "errors": {
        "field": ["Validation error"]
    }
}
```

### HTTP Status Codes

-   **200** - Success
-   **201** - Created
-   **400** - Bad Request
-   **401** - Unauthorized
-   **403** - Forbidden
-   **404** - Not Found
-   **422** - Validation Error
-   **429** - Too Many Requests
-   **500** - Server Error

### Validation Errors

Laravel Form Request classes handle validation:

-   Automatic error formatting
-   Field-specific error messages
-   Custom validation rules

### Logging

-   **Log Channel**: `storage/logs/laravel.log`
-   **Log Levels**: debug, info, warning, error
-   **Context**: Request details, user info, stack traces

---

## Testing Strategy

### Test Structure

```
tests/
├── Feature/          # Integration tests
├── Unit/             # Unit tests
└── TestCase.php      # Base test class
```

### Testing Tools

-   **PHPUnit**: ^11.5.3
-   **Laravel Testing**: Built-in test helpers
-   **Database Transactions**: Isolated test data

### Test Types

1. **Feature Tests**: API endpoint testing
2. **Unit Tests**: Service and model testing
3. **Integration Tests**: Multi-component testing

### Test Execution

```bash
php artisan test
php artisan test --filter=StudentTest
php artisan test --coverage
```

---

## Deployment Considerations

### Environment Configuration

**`.env` File Variables:**

-   `APP_ENV` - Environment (local, staging, production)
-   `APP_DEBUG` - Debug mode
-   `DB_CONNECTION` - Database type
-   `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
-   `SANCTUM_STATEFUL_DOMAINS` - Frontend domain
-   `SESSION_DRIVER` - Session storage
-   `CACHE_DRIVER` - Cache driver
-   `QUEUE_CONNECTION` - Queue driver

### Server Requirements

-   PHP 8.2+
-   MySQL 5.7+ or MariaDB 10.3+
-   Composer
-   Node.js & NPM (for asset compilation)

### Deployment Steps

1. Clone repository
2. Install dependencies: `composer install`
3. Copy `.env.example` to `.env`
4. Generate app key: `php artisan key:generate`
5. Run migrations: `php artisan migrate`
6. Seed database (optional): `php artisan db:seed`
7. Create storage symlink: `php artisan storage:link`
8. Optimize: `php artisan config:cache`, `php artisan route:cache`
9. Set permissions: `chmod -R 775 storage bootstrap/cache`

---

## API Documentation

### Authentication Endpoints

| Method | Endpoint               | Description            | Auth Required |
| ------ | ---------------------- | ---------------------- | ------------- |
| POST   | `/api/register`        | User registration      | No            |
| POST   | `/api/login`           | User login             | No            |
| POST   | `/api/forgot-password` | Password reset request | No            |
| POST   | `/api/reset-password`  | Password reset         | No            |
| GET    | `/api/me`              | Get current user       | Yes           |
| POST   | `/api/logout`          | Logout user            | Yes           |
| PUT    | `/api/profile`         | Update profile         | Yes           |
| PUT    | `/api/change-password` | Change password        | Yes           |

### Student Endpoints

| Method | Endpoint                | Description      | Auth Required |
| ------ | ----------------------- | ---------------- | ------------- |
| GET    | `/api/students`         | List students    | Yes           |
| POST   | `/api/students`         | Create student   | Yes           |
| GET    | `/api/students/{id}`    | Get student      | Yes           |
| PUT    | `/api/students/{id}`    | Update student   | Yes           |
| DELETE | `/api/students/{id}`    | Delete student   | Yes           |
| POST   | `/api/students/promote` | Promote students | Yes           |
| GET    | `/api/students/export`  | Export students  | Yes           |

### Teacher Endpoints

| Method | Endpoint               | Description     | Auth Required |
| ------ | ---------------------- | --------------- | ------------- |
| GET    | `/api/teachers`        | List teachers   | Yes           |
| POST   | `/api/teachers`        | Create teacher  | Yes           |
| GET    | `/api/teachers/{id}`   | Get teacher     | Yes           |
| PUT    | `/api/teachers/{id}`   | Update teacher  | Yes           |
| DELETE | `/api/teachers/{id}`   | Delete teacher  | Yes           |
| GET    | `/api/teachers/export` | Export teachers | Yes           |

### Branch Endpoints

| Method | Endpoint                   | Description           | Auth Required |
| ------ | -------------------------- | --------------------- | ------------- |
| GET    | `/api/branches`            | List branches         | Yes           |
| POST   | `/api/branches`            | Create branch         | Yes           |
| GET    | `/api/branches/{id}`       | Get branch            | Yes           |
| PUT    | `/api/branches/{id}`       | Update branch         | Yes           |
| DELETE | `/api/branches/{id}`       | Delete branch         | Yes           |
| GET    | `/api/branches/hierarchy`  | Get branch hierarchy  | Yes           |
| GET    | `/api/branches/{id}/stats` | Get branch statistics | Yes           |

---

## Best Practices

### Code Organization

-   Controllers are thin (delegate to services)
-   Business logic in services
-   Models handle data access only
-   Reusable code in traits

### Security

-   Always validate input
-   Use parameterized queries (Eloquent)
-   Sanitize user input
-   Implement rate limiting
-   Use HTTPS in production

### Performance

-   Use eager loading for relationships
-   Implement pagination
-   Add database indexes
-   Cache frequently accessed data
-   Optimize queries

### Maintainability

-   Follow PSR-12 coding standards
-   Write comprehensive comments
-   Use meaningful variable names
-   Keep functions small and focused
-   Write tests

---

## Conclusion

This backend architecture provides a robust, scalable, and maintainable foundation for the School Management System. It follows Laravel best practices, implements proper security measures, and provides comprehensive APIs for frontend consumption.

The separation of concerns (Controllers → Services → Models) ensures code maintainability, while the service layer allows for complex business logic to be tested and reused independently.


