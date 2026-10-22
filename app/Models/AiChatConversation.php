<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 受講生の AI チャット会話を表す Model。
 */
class AiChatConversation extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'enrollment_id',
        'section_id',
        'title',
        'title_manually_edited',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'title_manually_edited' => 'boolean',
    ];

    /**
     * 会話の所有者を取得する。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 会話に紐づく受講登録を取得する。
     *
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * 会話の開始元となった教材セクションを取得する。
     *
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * 会話のメッセージを取得する。
     *
     * @return HasMany<AiChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'conversation_id');
    }
}
