<?php

declare(strict_types=1);

namespace App\Application\Files\Actions;

use App\Domain\Files\Enums\DeletionReason;
use App\Models\StoredFile;
use Illuminate\Contracts\Config\Repository as Config;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class DeleteExpiredFiles
{
    public function __construct(
        private DeleteStoredFile $deleteStoredFile,
        private Config $config,
        private LoggerInterface $logger,
    ) {}

    public function execute(): int
    {
        $deleted = 0;
        $batchSize = max(1, (int) $this->config->get('documents.expiration_batch_size', 100));

        StoredFile::query()
            ->expired()
            ->orderBy('id')
            ->chunkById($batchSize, function ($files) use (&$deleted): void {
                /** @var StoredFile $file */
                foreach ($files as $file) {
                    try {
                        if ($this->deleteStoredFile->execute($file, DeletionReason::Expired)) {
                            $deleted++;
                        }
                    } catch (Throwable $exception) {
                        $this->logger->error('Failed to delete an expired file.', [
                            'file_id' => $file->public_id,
                            'exception' => $exception,
                        ]);
                    }
                }
            });

        return $deleted;
    }
}
