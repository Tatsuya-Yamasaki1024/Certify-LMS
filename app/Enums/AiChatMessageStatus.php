<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * AI チャットメッセージの処理状態を表す Enum。
 */
enum AiChatMessageStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Error = 'error';
}
