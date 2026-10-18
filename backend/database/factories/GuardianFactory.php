<?php

namespace Database\Factories;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'name' => fake()->name(),
            'relationship' => fake()->randomElement(['ayah', 'ibu', 'wali']),
            'phone' => fake()->unique()->numerify('08##########'),
            'is_primary' => true,
            'consent_at' => now(),
        ];
    }

    /**
     * Wali yang belum memberi izin untuk dihubungi.
     */
    public function withoutConsent(): static
    {
        return $this->state(fn (array $attributes) => [
            'consent_at' => null,
        ]);
    }
}
