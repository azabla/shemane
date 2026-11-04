<?php

namespace Liberu\Ecommerce\FixtureSupplier;

use Liberu\Ecommerce\Contracts\Modules\ModuleDefinition;
use Liberu\Ecommerce\Contracts\Modules\ProvidesPanelPlugins;
use Liberu\Ecommerce\Contracts\Modules\ReactsToActivation;
use Liberu\Ecommerce\Contracts\Modules\SettingField;
use Liberu\Ecommerce\FixtureSupplier\Filament\FixturePlugin;

class FixtureDefinition implements ModuleDefinition, ProvidesPanelPlugins, ReactsToActivation
{
    /** @var list<array{string, int}> */
    public static array $events = [];

    public function key(): string
    {
        return 'fixture-supplier';
    }

    public function label(): string
    {
        return 'Fixture Supplier';
    }

    public function description(): string
    {
        return 'Acceptance fixture for the module system.';
    }

    public function settings(): array
    {
        return [
            new SettingField('api_token', 'API token', SettingField::SECRET, required: true),
            new SettingField('region', 'Region', SettingField::SELECT, required: true, options: ['eu' => 'Europe', 'us' => 'United States', 'blocked' => 'Blocked']),
            new SettingField('sandbox', 'Sandbox', SettingField::BOOLEAN),
        ];
    }

    public function validateSettings(array $settings): array
    {
        return ($settings['region'] ?? null) === 'blocked' ? ['region' => 'This region cannot be used.'] : [];
    }

    public function panelPlugins(string $panel): array
    {
        return $panel === 'app' ? [new FixturePlugin] : [];
    }

    public function activated(int $storeId): void
    {
        self::$events[] = ['activated', $storeId];
    }

    public function deactivated(int $storeId): void
    {
        self::$events[] = ['deactivated', $storeId];
    }
}
