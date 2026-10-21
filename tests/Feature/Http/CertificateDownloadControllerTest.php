<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateDownloadControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * テスト用の修了証とPDFファイルを作成する。
     */
    private function createCertificate(User $student): Certificate
    {
        $enrollment = Enrollment::factory()
            ->passed()
            ->create([
                'user_id' => $student->id,
            ]);

        return Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();
    }

    /**
     * テスト用PDFファイルをprivate Storageに保存する。
     */
    private function storePdf(Certificate $certificate): void
    {
        Storage::disk('private')->put(
            $certificate->pdf_path,
            "%PDF-1.4\nTest certificate\n%%EOF",
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
    }

    // 本人の修了証をPDFでダウンロードできること
    public function test_student_can_download_own_certificate(): void
    {
        $student = User::factory()->student()->create();
        $certificate = $this->createCertificate($student);

        $this->storePdf($certificate);

        $this->actingAs($student)
            ->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertDownload(basename($certificate->pdf_path));
    }

    // 他人の修了証をダウンロードできないこと
    public function test_student_cannot_download_another_students_certificate(): void
    {
        $student = User::factory()->student()->create();
        $anotherStudent = User::factory()->student()->create();

        $certificate = $this->createCertificate($anotherStudent);

        $this->storePdf($certificate);

        $this->actingAs($student)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }

    // 卒業済みの受講生も退会前であれば修了証をダウンロードできること
    public function test_graduated_student_can_download_own_certificate(): void
    {
        $student = User::factory()
            ->student()
            ->graduated()
            ->create();

        $certificate = $this->createCertificate($student);

        $this->storePdf($certificate);

        $this->actingAs($student)
            ->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertDownload(basename($certificate->pdf_path));
    }

    // 担当資格の修了証をコーチがダウンロードできること
    public function test_coach_can_download_certificate_for_assigned_certification(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();

        $certificate = $this->createCertificate($student);

        CertificationCoachAssignment::factory()->create([
            'certification_id' => $certificate->certification_id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'unassigned_at' => null,
        ]);

        $this->storePdf($certificate);

        $this->actingAs($coach)
            ->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertDownload(basename($certificate->pdf_path));
    }

    // 担当解除済みの資格の修了証をコーチがダウンロードできないこと
    public function test_coach_cannot_download_certificate_for_unassigned_certification(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();

        $certificate = $this->createCertificate($student);

        CertificationCoachAssignment::factory()->unassigned()->create([
            'certification_id' => $certificate->certification_id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
        ]);

        $this->storePdf($certificate);

        $this->actingAs($coach)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }

    // 管理者はすべての修了証をダウンロードできること
    public function test_admin_can_download_any_certificate(): void
    {
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        $certificate = $this->createCertificate($student);

        $this->storePdf($certificate);

        $this->actingAs($admin)
            ->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertDownload(basename($certificate->pdf_path));
    }

    // PDFファイルが保管領域に存在しない場合はダウンロードできないこと
    public function test_download_returns_not_found_when_pdf_file_does_not_exist(): void
    {
        Storage::fake('private');

        $student = User::factory()->create([
            'role' => UserRole::Student,
        ]);

        $certificate = $this->createCertificate($student);

        $this->actingAs($student)
            ->get(route('certificates.download', $certificate))
            ->assertNotFound();

        Storage::disk('private')->assertMissing($certificate->pdf_path);
    }
}
