<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Category;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'description' => $this->faker->sentence,
            'i18n' => 'en',
            'header_img' => $this->faker->imageUrl(),
            'body_img1' => $this->faker->imageUrl(),
            'body_img2' => $this->faker->imageUrl(),
            'body_img3' => $this->faker->imageUrl(),
        ];
    }
}
