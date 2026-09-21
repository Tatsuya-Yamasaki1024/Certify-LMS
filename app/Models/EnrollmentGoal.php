<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 個人学習目標モデル。
 *
 * @property string $id
 * @property string $enrollment_id
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $target_date
 * @property Carbon|null $achieved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Enrollment $enrollment
 */
class EnrollmentGoal extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'enrollment_id',
        'title',
        'description',
        'target_date',
        'achieved_at',
    ];

    protected $casts = [
        'target_date' => 'date',
        'achieved_at' => 'datetime',
    ];

    /**
     * 目標が属する受講登録を取得する。
     *
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * 目標が達成済みか判定する。
     */
    public function isAchieved(): bool
    {
        return $this->achieved_at !== null;
    }

    /**
     * 目標を画面表示用の順序に並べる。
     *
     * 未達成を先頭、目標期日が近い順（期日未設定は末尾）、
     * 同条件では新しく作成した順に並べる。
     */
}
