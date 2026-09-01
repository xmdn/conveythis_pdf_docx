<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Files\DTO\StoredDocument;
use App\Domain\Files\Contracts\DocumentStorage;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class LaravelDocumentStorage implements DocumentStorage
{
    public function __construct(
        private FilesystemManager $filesystems,
        private Config $config,
    ) {}

    public function store(UploadedFile $file): StoredDocument
    {
        $disk = (string) $this->config->get('documents.disk', 'documents');
        $directory = trim((string) $this->config->get('documents.directory', 'uploads'), '/');
        $extension = Str::lower($file->getClientOriginalExtension());
        $filename = Str::uuid().'.'.$extension;
        $checksum = hash_file('sha256', $file->getRealPath());

        if ($checksum === false) {
            throw new RuntimeException('Unable to calculate the uploaded file checksum.');
        }

        $path = $file->storeAs($directory, $filename, ['disk' => $disk]);

        if (! is_string($path)) {
            throw new RuntimeException('Unable to persist the uploaded file.');
        }

        return new StoredDocument(
            originalName: $this->sanitizeOriginalName($file->getClientOriginalName(), $extension),
            disk: $disk,
            path: $path,
            mimeType: $file->getMimeType() ?? 'application/octet-stream',
            extension: $extension,
            sizeBytes: (int) $file->getSize(),
            checksumSha256: $checksum,
        );
    }

    public function delete(string $disk, string $path): void
    {
        $filesystem = $this->filesystems->disk($disk);

        if (! $filesystem->exists($path)) {
            return;
        }

        if (! $filesystem->delete($path)) {
            throw new RuntimeException("Unable to delete document at [{$disk}:{$path}].");
        }
    }

    public function download(string $disk, string $path, string $downloadName): StreamedResponse
    {
        return $this->filesystems->disk($disk)->download($path, $downloadName);
    }

    private function sanitizeOriginalName(string $originalName, string $extension): string
    {
        $basename = basename(str_replace('\\', '/', $originalName));
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/u', '', $basename);

        if (! is_string($sanitized) || trim($sanitized) === '') {
            $sanitized = 'document.'.$extension;
        }

        return Str::limit($sanitized, 255, '');
    }
}
