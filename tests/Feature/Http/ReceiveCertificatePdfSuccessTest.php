<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Enums\EnrollmentStatus;
use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiveCertificatePdfSuccessTest extends TestCase
{
    use RefreshDatabase;

    // 受講生が修了証を受け取ると実際のPDFが生成・保存される
    public function test_receiving_certificate_generates_and_stores_actual_pdf(): void
    {
        Storage::fake('private');

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'name' => '山田太郎',
            ]);

        $certification = Certification::factory()
            ->published()
            ->create([
                'name' => 'PHPエンジニア認定資格',
            ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();

        $exam = MockExam::factory()
            ->for($certification)
            ->create([
                'is_published' => true,
            ]);

        MockExamSession::factory()
            ->for($enrollment)
            ->for($exam)
            ->create([
                'pass' => true,
            ]);

        $response = $this->actingAs($student)->post(
            route('enrollments.receiveCertificate', $enrollment),
        );

        $response->assertRedirect();

        $enrollment->refresh();

        $this->assertSame(
            EnrollmentStatus::Passed,
            $enrollment->status,
        );

        $this->assertNotNull($enrollment->passed_at);

        $this->assertDatabaseHas('enrollment_status_logs', [
            'enrollment_id' => $enrollment->id,
            'from_status' => EnrollmentStatus::Learning->value,
            'to_status' => EnrollmentStatus::Passed->value,
            'changed_by_user_id' => $student->id,
            'changed_reason' => '受講生による修了証受領',
        ]);

        $certificate = Certificate::query()
            ->where('enrollment_id', $enrollment->id)
            ->sole();

        $this->assertSame($student->id, $certificate->user_id);

        $this->assertSame(
            $certification->id,
            $certificate->certification_id,
        );

        Storage::disk('private')->assertExists(
            $certificate->pdf_path,
        );

        $pdf = Storage::disk('private')->get(
            $certificate->pdf_path,
        );

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(0, strlen($pdf));
    }
}
