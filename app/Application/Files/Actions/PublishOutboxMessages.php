<?php

declare(strict_types=1);

namespace App\Application\Files\Actions;

use App\Application\Files\DTO\OutboxPublishResult;
use App\Domain\Files\Contracts\DeletionEventPublisher;
use App\Models\OutboxMessage;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class PublishOutboxMessages
{
    public function __construct(
        private DeletionEventPublisher $publisher,
        private LoggerInterface $logger,
    ) {}

    public function execute(int $limit = 100): OutboxPublishResult
    {
        $published = 0;
        $failed = 0;

        OutboxMessage::query()
            ->pending()
            ->orderBy('created_at')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (OutboxMessage $message) use (&$published, &$failed): void {
                $message->increment('attempts');
                $message->refresh();

                try {
                    $this->publisher->publish($message);

                    $message->forceFill([
                        'published_at' => now(),
                        'last_error' => null,
                    ])->save();

                    $published++;
                } catch (Throwable $exception) {
                    $delaySeconds = min(300, 2 ** min($message->attempts, 8));

                    $message->forceFill([
                        'available_at' => now()->addSeconds($delaySeconds),
                        'last_error' => mb_substr($exception->getMessage(), 0, 65_535),
                    ])->save();

                    $this->logger->warning('RabbitMQ outbox publication failed.', [
                        'event_id' => $message->getKey(),
                        'attempt' => $message->attempts,
                        'next_attempt_in_seconds' => $delaySeconds,
                        'exception' => $exception,
                    ]);

                    $failed++;
                }
            });

        return new OutboxPublishResult($published, $failed);
    }
}
