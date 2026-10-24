<?php

declare(strict_types=1);

namespace App\UI\Http\Controllers\Api\V1\Admin;

use App\Application\Upload\Actions\CreateUploadUrlAction;
use App\UI\Http\Requests\Upload\PresignUploadRequest;
use Illuminate\Http\JsonResponse;

final class UploadController
{
    /**
     * Flujo: 1) el front pide la URL acá; 2) hace PUT del archivo directo a R2 con esos headers;
     * 3) envía `path` en el producto (images[].path) o en la config de la tienda (logo_path).
     */
    public function presign(PresignUploadRequest $request, CreateUploadUrlAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->purpose(),
            $request->string('content_type')->toString(),
        );

        return response()->json(['data' => [
            'path' => $result['path'],
            'upload_url' => $result['upload']->url,
            'method' => 'PUT',
            'headers' => (object) $result['upload']->headers,
            'public_url' => $result['upload']->publicUrl,
            'expires_at' => $result['upload']->expiresAt->format(DATE_ATOM),
        ]]);
    }
}
