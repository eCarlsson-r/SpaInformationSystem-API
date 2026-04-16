<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Branch;
use App\Models\Conflict;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Room;
use App\Models\Session;
use App\Models\Treatment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Unit tests for ConflictController.
 *
 * Validates: Requirements 7.3, 7.4, 7.6, 8.2, 8.3
 */
class ConflictControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Create a manager User with an Employee linked to the given branch. */
    private function createManager(Branch $branch): User
    {
        $user = User::factory()->create(['type' => 'MANAGER']);
        Employee::factory()->create(['user_id' => $user->id, 'branch_id' => $branch->id]);
        return $user;
    }

    /** Create a non-manager staff User. */
    private function createStaff(Branch $branch, string $type = 'CASHIER'): User
    {
        $user = User::factory()->create(['type' => $type]);
        Employee::factory()->create(['user_id' => $user->id, 'branch_id' => $branch->id]);
        return $user;
    }

    /** Create a customer User with a linked Customer record. */
    private function createCustomer(): array
    {
        $user     = User::factory()->create(['type' => 'CUSTOMER']);
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        return [$user, $customer];
    }

    /** Build a full scenario: branch, room, bed, employee, treatment, customer, session. */
    private function buildScenario(?Branch $branch = null): array
    {
        $branch    = $branch ?? Branch::factory()->create();
        $room      = Room::factory()->create(['branch_id' => $branch->id]);
        $bed       = Bed::factory()->create(['room_id' => $room->id]);
        $employee  = Employee::factory()->create(['branch_id' => $branch->id]);
        $treatment = Treatment::factory()->create();
        [$userC, $customer] = $this->createCustomer();

        $session = Session::factory()->create([
            'customer_id'  => $customer->id,
            'employee_id'  => $employee->id,
            'bed_id'       => $bed->id,
            'treatment_id' => $treatment->id,
            'date'         => now()->toDateString(),
            'start'        => '10:00:00',
            'end'          => '11:00:00',
            'status'       => 'waiting',
        ]);

        return compact('branch', 'room', 'bed', 'employee', 'treatment', 'customer', 'userC', 'session');
    }

    /** Create a Conflict record for the given sessions. */
    private function createConflict(Session $booking, Session $conflicting, Branch $branch): Conflict
    {
        return Conflict::create([
            'booking_id'             => $booking->id,
            'conflicting_booking_id' => $conflicting->id,
            'conflict_type'          => 'therapist',
            'detection_timestamp'    => Carbon::now(),
            'resolution_status'      => 'pending',
            'resolution_action'      => null,
            'resolution_timestamp'   => null,
            'alternative_slots'      => [
                [
                    'date'         => now()->addDay()->toDateString(),
                    'startTime'    => '14:00',
                    'endTime'      => '15:00',
                    'therapistId'  => $booking->employee_id,
                    'roomId'       => 'R1',
                ],
            ],
            'branch_id' => $branch->id,
        ]);
    }

    // =========================================================================
    // GET /api/conflicts — returns 403 for non-manager staff
    // Validates: Requirement 8.3
    // =========================================================================

    /**
     * A cashier receives 403 on GET /api/conflicts.
     *
     * Validates: Requirement 8.3
     */
    public function test_get_conflicts_returns_403_for_non_manager_staff(): void
    {
        $branch = Branch::factory()->create();
        $cashier = $this->createStaff($branch, 'CASHIER');

        Sanctum::actingAs($cashier);

        $this->getJson('/api/conflicts')->assertStatus(403);
    }

    /**
     * A therapist receives 403 on GET /api/conflicts.
     *
     * Validates: Requirement 8.3
     */
    public function test_get_conflicts_returns_403_for_therapist(): void
    {
        $branch    = Branch::factory()->create();
        $therapist = $this->createStaff($branch, 'THERAPIST');

        Sanctum::actingAs($therapist);

        $this->getJson('/api/conflicts')->assertStatus(403);
    }

    /**
     * A manager receives 200 and only sees conflicts for their branch.
     *
     * Validates: Requirement 8.3
     */
    public function test_get_conflicts_returns_200_for_manager_with_branch_scoped_results(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $scenarioA = $this->buildScenario($branchA);
        $scenarioB = $this->buildScenario($branchB);

        // Conflict for branch A
        $conflictA = $this->createConflict($scenarioA['session'], $scenarioA['session'], $branchA);

        // Conflict for branch B — should NOT appear for manager of branch A
        $conflictB = $this->createConflict($scenarioB['session'], $scenarioB['session'], $branchB);

        $manager = $this->createManager($branchA);
        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/conflicts');
        $response->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->toArray();
        $this->assertContains($conflictA->id, $ids);
        $this->assertNotContains($conflictB->id, $ids);
    }

    // =========================================================================
    // POST /api/conflicts/{id}/dismiss — persists dismissal and prevents re-display
    // Validates: Requirements 7.4, 8.2
    // =========================================================================

    /**
     * A customer can dismiss a rescheduling suggestion.
     * The conflict record is updated with resolution_action='dismissed'.
     *
     * Validates: Requirements 7.4, 8.2
     */
    public function test_dismiss_persists_dismissal_and_prevents_re_display(): void
    {
        $scenario = $this->buildScenario();
        $conflict = $this->createConflict($scenario['session'], $scenario['session'], $scenario['branch']);

        Sanctum::actingAs($scenario['userC']);

        $response = $this->postJson("/api/conflicts/{$conflict->id}/dismiss");
        $response->assertStatus(200);

        $conflict->refresh();
        $this->assertEquals('dismissed', $conflict->resolution_status);
        $this->assertEquals('dismissed', $conflict->resolution_action);
        $this->assertNotNull($conflict->resolution_timestamp);

        // The conflict should no longer appear in GET /api/conflicts/pending
        $pendingResponse = $this->getJson('/api/conflicts/pending');
        $pendingResponse->assertStatus(200);

        $pendingIds = collect($pendingResponse->json())->pluck('id')->toArray();
        $this->assertNotContains($conflict->id, $pendingIds);
    }

    /**
     * A customer cannot dismiss a conflict belonging to another customer's booking.
     *
     * Validates: Requirement 7.4
     */
    public function test_dismiss_returns_403_for_wrong_customer(): void
    {
        $scenario = $this->buildScenario();
        $conflict = $this->createConflict($scenario['session'], $scenario['session'], $scenario['branch']);

        // Different customer
        [$otherUser] = $this->createCustomer();
        Sanctum::actingAs($otherUser);

        $this->postJson("/api/conflicts/{$conflict->id}/dismiss")->assertStatus(403);
    }

    /**
     * A non-customer (staff) user receives 403 on dismiss.
     *
     * Validates: Requirement 7.4
     */
    public function test_dismiss_returns_403_for_staff(): void
    {
        $branch   = Branch::factory()->create();
        $cashier  = $this->createStaff($branch, 'CASHIER');
        $scenario = $this->buildScenario($branch);
        $conflict = $this->createConflict($scenario['session'], $scenario['session'], $branch);

        Sanctum::actingAs($cashier);

        $this->postJson("/api/conflicts/{$conflict->id}/dismiss")->assertStatus(403);
    }

    // =========================================================================
    // GET /api/conflicts/pending — returns persisted suggestions for offline customer
    // Validates: Requirement 7.6
    // =========================================================================

    /**
     * GET /api/conflicts/pending returns pending suggestions for the authenticated customer.
     *
     * Validates: Requirement 7.6
     */
    public function test_pending_returns_persisted_suggestions_for_offline_customer(): void
    {
        $scenario = $this->buildScenario();
        $conflict = $this->createConflict($scenario['session'], $scenario['session'], $scenario['branch']);

        Sanctum::actingAs($scenario['userC']);

        $response = $this->getJson('/api/conflicts/pending');
        $response->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->toArray();
        $this->assertContains($conflict->id, $ids);
    }

    /**
     * GET /api/conflicts/pending does not return dismissed or accepted conflicts.
     *
     * Validates: Requirement 7.6
     */
    public function test_pending_excludes_dismissed_and_accepted_conflicts(): void
    {
        $scenario = $this->buildScenario();

        // Dismissed conflict
        $dismissed = $this->createConflict($scenario['session'], $scenario['session'], $scenario['branch']);
        $dismissed->update(['resolution_status' => 'dismissed', 'resolution_action' => 'dismissed']);

        // Accepted conflict — create a second session for this
        $session2 = Session::factory()->create([
            'customer_id'  => $scenario['customer']->id,
            'employee_id'  => $scenario['employee']->id,
            'bed_id'       => $scenario['bed']->id,
            'treatment_id' => $scenario['treatment']->id,
            'date'         => now()->toDateString(),
            'start'        => '12:00:00',
            'end'          => '13:00:00',
            'status'       => 'waiting',
        ]);
        $accepted = $this->createConflict($session2, $scenario['session'], $scenario['branch']);
        $accepted->update(['resolution_status' => 'accepted', 'resolution_action' => 'accepted']);

        Sanctum::actingAs($scenario['userC']);

        $response = $this->getJson('/api/conflicts/pending');
        $response->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->toArray();
        $this->assertNotContains($dismissed->id, $ids);
        $this->assertNotContains($accepted->id, $ids);
    }

    /**
     * GET /api/conflicts/pending returns 403 for non-customer users.
     *
     * Validates: Requirement 7.6
     */
    public function test_pending_returns_403_for_staff(): void
    {
        $branch  = Branch::factory()->create();
        $cashier = $this->createStaff($branch, 'CASHIER');

        Sanctum::actingAs($cashier);

        $this->getJson('/api/conflicts/pending')->assertStatus(403);
    }

    /**
     * GET /api/conflicts/pending does not return conflicts belonging to other customers.
     *
     * Validates: Requirement 7.6
     */
    public function test_pending_only_returns_conflicts_for_authenticated_customer(): void
    {
        $scenarioA = $this->buildScenario();
        $scenarioB = $this->buildScenario();

        $conflictA = $this->createConflict($scenarioA['session'], $scenarioA['session'], $scenarioA['branch']);
        $conflictB = $this->createConflict($scenarioB['session'], $scenarioB['session'], $scenarioB['branch']);

        // Authenticate as customer A
        Sanctum::actingAs($scenarioA['userC']);

        $response = $this->getJson('/api/conflicts/pending');
        $response->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->toArray();
        $this->assertContains($conflictA->id, $ids);
        $this->assertNotContains($conflictB->id, $ids);
    }
}
