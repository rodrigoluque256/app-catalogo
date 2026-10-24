<?php

declare(strict_types=1);

namespace App\Domain\Store\Contracts;

interface StorefrontRevalidator
{
    /**
     * Pide al frontend invalidar las páginas cacheadas bajo esos tags.
     * Lanza excepción si no pudo hacerlo, para que el job se reintente.
     *
     * @param  list<string>  $tags
     */
    public function revalidate(array $tags): void;
}
