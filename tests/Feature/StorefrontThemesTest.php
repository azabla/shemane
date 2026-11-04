<?php

namespace Tests\Feature;

use App\Support\StorefrontTheme;
use App\Support\StorefrontThemes;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Discovering and validating themes, tracked or Composer-installed. */
class StorefrontThemesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/themes-'.uniqid());
        File::ensureDirectoryExists($this->root);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function theme(string $directory, array $manifest = [], ?string $raw = null): void
    {
        File::ensureDirectoryExists("{$this->root}/{$directory}");
        File::put("{$this->root}/{$directory}/theme.json", $raw ?? json_encode($manifest + ['name' => $directory, 'display_name' => ucfirst($directory)]));
    }

    private function themes(): StorefrontThemes
    {
        return new StorefrontThemes($this->root);
    }

    public function test_the_core_storefront_is_always_available_and_valid_themes_are_offered(): void
    {
        $this->theme('alpine', ['parent' => StorefrontTheme::DEFAULT]);
        $this->theme('alpine-winter', ['parent' => 'alpine']);

        $this->assertSame([StorefrontTheme::DEFAULT => 'Core storefront', 'alpine' => 'Alpine', 'alpine-winter' => 'Alpine-winter'], $this->themes()->available());
        $this->assertSame([], $this->themes()->validate());
    }

    public function test_a_faulty_theme_is_reported_and_not_offered(): void
    {
        $this->theme('unreadable', raw: '{ not json');
        $this->theme('wrong-name', ['name' => 'something-else']);
        $this->theme('orphan', ['parent' => 'not-installed']);
        $this->theme('loop-a', ['parent' => 'loop-b']);
        $this->theme('loop-b', ['parent' => 'loop-a']);
        $this->theme('no-assets', ['assets' => ['css' => ['resources/css/app.css']]]);
        $this->theme('too-new', ['requires' => ['liberusoftware/ecommerce-contracts' => '^9.0']]);

        $problems = $this->themes()->validate();

        $this->assertStringContainsString('not valid JSON', $problems['unreadable'][0]);
        $this->assertStringContainsString('the directory is [wrong-name]', $problems['wrong-name'][0]);
        $this->assertStringContainsString('[not-installed] is not installed', $problems['orphan'][0]);
        $this->assertStringContainsString('loops', $problems['loop-a'][0]);
        $this->assertStringContainsString('does not exist', $problems['no-assets'][0]);
        $this->assertStringContainsString('ecommerce-contracts ^9.0', $problems['too-new'][0]);
        $this->assertSame([StorefrontTheme::DEFAULT], array_keys($this->themes()->available()));
    }

    public function test_a_compatible_requirement_passes(): void
    {
        $this->theme('fits', ['requires' => ['liberusoftware/ecommerce-contracts' => '^1.0']]);

        $this->assertSame([], $this->themes()->validate());
    }

    public function test_a_composer_theme_must_route_itself_to_its_directory(): void
    {
        $this->theme('packaged');
        File::put("{$this->root}/packaged/composer.json", json_encode(['name' => 'acme/packaged', 'type' => 'library']));
        $this->assertStringContainsString('type liberu-theme', $this->themes()->validate()['packaged'][0]);

        File::put("{$this->root}/packaged/composer.json", json_encode(['name' => 'acme/packaged', 'type' => 'liberu-theme', 'extra' => ['liberu' => ['name' => 'other']]]));
        $this->assertStringContainsString('extra.liberu.name', $this->themes()->validate()['packaged'][0]);

        File::put("{$this->root}/packaged/composer.json", json_encode(['name' => 'acme/packaged', 'type' => 'liberu-theme', 'extra' => ['liberu' => ['name' => 'packaged']]]));
        $this->assertSame([], $this->themes()->validate());
    }

    public function test_the_shipped_fixture_is_a_valid_package(): void
    {
        $this->assertSame([], (new StorefrontThemes(base_path('tests/Fixtures/theme-packages')))->validate());
    }

    public function test_the_commands_report_a_clean_tree(): void
    {
        $this->artisan('theme:list')->assertSuccessful();
        $this->artisan('theme:validate')->assertSuccessful();
    }
}
