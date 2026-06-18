<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase, WithFaker;

    /**
     * Helper method to create an authenticated user
     */
    protected function createAuthenticatedUser($role = 'BranchAdmin', $attributes = [])
    {
        $branch = $attributes['branch_id'] ?? null;
        if (!$branch) {
            $branch = $this->createBranch();
            $attributes['branch_id'] = $branch->id;
        }

        $user = \App\Models\User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branch->id ?? $branch,
        ], $attributes));

        return $user;
    }

    /**
     * Helper method to create a branch for testing
     */
    protected function createBranch($attributes = [])
    {
        // Check if BranchFactory exists, if not create branch manually
        if (class_exists(\Database\Factories\BranchFactory::class)) {
            return \App\Models\Branch::factory()->create($attributes);
        }

        // Fallback: Create branch manually
        return \App\Models\Branch::create(array_merge([
            'name' => $this->faker->company(),
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'branch_type' => 'School',
            'is_active' => true,
            'status' => 'Active',
        ], $attributes));
    }

    /**
     * Helper method to make authenticated API request
     */
    protected function actingAsUser($user = null, $role = 'BranchAdmin')
    {
        $user = $user ?? $this->createAuthenticatedUser($role);
        return $this->actingAs($user, 'sanctum');
    }

    /**
     * Helper to create and authenticate as SuperAdmin
     */
    protected function actingAsSuperAdmin()
    {
        return $this->actingAsUser(null, 'SuperAdmin');
    }

    /**
     * Helper to assert successful JSON response
     */
    protected function assertSuccessResponse($response, $statusCode = 200)
    {
        $response->assertStatus($statusCode);
        $response->assertJsonStructure([
            'success',
        ]);
        $this->assertTrue($response->json('success'), 'Response should have success: true');
        return $response;
    }

    /**
     * Helper to assert validation error response
     */
    protected function assertValidationError($response, $field = null)
    {
        $response->assertStatus(422);
        $response->assertJsonStructure([
            'success',
            'errors',
        ]);
        $this->assertFalse($response->json('success'), 'Response should have success: false');
        
        if ($field) {
            $this->assertArrayHasKey($field, $response->json('errors'), "Validation errors should contain field: {$field}");
        }
        
        return $response;
    }
}
