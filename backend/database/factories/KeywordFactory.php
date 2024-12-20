<?php

namespace Database\Factories;

use App\Models\Keyword;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class KeywordFactory extends Factory
{
    protected $model = Keyword::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'keyword' => $this->faker->words(3, true),
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'failed']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
