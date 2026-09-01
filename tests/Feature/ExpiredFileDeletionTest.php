<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Files\Enums\DeletionReason;
use App\Models\StoredFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ExpiredFileDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');
        config()->set('documents.notification_email', 'alerts@example.com');
    }

    public function test_command_deletes_only_expired_files(): void
    {
        Carbon::setTestNow('2026-08-31 12:00:00');
        $expired = StoredFile::factory()->create(['expires_at' => now()->subSecond()]);
        $active = StoredFile::factory()->create(['expires_at' => now()->addSecond()]);
        Storage::disk('documents')->put($expired->storage_path, 'expired');
        Storage::disk('documents')->put($active->storage_path, 'active');

        $this->artisan('files:delete-expired')
            ->expectsOutput('Deleted 1 expired file(s).')
            ->assertSuccessful();

        Storage::disk('documents')->assertMissing($expired->storage_path);
        Storage::disk('documents')->assertExists($active->storage_path);

        $deletedFile = StoredFile::withTrashed()->findOrFail($expired->getKey());
        self::assertSame(DeletionReason::Expired, $deletedFile->deletion_reason);
        self::assertDatabaseHas('outbox_messages', [
            'aggregate_id' => $expired->public_id,
            'event_type' => 'file.deleted',
        ]);
    }
}
