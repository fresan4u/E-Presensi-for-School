<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nis' => fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'status_id' => fn () => StudentStatus::query()
                ->firstOrCreate(['code' => 'aktif'], ['label' => 'Aktif'])
                ->id,
        ];
    }
}
