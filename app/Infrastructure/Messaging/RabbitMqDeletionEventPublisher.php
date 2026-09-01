<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Domain\Files\Contracts\DeletionEventPublisher;
use App\Models\OutboxMessage;
use Illuminate\Contracts\Config\Repository as Config;
use JsonException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;

final class RabbitMqDeletionEventPublisher implements DeletionEventPublisher
{
    private ?AMQPStreamConnection $connection = null;

    private ?AMQPChannel $channel = null;

    private bool $unroutable = false;

    public function __construct(private readonly Config $config) {}

    /** @throws JsonException */
    public function publish(OutboxMessage $message): void
    {
        $channel = $this->channel();
        $exchange = (string) $this->config->get('rabbitmq.exchange', 'file.events');
        $routingKey = (string) $message->routing_key;
        $this->unroutable = false;

        $amqpMessage = new AMQPMessage(
            json_encode($message->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            [
                'content_type' => 'application/json',
                'content_encoding' => 'utf-8',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => (string) $message->getKey(),
                'type' => (string) $message->event_type,
                'timestamp' => now()->getTimestamp(),
            ],
        );

        $channel->basic_publish($amqpMessage, $exchange, $routingKey, true);
        $channel->wait_for_pending_acks_returns(
            (float) $this->config->get('rabbitmq.confirm_timeout', 5),
        );

        if ($this->unroutable) {
            throw new RuntimeException("RabbitMQ returned unroutable event [{$message->getKey()}].");
        }
    }

    public function __destruct()
    {
        try {
            $this->channel?->close();
            $this->connection?->close();
        } catch (\Throwable) {
            // Destructors must not hide the original application error.
        }
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel?->is_open()) {
            return $this->channel;
        }

        $heartbeat = (int) $this->config->get('rabbitmq.heartbeat', 30);
        $readWriteTimeout = max(
            (float) $this->config->get('rabbitmq.read_write_timeout', 5),
            $heartbeat * 2,
        );

        $this->connection = new AMQPStreamConnection(
            (string) $this->config->get('rabbitmq.host'),
            (int) $this->config->get('rabbitmq.port'),
            (string) $this->config->get('rabbitmq.user'),
            (string) $this->config->get('rabbitmq.password'),
            (string) $this->config->get('rabbitmq.vhost', '/'),
            false,
            'AMQPLAIN',
            null,
            'en_US',
            (float) $this->config->get('rabbitmq.connection_timeout', 3),
            $readWriteTimeout,
            null,
            false,
            $heartbeat,
        );

        $this->channel = $this->connection->channel();
        $this->declareTopology($this->channel);
        $this->channel->confirm_select();
        $this->channel->set_return_listener(function (): void {
            $this->unroutable = true;
        });

        return $this->channel;
    }

    private function declareTopology(AMQPChannel $channel): void
    {
        $exchange = (string) $this->config->get('rabbitmq.exchange', 'file.events');
        $queue = (string) $this->config->get('rabbitmq.queue', 'email.notifications');
        $routingKey = (string) $this->config->get('rabbitmq.routing_key', 'file.deleted');

        $channel->exchange_declare($exchange, 'topic', false, true, false);
        $channel->queue_declare($queue, false, true, false, false);
        $channel->queue_bind($queue, $exchange, $routingKey);
    }
}
