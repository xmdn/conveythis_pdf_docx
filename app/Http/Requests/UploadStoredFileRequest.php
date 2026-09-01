<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ValidDocument;
use Illuminate\Foundation\Http\FormRequest;

final class UploadStoredFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = max(1, (int) config('documents.max_size_mb', 10));

        return [
            'document' => [
                'bail',
                'required',
                'file',
                'max:'.($maxSize * 1024),
                new ValidDocument,
            ],
        ];
    }

    public function messages(): array
    {
        $maxSize = max(1, (int) config('documents.max_size_mb', 10));

        return [
            'document.required' => 'Choose a PDF or DOCX file to upload.',
            'document.max' => "The document must not exceed {$maxSize} MB.",
        ];
    }
}
