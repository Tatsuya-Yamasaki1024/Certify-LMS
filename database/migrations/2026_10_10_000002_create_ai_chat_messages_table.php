<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI チャットのユーザー投稿と AI 応答を管理するテーブル。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('conversation_id')
                ->constrained('ai_chat_conversations')
                ->cascadeOnDelete();

            $table->string('role', 20);
            $table->string('status', 20)->default('completed');
            $table->text('content');
            $table->text('error_detail')->nullable();
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};
