<?php

declare(strict_types=1);

namespace App\UI\Http\Requests\Category;

use App\Application\Category\DTOs\CategoryData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create y update (PUT) comparten las mismas reglas.
 */
final class CategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'uuid'],
            'position' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function toData(): CategoryData
    {
        return new CategoryData(
            name: $this->string('name')->trim()->toString(),
            slug: $this->filled('slug') ? $this->string('slug')->toString() : null,
            description: $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            parentId: $this->filled('parent_id') ? $this->string('parent_id')->toString() : null,
            position: $this->integer('position'),
            isActive: $this->boolean('is_active', true),
        );
    }
}
