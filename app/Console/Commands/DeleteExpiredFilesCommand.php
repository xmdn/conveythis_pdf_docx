<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Files\Actions\DeleteExpiredFiles;
use Illuminate\Console\Command;

final class DeleteExpiredFilesCommand extends Command
{
    protected $signature = 'files:delete-expired';

    protected $description = 'Delete files whose retention period has elapsed';

    public function handle(DeleteExpiredFiles $deleteExpiredFiles): int
    {
        $count = $deleteExpiredFiles->execute();

        $this->info("Deleted {$count} expired file(s).");

        return self::SUCCESS;
    }
}
