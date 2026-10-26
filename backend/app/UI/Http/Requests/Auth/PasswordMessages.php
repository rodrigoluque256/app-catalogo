<?php

declare(strict_types=1);

namespace App\UI\Http\Requests\Auth;

/**
 * Mensajes en español de la regla Password::min(10)->letters()->numbers() (la app no tiene traducciones de
 * validación). Las fallas de letters/numbers llegan como regla "password.letters": de ahí la clave doble.
 */
final class PasswordMessages
{
    /**
     * @return array<string, string>
     */
    public static function for(string $attribute = 'password'): array
    {
        return [
            "{$attribute}.required" => 'Ingresá una contraseña.',
            "{$attribute}.min" => 'La contraseña debe tener al menos 10 caracteres.',
            "{$attribute}.password.letters" => 'La contraseña debe tener al menos una letra.',
            "{$attribute}.password.numbers" => 'La contraseña debe tener al menos un número.',
        ];
    }
}
