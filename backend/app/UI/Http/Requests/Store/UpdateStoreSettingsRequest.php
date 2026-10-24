<?php

declare(strict_types=1);

namespace App\UI\Http\Requests\Store;

use App\Application\Store\DTOs\UpdateStoreSettingsData;
use App\Application\Upload\Enums\UploadPurpose;
use App\Domain\Shared\Tenancy\TenantContext;
use App\UI\Http\Rules\UploadedImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateStoreSettingsRequest extends FormRequest
{
    private const array FIELDS = [
        'name', 'logo_path', 'primary_color', 'accent_color',
        'whatsapp_number', 'whatsapp_message_template', 'currency', 'locale',
    ];

    public function authorize(): bool
    {
        return true; // la ruta ya exige can:store.manage
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $logoPrefix = UploadPurpose::keyPrefix(app(TenantContext::class)->id(), UploadPurpose::StoreLogo);

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            // Solo keys generadas por /uploads/presign para esta tienda: impide apuntar al archivo de otra.
            'logo_path' => ['sometimes', 'nullable', 'string', 'max:255', 'starts_with:'.$logoPrefix, app(UploadedImage::class)],
            'primary_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'whatsapp_number' => ['sometimes', 'nullable', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'whatsapp_message_template' => ['sometimes', 'nullable', 'string', 'max:500'],
            'currency' => ['sometimes', 'required', 'alpha:ascii', 'size:3'],
            'locale' => ['sometimes', 'required', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/'],
        ];
    }

    public function toData(): UpdateStoreSettingsData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        $changes = array_intersect_key($validated, array_flip(self::FIELDS));

        if (isset($changes['currency'])) {
            $changes['currency'] = strtoupper((string) $changes['currency']);
        }

        if (isset($changes['primary_color'])) {
            $changes['primary_color'] = strtoupper((string) $changes['primary_color']);
        }

        if (isset($changes['accent_color'])) {
            $changes['accent_color'] = strtoupper((string) $changes['accent_color']);
        }

        return new UpdateStoreSettingsData($changes);
    }
}
