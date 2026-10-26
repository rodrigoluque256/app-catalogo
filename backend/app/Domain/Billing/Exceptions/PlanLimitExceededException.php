<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use App\Domain\Shared\Exceptions\DomainValidationException;

/**
 * La tienda llegó al límite de su plan. Nada se borra al bajar de plan: solo se bloquea agregar más.
 */
final class PlanLimitExceededException extends DomainValidationException
{
    private function __construct(string $message, private readonly string $field)
    {
        parent::__construct($message);
    }

    public static function products(string $plan, int $max): self
    {
        return new self("Tu plan {$plan} permite hasta {$max} productos. Subí de plan para cargar más.", 'plan');
    }

    public static function images(string $plan, int $max): self
    {
        return new self("Tu plan {$plan} permite hasta {$max} fotos por producto. Subí de plan para agregar más.", 'images');
    }

    public static function users(string $plan, int $max): self
    {
        $users = $max === 1 ? '1 usuario' : "{$max} usuarios";

        return new self("Tu plan {$plan} permite hasta {$users}. Subí de plan para sumar más.", 'plan');
    }

    public function field(): string
    {
        return $this->field;
    }
}
