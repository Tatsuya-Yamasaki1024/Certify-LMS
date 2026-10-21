<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Certificate;

use App\Models\Enrollment;
use App\UseCases\Certificate\IssueAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IssueActionPdfGenerationTest extends TestCase
{
    use RefreshDatabase;

    // 修了証発行時にPDFファイルが生成され、private Storageに保存されること
    public function test_generates_pdf_file_when_issuing_certificate(): void
    {
        Storage::fake('private');

        $enrollment = Enrollment::factory()->passed()->create();

        $action = $this->app->make(IssueAction::class);
        $certificate = $action($enrollment);

        $this->assertNotNull($certificate->pdf_path);

        Storage::disk('private')->assertExists($certificate->pdf_path);

        $pdf = Storage::disk('private')->get($certificate->pdf_path);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }
}
