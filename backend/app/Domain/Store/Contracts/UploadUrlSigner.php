<?php

declare(strict_types=1);

namespace App\Domain\Store\Contracts;

interface UploadUrlSigner
{
    /**
     * URL prefirmada de corta vida para subir un objeto con PUT en $path.
     *
     * Ni el tipo ni el tamaño quedan firmados (el SDK los excluye de las URLs prefirmadas y S3/R2 no admite
     * límites de tamaño en un PUT): se verifican después con UploadedObjectInspector al asociar la key.
     */
    public function sign(string $path, int $ttlSeconds): SignedUpload;
}
