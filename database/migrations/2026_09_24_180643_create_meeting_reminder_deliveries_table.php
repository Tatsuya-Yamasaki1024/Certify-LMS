<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meeting_reminder_deliveries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')
                ->constrained('meetings')
                ->cascadeOnDelete();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('reminder_type');
            $table->timestamps();

            $table->unique(
                ['meeting_id', 'user_id', 'reminder_type'],
                'meeting_reminder_delivery_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_reminder_deliveries');
    }
};
