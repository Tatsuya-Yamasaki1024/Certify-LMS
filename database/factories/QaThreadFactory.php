<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QaThread>
 */
class QaThreadFactory extends Factory
{
    protected $model = QaThread::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(8),
            'body' => fake()->realText(500),
            'status' => QaThreadStatus::Unresolved->value,
            'resolved_at' => null,
        ];
    }
}
