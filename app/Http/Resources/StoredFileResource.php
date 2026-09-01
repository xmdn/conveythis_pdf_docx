<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StoredFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size_bytes' => $this->size_bytes,
            'size_human' => $this->humanSize((int) $this->size_bytes),
            'uploaded_at' => $this->created_at?->toIso8601String(),
            'uploaded_at_human' => $this->created_at?->format('Y-m-d H:i:s').' UTC',
            'expires_at' => $this->expires_at->toIso8601String(),
            'expires_at_human' => $this->expires_at->format('Y-m-d H:i:s').' UTC',
            'download_url' => route('files.download', $this->public_id),
            'delete_url' => route('files.destroy', $this->public_id),
        ];
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }
}
