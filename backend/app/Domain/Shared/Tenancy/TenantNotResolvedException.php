<?php

declare(strict_types=1);

namespace App\Domain\Shared\Tenancy;

use LogicException;

final class TenantNotResolvedException extends LogicException
{
    public function __construct()
    {
        parent::__construct('No hay una tienda (tenant) resuelta para el contexto actual.');
    }
}
