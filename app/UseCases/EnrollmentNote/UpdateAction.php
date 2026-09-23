<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;

final class UpdateAction
{
    /**
     * コーチメモを更新する。
     *
     * @param array{
     *     body: string
     * } $data
     */
    public function __invoke(EnrollmentNote $note, array $data): EnrollmentNote
    {
        $note->update([
            'body' => $data['body'],
        ]);

        return $note->refresh();
    }
}
