<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StoredFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StoredFile> */
final class StoredFileFactory extends Factory
{
    protected $model = StoredFile::class;

    public function definition(): array
    {
        $name = fake()->word().'.pdf';

        return [
            'public_id' => (string) Str::ulid(),
            'original_name' => $name,
            'storage_disk' => 'documents',
            'storage_path' => 'uploads/'.Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => fake()->numberBetween(100, 5_000_000),
            'checksum_sha256' => hash('sha256', $name),
            'expires_at' => now()->addDay(),
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }
}
