<?php

declare(strict_types=1);

namespace App\Application\Files\DTO;

final readonly class StoredDocument
{
    public function __construct(
        public string $originalName,
        public string $disk,
        public string $path,
        public string $mimeType,
        public string $extension,
        public int $sizeBytes,
        public string $checksumSha256,
    ) {}
}
