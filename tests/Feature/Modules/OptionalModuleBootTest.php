<?php

namespace Tests\Feature\Modules;

use App\Support\Modules\ModuleInventory;
use Tests\Feature\Modules\Concerns\InstallsFixtureModules;
use Tests\TestCase;

class OptionalModuleBootTest extends TestCase
{
    use InstallsFixtureModules;

    protected function fixtureModulesEnabled(): array
    {
        return match ($this->name()) {
            'test_an_installed_module_that_nobody_enabled_does_not_boot' => [],
            default => ['fixture-supplier', 'fixture-throws'],
        };
    }

    protected function fixtureModulesDisabled(): array
    {
        return $this->name() === 'test_disabled_wins_over_enabled' ? ['fixture-supplier'] : [];
    }

    public function test_an_enabled_installed_module_boots_through_the_manager(): void
    {
        $this->assertTrue(app(ModuleInventory::class)->isBooted('fixture-supplier'));
        $this->assertTrue(app('fixture-supplier.booted'));
    }

    public function test_an_installed_module_that_nobody_enabled_does_not_boot(): void
    {
        $inventory = app(ModuleInventory::class);

        $this->assertArrayHasKey('fixture-supplier', $inventory->manifests);
        $this->assertSame('disabled', $inventory->status('fixture-supplier'));
        $this->assertFalse(app()->bound('fixture-supplier.booted'));
    }

    public function test_disabled_wins_over_enabled(): void
    {
        $this->assertSame('disabled', app(ModuleInventory::class)->status('fixture-supplier'));
        $this->assertFalse(app()->bound('fixture-supplier.booted'));
    }

    public function test_a_provider_that_throws_is_isolated_and_the_application_still_serves(): void
    {
        $inventory = app(ModuleInventory::class);

        $this->assertSame('invalid', $inventory->status('fixture-throws'));
        $this->assertStringContainsString('fixture provider failed', $inventory->problems['fixture-throws'][0]);
        $this->assertTrue($inventory->isBooted('fixture-supplier'));
        $this->get('/health')->assertSuccessful();
    }
}
