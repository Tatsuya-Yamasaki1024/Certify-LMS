<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_conversations', function (Blueprint $table) {
            $table->boolean('title_manually_edited')
                ->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_conversations', function (Blueprint $table) {
            $table->dropColumn('title_manually_edited');
        });
    }
};
