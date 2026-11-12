<?php

namespace Tests\Feature\Robit;

use App\Actions\Jetstream\CreateTeam as CreateTeamAction;
use App\Filament\App\Pages\CreateTeam as RegisterTenantPage;
use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use App\Support\Installation;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Http\Livewire\CreateTeamForm;
use Livewire\Livewire;
use Tests\TestCase;

// RH-M3-01 / S-01: one Robit merchant; nobody opens a second one by calling the endpoints directly.
class SingleMerchantBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private Team $robit;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ecommerce.installation.mode' => Installation::SINGLE]);
        $this->robit = Team::factory()->create(['personal_team' => false]);
        Store::factory()->create(['team_id' => $this->robit->id]);
    }

    private function counts(): array
    {
        return [Team::count(), Store::withoutGlobalScopes()->count()];
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['current_team_id' => $this->robit->id]);
        $this->robit->users()->attach($user, ['role' => $role]);

        return $user;
    }

    public function test_a_customer_cannot_open_the_jetstream_team_form(): void
    {
        $this->actingAs(User::factory()->create())->get('/teams/create')->assertForbidden();
    }

    public function test_a_customer_cannot_submit_the_jetstream_team_form(): void
    {
        $before = $this->counts();

        Livewire::actingAs(User::factory()->create())
            ->test(CreateTeamForm::class)
            ->set('state.name', 'Second merchant')
            ->call('createTeam')
            ->assertForbidden();

        $this->assertSame($before, $this->counts());
    }

    public function test_a_customer_cannot_open_the_panel_store_registration(): void
    {
        $this->actingAs(User::factory()->create())->get('/app/new')->assertForbidden();
    }

    public function test_a_customer_cannot_submit_the_panel_store_registration(): void
    {
        $before = $this->counts();
        Filament::setCurrentPanel(Filament::getPanel('app'));

        // Filament answers a page the user may not view with 404.
        Livewire::actingAs(User::factory()->create())
            ->test(RegisterTenantPage::class)
            ->assertNotFound();

        $this->assertSame($before, $this->counts());
    }

    public function test_robit_staff_cannot_open_a_second_merchant(): void
    {
        $before = $this->counts();
        $admin = $this->staff('admin');

        $this->actingAs($admin)->get('/teams/create')->assertForbidden();
        $this->actingAs($admin)->get('/app/new')->assertNotFound();
        $this->actingAs($admin)->get('/admin/teams/create')->assertForbidden();
        $this->actingAs($admin)->get('/admin/stores/create')->assertForbidden();

        $this->assertSame($before, $this->counts());
    }

    // Store staff work in /app; /admin is platform-wide (menus, roles, stores, modules) and needs a platform grant.
    public function test_robit_staff_reach_no_platform_admin_page(): void
    {
        $uris = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'filament.admin.')
                && ! str_contains((string) $route->getName(), '.auth.')
                && in_array('GET', $route->methods(), true)
                && $route->parameterNames() === [])
            ->map(fn ($route) => '/'.$route->uri())
            ->values();

        $this->assertGreaterThan(20, $uris->count());

        foreach (['admin', 'catalogue', 'orders', 'viewer'] as $role) {
            $user = $this->staff($role);

            foreach ($uris as $uri) {
                $status = $this->actingAs($user)->get($uri)->status();
                $this->assertContains($status, [403, 404], "{$role} reached {$uri} ({$status})");
            }
        }
    }

    public function test_the_create_team_action_refuses_without_a_platform_grant(): void
    {
        $this->expectException(AuthorizationException::class);

        app(CreateTeamAction::class)->create($this->staff('admin'), ['name' => 'Second merchant']);
    }

    public function test_signing_up_as_a_customer_opens_no_store(): void
    {
        $before = Store::withoutGlobalScopes()->count();

        $this->post('/register', [
            'name' => 'New Customer',
            'email' => 'new.customer@example.test',
            'password' => 'Long-Test-Passw0rd!',
            'password_confirmation' => 'Long-Test-Passw0rd!',
        ])->assertRedirect();

        $user = User::where('email', 'new.customer@example.test')->sole();
        $this->assertSame($before, Store::withoutGlobalScopes()->count());
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('app')));
    }
}
