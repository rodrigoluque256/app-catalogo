<?php

declare(strict_types=1);

// El paquete hace merge de su config por defecto: acá solo se sobrescriben las claves que cambian.
return [
    // team = store. El team activo lo fija ResolveTenantFromUser con setPermissionsTeamId().
    'teams' => true,
];
