<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use ZipArchive;

final class ValidDocument implements ValidationRule
{
    private const PDF_MIME_TYPES = [
        'application/pdf',
        'application/x-pdf',
    ];

    private const DOCX_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
        'application/x-zip-compressed',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('The :attribute must be a valid uploaded file.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        $mimeType = $value->getMimeType() ?? 'application/octet-stream';

        $isValid = match ($extension) {
            'pdf' => $this->isPdf($value, $mimeType),
            'docx' => $this->isDocx($value, $mimeType),
            default => false,
        };

        if (! $isValid) {
            $fail('The :attribute must be a valid PDF or DOCX document.');
        }
    }

    private function isPdf(UploadedFile $file, string $mimeType): bool
    {
        if (! in_array($mimeType, self::PDF_MIME_TYPES, true)) {
            return false;
        }

        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            return fread($handle, 5) === '%PDF-';
        } finally {
            fclose($handle);
        }
    }

    private function isDocx(UploadedFile $file, string $mimeType): bool
    {
        if (! in_array($mimeType, self::DOCX_MIME_TYPES, true)) {
            return false;
        }

        $archive = new ZipArchive;

        if ($archive->open($file->getRealPath()) !== true) {
            return false;
        }

        try {
            return $archive->locateName('[Content_Types].xml') !== false
                && $archive->locateName('word/document.xml') !== false;
        } finally {
            $archive->close();
        }
    }
}
