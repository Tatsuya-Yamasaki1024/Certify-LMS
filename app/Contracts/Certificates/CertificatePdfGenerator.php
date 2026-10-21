<?php

declare(strict_types=1);

namespace App\Contracts\Certificates;

use App\Models\Certificate;
use Throwable;

interface CertificatePdfGenerator
{
    /**
     * 修了証PDFを生成して保存する。
     *
     * @throws Throwable PDF生成または保存に失敗した場合
     */
    public function generate(Certificate $certificate): void;
}
