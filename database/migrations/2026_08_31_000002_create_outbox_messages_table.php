<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_type', 100);
            $table->string('aggregate_type', 100);
            $table->string('aggregate_id', 50);
            $table->string('routing_key', 100);
            $table->json('payload');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(
                ['event_type', 'aggregate_type', 'aggregate_id'],
                'outbox_aggregate_event_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
