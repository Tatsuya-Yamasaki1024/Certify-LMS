<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Enrollment;

use App\Contracts\Certificates\CertificatePdfGenerator;
use App\Enums\EnrollmentStatus;
use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamSession;
use App\Models\User;
use App\UseCases\Enrollment\ReceiveCertificateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ReceiveCertificatePdfFailureTest extends TestCase
{
    use RefreshDatabase;

    // PDF生成に失敗した場合、修了処理と修了証発行をロールバックする
    public function test_rolls_back_completion_when_certificate_pdf_generation_fails(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();

        $exam = MockExam::factory()
            ->for($certification)
            ->create(['is_published' => true]);

        MockExamSession::factory()
            ->for($enrollment)
            ->for($exam)
            ->create(['pass' => true]);

        $pdfService = Mockery::mock(CertificatePdfGenerator::class);

        $pdfService->shouldReceive('generate')
            ->once()
            ->with(Mockery::type(Certificate::class))
            ->andReturnUsing(function (Certificate $certificate): void {
                Storage::disk('private')->put(
                    $certificate->pdf_path,
                    '%PDF-partial',
                );

                throw new RuntimeException('PDF生成に失敗しました。');
            });

        $this->app->instance(CertificatePdfGenerator::class, $pdfService);

        $exception = null;

        try {
            app(ReceiveCertificateAction::class)($enrollment);
        } catch (RuntimeException $caughtException) {
            $exception = $caughtException;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);

        $this->assertSame(
            'PDF生成に失敗しました。',
            $exception->getMessage(),
        );

        $this->assertSame(
            EnrollmentStatus::Learning,
            $enrollment->refresh()->status,
        );

        $this->assertNull($enrollment->passed_at);

        $this->assertDatabaseMissing('certificates', [
            'enrollment_id' => $enrollment->id,
        ]);

        $this->assertDatabaseMissing('enrollment_status_logs', [
            'enrollment_id' => $enrollment->id,
            'to_status' => EnrollmentStatus::Passed->value,
        ]);

        $this->assertSame(
            [],
            Storage::disk('private')->allFiles('certificates'),
        );
    }

    // PDF生成に失敗した場合、HTTP 500を返して修了処理をロールバックする
    public function test_returns_500_and_rolls_back_completion_when_certificate_pdf_generation_fails(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();

        $exam = MockExam::factory()
            ->for($certification)
            ->create(['is_published' => true]);

        MockExamSession::factory()
            ->for($enrollment)
            ->for($exam)
            ->create(['pass' => true]);

        $pdfService = Mockery::mock(CertificatePdfGenerator::class);

        $pdfService->shouldReceive('generate')
            ->once()
            ->with(Mockery::type(Certificate::class))
            ->andReturnUsing(function (Certificate $certificate): void {
                Storage::disk('private')->put(
                    $certificate->pdf_path,
                    '%PDF-partial',
                );

                throw new RuntimeException('PDF生成に失敗しました。');
            });

        $this->app->instance(CertificatePdfGenerator::class, $pdfService);

        $response = $this->actingAs($student)->postJson(
            route('enrollments.receiveCertificate', $enrollment),
        );

        $response->assertInternalServerError();

        $this->assertSame(
            EnrollmentStatus::Learning,
            $enrollment->refresh()->status,
        );

        $this->assertNull($enrollment->passed_at);

        $this->assertDatabaseMissing('certificates', [
            'enrollment_id' => $enrollment->id,
        ]);

        $this->assertDatabaseMissing('enrollment_status_logs', [
            'enrollment_id' => $enrollment->id,
            'to_status' => EnrollmentStatus::Passed->value,
        ]);

        $this->assertSame(
            [],
            Storage::disk('private')->allFiles('certificates'),
        );
    }
}
