<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Files\Enums\DeletionReason;
use Database\Factories\StoredFileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class StoredFile extends Model
{
    /** @use HasFactory<StoredFileFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'original_name',
        'storage_disk',
        'storage_path',
        'mime_type',
        'extension',
        'size_bytes',
        'checksum_sha256',
        'expires_at',
        'deletion_reason',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $file): void {
            $file->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'expires_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
            'deletion_reason' => DeletionReason::class,
        ];
    }

    /** @param Builder<self> $query */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }
}
