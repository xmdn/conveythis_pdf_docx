@extends('layouts.app')

@section('title', 'Temporary files · '.config('app.name'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
                <div>
                    <h1 class="h3 mb-1">Temporary document storage</h1>
                    <p class="text-secondary mb-0">
                        PDF and DOCX files are automatically removed after {{ $retentionHours }} hours.
                    </p>
                </div>
                <span class="badge text-bg-light border fs-6">Private storage</span>
            </div>

            <div id="alert-container" aria-live="polite"></div>

            <section class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5">Upload a document</h2>
                    <p class="text-secondary small">
                        Accepted formats: PDF, DOCX. Maximum size: {{ $maxSizeMb }} MB.
                    </p>

                    <form id="upload-form" action="{{ route('files.store') }}" method="post" enctype="multipart/form-data" novalidate>
                        @csrf
                        <div class="input-group">
                            <input
                                class="form-control"
                                type="file"
                                id="document"
                                name="document"
                                accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                data-max-size-mb="{{ $maxSizeMb }}"
                                required
                            >
                            <button class="btn btn-primary px-4" id="upload-button" type="submit">Upload</button>
                        </div>
                        <div id="document-error" class="invalid-feedback d-block"></div>

                        <div id="upload-progress-wrapper" class="progress mt-3 d-none" role="progressbar" aria-label="Upload progress" aria-valuemin="0" aria-valuemax="100">
                            <div id="upload-progress" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%">0%</div>
                        </div>
                    </form>
                </div>
            </section>

            <section class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 p-4 pb-2">
                    <h2 class="h5 mb-0">Uploaded files</h2>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="files-table">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">File</th>
                                <th scope="col">Size</th>
                                <th scope="col">Uploaded</th>
                                <th scope="col">Expires</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="files-table-body">
                            @forelse ($files as $file)
                                <tr data-file-id="{{ $file->public_id }}">
                                    <td>
                                        <div class="fw-semibold text-break">{{ $file->original_name }}</div>
                                        <small class="text-uppercase text-secondary">{{ $file->extension }}</small>
                                    </td>
                                    <td>
                                        @if ($file->size_bytes >= 1024 * 1024)
                                            {{ number_format($file->size_bytes / (1024 * 1024), 1) }} MB
                                        @elseif ($file->size_bytes >= 1024)
                                            {{ number_format($file->size_bytes / 1024, 1) }} KB
                                        @else
                                            {{ $file->size_bytes }} B
                                        @endif
                                    </td>
                                    <td><time datetime="{{ $file->created_at->toIso8601String() }}">{{ $file->created_at->format('Y-m-d H:i') }} UTC</time></td>
                                    <td><time datetime="{{ $file->expires_at->toIso8601String() }}">{{ $file->expires_at->format('Y-m-d H:i') }} UTC</time></td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('files.download', $file->public_id) }}">Download</a>
                                        <button
                                            class="btn btn-sm btn-outline-danger delete-file"
                                            type="button"
                                            data-url="{{ route('files.destroy', $file->public_id) }}"
                                            data-name="{{ $file->original_name }}"
                                        >Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr id="empty-state">
                                    <td colspan="5" class="text-center text-secondary py-5">No files have been uploaded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($files->hasPages())
                    <div class="card-footer bg-white p-3">
                        {{ $files->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
