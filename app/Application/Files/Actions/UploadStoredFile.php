<?php

declare(strict_types=1);

namespace App\Application\Files\Actions;

use App\Domain\Files\Contracts\DocumentStorage;
use App\Models\StoredFile;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\UploadedFile;
use Throwable;

final readonly class UploadStoredFile
{
    public function __construct(
        private DocumentStorage $storage,
        private Config $config,
    ) {}

    public function execute(UploadedFile $uploadedFile): StoredFile
    {
        $document = $this->storage->store($uploadedFile);

        try {
            return StoredFile::query()->create([
                'original_name' => $document->originalName,
                'storage_disk' => $document->disk,
                'storage_path' => $document->path,
                'mime_type' => $document->mimeType,
                'extension' => $document->extension,
                'size_bytes' => $document->sizeBytes,
                'checksum_sha256' => $document->checksumSha256,
                'expires_at' => now()->addHours(
                    (int) $this->config->get('documents.retention_hours', 24),
                ),
            ]);
        } catch (Throwable $exception) {
            // Compensate for a database failure so no orphan file remains.
            $this->storage->delete($document->disk, $document->path);

            throw $exception;
        }
    }
}
