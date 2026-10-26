<?php

declare(strict_types=1);

namespace App\UI\Http\Requests\Platform;

use App\Domain\Store\Enums\StoreStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateStoreStatusRequest extends FormRequest
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
        return ['status' => ['required', Rule::enum(StoreStatus::class)]];
    }

    public function status(): StoreStatus
    {
        return StoreStatus::from($this->string('status')->toString());
    }
}
