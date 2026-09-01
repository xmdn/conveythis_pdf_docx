<?php

declare(strict_types=1);

namespace App\Domain\Files\Enums;

enum DeletionReason: string
{
    case Manual = 'manual';
    case Expired = 'expired';
}
