<?php

declare(strict_types=1);

namespace App\UI\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class TwoFactorCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta ya exige el token de desafío
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required_without:recovery_code', 'nullable', 'string', 'max:20'],
            'recovery_code' => ['required_without:code', 'nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required_without' => 'Ingresá el código de 6 dígitos de tu app.',
            'recovery_code.required_without' => 'Ingresá un código de recuperación.',
        ];
    }

    public function code(): ?string
    {
        return $this->filled('code') ? $this->string('code')->toString() : null;
    }

    public function recoveryCode(): ?string
    {
        return $this->filled('recovery_code') ? $this->string('recovery_code')->toString() : null;
    }
}
