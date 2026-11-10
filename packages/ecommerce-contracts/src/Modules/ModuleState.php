<?php

namespace Liberu\Ecommerce\Contracts\Modules;

/**
 * A module's view of its own state. A team enables and configures a module, and every store of the
 * team inherits it, so a store and its team give the same answer. A null store means the store of
 * the current request.
 */
interface ModuleState
{
    /** Null wherever no single store is current, such as the panel of a team with several stores. */
    public function currentStoreId(): ?int;

    /** The team of the current request: the storefront's owner, or the merchant panel's team. Null on the platform. */
    public function currentTeamId(): ?int;

    /** Installed and booted, available to the store's team, enabled by it, and fully configured. */
    public function isActive(string $module, ?int $storeId = null): bool;

    /** As isActive(), for a team rather than one of its stores. */
    public function isActiveForTeam(string $module, int $teamId): bool;

    /**
     * @return array<string, mixed> decrypted settings
     *
     * @throws ModuleNotActive
     */
    public function settings(string $module, ?int $storeId = null): array;

    /**
     * @return array<string, mixed> decrypted settings, the same for every store of the team
     *
     * @throws ModuleNotActive
     */
    public function teamSettings(string $module, int $teamId): array;
}
