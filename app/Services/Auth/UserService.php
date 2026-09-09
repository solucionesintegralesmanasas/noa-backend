<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Servicio para la gestión de usuarios.
 *
 * Este recurso utiliza 'uuid' como identificador público y posee soporte para 'company_uuid'.
 * Se implementa la lógica multi-tenant nativa de BaseService.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class UserService extends BaseService
{
    /**
     * Campos sobre los cuales se aplica la búsqueda libre.
     *
     * @var array<string>
     */
    protected array $searchableFields = [
        'name',
        'email',
        'user_name',
    ];

    /**
     * Retorna la instancia del modelo que este servicio gestiona.
     */
    protected function getModelInstance(): Model
    {
        return new User;
    }

    /**
     * Método getAllUsersWithPagination.
     */
    public function getAllUsersWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        $paginator = $this->getPaginatedData($perPage, $page, $search, $companyUuid, ['roles']);
        // Añadir nombres de roles para el frontend
        $paginator->getCollection()->transform(function ($user) {
            $roleNames = $user->getRoleNames()->toArray();
            $roleIds = $user->roles->pluck('id')->toArray();
            $user->unsetRelation('roles');
            $user->setAttribute('roles', $roleNames);
            $user->setAttribute('role_ids', $roleIds);

            return $user;
        });

        return $paginator;
    }

    /**
     * Método getAllUsers.
     */
    public function getAllUsers(): Collection
    {
        $users = $this->all(['*'], ['roles']);
        $users->each(function ($user) {
            $roleNames = $user->getRoleNames()->toArray();
            $user->unsetRelation('roles');
            $user->setAttribute('roles', $roleNames);
        });

        return $users;
    }

    /**
     * Método getUserByUuid.
     */
    public function getUserByUuid(string $uuid, array $relations = []): ?Model
    {
        $relations = array_unique(array_merge($relations, ['roles']));
        $user = $this->findByUuid($uuid, ['*'], $relations);
        if ($user) {
            $roleNames = $user->getRoleNames()->toArray();
            $user->unsetRelation('roles');
            $user->setAttribute('roles', $roleNames);
        }

        return $user;
    }

    /**
     * Método createUser.
     */
    public function createUser(array $data): Model
    {
        return $this->transaction(fn () => User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'email_verified_at' => now(),
            'user_name' => $data['user_name'] ?? null,
            'password' => $data['password'],
            'verification_code' => $data['verification_code'] ?? null,
            'verification_code_expires_at' => $data['verification_code_expires_at'] ?? null,
            'failed_login_attempts' => $data['failed_login_attempts'] ?? 0,
            'locked_until' => $data['locked_until'] ?? null,
            'company_uuid' => $data['company_uuid'] ?? null,
            'third_party_uuid' => $data['third_party_uuid'] ?? null,
            'google_id' => $data['google_id'] ?? null,
            'google_email' => $data['google_email'] ?? null,
            'google_drive_refresh_token' => $data['google_drive_refresh_token'] ?? null,
            'status' => $data['status'] ?? 1,
        ]));
    }

    /**
     * Método updateUser.
     * Si se envía `roles` sincroniza los roles RBAC (permite quitar).
     */
    public function updateUser(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid, ['*'], ['roles']);

            $payload = [
                'name' => $data['name'] ?? $record->name,
                'email' => $data['email'] ?? $record->email,
                'user_name' => $data['user_name'] ?? $record->user_name,
                'verification_code' => $data['verification_code'] ?? $record->verification_code,
                'verification_code_expires_at' => $data['verification_code_expires_at'] ?? $record->verification_code_expires_at,
                'failed_login_attempts' => $data['failed_login_attempts'] ?? $record->failed_login_attempts,
                'locked_until' => $data['locked_until'] ?? $record->locked_until,
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'third_party_uuid' => $data['third_party_uuid'] ?? $record->third_party_uuid,
                'google_id' => $data['google_id'] ?? $record->google_id,
                'google_email' => $data['google_email'] ?? $record->google_email,
                'google_drive_refresh_token' => $data['google_drive_refresh_token'] ?? $record->google_drive_refresh_token,
                'status' => $data['status'] ?? $record->status,
            ];

            // Sólo actualizar la contraseña si se envía explícitamente en $data,
            // para evitar doble-hash del valor ya almacenado (cast 'hashed').
            if (isset($data['password'])) {
                $payload['password'] = $data['password'];
            }

            $record->update($payload);

            // Sincronización de roles RBAC - permite quitar roles enviando array sin ese rol
            if (array_key_exists('roles', $data)) {
                $rolesToSync = array_values(array_filter((array) $data['roles']));
                $record->syncRoles($rolesToSync);
            }

            $record = $record->fresh(['roles']);
            $roleNames = $record->getRoleNames()->toArray();
            $record->unsetRelation('roles');
            $record->setAttribute('roles', $roleNames);

            return $record;
        });
    }

    /**
     * Método deleteUser.
     */
    public function deleteUser(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException(
                "No se pudo eliminar el usuario con UUID: {$uuid}"
            );
        }
    }

    /**
     * Método toggleUserStatus.
     */
    public function toggleUserStatus(string $uuid): bool
    {
        return $this->toggleStatus($uuid, 'status');
    }
}
