<?php

declare(strict_types=1);

namespace App\UI\Http\Requests\Upload;

use App\Application\Upload\Actions\CreateUploadUrlAction;
use App\Application\Upload\Enums\UploadPurpose;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PresignUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purpose = $this->enum('purpose', UploadPurpose::class);

        // Un propósito inválido lo rechaza rules() con 422; acá solo se exige el permiso del válido.
        return $purpose === null || ($this->user()?->can($purpose->permission()) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['required', Rule::enum(UploadPurpose::class)],
            'content_type' => ['required', 'string', Rule::in(array_keys(CreateUploadUrlAction::EXTENSIONS))],
            'size' => ['required', 'integer', 'min:1', 'max:'.CreateUploadUrlAction::MAX_BYTES],
        ];
    }

    public function purpose(): UploadPurpose
    {
        return UploadPurpose::from($this->string('purpose')->toString());
    }
}
