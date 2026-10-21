<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\User;
use Database\Seeders\CertificateSeeder;
use Database\Seeders\EnrollmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateSeederPdfIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // EnrollmentSeederで修了済み受講登録の修了証PDFが生成される
    public function test_enrollment_seeder_generates_certificate_pdfs(): void
    {
        Storage::fake('private');

        Certification::factory()->published()->create();

        User::factory()->student()->inProgress()->create([
            'email' => 'student@certify-lms.test',
        ]);

        User::factory()->student()->inProgress()->count(8)->create();

        $this->seed(EnrollmentSeeder::class);

        $certificates = Certificate::query()
            ->with('enrollment')
            ->get();

        $this->assertGreaterThan(0, $certificates->count());

        foreach ($certificates as $certificate) {
            Storage::disk('private')->assertExists($certificate->pdf_path);

            $this->assertStringStartsWith(
                '%PDF-',
                Storage::disk('private')->get($certificate->pdf_path),
            );

            $this->assertTrue(
                $certificate->issued_at->equalTo($certificate->enrollment->passed_at),
            );
        }
    }

    // CertificateSeederで卒業生の修了証PDFが生成される
    public function test_certificate_seeder_generates_graduated_student_pdfs(): void
    {
        Storage::fake('private');

        Certification::factory()->published()->create();

        User::factory()->student()->graduated()->count(3)->create([
            'plan_expires_at' => now()->subDays(30),
        ]);

        $this->seed(CertificateSeeder::class);

        $certificates = Certificate::query()
            ->with('enrollment')
            ->get();

        $this->assertCount(3, $certificates);

        foreach ($certificates as $certificate) {
            Storage::disk('private')->assertExists($certificate->pdf_path);

            $this->assertStringStartsWith(
                '%PDF-',
                Storage::disk('private')->get($certificate->pdf_path),
            );

            $this->assertTrue(
                $certificate->issued_at->equalTo($certificate->enrollment->passed_at),
            );
        }
    }
}
