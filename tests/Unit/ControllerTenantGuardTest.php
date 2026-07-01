<?php

namespace Tests\Unit;

use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\BranchTransferController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\StudentGroupController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Static guard against the "DepartmentController" bug class: a controller method that
 * loads a single tenant-scoped record by ID (show/update/destroy/approve/...) without
 * checking the acting user's access to that record's branch.
 *
 * This matters even for models that use the BelongsToTenant global scope, because that
 * scope is defense-in-depth, not a substitute — a raw query builder call, a
 * withoutGlobalScopes(), or (as with BranchTransfer, which spans two branches and isn't
 * tenant-scoped at the model layer at all) a model that never had the trait will bypass
 * it silently. See api/CLAUDE.md > "New feature checklist" #1.
 *
 * When you add a new controller with single-record actions on tenant data, add it below.
 */
class ControllerTenantGuardTest extends TestCase
{
    /**
     * controller class => action methods that load one record by ID and must call a
     * branch-access guard.
     */
    private const CHECKED_CONTROLLERS = [
        DepartmentController::class => ['show', 'update', 'destroy', 'toggleStatus'],
        StudentGroupController::class => ['show', 'update', 'destroy', 'addMember', 'removeMember'],
        AdmissionController::class => ['show', 'update', 'destroy', 'updateStatus', 'convertToStudent'],
        BranchTransferController::class => ['show', 'approve', 'reject', 'complete', 'cancel'],
    ];

    /** Method-body substrings that count as an explicit branch-access guard. */
    private const GUARD_CALLS = ['canAccessBranch(', 'canTransferBranch(', 'canManageBranch('];

    public static function methodProvider(): array
    {
        $cases = [];
        foreach (self::CHECKED_CONTROLLERS as $controller => $methods) {
            foreach ($methods as $method) {
                $cases["{$controller}::{$method}"] = [$controller, $method];
            }
        }

        return $cases;
    }

    #[DataProvider('methodProvider')]
    public function test_single_record_action_checks_branch_access(string $controller, string $method): void
    {
        $source = $this->methodSource($controller, $method);

        $hasGuard = false;
        foreach (self::GUARD_CALLS as $call) {
            if (str_contains($source, $call)) {
                $hasGuard = true;
                break;
            }
        }

        $this->assertTrue(
            $hasGuard,
            "{$controller}::{$method}() loads a record by ID but never calls a branch-access " .
            'guard (' . implode('/', self::GUARD_CALLS) . '). See api/CLAUDE.md > New feature checklist #1.'
        );
    }

    private function methodSource(string $class, string $method): string
    {
        $reflection = new ReflectionMethod($class, $method);
        $filename = $reflection->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        $lines = file($filename);

        return implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    }
}
