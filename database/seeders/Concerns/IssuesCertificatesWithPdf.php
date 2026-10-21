<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use App\Contracts\Certificates\CertificatePdfGenerator;
use App\Models\Certificate;
use App\Models\Enrollment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait IssuesCertificatesWithPdf
{
    /**
     * 修了証レコードを作成し、PDFが存在しない場合は生成する。
     *
     * 既存の修了証がある場合は、発行日時と保存先を維持する。
     */
    private function issueCertificateWithPdf(
        Enrollment $enrollment,
        ?Carbon $issuedAt = null,
    ): Certificate {
        return DB::transaction(function () use ($enrollment, $issuedAt): Certificate {
            $certificate = Certificate::query()
                ->where('enrollment_id', $enrollment->id)
                ->lockForUpdate()
                ->first();

            if ($certificate === null) {
                $certificate = Certificate::factory()
                    ->forEnrollment($enrollment)
                    ->create([
                        'issued_at' => $issuedAt ?? now(),
                    ]);
            }

            if (! Storage::disk('private')->exists($certificate->pdf_path)) {
                app(CertificatePdfGenerator::class)->generate($certificate);
            }

            return $certificate;
        });
    }
}
