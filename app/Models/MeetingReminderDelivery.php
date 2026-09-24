<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeetingReminderType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 面談リマインダーの送信履歴を表す Model。
 *
 * 同じ面談・ユーザー・リマインダー種別への二重配信を防止するための送信履歴を保持する。
 */
class MeetingReminderDelivery extends Model
{
    /** @use HasFactory<MeetingReminderDeliveryFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'meeting_id',
        'user_id',
        'reminder_type',
    ];

    protected $casts = [
        'reminder_type' => MeetingReminderType::class,
    ];

    /**
     * リマインダー対象の面談を取得する。
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * リマインダーの通知対象ユーザーを取得する。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
