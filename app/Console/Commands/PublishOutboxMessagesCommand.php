<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Files\Actions\PublishOutboxMessages;
use Illuminate\Console\Command;

final class PublishOutboxMessagesCommand extends Command
{
    protected $signature = 'outbox:publish {--limit=100 : Maximum number of messages per run}';

    protected $description = 'Publish pending file deletion events to RabbitMQ';

    public function handle(PublishOutboxMessages $publishOutboxMessages): int
    {
        $result = $publishOutboxMessages->execute(max(1, (int) $this->option('limit')));

        $this->info("Published: {$result->published}; failed: {$result->failed}.");

        return $result->failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
