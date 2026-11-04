<?php

namespace App\Support\Modules;

use Composer\InstalledVersions;
use Illuminate\Foundation\Application;
use Liberu\Foundation\ModuleManager\Cache\RegistryCache;
use Liberu\Foundation\ModuleManager\Exceptions\DependencyResolutionFailed;
use Liberu\Foundation\ModuleManager\Manifest;
use Liberu\Foundation\ModuleManager\ModuleRegistry;
use Liberu\Foundation\ModuleManager\ModuleValidator;
use RuntimeException;
use Throwable;

/**
 * Discovers, validates and selects modules with the manager's own classes, one module at a
 * time. A broken optional module is left out and reported; only a required one stops the boot.
 * ADR 0014 records why this is not the manager's provider, which stops at the first error.
 */
final class ModuleLoader
{
    /**
     * @param  list<string>  $paths
     * @param  list<string>  $enabled
     * @param  list<string>  $disabled
     * @param  list<string>  $required
     */
    public function __construct(
        private readonly ModuleValidator $validator,
        private readonly RegistryCache $cache,
        private readonly array $paths,
        private readonly array $enabled,
        private readonly array $disabled,
        private readonly array $required,
        private readonly bool $useCache = false,
        private readonly string $cachePath = '',
    ) {}

    public function load(): ModuleInventory
    {
        if ($this->useCache && is_file($this->cachePath)) {
            $manifests = $this->cache->load($this->paths, true, $this->cachePath)->all();

            return $this->select($manifests, [], validate: false);
        }

        [$manifests, $problems] = $this->discover();

        return $this->select($manifests, $problems, validate: true);
    }

    /**
     * Every module's faults, booted or not. This is the deployment check.
     *
     * @return array<string, list<string>>
     */
    public function auditAll(): array
    {
        [$manifests, $problems] = $this->discover();

        foreach ($this->validator->validate(new ModuleRegistry($manifests), Application::VERSION) as $error) {
            $problems[$this->owner($error, $manifests)][] = $error;
        }

        return $problems;
    }

    /** @return array{0: array<string, Manifest>, 1: array<string, list<string>>} */
    public function discover(): array
    {
        $manifests = [];
        $problems = [];
        $packages = [];
        $capabilities = [];

        foreach ($this->manifestFiles() as $file) {
            $directory = basename(dirname($file));

            try {
                $manifest = Manifest::fromFile($file);
                $package = $this->composerName(dirname($file));
            } catch (Throwable $e) {
                $problems[$directory][] = $e->getMessage();

                continue;
            }

            $name = $manifest->name();
            $clash = match (true) {
                $package === null => 'it has no composer.json with a package name.',
                isset($manifests[$name]) => "the module name is already used by {$manifests[$name]->path}.",
                isset($packages[$package]) => "Composer package {$package} is already claimed by [{$packages[$package]}].",
                default => null,
            };

            foreach ($manifest->capabilities() as $capability) {
                $clash ??= isset($capabilities[$capability]) ? "capability {$capability} is already provided by [{$capabilities[$capability]}]." : null;
            }

            if ($clash !== null) {
                $problems[$name][] = "Module [{$name}] was not loaded: {$clash}";

                continue;
            }

            $manifests[$name] = $manifest;
            $packages[$package] = $name;
            foreach ($manifest->capabilities() as $capability) {
                $capabilities[$capability] = $name;
            }
        }

        ksort($manifests);

        return [$manifests, $problems];
    }

    /**
     * @param  array<string, Manifest>  $manifests
     * @param  array<string, list<string>>  $problems
     */
    private function select(array $manifests, array $problems, bool $validate): ModuleInventory
    {
        foreach ($this->required as $name) {
            if (! isset($manifests[$name])) {
                throw RequiredModuleFailed::because($name, implode(' ', $problems[$name] ?? ['it is not installed.']));
            }
        }

        $excluded = array_values(array_diff($this->disabled, $this->required));
        $enabled = array_values(array_unique([...$this->enabled, ...$this->required]));
        $registry = new ModuleRegistry($manifests);

        // Excluding a module can break a module that needs it, so repeat until the set is stable.
        while (true) {
            try {
                $selected = [];
                foreach ($registry->resolve($enabled, $excluded) as $manifest) {
                    $selected[$manifest->name()] = $manifest;
                }
            } catch (DependencyResolutionFailed $e) {
                $this->exclude($this->owner($e->getMessage(), $manifests), $e->getMessage(), $excluded, $problems);

                continue;
            }

            $errors = $validate ? $this->validator->validate(new ModuleRegistry($selected), Application::VERSION) : [];
            if ($errors === []) {
                break;
            }

            $owners = [];
            foreach ($errors as $error) {
                $owners[$this->owner($error, $manifests)][] = $error;
            }
            foreach ($owners as $name => $messages) {
                $this->exclude($name, implode(' ', $messages), $excluded, $problems);
            }
        }

        return new ModuleInventory(
            $manifests,
            $selected,
            $problems,
            $this->required,
            array_values(array_intersect($this->disabled, $this->required)),
        );
    }

    /**
     * @param  list<string>  $excluded
     * @param  array<string, list<string>>  $problems
     */
    private function exclude(string $name, string $problem, array &$excluded, array &$problems): void
    {
        if (in_array($name, $this->required, true)) {
            throw RequiredModuleFailed::because($name, $problem);
        }

        // Excluding it again would not change the set, so the selection would never settle.
        if ($name === 'unknown' || in_array($name, $excluded, true)) {
            throw new RuntimeException("Module selection could not settle: {$problem}");
        }

        $excluded[] = $name;
        $problems[$name][] = $problem;
    }

    /** The manager's messages name their module first: `name: …`, or `Module [name] …`. */
    private function owner(string $message, array $manifests): string
    {
        foreach (['/^([a-z0-9-]+): /', '/\[([a-z0-9-]+)\]/'] as $pattern) {
            if (preg_match($pattern, $message, $match) && isset($manifests[$match[1]])) {
                return $match[1];
            }
        }

        return 'unknown';
    }

    /** @return list<string> */
    private function manifestFiles(): array
    {
        $roots = $this->paths;

        foreach (InstalledVersions::getInstalledPackagesByType('liberu-module') as $package) {
            $path = InstalledVersions::getInstallPath($package);
            if (is_string($path)) {
                $roots[] = dirname($path);
            }
        }

        $files = [];
        foreach (array_unique(array_filter(array_map('realpath', $roots))) as $root) {
            array_push($files, ...(glob(rtrim($root, '/').'/*/module.json') ?: []));
        }
        sort($files);

        return $files;
    }

    private function composerName(string $directory): ?string
    {
        $file = $directory.'/composer.json';
        $name = is_file($file) ? (json_decode((string) file_get_contents($file), true)['name'] ?? null) : null;

        return is_string($name) ? $name : null;
    }
}
