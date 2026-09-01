<?php

declare(strict_types=1);

namespace App\Domain\Files\Contracts;

use App\Models\OutboxMessage;

interface DeletionEventPublisher
{
    /**
     * Publish an outbox event and return only after RabbitMQ confirms it.
     */
    public function publish(OutboxMessage $message): void;
}
