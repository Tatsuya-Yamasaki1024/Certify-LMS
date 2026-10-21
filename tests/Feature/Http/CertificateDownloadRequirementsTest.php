<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateDownloadRequirementsTest extends TestCase
{
    use RefreshDatabase;

    // 未認証ユーザーは修了証をダウンロードできない
    public function test_guest_cannot_download_certificate(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->passed()
            ->create();

        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();

        Storage::disk('private')->put(
            $certificate->pdf_path,
            "%PDF-1.4\nTest certificate\n%%EOF",
        );

        $this->get(route('certificates.download', $certificate))
            ->assertRedirect(route('login'));
    }

    // 修了証テンプレートには必要な証書情報のみを表示する
    public function test_certificate_template_contains_only_required_information(): void
    {
        $student = User::factory()->student()->create([
            'name' => '山田太郎',
        ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->passed()
            ->create();

        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();

        $html = view('certificates.pdf', [
            'certificate' => $certificate->load(['user', 'certification']),
        ])->render();

        $this->assertStringContainsString('修了証', $html);
        $this->assertStringContainsString('山田太郎', $html);
        $this->assertStringContainsString($certificate->certification->name, $html);
        $this->assertStringContainsString('発行日', $html);

        $this->assertStringNotContainsString('資格コード', $html);
        $this->assertStringNotContainsString('試験区分', $html);
    }
}
