<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;

final class StoreAction
{
    /**
     * 受講登録にコーチメモを作成する。
     *
     * @param array{
     *     body: string,
     *     user_id: string
     * } $data
     */
    public function __invoke(Enrollment $enrollment, array $data): EnrollmentNote
    {
        return $enrollment->notes()->create([
            'user_id' => $data['user_id'],
            'body' => $data['body'],
        ]);
    }
}
