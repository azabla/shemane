<?php

namespace Tests\Feature\Modules\Concerns;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Illuminate\Foundation\Application;

/**
 * Boots the application as if the fixture modules were Composer-installed: the installed-package
 * list, the autoloader and the environment all say so before the application is created. The
 * modules.yml workflow proves the same fixtures through a real `composer require`.
 */
trait InstallsFixtureModules
{
    /** @var array<string, string> */
    private array $moduleEnvironment = [];

    /** @return list<string> */
    protected function fixtureModulesEnabled(): array
    {
        return ['fixture-supplier'];
    }

    /** @return list<string> */
    protected function fixtureModulesDisabled(): array
    {
        return [];
    }

    public function createApplication(): Application
    {
        $root = dirname(__DIR__, 4);
        $installed = require "{$root}/vendor/composer/installed.php";

        foreach (glob("{$root}/tests/Fixtures/modules/*/composer.json") ?: [] as $file) {
            $composer = json_decode((string) file_get_contents($file), true);
            $installed['versions'][$composer['name']] = [
                'pretty_version' => $composer['version'],
                'version' => $composer['version'].'.0',
                'reference' => null,
                'type' => $composer['type'],
                'install_path' => dirname($file),
                'aliases' => [],
                'dev_requirement' => true,
            ];

            foreach ($composer['autoload']['psr-4'] as $namespace => $directory) {
                $this->classLoader()->addPsr4($namespace, dirname($file).'/'.$directory);
            }
        }

        InstalledVersions::reload($installed);

        $this->setModuleEnvironment('MODULES_ENABLED', implode(',', $this->fixtureModulesEnabled()));
        $this->setModuleEnvironment('MODULES_DISABLED', implode(',', $this->fixtureModulesDisabled()));

        return parent::createApplication();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (array_keys($this->moduleEnvironment) as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        InstalledVersions::reload(require dirname(__DIR__, 4).'/vendor/composer/installed.php');
    }

    private function setModuleEnvironment(string $key, string $value): void
    {
        $this->moduleEnvironment[$key] = $value;
        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }

    private function classLoader(): ClassLoader
    {
        return ClassLoader::getRegisteredLoaders()[dirname(__DIR__, 4).'/vendor'];
    }
}
