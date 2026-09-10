<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QaReply extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'qa_thread_id',
        'user_id',
        'body',
    ];

    /**
     * 返信先の質問。
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(QaThread::class, 'qa_thread_id');
    }

    /**
     * 返信を投稿したユーザー。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
