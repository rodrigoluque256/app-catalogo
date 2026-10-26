<?php

declare(strict_types=1);

namespace App\UI\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListStoresRequest extends FormRequest
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
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'suspended', 'deleted'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function search(): ?string
    {
        return $this->filled('search') ? $this->string('search')->trim()->toString() : null;
    }

    /**
     * @return 'active'|'suspended'|'deleted'|null
     */
    public function status(): ?string
    {
        /** @var 'active'|'suspended'|'deleted'|null $status */
        $status = $this->filled('status') ? $this->string('status')->toString() : null;

        return $status;
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 25);
    }
}
