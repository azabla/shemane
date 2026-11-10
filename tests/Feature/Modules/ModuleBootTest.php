<?php

namespace Tests\Feature\Modules;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Liberu\Foundation\ModuleManager\Cache\RegistryCache;
use Liberu\Foundation\ModuleManager\Exceptions\RequiredModuleFailed;
use Liberu\Foundation\ModuleManager\ModuleDiscovery;
use Liberu\Foundation\ModuleManager\ModuleInventory;
use Liberu\Foundation\ModuleManager\ModuleLoader;
use Liberu\Foundation\ModuleManager\ModuleValidator;
use Liberu\Foundation\Tenancy\TenancyServiceProvider;
use Liberu\Foundation\UsersRoles\UsersRolesServiceProvider;
use Tests\TestCase;

/** The real boot, in a separate process wherever the environment is what is being tested. */
class ModuleBootTest extends TestCase
{
    private string $extra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extra = storage_path('framework/testing/modules-'.uniqid());
        File::ensureDirectoryExists($this->extra);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->extra);
        parent::tearDown();
    }

    /** @param array<string, string> $env */
    private function runArtisan(array $env, string ...$arguments): ProcessResult
    {
        return Process::path(base_path())->env($env + ['MODULES_EXTRA_PATHS' => $this->extra, 'MODULES_ENABLED' => '', 'MODULES_DISABLED' => ''])
            ->run(['php', 'artisan', ...$arguments]);
    }

    /** @return array<string, array<string, mixed>> */
    private function listed(array $env = []): array
    {
        $result = $this->runArtisan($env, 'module:list', '--json');
        $this->assertTrue($result->successful(), $result->errorOutput().$result->output());

        return collect(json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR))->keyBy('module')->all();
    }

    private function module(string $directory, array $overrides = [], ?string $rawManifest = null, array $composer = []): void
    {
        File::ensureDirectoryExists("{$this->extra}/{$directory}");
        File::put("{$this->extra}/{$directory}/module.json", $rawManifest ?? json_encode($overrides + [
            'name' => $directory,
            'display_name' => $directory,
            'description' => 'test',
            'version' => '1.0.0',
            'category' => 'adapter',
            'provider' => 'Missing\\Provider',
            'requires' => ['packages' => []],
            'capabilities' => [],
            'default_enabled' => true,
            'features' => ['test'],
        ]));
        File::put("{$this->extra}/{$directory}/composer.json", json_encode($composer + [
            'name' => "liberusoftware/{$directory}",
            'type' => 'liberu-module',
            'extra' => ['liberu' => ['name' => $directory]],
        ]));
    }

    public function test_the_manager_registers_the_required_modules_and_package_discovery_does_not(): void
    {
        $inventory = app(ModuleInventory::class);

        foreach (['module-manager', 'tenancy', 'users-roles'] as $name) {
            $this->assertTrue($inventory->isBooted($name), $name);
            $this->assertTrue($inventory->isRequired($name), $name);
        }

        $this->assertNotNull(app()->getProvider(TenancyServiceProvider::class));
        $this->assertNotNull(app()->getProvider(UsersRolesServiceProvider::class));
        $this->assertNotContains(TenancyServiceProvider::class, app(PackageManifest::class)->providers());
        $this->assertNotContains(UsersRolesServiceProvider::class, app(PackageManifest::class)->providers());
        $this->assertSame([], $inventory->problems);
    }

    public function test_the_commands_exist_and_a_clean_install_validates(): void
    {
        $result = Process::path(base_path())->env(['MODULES_ENABLED' => '', 'MODULES_DISABLED' => ''])->run(['php', 'artisan', 'module:validate']);

        $this->assertTrue($result->successful(), $result->errorOutput().$result->output());
        $this->assertStringContainsString('All discovered modules are valid.', $result->output());
        $this->assertTrue($this->runArtisan([], 'module:status', 'tenancy')->successful());
        $this->assertTrue($this->runArtisan([], 'module:features')->successful());
        $this->assertFalse($this->runArtisan([], 'module:status', 'nothing-by-this-name')->successful());
    }

    public function test_a_required_module_cannot_be_disabled_and_the_application_still_boots(): void
    {
        $modules = $this->listed(['MODULES_DISABLED' => 'tenancy,users-roles']);

        $this->assertSame('booted', $modules['tenancy']['status']);
        $this->assertSame('booted', $modules['users-roles']['status']);

        $validate = $this->runArtisan(['MODULES_DISABLED' => 'tenancy'], 'module:validate');
        $this->assertFalse($validate->successful());
        $this->assertStringContainsString('the disabled list names a required module', $validate->output().$validate->errorOutput());
    }

    public function test_an_unreadable_manifest_is_reported_and_stops_nothing_else(): void
    {
        $this->module('broken-json', rawManifest: '{ not json');
        $this->module('missing-keys', rawManifest: '{"name":"missing-keys"}');

        $modules = $this->listed();

        $this->assertSame('invalid', $modules['broken-json']['status']);
        $this->assertSame('invalid', $modules['missing-keys']['status']);
        $this->assertStringContainsString('missing required key', $modules['missing-keys']['problems'][0]);
        $this->assertSame('booted', $modules['tenancy']['status']);
        $this->assertTrue($this->runArtisan([], 'about')->successful());
        $this->assertFalse($this->runArtisan([], 'module:validate')->successful());
    }

    public function test_a_module_with_a_missing_provider_does_not_boot_and_says_why(): void
    {
        $this->module('no-provider', composer: ['require' => []]);

        $modules = $this->listed();

        $this->assertSame('invalid', $modules['no-provider']['status']);
        $this->assertStringContainsString('not autoloadable', implode(' ', $modules['no-provider']['problems']));
    }

    public function test_a_module_is_refused_when_its_dependency_is_missing_and_so_is_whatever_needs_it(): void
    {
        $this->module('needs-nothing-real', [
            'capabilities' => ['middle.layer'],
            'requires' => ['capabilities' => ['does.not.exist' => '^1.0']],
        ]);
        $this->module('needs-the-middle', ['requires' => ['capabilities' => ['middle.layer' => '^1.0']]]);

        $modules = $this->listed();

        $this->assertSame('invalid', $modules['needs-the-middle']['status']);

        $this->assertSame('invalid', $modules['needs-nothing-real']['status']);
        $this->assertStringContainsString('does.not.exist', implode(' ', $modules['needs-nothing-real']['problems']));
        $this->assertSame('booted', $modules['users-roles']['status']);
    }

    public function test_incompatible_laravel_is_refused_on_that_module_alone(): void
    {
        $this->module('old-laravel', ['requires' => ['laravel' => '^9.0', 'packages' => []]]);

        $modules = $this->listed();

        $this->assertSame('invalid', $modules['old-laravel']['status']);
        $this->assertStringContainsString('does not satisfy ^9.0', implode(' ', $modules['old-laravel']['problems']));
    }

    public function test_a_capability_clash_rejects_the_later_module(): void
    {
        $this->module('clash-a', ['capabilities' => ['same.thing']]);
        $this->module('clash-b', ['capabilities' => ['same.thing']]);

        $this->assertStringContainsString('same.thing', implode(' ', $this->listed()['clash-b']['problems']));
    }

    public function test_a_missing_or_broken_required_module_stops_with_its_name(): void
    {
        $this->expectException(RequiredModuleFailed::class);
        $this->expectExceptionMessage('Required module [not-installed] cannot load');

        (new ModuleLoader(app(ModuleValidator::class), app(RegistryCache::class), [base_path('modules')], [], [], ['not-installed']))->load();
    }

    public function test_a_required_module_with_a_broken_manifest_stops_with_the_reason(): void
    {
        $this->module('needed', rawManifest: '{"name":"needed"}');

        $this->expectException(RequiredModuleFailed::class);
        $this->expectExceptionMessage('missing required key');

        (new ModuleLoader(app(ModuleValidator::class), app(RegistryCache::class), [$this->extra], [], [], ['needed']))->load();
    }

    public function test_a_cached_registry_boots_the_same_modules(): void
    {
        $path = "{$this->extra}/registry.cache";
        $loader = fn (bool $cached) => new ModuleLoader(app(ModuleValidator::class), app(RegistryCache::class), [base_path('modules')], [], [], ['module-manager', 'tenancy', 'users-roles'], $cached, $path);

        app(RegistryCache::class)->write((new ModuleDiscovery)->discover([base_path('modules')]), $path);

        $this->assertSame(array_keys($loader(false)->load()->booted), array_keys($loader(true)->load()->booted));
    }
}
