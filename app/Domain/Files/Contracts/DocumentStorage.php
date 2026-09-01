<?php

declare(strict_types=1);

namespace App\Domain\Files\Contracts;

use App\Application\Files\DTO\StoredDocument;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface DocumentStorage
{
    public function store(UploadedFile $file): StoredDocument;

    public function delete(string $disk, string $path): void;

    public function download(string $disk, string $path, string $downloadName): StreamedResponse;
}
