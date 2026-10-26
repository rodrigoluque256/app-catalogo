<?php

declare(strict_types=1);

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\Exceptions\PlanLimitExceededException;

/**
 * Límites del plan de la tienda del tenant actual. Solo frena lo que se agrega:
 * si la tienda ya estaba por encima (bajó de plan), conserva lo que tiene.
 */
interface PlanLimitGuard
{
    /** @throws PlanLimitExceededException */
    public function ensureCanAddProduct(): void;

    /**
     * @param  int  $current  fotos que el producto ya tiene (0 al crearlo)
     *
     * @throws PlanLimitExceededException
     */
    public function ensureImagesAllowed(int $new, int $current = 0): void;

    /** @throws PlanLimitExceededException */
    public function ensureCanAddUser(): void;
}
