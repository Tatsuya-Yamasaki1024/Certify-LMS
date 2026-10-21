<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 修了証PDFのダウンロードを担当するコントローラー。
 */
class CertificateDownloadController extends Controller
{
    /**
     * 修了証PDFを添付ファイルとして返す。
     *
     * @throws NotFoundHttpException PDFファイルが存在しない場合
     */
    public function __invoke(Certificate $certificate): StreamedResponse
    {
        $this->authorize('download', $certificate);

        $disk = Storage::disk('private');

        if (! $disk->exists($certificate->pdf_path)) {
            throw new NotFoundHttpException('修了証PDFが見つかりません。');
        }

        return $disk->download(
            $certificate->pdf_path,
            basename($certificate->pdf_path),
            ['Content-Type' => 'application/pdf'],
        );
    }
}
