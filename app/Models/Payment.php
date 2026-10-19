<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 追加面談パックの購入記録。
 */
class Payment extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'meeting_pack_id',
        'status',
        'amount',
        'quantity',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'paid_at',
    ];

    protected $casts = [
        'status' => PaymentStatus::class,
        'amount' => 'integer',
        'quantity' => 'integer',
        'paid_at' => 'datetime',
    ];

    /**
     * 購入者を取得する。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 購入対象の面談パックを取得する。
     *
     * @return BelongsTo<MeetingPack, $this>
     */
    public function meetingPack(): BelongsTo
    {
        return $this->belongsTo(MeetingPack::class);
    }
}
