<?php

declare(strict_types=1);

namespace App\UI\Http\Requests\Platform;

use App\Domain\Billing\Enums\BillingCycle;
use App\Domain\Billing\Models\Plan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeStorePlanRequest extends FormRequest
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
            'plan' => ['required', 'string', Rule::exists('plans', 'code')->where('is_active', true)],
            // Los planes pagos necesitan ciclo; en Gratis se ignora.
            'billing_cycle' => [Rule::requiredIf(fn (): bool => $this->input('plan') !== Plan::FREE), 'nullable', Rule::enum(BillingCycle::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan.exists' => 'Ese plan no existe.',
            'billing_cycle.required' => 'Elegí si el plan es mensual o anual.',
        ];
    }

    public function plan(): Plan
    {
        return Plan::query()->where('code', $this->string('plan')->toString())->firstOrFail();
    }

    public function cycle(): ?BillingCycle
    {
        return $this->filled('billing_cycle') ? BillingCycle::from($this->string('billing_cycle')->toString()) : null;
    }
}
