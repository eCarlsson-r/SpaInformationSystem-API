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
            'header_img' => null,
            'body_img1' => null,
            'body_img2' => null,
            'body_img3' => null,
        ];
    }
}
