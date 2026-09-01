<?php

declare(strict_types=1);

namespace App\Application\Files\Actions;

use App\Domain\Files\Contracts\DocumentStorage;
use App\Domain\Files\Enums\DeletionReason;
use App\Models\OutboxMessage;
use App\Models\StoredFile;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class DeleteStoredFile
{
    public function __construct(
        private DocumentStorage $storage,
        private DatabaseManager $database,
        private Config $config,
    ) {}

    /**
     * Delete the physical file and atomically record both the logical deletion
     * and its integration event. Returns false when it was already deleted.
     */
    public function execute(StoredFile $file, DeletionReason $reason): bool
    {
        return $this->database->transaction(function () use ($file, $reason): bool {
            /** @var StoredFile $lockedFile */
            $lockedFile = StoredFile::withTrashed()
                ->whereKey($file->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedFile->trashed()) {
                return false;
            }

            $recipient = $this->config->get('documents.notification_email');

            if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('DOCUMENT_NOTIFICATION_EMAIL must contain a valid email address.');
            }

            // For this bounded use case the private local disk operation is kept
            // inside the short transaction to serialize manual and scheduled deletion.
            $this->storage->delete($lockedFile->storage_disk, $lockedFile->storage_path);

            $lockedFile->deletion_reason = $reason;
            $lockedFile->save();
            $lockedFile->delete();

            $eventId = (string) Str::uuid();

            OutboxMessage::query()->create([
                'id' => $eventId,
                'event_type' => 'file.deleted',
                'aggregate_type' => 'stored_file',
                'aggregate_id' => (string) $lockedFile->public_id,
                'routing_key' => (string) $this->config->get('rabbitmq.routing_key', 'file.deleted'),
                'payload' => [
                    'event_id' => $eventId,
                    'event_type' => 'file.deleted',
                    'occurred_at' => now()->toIso8601String(),
                    'recipient' => $recipient,
                    'data' => [
                        'file_id' => $lockedFile->public_id,
                        'original_name' => $lockedFile->original_name,
                        'mime_type' => $lockedFile->mime_type,
                        'size_bytes' => $lockedFile->size_bytes,
                        'checksum_sha256' => $lockedFile->checksum_sha256,
                        'uploaded_at' => $lockedFile->created_at?->toIso8601String(),
                        'expires_at' => $lockedFile->expires_at->toIso8601String(),
                        'deletion_reason' => $reason->value,
                    ],
                ],
                'attempts' => 0,
                'available_at' => now(),
            ]);

            return true;
        }, 3);
    }
}
