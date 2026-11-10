<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use Composer\InstalledVersions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Liberu\Foundation\ModuleManager\Cache\RegistryCache;
use Liberu\Foundation\ModuleManager\ModuleActivations;
use Liberu\Foundation\ModuleManager\ModuleLoader;
use Liberu\Foundation\ModuleManager\ModuleValidator;
use Liberu\Foundation\ModuleManagerFilament\Pages\ModuleManagement;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Feature\Modules\Concerns\InstallsFixtureModules;
use Tests\TestCase;

class ModuleManagementPageTest extends TestCase
{
    use InstallsFixtureModules;
    use RefreshDatabase;

    private const MODULE = 'fixture-supplier';

    private function platformAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $user;
    }

    private function nextBootIncludes(string $module): bool
    {
        [$enabled, $disabled] = ModuleActivations::apply(
            (array) config('modules.enabled'),
            (array) config('modules.disabled'),
            app(ModuleActivations::class)->all(),
        );

        return (new ModuleLoader(new ModuleValidator, new RegistryCache, (array) config('modules.paths'), $enabled, $disabled, (array) config('modules.required')))
            ->load()->isBooted($module);
    }

    public function test_it_lists_installed_modules_with_composer_version_and_status(): void
    {
        $this->platformAdmin();

        Livewire::test(ModuleManagement::class)
            ->assertTableColumnStateSet('version', ltrim((string) InstalledVersions::getPrettyVersion('liberusoftware/ecommerce-fixture-supplier'), 'v'), self::MODULE)
            ->assertTableColumnStateSet('status', 'enabled', self::MODULE)
            ->assertTableColumnStateSet('version', '1.8.0', 'module-manager')
            ->assertDontSee(['Stores allowed', 'Choose stores', 'built in', 'not installed', 'ecommerce-printful']);
    }

    public function test_disabling_stops_the_module_registering_and_enabling_brings_it_back(): void
    {
        $this->platformAdmin();
        $this->assertTrue($this->nextBootIncludes(self::MODULE));

        Livewire::test(ModuleManagement::class)
            ->callTableAction('disable', self::MODULE)
            ->assertTableColumnStateSet('status', 'disabled', self::MODULE)
            ->assertTableActionVisible('enable', self::MODULE);

        $this->assertFalse($this->nextBootIncludes(self::MODULE));
        $this->assertTrue(InstalledVersions::isInstalled('liberusoftware/ecommerce-fixture-supplier'));

        Livewire::test(ModuleManagement::class)
            ->callTableAction('enable', self::MODULE)
            ->assertTableColumnStateSet('status', 'enabled', self::MODULE);

        $this->assertTrue($this->nextBootIncludes(self::MODULE));
    }

    public function test_the_modules_an_application_cannot_run_without_cannot_be_turned_off(): void
    {
        $this->platformAdmin();

        Livewire::test(ModuleManagement::class)
            ->assertTableActionHidden('disable', 'tenancy')
            ->assertTableActionHidden('disable', 'module-manager')
            ->assertTableActionHidden('disable', 'module-manager-filament');
    }

    public function test_only_staff_who_manage_modules_can_reach_it(): void
    {
        $staff = User::factory()->create();
        $staff->givePermissionTo(Permission::firstOrCreate(['name' => 'access_admin_panel', 'guard_name' => 'web']));
        $this->actingAs($staff);
        $this->assertFalse(ModuleManagement::canAccess());

        $staff->givePermissionTo(Permission::firstOrCreate(['name' => 'manage_modules', 'guard_name' => 'web']));
        $this->assertTrue(ModuleManagement::canAccess());
    }
}
