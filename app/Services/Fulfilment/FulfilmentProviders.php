<?php

namespace App\Services\Fulfilment;

use App\Support\Modules\StoreModules;
use Illuminate\Contracts\Container\Container;
use Liberu\Ecommerce\Contracts\Fulfilment\FulfilmentProvider;
use Liberu\Ecommerce\Contracts\Modules\ModuleNotActive;

/** Finds the provider for a product's supplier_id, for one store. Nothing else names a supplier. */
final class FulfilmentProviders
{
    /** @var array<string, FulfilmentProvider>|null */
    private ?array $providers = null;

    public function __construct(private readonly Container $container, private readonly StoreModules $modules) {}

    /** @return array<string, FulfilmentProvider> */
    public function all(): array
    {
        if ($this->providers === null) {
            $this->providers = [];

            foreach ($this->container->tagged(FulfilmentProvider::class) as $provider) {
                $this->providers[$provider->supplierKey()] = $provider;
            }
        }

        return $this->providers;
    }

    /** @return array<string, FulfilmentProvider> the providers this store has active */
    public function activeFor(int $storeId): array
    {
        return array_filter($this->all(), fn (FulfilmentProvider $p) => $this->modules->isActive($p->moduleKey(), $storeId));
    }

    public function canFulfil(string $supplier, ?int $storeId): bool
    {
        $provider = $this->all()[$supplier] ?? null;

        return $provider !== null && $storeId !== null && $this->modules->isActive($provider->moduleKey(), $storeId);
    }

    /** @throws ModuleNotActive */
    public function provider(string $supplier, int $storeId): FulfilmentProvider
    {
        if (! $this->canFulfil($supplier, $storeId)) {
            throw ModuleNotActive::for($supplier, $storeId);
        }

        return $this->all()[$supplier];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ModuleNotActive
     */
    public function settings(FulfilmentProvider $provider, int $storeId): array
    {
        return $this->modules->settings($provider->moduleKey(), $storeId);
    }
}
