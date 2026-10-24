<?php

declare(strict_types=1);

namespace App\Domain\Store\Contracts;

interface UploadedObjectInspector
{
    /**
     * Metadatos reales del objeto ya subido, o null si no existe.
     */
    public function inspect(string $path): ?UploadedObject;
}
