<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;

final class DestroyAction
{
    /**
     * コーチメモを削除する。
     */
    public function __invoke(EnrollmentNote $note): void
    {
        $note->delete();
    }
}
