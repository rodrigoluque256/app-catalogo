<?php

declare(strict_types=1);

namespace App\Application\Platform\Services;

use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class PlatformAuditLogger
{
    public function __construct(
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(string $action, ?PlatformAdmin $admin, ?string $storeId = null, array $metadata = []): void
    {
        PlatformAuditLog::query()->create([
            'platform_admin_id' => $admin?->id,
            'action' => $action,
            'store_id' => $storeId,
            'metadata' => $metadata,
            'ip' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 250, ''),
        ]);
    }
}
