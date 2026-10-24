<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

/**
 * Evento que deja desactualizado el contenido público de una tienda.
 * Un único listener lo traduce a tags de caché y pide la revalidación ISR al frontend.
 */
interface AffectsStorefront
{
    public function storeId(): string;

    /**
     * @return list<string>
     */
    public function tags(string $storeSlug): array;
}
