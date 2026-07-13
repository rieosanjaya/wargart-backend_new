<?php

namespace Database\Factories;

use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResidentFactory extends Factory
{
    protected $model = Resident::class;

    public function definition(): array
    {
        return [
            'nik' => fake()->unique()->numerify('################'),
            'full_name' => fake()->name(),
            'birth_place' => fake()->city(),
            'birth_date' => fake()->dateTimeBetween('-70 years', '-17 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['MALE', 'FEMALE']),
            'address' => fake()->address(),
            'nationality' => 'Indonesia',
            'is_active' => true,
        ];
    }
}
