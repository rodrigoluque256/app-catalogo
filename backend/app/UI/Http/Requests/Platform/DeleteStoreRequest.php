<?php

declare(strict_types=1);

namespace App\UI\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['confirm_slug' => ['required', 'string', 'max:63']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['confirm_slug.required' => 'Escribí el subdominio de la tienda para confirmar el borrado.'];
    }
}
