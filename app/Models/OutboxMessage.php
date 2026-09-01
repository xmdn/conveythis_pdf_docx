<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class OutboxMessage extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'routing_key',
        'payload',
        'attempts',
        'available_at',
        'published_at',
        'last_error',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $message): void {
            $message->id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'available_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @param Builder<self> $query */
    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('published_at')
            ->where('available_at', '<=', now());
    }
}
