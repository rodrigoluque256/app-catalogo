<?php

declare(strict_types=1);

namespace App\Domain\Shared\Tenancy;

use App\Domain\Store\Models\Store;

/**
 * Tienda (tenant) activa durante el request. Registrada como scoped singleton.
 * Falla cerrado: pedir el tenant sin haberlo resuelto lanza excepción en vez de devolver datos de todas las tiendas.
 */
final class TenantContext
{
    private ?Store $store = null;

    public function set(Store $store): void
    {
        $this->store = $store;
    }

    public function clear(): void
    {
        $this->store = null;
    }

    public function check(): bool
    {
        return $this->store !== null;
    }

    public function store(): Store
    {
        return $this->store ?? throw new TenantNotResolvedException;
    }

    public function id(): string
    {
        return $this->store()->id;
    }

    /**
     * Ejecuta el callback como si $store fuese el tenant actual (seeders, jobs, comandos).
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(Store $store, callable $callback): mixed
    {
        $previous = $this->store;
        $this->store = $store;

        try {
            return $callback();
        } finally {
            $this->store = $previous;
        }
    }
}
