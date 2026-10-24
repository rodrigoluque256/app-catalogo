<?php

declare(strict_types=1);

namespace App\Application\Upload\Enums;

enum UploadPurpose: string
{
    case ProductImage = 'product_image';
    case StoreLogo = 'store_logo';

    /**
     * Carpeta bajo stores/{storeId}/. El aislamiento por tienda vive en la key del objeto.
     */
    public function folder(): string
    {
        return match ($this) {
            self::ProductImage => 'products',
            self::StoreLogo => 'logo',
        };
    }

    public function permission(): string
    {
        return match ($this) {
            self::ProductImage => 'products.manage',
            self::StoreLogo => 'store.manage',
        };
    }

    public static function keyPrefix(string $storeId, self $purpose): string
    {
        return "stores/{$storeId}/{$purpose->folder()}/";
    }
}
