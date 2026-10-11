<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI チャットの個別メッセージを表す Model。
 */
class AiChatMessage extends Model
{
    use HasUlids;

    protected $fillable = [
        'conversation_id',
        'role',
        'status',
        'content',
        'error_detail',
        'model',
        'response_time_ms',
        'input_tokens',
        'output_tokens',
    ];

    protected $casts = [
        'role' => AiChatMessageRole::class,
        'status' => AiChatMessageStatus::class,
        'response_time_ms' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
    ];

    /**
     * メッセージが属する会話を取得する。
     *
     * @return BelongsTo<AiChatConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiChatConversation::class, 'conversation_id');
    }
}
