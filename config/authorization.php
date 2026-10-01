<?php

/*
|--------------------------------------------------------------------------
| Autorización por recurso (middleware `authz`)
|--------------------------------------------------------------------------
|
| Deriva el permiso Spatie de cada ruta del grupo `auth:sanctum` a partir del
| nombre de la ruta (api.v1.<módulo>.<recurso>.<acción>) y del método HTTP:
|
|   GET            -> <recurso>.index | .profile | .view | .show   (cualquiera sirve)
|   POST           -> <recurso>.create
|   PUT / PATCH    -> <recurso>.update
|   DELETE         -> <recurso>.delete
|
| Las rutas que ya llevan `permission:` o `role:` propios no se vuelven a evaluar.
| SUPERADMIN pasa siempre. Lo que no se pueda mapear a un permiso existente queda
| sin evaluar (ver `php artisan authz:report`).
*/

return [

    /*
     | false = modo auditoría: NO bloquea, solo registra un warning por cada acceso que
     | se habría rechazado. true = responde 403. Activar solo tras revisar el log en
     | producción (AUTHZ_ENFORCE=true en el .env).
     */
    'enforce' => (bool) env('AUTHZ_ENFORCE', false),

    // Roles que siempre pasan.
    'bypass_roles' => ['SUPERADMIN'],

    /*
     | Prefijos de nombre de ruta que no se evalúan (acceso propio del usuario o ya
     | protegidos por otro mecanismo).
     */
    'exempt_prefixes' => [
        'api.v1.2fa',
        'api.v1.auth',            // /auth/* ya exige rol en sus escrituras
        'api.v1.dashboard',
        'api.v1.notifications',
        'api.v1.signatures',
        'api.v1.tracking',        // ya usa permission:locations.*
        'api.v1.integrations',
        'api.v1.public',
    ],

    /*
     | Segmento de la ruta (penúltimo del nombre, en kebab-case) -> recurso de permiso,
     | solo para los casos que el nombre por sí solo no resuelve. El resto se deduce:
     | bank-details -> bank_details, fuecs -> fuec, vehicles -> vehicles...
     */
    'resource_overrides' => [
        'rup' => 'rup_records',
        'employment-contracts' => 'employmentContracts',
        'social-security-contributions' => 'security_contributions',
        'capacity-inventories' => 'capacity_inventory',
        'object-contracts' => 'objects_contracts',
        'owners' => 'vehicles',
        'owner-drivers' => 'vehicles',
        'vehicles-branches' => 'vehicles',
        'fuec-passengers' => 'fuec',
    ],

    /*
     | Catálogos de referencia (api.v1.catalogs.*): datos compartidos. La lectura queda
     | abierta a cualquier usuario autenticado (los formularios los necesitan) y la
     | escritura exige uno de estos roles.
     */
    'catalog_write_roles' => ['SUPERADMIN'],
];
