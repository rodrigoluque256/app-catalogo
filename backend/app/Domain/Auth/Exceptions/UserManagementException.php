<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use App\Domain\Shared\Exceptions\DomainValidationException;

/**
 * Reglas de gestión de usuarios: nadie se saca el acceso a sí mismo y la tienda nunca se queda sin dueño.
 */
final class UserManagementException extends DomainValidationException
{
    private function __construct(string $message, private readonly string $field)
    {
        parent::__construct($message);
    }

    public static function cannotDeleteSelf(): self
    {
        return new self('No podés eliminar tu propio usuario.', 'user');
    }

    public static function cannotChangeOwnRole(): self
    {
        return new self('No podés cambiar tu propio rol.', 'role');
    }

    public static function lastOwner(): self
    {
        return new self('La tienda debe conservar al menos un dueño.', 'role');
    }

    public static function wrongCurrentPassword(): self
    {
        return new self('La contraseña actual no es correcta.', 'current_password');
    }

    public function field(): string
    {
        return $this->field;
    }
}
