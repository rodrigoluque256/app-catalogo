<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

/**
 * Evento que tiene que revalidar la tienda aunque ya no esté activa (suspendida o borrada):
 * justamente para que su contenido cacheado deje de verse. Trae el slug porque la tienda
 * puede no encontrarse con las consultas normales.
 */
interface RevalidatesRegardlessOfStatus extends AffectsStorefront
{
    public function storeSlug(): string;
}
