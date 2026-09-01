<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Files\Contracts\DeletionEventPublisher;
use App\Domain\Files\Contracts\DocumentStorage;
use App\Infrastructure\Messaging\RabbitMqDeletionEventPublisher;
use App\Infrastructure\Storage\LaravelDocumentStorage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DocumentStorage::class, LaravelDocumentStorage::class);
        $this->app->singleton(DeletionEventPublisher::class, RabbitMqDeletionEventPublisher::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
    }
}
