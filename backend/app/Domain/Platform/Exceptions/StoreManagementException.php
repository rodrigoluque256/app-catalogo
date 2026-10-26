<?php

declare(strict_types=1);

namespace App\Domain\Platform\Exceptions;

use App\Domain\Shared\Exceptions\DomainValidationException;

final class StoreManagementException extends DomainValidationException
{
    private function __construct(string $message, private readonly string $field)
    {
        parent::__construct($message);
    }

    public static function slugConfirmationMismatch(): self
    {
        return new self('Escribí el subdominio exacto de la tienda para confirmar el borrado.', 'confirm_slug');
    }

    public static function deleted(): self
    {
        return new self('La tienda está borrada: restaurala antes de cambiar su estado.', 'status');
    }

    public static function notDeleted(): self
    {
        return new self('La tienda no está borrada.', 'store');
    }

    public function field(): string
    {
        return $this->field;
    }
}
