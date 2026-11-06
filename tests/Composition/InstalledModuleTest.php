<?php

namespace Tests\Composition;

use App\Models\Store;
use App\Models\Team;
use App\Support\Modules\TeamModules;
use Filament\Facades\Filament;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Liberu\Ecommerce\Contracts\Modules\SettingField;
use Liberu\Foundation\ModuleManager\ModuleInventory;
use Tests\TestCase;

/**
 * What every installed module must satisfy in a real host. Not in the default suite: it runs after a
 * real `composer require`, with MODULE_UNDER_TEST and MODULES_ENABLED set. See verify-module.sh.
 */
class InstalledModuleTest extends TestCase
{
    use RefreshDatabase;

    private function module(): string
    {
        $module = (string) env('MODULE_UNDER_TEST');
        $this->assertNotSame('', $module, 'Set MODULE_UNDER_TEST.');

        return $module;
    }

    public function test_it_boots_through_the_manager_alone(): void
    {
        $inventory = app(ModuleInventory::class);
        $module = $this->module();

        $this->assertTrue($inventory->isBooted($module), implode(' ', $inventory->problems[$module] ?? ['not booted']));
        $this->assertFalse($inventory->isRequired($module), 'an integration must be optional');
        $this->assertSame([], $inventory->problems);
        $this->assertNotContains($inventory->manifests[$module]->provider(), app(PackageManifest::class)->providers());
    }

    public function test_its_migrations_have_run(): void
    {
        $this->artisan('migrate:status', ['--pending' => 1])->assertSuccessful();
    }

    public function test_it_is_off_until_a_team_enables_it_and_only_for_that_teams_stores(): void
    {
        $modules = app(TeamModules::class);
        $module = $this->module();
        [$teamA, $teamB] = Team::factory()->count(2)->create();
        $a = Store::factory()->create(['team_id' => $teamA->id]);
        $b = Store::factory()->create(['team_id' => $teamB->id]);

        $this->assertArrayHasKey($module, $modules->definitions());
        $this->assertFalse($modules->isActive($module, $a->id));

        $modules->setAvailable($module, true);
        $modules->configure($module, $teamA->id, $this->validSettings($module));
        $modules->enable($module, $teamA->id);

        $this->assertTrue($modules->isActive($module, $a->id));
        $this->assertFalse($modules->isActive($module, $b->id));

        $modules->disable($module, $teamA->id);
        $this->assertFalse($modules->isActive($module, $a->id));
    }

    public function test_the_application_still_serves_and_both_panels_load(): void
    {
        $this->get('/health')->assertSuccessful();
        $this->assertNotNull(Filament::getPanel('admin'));
        $this->assertNotNull(Filament::getPanel('app'));
        $this->assertNotEmpty(Filament::getPanel('app')->getPages());
    }

    /** @return array<string, mixed> */
    private function validSettings(string $module): array
    {
        $settings = [];

        foreach (app(TeamModules::class)->definition($module)->settings() as $field) {
            $settings[$field->key] = match ($field->type) {
                SettingField::URL => 'https://example.test',
                SettingField::BOOLEAN => false,
                SettingField::SELECT => array_key_first($field->options),
                default => 'test-value',
            };
        }

        return $settings;
    }
}
