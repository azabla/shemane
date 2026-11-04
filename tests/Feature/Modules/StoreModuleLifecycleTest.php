<?php

namespace Tests\Feature\Modules;

use App\Filament\Admin\Pages\Modules;
use App\Filament\App\Pages\Integrations;
use App\Models\Channel;
use App\Models\ChannelDomain;
use App\Models\Store;
use App\Models\StoreModule;
use App\Models\Team;
use App\Models\User;
use App\Support\Modules\ModuleActivationRefused;
use App\Support\Modules\ModuleInventory;
use App\Support\Modules\StoreModules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Liberu\Ecommerce\Contracts\Modules\Abilities;
use Liberu\Ecommerce\Contracts\Modules\ModuleNotActive;
use Liberu\Ecommerce\Contracts\Modules\ModuleState;
use Liberu\Ecommerce\FixtureSupplier\Filament\FixturePage;
use Liberu\Ecommerce\FixtureSupplier\FixtureClient;
use Liberu\Ecommerce\FixtureSupplier\FixtureDefinition;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Modules\Concerns\InstallsFixtureModules;
use Tests\TestCase;

/** The acceptance test of the module system: one installed module, two stores, independently. */
class StoreModuleLifecycleTest extends TestCase
{
    use InstallsFixtureModules;
    use RefreshDatabase;

    private const MODULE = 'fixture-supplier';

    private StoreModules $modules;

    private Store $storeA;

    private Store $storeB;

    private Team $teamA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modules = app(StoreModules::class);
        FixtureDefinition::$events = [];
        $this->teamA = Team::factory()->create();
        $this->storeA = $this->storeOn('a.shop.test', $this->teamA);
        $this->storeB = $this->storeOn('b.shop.test', Team::factory()->create());
    }

    private function storeOn(string $host, Team $team): Store
    {
        $store = Store::factory()->create(['team_id' => $team->id]);
        $channel = Channel::factory()->create(['store_id' => $store->id]);
        ChannelDomain::factory()->primary()->create(['channel_id' => $channel->id, 'host' => $host]);

        return $store;
    }

    private function activate(Store $store, string $region = 'eu', string $token = 'secret-token'): void
    {
        $this->modules->setAvailable(self::MODULE, $store->id, true);
        $this->modules->configure(self::MODULE, $store->id, ['api_token' => $token, 'region' => $region]);
        $this->modules->enable(self::MODULE, $store->id);
    }

    private function merchant(string $role = 'admin', ?Team $team = null): User
    {
        $team ??= $this->teamA;
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($team);

        return $user;
    }

    private function platformAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $user;
    }

    public function test_it_is_installed_discovered_and_booted_but_off_for_every_store(): void
    {
        $this->assertTrue(app(ModuleInventory::class)->isBooted(self::MODULE));
        $this->assertArrayHasKey(self::MODULE, $this->modules->definitions());
        $this->assertSame('unavailable', $this->modules->status(self::MODULE, $this->storeA->id));
        $this->assertFalse($this->modules->isActive(self::MODULE, $this->storeA->id));
    }

    public function test_its_migrations_are_loaded_with_the_module(): void
    {
        $this->artisan('migrate');

        $this->assertTrue(Schema::hasTable('fixture_supplier_notes'));
    }

    public function test_one_store_can_enable_it_while_another_does_not(): void
    {
        $this->activate($this->storeA);

        $this->assertTrue($this->modules->isActive(self::MODULE, $this->storeA->id));
        $this->assertFalse($this->modules->isActive(self::MODULE, $this->storeB->id));
    }

    public function test_configuration_is_isolated_between_stores(): void
    {
        $this->activate($this->storeA, 'eu', 'token-a');
        $this->activate($this->storeB, 'us', 'token-b');

        $this->assertSame(['api_token' => 'token-a', 'region' => 'eu', 'sandbox' => null], $this->modules->settings(self::MODULE, $this->storeA->id));
        $this->assertSame('token-b', $this->modules->settings(self::MODULE, $this->storeB->id)['api_token']);
    }

    public function test_its_routes_exist_only_for_the_store_that_activated_it(): void
    {
        $this->activate($this->storeA);

        $this->get('http://a.shop.test/fixture-supplier/region')->assertOk()->assertJson(['region' => 'eu']);
        $this->get('http://b.shop.test/fixture-supplier/region')->assertNotFound();
    }

    public function test_its_services_refuse_a_store_without_it(): void
    {
        $this->activate($this->storeA);
        $client = app(FixtureClient::class);

        $this->assertSame('eu', $client->regionFor($this->storeA->id));
        $this->expectException(ModuleNotActive::class);
        $client->regionFor($this->storeB->id);
    }

    public function test_disabling_for_one_store_leaves_the_other_working_and_keeps_its_settings(): void
    {
        $this->activate($this->storeA);
        $this->activate($this->storeB, 'us');

        app(StoreModules::class)->disable(self::MODULE, $this->storeA->id);

        $this->get('http://a.shop.test/fixture-supplier/region')->assertNotFound();
        $this->get('http://b.shop.test/fixture-supplier/region')->assertOk()->assertJson(['region' => 'us']);

        $this->modules->enable(self::MODULE, $this->storeA->id);
        $this->assertSame('eu', $this->modules->settings(self::MODULE, $this->storeA->id)['region']);
        $this->assertSame([['activated', $this->storeA->id], ['activated', $this->storeB->id], ['deactivated', $this->storeA->id], ['activated', $this->storeA->id]], FixtureDefinition::$events);
    }

    public function test_withdrawing_availability_turns_it_off_for_that_store(): void
    {
        $this->activate($this->storeA);

        $this->modules->setAvailable(self::MODULE, $this->storeA->id, false);

        $this->assertSame('unavailable', $this->modules->status(self::MODULE, $this->storeA->id));
        $this->assertFalse(StoreModule::withoutGlobalScopes()->where('store_id', $this->storeA->id)->sole()->enabled);
    }

    public function test_a_module_not_available_to_the_store_cannot_be_enabled_or_configured(): void
    {
        $this->assertRefused(fn () => $this->modules->enable(self::MODULE, $this->storeA->id));
        $this->assertRefused(fn () => $this->modules->configure(self::MODULE, $this->storeA->id, ['api_token' => 'x', 'region' => 'eu']));
    }

    public function test_a_module_that_is_not_installed_cannot_be_made_available_or_enabled(): void
    {
        $this->assertRefused(fn () => $this->modules->setAvailable('nothing-installed', $this->storeA->id, true));
        $this->assertRefused(fn () => $this->modules->enable('nothing-installed', $this->storeA->id));
    }

    public function test_it_cannot_be_enabled_until_required_settings_exist(): void
    {
        $this->modules->setAvailable(self::MODULE, $this->storeA->id, true);

        $this->assertRefused(fn () => $this->modules->enable(self::MODULE, $this->storeA->id));
    }

    public function test_configuration_is_validated_by_the_fields_and_by_the_module(): void
    {
        $this->modules->setAvailable(self::MODULE, $this->storeA->id, true);

        $this->assertInvalid(['region' => 'nowhere', 'api_token' => 'x'], 'region');
        $this->assertInvalid(['region' => 'blocked', 'api_token' => 'x'], 'region');
        $this->assertInvalid(['region' => 'eu'], 'api_token');
    }

    public function test_undeclared_settings_are_not_stored(): void
    {
        $this->modules->setAvailable(self::MODULE, $this->storeA->id, true);
        $this->modules->configure(self::MODULE, $this->storeA->id, ['api_token' => 'x', 'region' => 'eu', 'evil' => 'y']);

        $this->assertArrayNotHasKey('evil', StoreModule::withoutGlobalScopes()->sole()->settings);
    }

    public function test_secrets_are_encrypted_at_rest_kept_when_blank_and_never_redisplayed(): void
    {
        $this->activate($this->storeA, 'eu', 'very-secret-token');

        $raw = DB::table('store_modules')->where('store_id', $this->storeA->id)->value('settings');
        $this->assertStringNotContainsString('very-secret-token', (string) $raw);
        $this->assertArrayNotHasKey('settings', StoreModule::withoutGlobalScopes()->first()->toArray());

        $this->modules->configure(self::MODULE, $this->storeA->id, ['api_token' => '', 'region' => 'us']);
        $this->assertSame('very-secret-token', $this->modules->settings(self::MODULE, $this->storeA->id)['api_token']);
        $this->assertArrayNotHasKey('api_token', $this->modules->displayableSettings(self::MODULE, $this->storeA->id));
    }

    public function test_state_does_not_leak_between_requests_in_a_long_lived_worker(): void
    {
        $this->activate($this->storeA);

        // Same application instance throughout, as under Octane; only the host changes.
        $this->get('http://a.shop.test/fixture-supplier/region')->assertOk();
        $this->get('http://b.shop.test/fixture-supplier/region')->assertNotFound();
        $this->get('http://a.shop.test/fixture-supplier/region')->assertOk();

        $first = app(StoreModules::class);
        app()->forgetScopedInstances();
        $this->assertNotSame($first, app(StoreModules::class));
        $this->assertSame(app(ModuleState::class), app(StoreModules::class));
    }

    public function test_the_abilities_follow_the_existing_team_roles_and_never_cross_teams(): void
    {
        $admin = $this->merchant();
        $viewer = $this->merchant('viewer');

        $this->assertTrue($admin->can(Abilities::MANAGE_STORE, $this->storeA->id));
        $this->assertFalse($viewer->can(Abilities::MANAGE_STORE, $this->storeA->id));
        $this->assertFalse($admin->can(Abilities::MANAGE_STORE, $this->storeB->id));
        $this->assertFalse($admin->can(Abilities::MANAGE_STORE, 999999));
    }

    public function test_a_platform_admin_chooses_the_stores_on_the_modules_page(): void
    {
        $this->platformAdmin();

        Livewire::test(Modules::class)
            ->assertCanSeeTableRecords([self::MODULE, 'tenancy'])
            ->callTableAction('stores', self::MODULE, data: ['stores' => [$this->storeA->id]])
            ->assertHasNoTableActionErrors();

        $this->assertSame([$this->storeA->id], app(StoreModules::class)->storesAllowing(self::MODULE));

        Livewire::test(Modules::class)->callTableAction('stores', self::MODULE, data: ['stores' => [$this->storeB->id]]);
        $this->assertSame([$this->storeB->id], app(StoreModules::class)->storesAllowing(self::MODULE));
    }

    public function test_merchants_and_ordinary_users_cannot_reach_the_modules_page(): void
    {
        $this->merchant();
        $this->assertFalse(Modules::canAccess());

        $this->actingAs(User::factory()->create());
        $this->assertFalse(Modules::canAccess());
    }

    public function test_a_merchant_sees_only_what_is_available_then_configures_and_enables_it(): void
    {
        $this->merchant();

        Livewire::test(Integrations::class)->assertCanNotSeeTableRecords([self::MODULE]);

        $this->modules->setAvailable(self::MODULE, $this->storeA->id, true);

        Livewire::test(Integrations::class)
            ->assertCanSeeTableRecords([self::MODULE])
            ->assertTableActionVisible('enable', self::MODULE)
            ->callTableAction('enable', self::MODULE);
        $this->assertFalse(app(StoreModules::class)->isActive(self::MODULE, $this->storeA->id), 'no settings yet');

        Livewire::test(Integrations::class)
            ->callTableAction('configure', self::MODULE, data: ['api_token' => 'merchant-token', 'region' => 'eu', 'sandbox' => false])
            ->assertHasNoTableActionErrors()
            ->callTableAction('enable', self::MODULE);
        app()->forgetScopedInstances();

        $this->assertTrue(app(StoreModules::class)->isActive(self::MODULE, $this->storeA->id));
        $this->assertSame('merchant-token', app(StoreModules::class)->settings(self::MODULE, $this->storeA->id)['api_token']);
    }

    public function test_a_team_member_without_store_management_cannot_use_the_integrations_page(): void
    {
        $this->merchant('viewer');

        $this->assertFalse(Integrations::canAccess());
    }

    public function test_a_merchant_cannot_reach_another_teams_store_through_the_url(): void
    {
        $this->modules->setAvailable(self::MODULE, $this->storeB->id, true);
        $this->merchant();

        Livewire::withQueryParams(['store' => $this->storeB->id])->test(Integrations::class)->assertCanNotSeeTableRecords([self::MODULE]);
    }

    public function test_the_modules_own_filament_page_follows_activation_and_authorization(): void
    {
        $this->merchant();
        $this->assertFalse(FixturePage::canAccess());

        $this->activate($this->storeA);
        app()->forgetScopedInstances();
        $this->assertTrue(FixturePage::canAccess());
        $this->assertContains(FixturePage::class, Filament::getPanel('app')->getPages());

        $this->merchant('viewer');
        $this->assertFalse(FixturePage::canAccess());
    }

    private function assertRefused(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected the module system to refuse this.');
        } catch (ModuleActivationRefused) {
            $this->addToAssertionCount(1);
        }
    }

    private function assertInvalid(array $input, string $field): void
    {
        try {
            $this->modules->configure(self::MODULE, $this->storeA->id, $input);
            $this->fail('Expected validation to fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }
}
