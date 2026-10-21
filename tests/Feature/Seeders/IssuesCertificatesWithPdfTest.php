<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Contracts\Certificates\CertificatePdfGenerator;
use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\Concerns\IssuesCertificatesWithPdf;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class IssuesCertificatesWithPdfTest extends TestCase
{
    use RefreshDatabase;

    // 新規修了証を発行するとPDFファイルも生成される
    public function test_creates_certificate_and_pdf_file(): void
    {
        Storage::fake('private');

        $enrollment = $this->createPassedEnrollment();

        $certificate = $this->issue($enrollment);

        $this->assertDatabaseHas('certificates', [
            'id' => $certificate->id,
            'enrollment_id' => $enrollment->id,
        ]);

        Storage::disk('private')->assertExists($certificate->pdf_path);

        $this->assertStringStartsWith(
            '%PDF-',
            Storage::disk('private')->get($certificate->pdf_path),
        );
    }

    // 既存の修了証がある場合は重複発行せずPDFも再生成しない
    public function test_does_not_duplicate_existing_certificate(): void
    {
        Storage::fake('private');

        $enrollment = $this->createPassedEnrollment();

        $first = $this->issue($enrollment);
        $second = $this->issue($enrollment);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Certificate::query()->where('enrollment_id', $enrollment->id)->count());
    }

    // PDFが存在しない場合は既存修了証のPDFを再生成する
    public function test_regenerates_missing_pdf(): void
    {
        Storage::fake('private');

        $enrollment = $this->createPassedEnrollment();

        $certificate = $this->issue($enrollment);

        Storage::disk('private')->delete($certificate->pdf_path);

        $this->assertFalse(Storage::disk('private')->exists($certificate->pdf_path));

        $reissued = $this->issue($enrollment);

        $this->assertSame($certificate->id, $reissued->id);

        Storage::disk('private')->assertExists($certificate->pdf_path);
    }

    // PDF生成失敗時は新規修了証の作成をロールバックする
    public function test_rolls_back_new_certificate_when_pdf_generation_fails(): void
    {
        Storage::fake('private');

        $enrollment = $this->createPassedEnrollment();

        $pdfService = Mockery::mock(CertificatePdfGenerator::class);

        $pdfService->shouldReceive('generate')
            ->once()
            ->andThrow(new RuntimeException('PDF generation failed.'));

        $this->app->instance(CertificatePdfGenerator::class, $pdfService);

        try {
            $this->issue($enrollment);
            $this->fail('PDF生成失敗の例外が発生しませんでした。');
        } catch (RuntimeException $exception) {
            $this->assertSame('PDF generation failed.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('certificates', [
            'enrollment_id' => $enrollment->id,
        ]);
    }

    // 既存修了証の発行日時と保存先を変更しない
    public function test_preserves_existing_certificate_metadata(): void
    {
        Storage::fake('private');

        $enrollment = $this->createPassedEnrollment();

        $issuedAt = Carbon::parse('2025-01-15 12:00:00');

        $certificate = $this->issue($enrollment, $issuedAt);

        $originalPath = $certificate->pdf_path;

        $this->issue($enrollment, now());

        $certificate->refresh();

        $this->assertSame($originalPath, $certificate->pdf_path);
        $this->assertTrue($certificate->issued_at->equalTo($issuedAt));
    }

    private function createPassedEnrollment(): Enrollment
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        return Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create([
                'passed_at' => now(),
            ]);
    }

    private function issue(Enrollment $enrollment, ?Carbon $issuedAt = null): Certificate
    {
        $seeder = new class extends Seeder
        {
            use IssuesCertificatesWithPdf;

            public function issue(Enrollment $enrollment, ?Carbon $issuedAt): Certificate
            {
                return $this->issueCertificateWithPdf($enrollment, $issuedAt);
            }
        };

        return $seeder->issue($enrollment, $issuedAt);
    }
}
