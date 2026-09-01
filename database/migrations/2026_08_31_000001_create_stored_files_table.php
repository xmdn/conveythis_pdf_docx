<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_files', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('original_name');
            $table->string('storage_disk', 50);
            $table->string('storage_path', 500);
            $table->string('mime_type', 150);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->timestamp('expires_at')->index();
            $table->string('deletion_reason', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['deleted_at', 'expires_at'], 'stored_files_expiration_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_files');
    }
};
