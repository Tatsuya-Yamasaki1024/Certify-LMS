<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentGoal>
 */
class EnrollmentGoalFactory extends Factory
{
    protected $model = EnrollmentGoal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory()->learning(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'target_date' => now()->addMonth()->toDateString(),
            'achieved_at' => null,
        ];
    }

    /**
     * 達成済みの状態にする。
     */
    public function achieved(): static
    {
        return $this->state(fn () => [
            'achieved_at' => now(),
        ]);
    }

    /**
     * 目標期日を未設定にする。
     */
    public function withoutTargetDate(): static
    {
        return $this->state(fn () => [
            'target_date' => null,
        ]);
    }
}
