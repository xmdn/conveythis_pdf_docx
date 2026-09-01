<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Rules\ValidDocument;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ValidDocumentTest extends TestCase
{
    public function test_pdf_signature_and_mime_are_accepted(): void
    {
        $path = $this->temporaryFile("%PDF-1.4\n%%EOF");
        $file = new UploadedFile($path, 'document.pdf', 'application/pdf', null, true);

        self::assertSame([], $this->validate($file));
    }

    public function test_docx_container_structure_is_accepted(): void
    {
        $path = $this->temporaryFile('');
        $archive = new ZipArchive;
        self::assertTrue($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $archive->addFromString('[Content_Types].xml', '<Types/>');
        $archive->addFromString('word/document.xml', '<document/>');
        $archive->close();

        $file = new UploadedFile(
            $path,
            'document.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true,
        );

        self::assertSame([], $this->validate($file));
    }

    public function test_renamed_zip_is_rejected(): void
    {
        $path = $this->temporaryFile('');
        $archive = new ZipArchive;
        self::assertTrue($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $archive->addFromString('unrelated.txt', 'not a Word document');
        $archive->close();

        $file = new UploadedFile($path, 'fake.docx', 'application/zip', null, true);

        self::assertNotSame([], $this->validate($file));
    }

    /** @return list<string> */
    private function validate(UploadedFile $file): array
    {
        $errors = [];

        (new ValidDocument)->validate(
            'document',
            $file,
            function (string $message) use (&$errors): void {
                $errors[] = $message;
            },
        );

        return $errors;
    }

    private function temporaryFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'document-rule-');
        self::assertIsString($path);
        file_put_contents($path, $contents);

        return $path;
    }
}
