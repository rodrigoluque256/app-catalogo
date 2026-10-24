<?php

declare(strict_types=1);

namespace App\Application\Product\DTOs;

/**
 * Imagen ya subida a R2 (el front sube con URL prefirmada y envía la key).
 */
final readonly class ProductImageData
{
    public function __construct(
        public string $path,
        public ?string $altText = null,
        public string $disk = 'r2',
        public ?int $width = null,
        public ?int $height = null,
        public int $position = 0,
        public bool $isPrimary = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data  payload ya validado (snake_case)
     */
    public static function fromArray(array $data, int $position = 0): self
    {
        return new self(
            path: (string) $data['path'],
            altText: isset($data['alt_text']) ? (string) $data['alt_text'] : null,
            width: isset($data['width']) ? (int) $data['width'] : null,
            height: isset($data['height']) ? (int) $data['height'] : null,
            position: $position,
            isPrimary: (bool) ($data['is_primary'] ?? false),
        );
    }
}
