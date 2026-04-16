<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeedbackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'session_id'        => Session::factory(),
            'customer_id'       => Customer::factory(),
            'rating'            => $this->faker->numberBetween(1, 5),
            'comment'           => $this->faker->sentence(),
            'sentiment_score'   => null,
            'sentiment_label'   => null,
            'analysis_status'   => 'pending',
            'analysis_attempts' => 0,
            'submitted_at'      => now(),
            'analyzed_at'       => null,
        ];
    }
}
