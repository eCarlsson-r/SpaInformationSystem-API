<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Journal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JournalRecord>
 */
class JournalRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'journal_id' => Journal::factory(),
            'account_id' => Account::factory(),
            'debit' => $this->faker->randomNumber(7),
            'credit' => $this->faker->randomNumber(7),
            'description' => $this->faker->sentence(),
        ];
    }
}
