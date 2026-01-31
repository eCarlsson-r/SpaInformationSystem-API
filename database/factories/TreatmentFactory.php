<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Treatment;
use App\Models\Category;

class TreatmentFactory extends Factory
{
    protected $model = Treatment::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'description' => $this->faker->sentence,
            'price' => $this->faker->numberBetween(100000, 500000),
            'duration' => $this->faker->numberBetween(30, 120),
            'category_id' => Category::factory(),
            'applicable_days' => 'Mon,Tue,Wed,Thu,Fri,Sat,Sun',
            'applicable_time_start' => '09:00:00',
            'applicable_time_end' => '21:00:00',
            'voucher_normal_quantity' => 1,
            'voucher_purchase_quantity' => 1,
            'minimum_quantity' => 1,
            'room' => json_encode(["VIPSG","VIPCP","STDRM"]),
            'body_img' => $this->faker->imageUrl(),
            'icon_img' => $this->faker->imageUrl()
        ];
    }
}
