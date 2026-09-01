<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Files\Enums\DeletionReason;
use App\Models\OutboxMessage;
use App\Models\StoredFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ManualFileDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');
        config()->set('documents.notification_email', 'alerts@example.com');
        config()->set('rabbitmq.routing_key', 'file.deleted');
    }

    public function test_manual_deletion_removes_file_and_creates_outbox_event(): void
    {
        $file = StoredFile::factory()->create();
        Storage::disk('documents')->put($file->storage_path, 'document');

        $this->deleteJson(route('files.destroy', $file->public_id))
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        Storage::disk('documents')->assertMissing($file->storage_path);

        $deletedFile = StoredFile::withTrashed()->findOrFail($file->getKey());
        self::assertTrue($deletedFile->trashed());
        self::assertSame(DeletionReason::Manual, $deletedFile->deletion_reason);

        $event = OutboxMessage::query()->sole();
        self::assertSame('file.deleted', $event->event_type);
        self::assertSame('alerts@example.com', $event->payload['recipient']);
        self::assertSame('manual', $event->payload['data']['deletion_reason']);
        self::assertSame($file->public_id, $event->payload['data']['file_id']);
    }

    public function test_repeated_deletion_is_idempotent(): void
    {
        $file = StoredFile::factory()->create();
        Storage::disk('documents')->put($file->storage_path, 'document');

        $this->deleteJson(route('files.destroy', $file->public_id))
            ->assertJsonPath('data.deleted', true);

        $this->deleteJson(route('files.destroy', $file->public_id))
            ->assertOk()
            ->assertJsonPath('data.deleted', false);

        self::assertDatabaseCount('outbox_messages', 1);
    }
}
