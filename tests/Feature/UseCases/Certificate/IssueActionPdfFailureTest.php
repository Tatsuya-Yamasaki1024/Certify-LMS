<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Certificate;

use App\Contracts\Certificates\CertificatePdfGenerator;
use App\Enums\EnrollmentStatus;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\EnrollmentStatusLog;
use App\UseCases\Certificate\IssueAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class IssueActionPdfFailureTest extends TestCase
{
    use RefreshDatabase;

    // PDF生成失敗時に修了証レコードがロールバックされること
    public function test_rolls_back_certificate_when_pdf_generation_fails(): void
    {
        Storage::fake('private');

        $enrollment = Enrollment::factory()->passed()->create();

        $certificateCountBefore = Certificate::query()
            ->where('enrollment_id', $enrollment->id)
            ->count();

        $this->mock(CertificatePdfGenerator::class)
            ->shouldReceive('generate')
            ->once()
            ->andThrow(new RuntimeException('PDF generation failed.'));

        $action = $this->app->make(IssueAction::class);

        try {
            $action($enrollment);

            $this->fail('PDF生成失敗時には例外が送出される必要があります。');
        } catch (RuntimeException $exception) {
            $this->assertSame('PDF generation failed.', $exception->getMessage());
        }

        $this->assertSame(
            $certificateCountBefore,
            Certificate::query()
                ->where('enrollment_id', $enrollment->id)
                ->count()
        );

        $this->assertSame(
            EnrollmentStatus::Passed,
            $enrollment->fresh()->status
        );

        Storage::disk('private')->assertDirectoryEmpty('certificates');
    }

    // PDF生成失敗時にEnrollmentのステータス変更履歴が増えないこと
    public function test_does_not_create_status_log_when_pdf_generation_fails(): void
    {
        Storage::fake('private');

        $enrollment = Enrollment::factory()->passed()->create();

        $logCountBefore = EnrollmentStatusLog::query()
            ->where('enrollment_id', $enrollment->id)
            ->count();

        $this->mock(CertificatePdfGenerator::class)
            ->shouldReceive('generate')
            ->once()
            ->andThrow(new RuntimeException('PDF generation failed.'));

        $action = $this->app->make(IssueAction::class);

        try {
            $action($enrollment);

            $this->fail('PDF生成失敗時には例外が送出される必要があります。');
        } catch (RuntimeException $exception) {
            $this->assertSame('PDF generation failed.', $exception->getMessage());
        }

        $this->assertSame(
            $logCountBefore,
            EnrollmentStatusLog::query()
                ->where('enrollment_id', $enrollment->id)
                ->count()
        );
    }
}
