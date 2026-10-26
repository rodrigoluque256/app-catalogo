<?php

declare(strict_types=1);

namespace App\Domain\Store\Models;

use App\Domain\Product\Models\Category;
use App\Domain\Product\Models\Product;
use App\Domain\Store\Enums\StoreStatus;
use App\Models\User;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * Tenant de la plataforma. No usa BelongsToStore: es la raíz del aislamiento.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $custom_domain
 * @property string|null $logo_path
 * @property string $primary_color
 * @property string $accent_color
 * @property string|null $whatsapp_number
 * @property string|null $whatsapp_message_template
 * @property string $currency
 * @property string $locale
 * @property StoreStatus $status
 * @property array<string, mixed> $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'custom_domain',
        'logo_path',
        'primary_color',
        'accent_color',
        'whatsapp_number',
        'whatsapp_message_template',
        'currency',
        'locale',
        'status',
        'settings',
    ];

    protected $attributes = [
        'status' => 'active',
        'settings' => '{}',
    ];

    public function isActive(): bool
    {
        return $this->status === StoreStatus::Active;
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Usuarios con rol owner en esta tienda. Consulta directa a model_has_roles (team = la tienda) porque la
     * relación roles() de Spatie filtra por el team del request, que en el super panel no existe.
     *
     * @return HasMany<User, $this>
     */
    public function owners(): HasMany
    {
        return $this->users()->whereExists(fn (QueryBuilder $query) => $query->selectRaw('1')
            ->from('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereColumn('model_has_roles.model_id', 'users.id')
            ->whereColumn('model_has_roles.team_id', 'users.store_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->where('roles.name', 'owner'));
    }

    protected static function newFactory(): StoreFactory
    {
        return StoreFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
            'settings' => 'array',
        ];
    }
}
