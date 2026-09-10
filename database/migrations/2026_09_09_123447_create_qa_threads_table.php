<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_threads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUlid('certification_id')
                ->constrained('certifications')
                ->restrictOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->string('status', 20)->default('unresolved');
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['certification_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_threads');
    }
};
