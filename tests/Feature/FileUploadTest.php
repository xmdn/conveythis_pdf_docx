<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\StoredFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

final class FileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');
        config()->set('documents.disk', 'documents');
        config()->set('documents.retention_hours', 24);
        config()->set('documents.max_size_mb', 10);
    }

    public function test_pdf_can_be_uploaded_asynchronously(): void
    {
        Carbon::setTestNow('2026-08-31 12:00:00');
        $document = UploadedFile::fake()->createWithContent(
            'quarterly-report.pdf',
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF",
        );

        $response = $this->postJson(route('files.store'), [
            'document' => $document,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'quarterly-report.pdf')
            ->assertJsonPath('data.extension', 'pdf');

        $storedFile = StoredFile::query()->sole();

        self::assertSame('documents', $storedFile->storage_disk);
        self::assertSame('2026-09-01 12:00:00', $storedFile->expires_at->format('Y-m-d H:i:s'));
        self::assertSame(64, strlen($storedFile->checksum_sha256));
        Storage::disk('documents')->assertExists($storedFile->storage_path);
    }

    public function test_unsupported_file_type_is_rejected(): void
    {
        $response = $this->postJson(route('files.store'), [
            'document' => UploadedFile::fake()->createWithContent('payload.php', '<?php echo 1;'),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');

        self::assertDatabaseCount('stored_files', 0);
    }

    public function test_real_docx_container_can_be_uploaded(): void
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'docx-test-');
        self::assertIsString($temporaryPath);

        $archive = new ZipArchive;
        self::assertTrue($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $archive->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $archive->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>');
        $archive->close();

        $document = new UploadedFile(
            $temporaryPath,
            'proposal.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true,
        );

        $this->postJson(route('files.store'), ['document' => $document])
            ->assertCreated()
            ->assertJsonPath('data.extension', 'docx');

        self::assertDatabaseHas('stored_files', ['original_name' => 'proposal.docx']);
    }

    public function test_file_larger_than_configured_limit_is_rejected(): void
    {
        config()->set('documents.max_size_mb', 1);

        $response = $this->postJson(route('files.store'), [
            'document' => UploadedFile::fake()->create('large.pdf', 1_025, 'application/pdf'),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');
    }
}
