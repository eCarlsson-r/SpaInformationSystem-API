<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Feedback;
use App\Models\Room;
use App\Models\Session;
use App\Models\Treatment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Unit tests for SentimentController.
 *
 * Validates: Requirements 11.1, 11.3, 11.4
 */
class SentimentControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Create a manager User with an Employee linked to the given branch. */
    private function createManager(Branch $branch): User
    {
        $user = User::factory()->create(['type' => 'MANAGER']);
        Employee::factory()->create([
            'user_id'   => $user->id,
            'branch_id' => $branch->id,
        ]);
        return $user;
    }

    /** Create a non-manager staff User of the given type. */
    private function createStaff(Branch $branch, string $type = 'CASHIER'): User
    {
        $user = User::factory()->create(['type' => $type]);
        Employee::factory()->create([
            'user_id'   => $user->id,
            'branch_id' => $branch->id,
        ]);
        return $user;
    }

    /** Create a customer User with a linked Customer record. */
    private function createCustomer(): array
    {
        $user     = User::factory()->create(['type' => 'CUSTOMER']);
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        return [$user, $customer];
    }

    /**
     * Create a completed Session for the given customer, employee, and treatment.
     */
    private function createSession(int $customerId, int $employeeId, int $treatmentId, int $bedId): Session
    {
        return Session::factory()->create([
            'customer_id'  => $customerId,
            'employee_id'  => $employeeId,
            'treatment_id' => $treatmentId,
            'bed_id'       => $bedId,
            'date'         => now()->toDateString(),
            'start'        => '10:00:00',
            'end'          => '11:00:00',
            'status'       => 'completed',
        ]);
    }

    /**
     * Create a completed-analysis Feedback record for the given session and customer,
     * submitted N days ago.
     */
    private function createFeedback(
        int $sessionId,
        int $customerId,
        int $daysAgo = 0,
        string $label = 'positive',
        float $score = 0.8
    ): Feedback {
        return Feedback::create([
            'session_id'       => $sessionId,
            'customer_id'      => $customerId,
            'rating'           => 5,
            'comment'          => 'Great service!',
            'sentiment_score'  => $score,
            'sentiment_label'  => $label,
            'analysis_status'  => 'completed',
            'analysis_attempts'=> 1,
            'submitted_at'     => Carbon::now()->subDays($daysAgo)->toDateTimeString(),
            'analyzed_at'      => Carbon::now()->subDays($daysAgo)->toDateTimeString(),
        ]);
    }

    /**
     * Build a full set of related records (branch, room, bed, employee, treatment, customer, session)
     * and return them as an array.
     */
    private function buildScenario(?Branch $branch = null): array
    {
        $branch    = $branch ?? Branch::factory()->create();
        $room      = Room::factory()->create(['branch_id' => $branch->id]);
        $bed       = Bed::factory()->create(['room_id' => $room->id]);
        $employee  = Employee::factory()->create(['branch_id' => $branch->id]);
        $treatment = Treatment::factory()->create();
        [, $customer] = $this->createCustomer();

        $session = $this->createSession($customer->id, $employee->id, $treatment->id, $bed->id);

        return compact('branch', 'room', 'bed', 'employee', 'treatment', 'customer', 'session');
    }

    // =========================================================================
    // 403 — non-manager staff (Requirement 11.1)
    // =========================================================================

    /**
     * A cashier receives 403 on GET /api/ai/sentiment/dashboard.
     *
     * Validates: Requirement 11.1
     */
    public function test_dashboard_returns_403_for_cashier(): void
    {
        $branch = Branch::factory()->create();
        $cashier = $this->createStaff($branch, 'CASHIER');

        Sanctum::actingAs($cashier);

        $this->getJson('/api/ai/sentiment/dashboard')->assertStatus(403);
    }

    /**
     * A therapist receives 403 on GET /api/ai/sentiment/dashboard.
     *
     * Validates: Requirement 11.1
     */
    public function test_dashboard_returns_403_for_therapist(): void
    {
        $branch   = Branch::factory()->create();
        $therapist = $this->createStaff($branch, 'THERAPIST');

        Sanctum::actingAs($therapist);

        $this->getJson('/api/ai/sentiment/dashboard')->assertStatus(403);
    }

    /**
     * A cashier receives 403 on GET /api/ai/sentiment/summary.
     *
     * Validates: Requirement 11.1
     */
    public function test_summary_returns_403_for_cashier(): void
    {
        $branch  = Branch::factory()->create();
        $cashier = $this->createStaff($branch, 'CASHIER');

        Sanctum::actingAs($cashier);

        $this->getJson('/api/ai/sentiment/summary')->assertStatus(403);
    }

    /**
     * A therapist receives 403 on GET /api/ai/sentiment/summary.
     *
     * Validates: Requirement 11.1
     */
    public function test_summary_returns_403_for_therapist(): void
    {
        $branch    = Branch::factory()->create();
        $therapist = $this->createStaff($branch, 'THERAPIST');

        Sanctum::actingAs($therapist);

        $this->getJson('/api/ai/sentiment/summary')->assertStatus(403);
    }

    /**
     * A manager receives 200 on GET /api/ai/sentiment/dashboard.
     *
     * Validates: Requirement 11.1
     */
    public function test_dashboard_returns_200_for_manager(): void
    {
        $branch  = Branch::factory()->create();
        $manager = $this->createManager($branch);

        Sanctum::actingAs($manager);

        $this->getJson('/api/ai/sentiment/dashboard')->assertStatus(200);
    }

    // =========================================================================
    // Filter by branch_id (Requirement 11.3)
    // =========================================================================

    /**
     * Filtering by branch_id returns only feedback records from that branch.
     *
     * Validates: Requirement 11.3
     */
    public function test_filter_by_branch_id_returns_only_matching_records(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $scenarioA = $this->buildScenario($branchA);
        $scenarioB = $this->buildScenario($branchB);

        // Feedback for branch A
        $this->createFeedback($scenarioA['session']->id, $scenarioA['customer']->id, 0, 'positive', 0.9);

        // Feedback for branch B — should NOT appear when filtering by branch A
        $this->createFeedback($scenarioB['session']->id, $scenarioB['customer']->id, 0, 'negative', -0.5);

        $manager = $this->createManager($branchA);
        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/ai/sentiment/dashboard?branch_id={$branchA->id}");
        $response->assertStatus(200);

        $data = $response->json();

        // Branch A has 1 positive feedback, branch B has 1 negative
        // If filtering works, label distribution should show 1 positive and 0 negative
        $this->assertEquals(1, $data['labelDistribution']['positive']);
        $this->assertEquals(0, $data['labelDistribution']['negative']);
    }

    // =========================================================================
    // Filter by treatment_id (Requirement 11.3)
    // =========================================================================

    /**
     * Filtering by treatment_id returns only feedback records for that treatment.
     *
     * Validates: Requirement 11.3
     */
    public function test_filter_by_treatment_id_returns_only_matching_records(): void
    {
        $branch     = Branch::factory()->create();
        $room       = Room::factory()->create(['branch_id' => $branch->id]);
        $bed        = Bed::factory()->create(['room_id' => $room->id]);
        $employee   = Employee::factory()->create(['branch_id' => $branch->id]);
        $treatmentA = Treatment::factory()->create();
        $treatmentB = Treatment::factory()->create();

        [, $customerA] = $this->createCustomer();
        [, $customerB] = $this->createCustomer();

        $sessionA = $this->createSession($customerA->id, $employee->id, $treatmentA->id, $bed->id);
        $sessionB = $this->createSession($customerB->id, $employee->id, $treatmentB->id, $bed->id);

        // Positive feedback for treatment A
        $this->createFeedback($sessionA->id, $customerA->id, 0, 'positive', 0.9);
        // Negative feedback for treatment B — should NOT appear when filtering by treatment A
        $this->createFeedback($sessionB->id, $customerB->id, 0, 'negative', -0.7);

        $manager = $this->createManager($branch);
        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/ai/sentiment/dashboard?treatment_id={$treatmentA->id}");
        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals(1, $data['labelDistribution']['positive']);
        $this->assertEquals(0, $data['labelDistribution']['negative']);
    }

    // =========================================================================
    // Filter by therapist_id (Requirement 11.3)
    // =========================================================================

    /**
     * Filtering by therapist_id returns only feedback records for that therapist.
     *
     * Validates: Requirement 11.3
     */
    public function test_filter_by_therapist_id_returns_only_matching_records(): void
    {
        $branch    = Branch::factory()->create();
        $room      = Room::factory()->create(['branch_id' => $branch->id]);
        $bed       = Bed::factory()->create(['room_id' => $room->id]);
        $employeeA = Employee::factory()->create(['branch_id' => $branch->id]);
        $employeeB = Employee::factory()->create(['branch_id' => $branch->id]);
        $treatment = Treatment::factory()->create();

        [, $customerA] = $this->createCustomer();
        [, $customerB] = $this->createCustomer();

        $sessionA = $this->createSession($customerA->id, $employeeA->id, $treatment->id, $bed->id);
        $sessionB = $this->createSession($customerB->id, $employeeB->id, $treatment->id, $bed->id);

        // Positive feedback for therapist A
        $this->createFeedback($sessionA->id, $customerA->id, 0, 'positive', 0.8);
        // Negative feedback for therapist B — should NOT appear when filtering by therapist A
        $this->createFeedback($sessionB->id, $customerB->id, 0, 'negative', -0.6);

        $manager = $this->createManager($branch);
        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/ai/sentiment/dashboard?therapist_id={$employeeA->id}");
        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals(1, $data['labelDistribution']['positive']);
        $this->assertEquals(0, $data['labelDistribution']['negative']);
    }

    // =========================================================================
    // Time-series date range for each period option (Requirement 11.4)
    // =========================================================================

    /**
     * Period=7 returns a time-series with exactly 7 data points.
     *
     * Validates: Requirement 11.4
     */
    public function test_time_series_has_7_data_points_for_period_7(): void
    {
        $branch  = Branch::factory()->create();
        $manager = $this->createManager($branch);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/ai/sentiment/dashboard?period=7');
        $response->assertStatus(200);

        $timeSeries = $response->json('timeSeries');
        $this->assertCount(7, $timeSeries);
    }

    /**
     * Period=30 returns a time-series with exactly 30 data points.
     *
     * Validates: Requirement 11.4
     */
    public function test_time_series_has_30_data_points_for_period_30(): void
    {
        $branch  = Branch::factory()->create();
        $manager = $this->createManager($branch);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/ai/sentiment/dashboard?period=30');
        $response->assertStatus(200);

        $timeSeries = $response->json('timeSeries');
        $this->assertCount(30, $timeSeries);
    }

    /**
     * Period=90 returns a time-series with exactly 90 data points.
     *
     * Validates: Requirement 11.4
     */
    public function test_time_series_has_90_data_points_for_period_90(): void
    {
        $branch  = Branch::factory()->create();
        $manager = $this->createManager($branch);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/ai/sentiment/dashboard?period=90');
        $response->assertStatus(200);

        $timeSeries = $response->json('timeSeries');
        $this->assertCount(90, $timeSeries);
    }

    /**
     * Default period (no param) returns 30 data points.
     *
     * Validates: Requirement 11.4
     */
    public function test_time_series_defaults_to_30_data_points_when_no_period_given(): void
    {
        $branch  = Branch::factory()->create();
        $manager = $this->createManager($branch);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/ai/sentiment/dashboard');
        $response->assertStatus(200);

        $timeSeries = $response->json('timeSeries');
        $this->assertCount(30, $timeSeries);
    }

    /**
     * Time-series dates span the correct range: from (today - period + 1) to today.
     *
     * Validates: Requirement 11.4
     */
    public function test_time_series_covers_correct_date_range_for_period_7(): void
    {
        $branch  = Branch::factory()->create();
        $manager = $this->createManager($branch);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/ai/sentiment/dashboard?period=7');
        $response->assertStatus(200);

        $timeSeries = $response->json('timeSeries');

        $expectedStart = Carbon::now()->subDays(6)->toDateString();
        $expectedEnd   = Carbon::now()->toDateString();

        $this->assertEquals($expectedStart, $timeSeries[0]['date']);
        $this->assertEquals($expectedEnd,   $timeSeries[6]['date']);
    }

    /**
     * Feedback submitted outside the period window is excluded from time-series.
     *
     * Validates: Requirement 11.4
     */
    public function test_time_series_excludes_feedback_outside_period_window(): void
    {
        $branch   = Branch::factory()->create();
        $scenario = $this->buildScenario($branch);

        // Feedback from 100 days ago — outside any period window
        $this->createFeedback($scenario['session']->id, $scenario['customer']->id, 100, 'positive', 0.9);

        $manager = $this->createManager($branch);
        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/ai/sentiment/dashboard?period=90');
        $response->assertStatus(200);

        $timeSeries = $response->json('timeSeries');

        // All data points should have averageScore of 0.0 since the only feedback is outside the window
        foreach ($timeSeries as $point) {
            $this->assertEquals(0.0, $point['averageScore'], "Expected 0.0 for date {$point['date']} but got {$point['averageScore']}");
        }
    }
}
