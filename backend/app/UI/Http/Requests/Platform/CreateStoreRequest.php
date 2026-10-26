<?php

declare(strict_types=1);

namespace App\UI\Http\Requests\Platform;

use App\Domain\Store\ReservedSlugs;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta ya exige un token de plataforma
    }

    protected function prepareForValidation(): void
    {
        foreach (['slug', 'owner_email'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => strtolower(trim($this->input($field)))]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required', 'string', 'min:3', 'max:63',
                // Mismo formato que el CHECK de la tabla: es un label DNS (el subdominio de la tienda).
                'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
                // Sin excluir las borradas: su slug queda ocupado para que nadie tome sus URLs.
                Rule::unique('stores', 'slug'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && ReservedSlugs::isReserved($value)) {
                        $fail('Ese subdominio está reservado por la plataforma.');
                    }
                },
            ],
            'owner_name' => ['required', 'string', 'max:120'],
            // Único en toda la plataforma: el login identifica a la persona solo por email.
            'owner_email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ingresá el nombre de la tienda.',
            'slug.required' => 'Ingresá el subdominio.',
            'slug.min' => 'El subdominio debe tener al menos 3 caracteres.',
            'slug.max' => 'El subdominio puede tener hasta 63 caracteres.',
            'slug.regex' => 'Solo minúsculas, números y guiones (sin guion al principio ni al final).',
            'slug.unique' => 'Ese subdominio ya está en uso.',
            'owner_name.required' => 'Ingresá el nombre del dueño.',
            'owner_email.required' => 'Ingresá el email del dueño.',
            'owner_email.email' => 'El email no es válido.',
            'owner_email.unique' => 'Ya existe un usuario con ese email.',
        ];
    }
}
