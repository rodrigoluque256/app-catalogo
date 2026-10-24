<?php

declare(strict_types=1);

namespace App\Application\Upload\Actions;

use App\Application\Upload\Enums\UploadPurpose;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Store\Contracts\SignedUpload;
use App\Domain\Store\Contracts\UploadUrlSigner;
use Illuminate\Support\Str;

final readonly class CreateUploadUrlAction
{
    public const int TTL_SECONDS = 600;

    /** @var array<string, string> */
    public const array EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public const int MAX_BYTES = 5_242_880;

    public function __construct(
        private TenantContext $tenant,
        private UploadUrlSigner $signer,
    ) {}

    /**
     * La key se genera acá (nunca la elige el cliente): stores/{storeId}/{carpeta}/{uuid}.{ext}.
     *
     * @return array{path: string, upload: SignedUpload}
     */
    public function execute(UploadPurpose $purpose, string $contentType): array
    {
        $path = UploadPurpose::keyPrefix($this->tenant->id(), $purpose).Str::uuid7().'.'.self::EXTENSIONS[$contentType];

        return [
            'path' => $path,
            'upload' => $this->signer->sign($path, self::TTL_SECONDS),
        ];
    }
}
