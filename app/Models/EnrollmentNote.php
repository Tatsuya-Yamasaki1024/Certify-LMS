<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 受講登録に紐づくコーチメモを表す Model。
 *
 * 作成者と対象の受講登録を保持し、コーチ・管理者のみが閲覧・管理する。
 */
class EnrollmentNote extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'enrollment_id',
        'user_id',
        'body',
    ];

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
