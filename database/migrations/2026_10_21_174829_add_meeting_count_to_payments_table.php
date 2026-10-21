<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedSmallInteger('meeting_count')->nullable()->after('quantity');
        });

        DB::table('payments')
            ->join('meeting_packs', 'payments.meeting_pack_id', '=', 'meeting_packs.id')
            ->update([
                'payments.meeting_count' => DB::raw('meeting_packs.meeting_count'),
            ]);

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedSmallInteger('meeting_count')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('meeting_count');
        });
    }
};
