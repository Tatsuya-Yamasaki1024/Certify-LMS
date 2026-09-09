<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QaReply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QaReply>
 */
class QaReplyFactory extends Factory
{
    protected $model = QaReply::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'body' => fake()->realText(300),
        ];
    }
}
