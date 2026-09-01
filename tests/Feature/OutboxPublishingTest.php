<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Files\Contracts\DeletionEventPublisher;
use App\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class OutboxPublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_message_is_marked_as_published(): void
    {
        $publisher = new class implements DeletionEventPublisher
        {
            /** @var list<string> */
            public array $published = [];

            public function publish(OutboxMessage $message): void
            {
                $this->published[] = (string) $message->getKey();
            }
        };

        $this->app->instance(DeletionEventPublisher::class, $publisher);
        $message = $this->createMessage();

        $this->artisan('outbox:publish')
            ->expectsOutput('Published: 1; failed: 0.')
            ->assertSuccessful();

        self::assertSame([$message->getKey()], $publisher->published);
        self::assertNotNull($message->fresh()->published_at);
        self::assertSame(1, $message->fresh()->attempts);
    }

    public function test_failed_publication_is_retried_later(): void
    {
        $this->app->instance(DeletionEventPublisher::class, new class implements DeletionEventPublisher
        {
            public function publish(OutboxMessage $message): void
            {
                throw new RuntimeException('RabbitMQ unavailable');
            }
        });

        $message = $this->createMessage();

        $this->artisan('outbox:publish')
            ->expectsOutput('Published: 0; failed: 1.')
            ->assertFailed();

        $message->refresh();
        self::assertNull($message->published_at);
        self::assertSame(1, $message->attempts);
        self::assertSame('RabbitMQ unavailable', $message->last_error);
        self::assertTrue($message->available_at->isFuture());
    }

    private function createMessage(): OutboxMessage
    {
        return OutboxMessage::query()->create([
            'event_type' => 'file.deleted',
            'aggregate_type' => 'stored_file',
            'aggregate_id' => '01TESTFILE',
            'routing_key' => 'file.deleted',
            'payload' => ['event_id' => 'event-id', 'recipient' => 'alerts@example.com'],
            'available_at' => now(),
        ]);
    }
}
