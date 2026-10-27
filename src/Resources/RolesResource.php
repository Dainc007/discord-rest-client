<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Resources;

use Dainc007\DiscordRestClient\Data\Role;
use Dainc007\DiscordRestClient\Enums\Permission;
use Dainc007\DiscordRestClient\Http\Route;
use InvalidArgumentException;

/**
 * Guild roles. The bot can only change roles below its own highest role;
 * Discord answers anything else with 403 (MissingPermissionException).
 */
final class RolesResource extends Resource
{
    /**
     * @param  list<Permission>|null  $permissions  null keeps Discord's default for new roles
     * @param  array<string, mixed>  $attributes  Extra Discord fields: icon, unicode_emoji, ...
     */
    public function create(
        string $guildId,
        string $name,
        ?array $permissions = null,
        int $color = 0,
        bool $hoist = false,
        bool $mentionable = false,
        array $attributes = [],
        ?string $reason = null,
    ): Role {
        if ($color < 0 || $color > 0xFFFFFF) {
            throw new InvalidArgumentException('Role colour must be between 0 and 0xFFFFFF.');
        }

        $body = ['name' => $name, 'color' => $color, 'hoist' => $hoist, 'mentionable' => $mentionable] + $attributes;

        if ($permissions !== null) {
            $body['permissions'] = Permission::combine(...$permissions);
        }

        return Role::fromArray($this->requestArray(
            Route::post('/guilds/{guild}/roles', ['guild' => $guildId]),
            $body,
            auditReason: $reason,
        ));
    }

    /**
     * @param  array<string, mixed>  $attributes  Discord fields to change: name, permissions, color, hoist, ...
     */
    public function edit(string $guildId, string $roleId, array $attributes, ?string $reason = null): Role
    {
        if (isset($attributes['permissions']) && is_array($attributes['permissions'])) {
            $attributes['permissions'] = Permission::combine(...array_map(
                static fn (mixed $permission): Permission => $permission instanceof Permission
                    ? $permission
                    : throw new InvalidArgumentException('permissions must be a list of Permission cases.'),
                array_values($attributes['permissions']),
            ));
        }

        return Role::fromArray($this->requestArray(
            Route::patch('/guilds/{guild}/roles/{role}', ['guild' => $guildId, 'role' => $roleId]),
            $attributes,
            auditReason: $reason,
        ));
    }

    public function delete(string $guildId, string $roleId, ?string $reason = null): void
    {
        $this->requestNothing(
            Route::delete('/guilds/{guild}/roles/{role}', ['guild' => $guildId, 'role' => $roleId]),
            auditReason: $reason,
        );
    }

    /**
     * Change role positions. Returns the guild's roles in their new order.
     *
     * @param  array<string, int>  $positions  role id => position
     * @return list<Role>
     */
    public function reorder(string $guildId, array $positions, ?string $reason = null): array
    {
        return $this->mapList(
            $this->requestArray(
                Route::patch('/guilds/{guild}/roles', ['guild' => $guildId]),
                $this->positionsBody($positions),
                auditReason: $reason,
            ),
            Role::fromArray(...),
        );
    }
}
