<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles globales (team_id null); se asignan a cada usuario dentro de su tienda.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        setPermissionsTeamId(null);

        $manageProducts = Permission::findOrCreate('products.manage', 'web');
        $manageStore = Permission::findOrCreate('store.manage', 'web');
        $manageUsers = Permission::findOrCreate('users.manage', 'web');

        Role::findOrCreate('owner', 'web')->syncPermissions([$manageProducts, $manageStore, $manageUsers]);
        Role::findOrCreate('editor', 'web')->syncPermissions([$manageProducts]);
    }
}
