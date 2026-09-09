# Route verification script for MySchool API backend
# Tests every GET endpoint with an authenticated token and records status codes
$base = "http://localhost:8000"

function Login($email, $password) {
    try {
        $body = @{ email = $email; password = $password } | ConvertTo-Json
        $r = Invoke-WebRequest -Uri "$base/api/login" -Method POST -Body $body -ContentType "application/json" -UseBasicParsing -TimeoutSec 20
        $j = $r.Content | ConvertFrom-Json
        if ($j.success) { return $j.access_token }
    } catch { }
    return $null
}

function TestEndpoint($name, $path, $token, $method = "GET", $reqBody = $null) {
    $headers = @{ Authorization = "Bearer $token" }
    try {
        $r = Invoke-WebRequest -Uri "$base$path" -Headers $headers -UseBasicParsing -TimeoutSec 30
        $content = $r.Content
        if ($content.Length -gt 80) { $content = $content.Substring(0, 80) + "..." }
        Write-Output ("[$($r.StatusCode)] $method $path => $content")
    } catch {
        $code = "ERR"
        if ($_.Exception.Response -ne $null) { $code = $_.Exception.Response.StatusCode }
        Write-Output ("[$code] $method $path => " + $_.Exception.Message)
    }
}
Write-Output "=== LOGIN: superadmin@school.com ==="
$adminToken = Login "superadmin@school.com" "Admin@123"
Write-Output ("Admin token: " + $(if ($adminToken) { "OK" } else { "FAILED" }))

Write-Output ""
Write-Output "=== PUBLIC ROUTES ==="
try {
    $r = Invoke-WebRequest -Uri "$base/api/health" -UseBasicParsing -TimeoutSec 15
    Write-Output "[$($r.StatusCode)] GET /api/health => $($r.Content)"
} catch { Write-Output "[/api/health ERR]" }

Write-Output ""
Write-Output "=== AUTH ROUTES (admin) ==="
TestEndpoint "me" "/api/me" $adminToken
TestEndpoint "preferences-index" "/api/preferences" $adminToken

Write-Output ""
Write-Output "=== ACADEMIC YEARS ==="
TestEndpoint "academic-years" "/api/academic-years" $adminToken
TestEndpoint "academic-years-current" "/api/academic-years/current" $adminToken

Write-Output ""
Write-Output "=== DASHBOARD ==="
TestEndpoint "dashboard" "/api/dashboard" $adminToken
TestEndpoint "dashboard-stats" "/api/dashboard/stats" $adminToken
TestEndpoint "dashboard-attendance" "/api/dashboard/attendance" $adminToken
TestEndpoint "dashboard-top-performers" "/api/dashboard/top-performers" $adminToken
TestEndpoint "dashboard-low-attendance" "/api/dashboard/low-attendance" $adminToken
TestEndpoint "dashboard-upcoming-exams" "/api/dashboard/upcoming-exams" $adminToken
TestEndpoint "dashboard-student-results" "/api/dashboard/student-results" $adminToken
Write-Output ""
Write-Output "=== BRANCHES ==="
TestEndpoint "branches" "/api/branches" $adminToken
TestEndpoint "branches-accessible" "/api/branches/accessible" $adminToken
TestEndpoint "branches-hierarchy" "/api/branches/hierarchy" $adminToken
TestEndpoint "branches-locations" "/api/branches/locations" $adminToken
TestEndpoint "branches-comparative" "/api/branches/comparative-analytics" $adminToken

Write-Output ""
Write-Output "=== CLASSES / SECTIONS / GRADES ==="
TestEndpoint "classes" "/api/classes" $adminToken
TestEndpoint "classes-grades" "/api/classes/grades" $adminToken
TestEndpoint "classes-sections" "/api/classes/sections" $adminToken
TestEndpoint "grades" "/api/grades" $adminToken
TestEndpoint "sections" "/api/sections" $adminToken
TestEndpoint "departments" "/api/departments" $adminToken
TestEndpoint "subjects" "/api/subjects" $adminToken

Write-Output ""
Write-Output "=== STUDENTS ==="
TestEndpoint "students" "/api/students" $adminToken
TestEndpoint "students-statistics" "/api/students/statistics" $adminToken

Write-Output ""
Write-Output "=== FEES ==="
TestEndpoint "fee-types" "/api/fee-types" $adminToken
TestEndpoint "fee-structures" "/api/fee-structures" $adminToken
TestEndpoint "fee-payments" "/api/fee-payments" $adminToken
TestEndpoint "fee-dues" "/api/fee-dues" $adminToken
TestEndpoint "fee-reports-dues" "/api/fee-reports/dues" $adminToken
TestEndpoint "fee-reports-collection" "/api/fee-reports/collection" $adminToken

Write-Output ""
Write-Output "=== ATTENDANCE / LEAVES ==="
TestEndpoint "attendance-dashboard" "/api/attendance/dashboard" $adminToken
TestEndpoint "attendance" "/api/attendance" $adminToken
TestEndpoint "leaves" "/api/leaves" $adminToken

Write-Output ""
Write-Output "=== EXAMS ==="
TestEndpoint "exams" "/api/exams" $adminToken
TestEndpoint "exam-terms" "/api/exam-terms" $adminToken
TestEndpoint "exam-schedules" "/api/exam-schedules" $adminToken

Write-Output ""
Write-Output "=== LIBRARY ==="
TestEndpoint "books" "/api/books" $adminToken
TestEndpoint "book-issues-active" "/api/book-issues/active" $adminToken

Write-Output ""
Write-Output "=== TRANSPORT ==="
TestEndpoint "transport-drivers" "/api/transport-drivers" $adminToken
TestEndpoint "vehicles" "/api/vehicles" $adminToken
TestEndpoint "transport-routes" "/api/transport-routes" $adminToken
Write-Output ""
Write-Output "=== ACCOUNTS / TRANSACTIONS / INVOICES ==="
TestEndpoint "accounts" "/api/accounts" $adminToken
TestEndpoint "accounts-dashboard" "/api/accounts/dashboard" $adminToken
TestEndpoint "transactions" "/api/transactions" $adminToken
TestEndpoint "invoices" "/api/invoices" $adminToken
TestEndpoint "invoices-stats" "/api/invoices/stats" $adminToken

Write-Output ""
Write-Output "=== HOLIDAYS ==="
TestEndpoint "holidays" "/api/holidays" $adminToken
TestEndpoint "holidays-upcoming" "/api/holidays/upcoming" $adminToken

Write-Output ""
Write-Output "=== USERS / ROLES / PERMISSIONS / MODULES ==="
TestEndpoint "users" "/api/users" $adminToken
TestEndpoint "roles" "/api/roles" $adminToken
TestEndpoint "permissions" "/api/permissions" $adminToken
TestEndpoint "permissions-by-module" "/api/permissions/by-module" $adminToken
TestEndpoint "modules" "/api/modules" $adminToken

Write-Output ""
Write-Output "=== COMMUNICATIONS ==="
TestEndpoint "notifications" "/api/communications/notifications" $adminToken
TestEndpoint "announcements" "/api/communications/announcements" $adminToken
TestEndpoint "circulars" "/api/communications/circulars" $adminToken

Write-Output ""
Write-Output "=== ADMISSIONS ==="
TestEndpoint "admissions" "/api/admissions" $adminToken
TestEndpoint "admissions-dashboard" "/api/admissions/dashboard" $adminToken

Write-Output ""
Write-Output "=== TIMETABLES ==="
TestEndpoint "timetables" "/api/timetables" $adminToken

Write-Output ""
Write-Output "=== GLOBAL SEARCH ==="
TestEndpoint "global-search" "/api/global-search?q=test" $adminToken

Write-Output ""
Write-Output "=== EVENTS ==="
TestEndpoint "events" "/api/events" $adminToken

Write-Output ""
Write-Output "=== STUDENT ACCOUNT TEST ==="
$studentToken = Login "test_00_student@gmail.com" "Admin@123"
if (-not $studentToken) { $studentToken = Login "test_00_student@gmail.com" "Password@123" }
if ($studentToken) {
    Write-Output "Student login OK"
    TestEndpoint "student-me" "/api/me" $studentToken
    TestEndpoint "student-dashboard" "/api/dashboard" $studentToken
    TestEndpoint "student-attendance-overview" "/api/attendance/student/1625/overview" $studentToken
    TestEndpoint "student-fees" "/api/students/1625/fees" $studentToken
    TestEndpoint "student-dues" "/api/fee-dues/student/1625" $studentToken
    TestEndpoint "student-results" "/api/students/1625/results" $studentToken
    TestEndpoint "student-leaves" "/api/leaves/student/1625" $studentToken
    TestEndpoint "student-timetable" "/api/timetables/class/6/A" $studentToken
} else {
    Write-Output "Student login FAILED with both Admin@123 and Password@123"
}