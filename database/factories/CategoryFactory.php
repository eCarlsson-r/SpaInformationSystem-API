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
            'id' => $this->faker->randomLetter(),
            'name' => $this->faker->word,
            'description' => $this->faker->sentence,
            'i18n' => 'en',
            'header_img' => 'default.jpg',
            'body_img1' => 'default.jpg',
            'body_img2' => 'default.jpg',
            'body_img3' => 'default.jpg',
        ];
    }
}
