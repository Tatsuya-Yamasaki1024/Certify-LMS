<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * コーチの Google Calendar 連携情報を表す Model。
 *
 * Google OAuth のアクセストークン / リフレッシュトークンと、
 * 連携対象のカレンダー情報を保持する。
 */
class GoogleCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'calendar_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'connected_at',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
    ];

    /**
     * Google Calendar を連携しているコーチ。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
