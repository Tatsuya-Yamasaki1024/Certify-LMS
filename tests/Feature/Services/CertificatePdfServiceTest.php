<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\CertificatePdfService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CertificatePdfServiceTest extends TestCase
{
    use RefreshDatabase;

    // 修了証PDFを日本語で生成しprivateストレージに保存する
    public function test_generates_and_stores_certificate_pdf(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->inProgress()->create([
            'name' => '山田太郎',
        ]);

        $certification = Certification::factory()->published()->create([
            'name' => 'PHPエンジニア認定資格',
        ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();

        $certificate = Certificate::factory()
            ->for($student)
            ->for($enrollment)
            ->for($certification)
            ->create([
                'pdf_path' => 'certificates/'.$enrollment->id.'.pdf',
                'issued_at' => now(),
            ]);

        app(CertificatePdfService::class)->generate($certificate);

        Storage::disk('private')->assertExists($certificate->pdf_path);

        $pdf = Storage::disk('private')->get($certificate->pdf_path);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(0, strlen($pdf));
    }

    // PDF保存に失敗した場合、例外を送出して不完全なファイルを削除する
    public function test_throws_exception_and_removes_partial_file_when_storage_write_fails(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->inProgress()->create([
            'name' => '山田太郎',
        ]);

        $certification = Certification::factory()->published()->create([
            'name' => 'PHPエンジニア認定資格',
        ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();

        $certificate = Certificate::factory()
            ->for($student)
            ->for($enrollment)
            ->for($certification)
            ->create([
                'pdf_path' => 'certificates/'.$enrollment->id.'.pdf',
                'issued_at' => now(),
            ]);

        $disk = Mockery::mock(
            Filesystem::class,
        );

        $disk->shouldReceive('put')
            ->once()
            ->with(
                $certificate->pdf_path,
                Mockery::type('string'),
            )
            ->andReturn(false);

        $disk->shouldReceive('delete')
            ->once()
            ->with($certificate->pdf_path)
            ->andReturn(true);

        Storage::shouldReceive('disk')
            ->with('private')
            ->andReturn($disk);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('修了証PDFの保存に失敗しました。');

        app(CertificatePdfService::class)->generate($certificate);
    }
}
