<?php

declare(strict_types=1);

namespace App\Application\Files\DTO;

final readonly class OutboxPublishResult
{
    public function __construct(
        public int $published,
        public int $failed,
    ) {}
}
