<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Files\Enums\DeletionReason;
use PHPUnit\Framework\TestCase;

final class DeletionReasonTest extends TestCase
{
    public function test_reasons_have_stable_integration_values(): void
    {
        self::assertSame('manual', DeletionReason::Manual->value);
        self::assertSame('expired', DeletionReason::Expired->value);
    }
}
