<?php

declare(strict_types=1);

namespace App\Application\Store\DTOs;

/**
 * Actualización parcial: solo viajan las columnas que el cliente envió (null es un valor válido para las opcionales).
 */
final readonly class UpdateStoreSettingsData
{
    /**
     * @param  array<string, mixed>  $changes  columna => valor
     */
    public function __construct(
        public array $changes,
    ) {}
}
