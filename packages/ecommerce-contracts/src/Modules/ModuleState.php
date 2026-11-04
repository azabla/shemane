<?php

namespace Liberu\Ecommerce\Contracts\Modules;

/** A module's view of its own per-store state. A null store means the store of the current request. */
interface ModuleState
{
    public function currentStoreId(): ?int;

    /** Installed and booted, available to the store, enabled by it, and fully configured. */
    public function isActive(string $module, ?int $storeId = null): bool;

    /**
     * @return array<string, mixed> decrypted settings
     *
     * @throws ModuleNotActive
     */
    public function settings(string $module, ?int $storeId = null): array;
}
