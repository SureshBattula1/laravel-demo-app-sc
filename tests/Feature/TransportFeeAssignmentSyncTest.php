<?php

namespace Tests\Feature;

use App\Models\FeeDue;
use App\Models\RouteStop;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransportFeeAssignmentSyncTest extends TestCase
{
    use RefreshDatabase;

    protected $branch;

    protected $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = $this->createBranch();

        $this->adminUser = User::factory()->create([
            'role' => 'SuperAdmin',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
    }

    public function test_assigning_transport_syncs_to_student_profile_and_fee_dues(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $studentUser = User::factory()->create([
            'role' => 'Student',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'branch_id' => $this->branch->id,
            'school_id' => $this->branch->school_id,
            'admission_number' => 'ADM-2026-001',
            'admission_date' => '2026-04-01',
            'grade' => '1',
            'section' => 'A',
            'academic_year' => '2026-2027',
            'date_of_birth' => '2019-01-01',
            'gender' => 'Male',
            'current_address' => '123 Test Street',
            'city' => 'Test City',
            'state' => 'Test State',
            'pincode' => '123456',
            'country' => 'India',
            'father_name' => 'Father Sharma',
            'father_phone' => '9876543210',
            'mother_name' => 'Mother Sharma',
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_contact_phone' => '5555555555',
            'emergency_contact_relation' => 'Father',
            'transport_required' => false,
        ]);

        $route = TransportRoute::create([
            'branch_id' => $this->branch->id,
            'school_id' => $this->branch->school_id,
            'route_number' => 'R-101',
            'route_name' => 'North City Route',
            'stops' => [],
            'fare' => 12000,
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'branch_id' => $this->branch->id,
            'school_id' => $this->branch->school_id,
            'route_id' => $route->id,
            'vehicle_number' => 'KA-01-AB-1234',
            'vehicle_type' => 'Bus',
            'capacity' => 40,
            'status' => 'Active',
        ]);

        $stop = RouteStop::create([
            'route_id' => $route->id,
            'branch_id' => $this->branch->id,
            'school_id' => $this->branch->school_id,
            'sequence_no' => 1,
            'stop_name' => 'Station Green Plaza',
            'pickup_time' => '07:30:00',
            'drop_time' => '16:00:00',
        ]);

        // 1. Assign student to transport via API
        $payload = [
            'student_id' => $studentUser->id,
            'route_id' => $route->id,
            'vehicle_id' => $vehicle->id,
            'pickup_stop_id' => $stop->id,
            'stop_name' => $stop->stop_name,
            'pickup_time' => '07:30:00',
            'drop_time' => '16:00:00',
            'annual_fee' => 15000,
            'monthly_fee' => 15000,
            'due_date' => '2026-08-15',
            'status' => 'Active',
        ];

        $response = $this->postJson('/api/transport-assignments', $payload);
        $response->assertStatus(201);

        // Assert student profile was synced
        $student->refresh();
        $this->assertEquals(1, $student->transport_required);
        $this->assertEquals('North City Route', $student->transport_route);
        $this->assertEquals('Station Green Plaza', $student->pickup_point);
        $this->assertEquals('KA-01-AB-1234', $student->vehicle_number);
        $this->assertEquals(15000, (float) $student->transport_fee);

        // Assert fee_dues was created with correct due_date
        $due = FeeDue::where('student_id', $student->id)
            ->where('fee_type', 'Transport')
            ->first();

        $this->assertNotNull($due);
        $this->assertEquals(15000, (float) $due->original_amount);
        $this->assertEquals(15000, (float) $due->balance_amount);
        $this->assertEquals(0, (float) $due->paid_amount);
        $this->assertEquals('2026-08-15', $due->due_date->toDateString());

        // 2. Query student fees via API - transport fee should appear in pending_fees
        $feesResponse = $this->getJson("/api/students/{$studentUser->id}/fees");
        $feesResponse->assertStatus(200);

        $pendingFees = collect($feesResponse->json('data.pending_fees'));
        $transportFeeItem = $pendingFees->firstWhere('fee_type', 'Transport Fee');

        $this->assertNotNull($transportFeeItem);
        $this->assertEquals(15000, (float) $transportFeeItem['amount']);
        $this->assertEquals(15000, (float) $transportFeeItem['remaining_amount']);
        $this->assertEquals('2026-08-15', $transportFeeItem['due_date']);
        $this->assertTrue($transportFeeItem['is_transport']);

        // 3. Record a payment against the Transport Fee structure
        $feeStructureId = $transportFeeItem['fee_structure_id'];
        $paymentPayload = [
            'student_id' => $studentUser->id,
            'fee_structure_id' => $feeStructureId,
            'amount_paid' => 5000,
            'total_amount' => 15000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_status' => 'Completed',
            'academic_year' => '2026-2027',
        ];

        $payResponse = $this->postJson('/api/fee-payments', $paymentPayload);
        $payResponse->assertStatus(201);

        // Assert FeeDue was updated with the payment
        $due->refresh();
        $this->assertEquals(5000, (float) $due->paid_amount);
        $this->assertEquals(10000, (float) $due->balance_amount);
        $this->assertEquals('PartiallyPaid', $due->status);

        // Query fees again and verify remaining_amount is updated to 10000
        $updatedFees = $this->getJson("/api/students/{$studentUser->id}/fees");
        $updatedPending = collect($updatedFees->json('data.pending_fees'));
        $updatedTransport = $updatedPending->firstWhere('fee_type', 'Transport Fee');
        $this->assertEquals(10000, (float) $updatedTransport['remaining_amount']);
        $this->assertEquals(5000, (float) $updatedTransport['amount_paid']);

        // 4. Update assignment with a new due date
        $assignmentId = $response->json('data.id');
        $updateResp = $this->putJson("/api/transport-assignments/{$assignmentId}", [
            'due_date' => '2026-09-01',
        ]);
        $updateResp->assertStatus(200);

        $due->refresh();
        $this->assertEquals('2026-09-01', $due->due_date->toDateString());

        // 5. Delete the transport assignment
        $deleteResponse = $this->deleteJson("/api/transport-assignments/{$assignmentId}");
        $deleteResponse->assertStatus(200);

        // Assert student transport profile is reset
        $student->refresh();
        $this->assertEquals(0, $student->transport_required);
        $this->assertNull($student->transport_route);
        $this->assertEquals(0, (float) $student->transport_fee);
    }

    public function test_bulk_assigning_transport_syncs_due_date_to_multiple_students_fee_dues(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $studentUser1 = User::factory()->create([
            'role' => 'Student',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $student1 = Student::create([
            'user_id' => $studentUser1->id,
            'branch_id' => $this->branch->id,
            'school_id' => $this->branch->school_id,
            'admission_number' => 'ADM-BULK-01',
            'admission_date' => '2026-04-01',
            'grade' => '2',
            'section' => 'A',
            'academic_year' => '2026-2027',
            'date_of_birth' => '2018-01-01',
            'gender' => 'Male',
            'current_address' => '123 Street A',
            'city' => 'City A',
            'state' => 'State A',
            'pincode' => '123456',
            'country' => 'India',
            'father_name' => 'Father 1',
            'father_phone' => '9876543211',
            'mother_name' => 'Mother 1',
            'emergency_contact_name' => 'Contact 1',
            'emergency_contact_phone' => '5555555551',
            'emergency_contact_relation' => 'Father',
            'transport_required' => false,
        ]);

        $studentUser2 = User::factory()->create([
            'role' => 'Student',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $student2 = Student::create([
            'user_id' => $studentUser2->id,
            'branch_id' => $this->branch->id,
            'school_id' => $this->branch->school_id,
            'admission_number' => 'ADM-BULK-02',
            'admission_date' => '2026-04-01',
            'grade' => '2',
            'section' => 'A',
            'academic_year' => '2026-2027',
            'date_of_birth' => '2018-02-02',
            'gender' => 'Female',
            'current_address' => '123 Street B',
            'city' => 'City B',
            'state' => 'State B',
            'pincode' => '123456',
            'country' => 'India',
            'father_name' => 'Father 2',
            'father_phone' => '9876543212',
            'mother_name' => 'Mother 2',
            'emergency_contact_name' => 'Contact 2',
            'emergency_contact_phone' => '5555555552',
            'emergency_contact_relation' => 'Mother',
            'transport_required' => false,
        ]);

        $route = TransportRoute::create([
            'branch_id' => $this->branch->id,
            'school_id' => $this->branch->school_id,
            'route_number' => 'R-202',
            'route_name' => 'South Express',
            'stops' => [],
            'fare' => 18000,
            'is_active' => true,
        ]);

        $bulkPayload = [
            'student_ids' => [$studentUser1->id, $studentUser2->id],
            'route_id' => $route->id,
            'annual_fee' => 18000,
            'due_date' => '2026-10-15',
            'status' => 'Active',
        ];

        $bulkResponse = $this->postJson('/api/transport-assignments/bulk', $bulkPayload);
        $bulkResponse->assertStatus(200);
        $bulkResponse->assertJsonPath('assigned_count', 2);

        // Check student 1 fee due
        $due1 = FeeDue::where('student_id', $student1->id)->where('fee_type', 'Transport')->first();
        $this->assertNotNull($due1);
        $this->assertEquals(18000, (float) $due1->original_amount);
        $this->assertEquals('2026-10-15', $due1->due_date->toDateString());

        // Check student 2 fee due
        $due2 = FeeDue::where('student_id', $student2->id)->where('fee_type', 'Transport')->first();
        $this->assertNotNull($due2);
        $this->assertEquals(18000, (float) $due2->original_amount);
        $this->assertEquals('2026-10-15', $due2->due_date->toDateString());
    }
}
