<?php

declare(strict_types=1);

namespace App\UI\Http\Rules;

use App\Application\Upload\Actions\CreateUploadUrlAction;
use App\Domain\Store\Contracts\UploadedObjectInspector;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * La key debe apuntar a un objeto realmente subido y que cumpla tipo y tamaño permitidos.
 * Es la barrera real, porque la URL prefirmada no puede imponer esos límites.
 */
final readonly class UploadedImage implements ValidationRule
{
    public function __construct(
        private UploadedObjectInspector $inspector,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $object = is_string($value) ? $this->inspector->inspect($value) : null;

        if ($object === null) {
            $fail('La imagen no fue subida. Pedí una URL en /uploads/presign y subí el archivo primero.');

            return;
        }

        if (! array_key_exists($object->contentType, CreateUploadUrlAction::EXTENSIONS)) {
            $fail('La imagen debe ser JPEG, PNG o WebP.');
        } elseif ($object->size > CreateUploadUrlAction::MAX_BYTES) {
            $fail('La imagen supera el tamaño máximo permitido (5 MB).');
        }
    }
}
