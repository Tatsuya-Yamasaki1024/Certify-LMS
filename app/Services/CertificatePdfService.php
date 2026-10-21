<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Certificates\CertificatePdfGenerator;
use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use RuntimeException;
use Throwable;

/**
 * 修了証PDFの生成とprivate Storageへの保存を担当するサービス。
 */
final class CertificatePdfService implements CertificatePdfGenerator
{
    /**
     * 修了証PDFを生成してprivate Storageへ保存する。
     *
     * @throws Throwable PDF生成または保存に失敗した場合
     */
    public function generate(Certificate $certificate): void
    {
        $disk = Storage::disk('private');
        $path = $certificate->pdf_path;

        try {
            $html = view('certificates.pdf', [
                'certificate' => $certificate->loadMissing([
                    'user',
                    'certification',
                ]),
            ])->render();

            $mpdf = new Mpdf([
                'mode' => 'ja',
            ]);

            $mpdf->WriteHTML($html);

            $pdf = $mpdf->Output('', 'S');

            if (! $disk->put($path, $pdf)) {
                throw new RuntimeException('修了証PDFの保存に失敗しました。');
            }
        } catch (Throwable $exception) {
            try {
                $disk->delete($path);
            } catch (Throwable) {
                // 元のPDF生成・保存エラーを維持する。
            }

            throw $exception;
        }
    }
}
