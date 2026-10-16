<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Room;
use App\Models\Session;
use App\Models\Treatment;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_time_window_changes_do_not_reuse_an_ineligible_cached_treatment(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->create();
        $morning = Treatment::factory()->create([
            'applicable_days' => now()->format('D'),
            'applicable_time_start' => '09:00:00',
            'applicable_time_end' => '11:00:00',
        ]);
        $afternoon = Treatment::factory()->create([
            'applicable_days' => now()->format('D'),
            'applicable_time_start' => '12:00:00',
            'applicable_time_end' => '16:00:00',
        ]);
        $service = app(RecommendationService::class);

        $this->assertSame($morning->id, $service->getRecommendations($customer->id, $branch->id)[0]['treatment']->id);

        $this->travelTo(now()->setTime(13, 0));
        $this->assertSame($afternoon->id, $service->getRecommendations($customer->id, $branch->id)[0]['treatment']->id);
    }

    public function test_branch_without_popularity_uses_currently_eligible_treatments(): void
    {
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->create();
        $treatment = Treatment::factory()->create([
            'applicable_days' => now()->format('D'),
            'applicable_time_start' => '00:00:00',
            'applicable_time_end' => '23:59:59',
        ]);

        $recommendations = app(RecommendationService::class)->getRecommendations($customer->id, $branch->id);

        $this->assertSame([$treatment->id], array_map(fn ($item) => $item['treatment']->id, $recommendations));
        $this->assertSame('Available during your selected day and time.', $recommendations[0]['rationale']);
    }

    public function test_completing_a_session_advances_the_recommendation_cache_generation(): void
    {
        Queue::fake();
        $branch = Branch::factory()->create();
        $room = Room::factory()->create(['branch_id' => $branch->id]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);
        $employee = Employee::factory()->create(['branch_id' => $branch->id]);
        $customer = Customer::factory()->create();
        $treatment = Treatment::factory()->create();
        $session = Session::factory()->create([
            'bed_id' => $bed->id,
            'employee_id' => $employee->id,
            'customer_id' => $customer->id,
            'treatment_id' => $treatment->id,
            'status' => 'waiting',
        ]);
        $generationKey = "rec:{$customer->id}:{$branch->id}:generation";
        Cache::put($generationKey, 7, 86400);

        $session->update(['status' => 'completed']);

        $this->assertSame(8, Cache::get($generationKey));
    }
}