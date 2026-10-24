<?php

declare(strict_types=1);

namespace App\Infrastructure\Storefront;

use App\Domain\Store\Contracts\StorefrontRevalidator;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Log;

/**
 * Llama al route handler POST {url} del frontend Next.js (que ejecuta revalidateTag por cada tag).
 * Sin URL configurada (dev/tests) no hace nada.
 */
final readonly class NextJsStorefrontRevalidator implements StorefrontRevalidator
{
    public function __construct(
        private Http $http,
        private ?string $url,
        private ?string $secret,
    ) {}

    public function revalidate(array $tags): void
    {
        if ($tags === []) {
            return;
        }

        if ($this->url === null || $this->url === '') {
            Log::debug('Revalidación ISR omitida: STOREFRONT_REVALIDATE_URL no configurada.', ['tags' => $tags]);

            return;
        }

        $this->http
            ->withHeaders(['x-revalidate-secret' => (string) $this->secret])
            ->acceptJson()
            ->timeout(10)
            ->post($this->url, ['tags' => $tags])
            ->throw();
    }
}
