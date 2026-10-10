<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * キャンセル済み面談を除外して、同一コーチ・同一日時の重複予約を防止する。
     */
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE meetings ADD INDEX meetings_coach_id_index (coach_id)'
        );

        DB::statement(
            'ALTER TABLE meetings DROP INDEX meetings_coach_id_scheduled_at_unique'
        );

        DB::statement(
            "ALTER TABLE meetings
                ADD COLUMN active_scheduled_at DATETIME
                GENERATED ALWAYS AS (
                    CASE
                        WHEN status = 'canceled' THEN NULL
                        ELSE scheduled_at
                    END
                ) STORED"
        );

        DB::statement(
            'ALTER TABLE meetings
                ADD UNIQUE INDEX meetings_coach_active_scheduled_at_unique
                (coach_id, active_scheduled_at)'
        );
    }

    /**
     * 変更前のユニーク制約に戻す。
     */
    public function down(): void
    {
        DB::statement(
            'ALTER TABLE meetings DROP INDEX meetings_coach_active_scheduled_at_unique'
        );

        DB::statement(
            'ALTER TABLE meetings DROP COLUMN active_scheduled_at'
        );

        DB::statement(
            'ALTER TABLE meetings
                ADD UNIQUE INDEX meetings_coach_id_scheduled_at_unique
                (coach_id, scheduled_at)'
        );

        DB::statement(
            'ALTER TABLE meetings DROP INDEX meetings_coach_id_index'
        );
    }
};
