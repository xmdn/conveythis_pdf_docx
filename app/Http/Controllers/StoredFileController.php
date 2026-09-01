<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Files\Actions\DeleteStoredFile;
use App\Application\Files\Actions\UploadStoredFile;
use App\Domain\Files\Contracts\DocumentStorage;
use App\Domain\Files\Enums\DeletionReason;
use App\Http\Requests\UploadStoredFileRequest;
use App\Http\Resources\StoredFileResource;
use App\Models\StoredFile;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StoredFileController extends Controller
{
    public function index(): View
    {
        $files = StoredFile::query()
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('files.index', [
            'files' => $files,
            'maxSizeMb' => (int) config('documents.max_size_mb', 10),
            'retentionHours' => (int) config('documents.retention_hours', 24),
        ]);
    }

    public function store(
        UploadStoredFileRequest $request,
        UploadStoredFile $uploadStoredFile,
    ): JsonResponse {
        $file = $uploadStoredFile->execute($request->file('document'));

        return (new StoredFileResource($file))
            ->response()
            ->setStatusCode(201);
    }

    public function download(
        string $publicId,
        DocumentStorage $storage,
    ): StreamedResponse {
        $file = StoredFile::query()->where('public_id', $publicId)->firstOrFail();

        return $storage->download(
            $file->storage_disk,
            $file->storage_path,
            $file->original_name,
        );
    }

    public function destroy(
        string $publicId,
        DeleteStoredFile $deleteStoredFile,
    ): JsonResponse {
        $file = StoredFile::withTrashed()
            ->where('public_id', $publicId)
            ->firstOrFail();

        $deleted = $deleteStoredFile->execute($file, DeletionReason::Manual);

        return response()->json([
            'data' => [
                'id' => $publicId,
                'deleted' => $deleted,
            ],
        ]);
    }
}
