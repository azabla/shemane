<?php

namespace App\Support;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Illuminate\Support\Facades\File;
use Throwable;

/** Every theme in themes/, tracked or Composer-installed, and whether each one can be used. */
final class StorefrontThemes
{
    public function __construct(private readonly string $root) {}

    /** @return array<string, string> name => label, the core's own storefront first */
    public function available(): array
    {
        $themes = [StorefrontTheme::DEFAULT => 'Core storefront'];

        foreach ($this->all() as $name => $manifest) {
            if ($this->problems($name, $manifest) === []) {
                $themes[$name] = (string) ($manifest['display_name'] ?? $name);
            }
        }

        return $themes;
    }

    /** @return array<string, list<string>> faults by theme directory; empty when all are valid */
    public function validate(): array
    {
        $problems = [];

        foreach ($this->all() as $name => $manifest) {
            if (($faults = $this->problems($name, $manifest)) !== []) {
                $problems[$name] = $faults;
            }
        }

        return $problems;
    }

    /** @return array<string, array<string, mixed>|null> by directory; null when the manifest is unreadable */
    public function all(): array
    {
        $themes = [];

        foreach (glob("{$this->root}/*/theme.json") ?: [] as $file) {
            $manifest = json_decode((string) file_get_contents($file), true);
            $themes[basename(dirname($file))] = is_array($manifest) ? $manifest : null;
        }

        ksort($themes);

        return $themes;
    }

    /**
     * @param  array<string, mixed>|null  $manifest
     * @return list<string>
     */
    private function problems(string $directory, ?array $manifest): array
    {
        if ($manifest === null) {
            return ['theme.json is not valid JSON.'];
        }

        $problems = [];

        if (($manifest['name'] ?? null) !== $directory) {
            $problems[] = 'theme.json names ['.($manifest['name'] ?? '').'], but the directory is ['.$directory.'].';
        }

        array_push(
            $problems,
            ...$this->parentProblems($directory, $manifest),
            ...$this->assetProblems($directory, $manifest),
            ...$this->requirementProblems($manifest),
            ...$this->composerProblems($directory),
        );

        return $problems;
    }

    /** @return list<string> */
    private function parentProblems(string $directory, array $manifest): array
    {
        $seen = [$directory];
        $parent = $manifest['parent'] ?? null;

        while ($parent !== null && $parent !== StorefrontTheme::DEFAULT) {
            if (in_array($parent, $seen, true)) {
                return ["Its parent chain loops at [{$parent}]."];
            }

            $file = "{$this->root}/{$parent}/theme.json";
            if (! is_file($file)) {
                return ["Its parent [{$parent}] is not installed."];
            }

            $seen[] = $parent;
            $parent = json_decode((string) file_get_contents($file), true)['parent'] ?? null;
        }

        return [];
    }

    /** @return list<string> */
    private function assetProblems(string $directory, array $manifest): array
    {
        $problems = [];

        foreach ([...($manifest['assets']['css'] ?? []), ...($manifest['assets']['js'] ?? [])] as $asset) {
            if (! is_file("{$this->root}/{$directory}/".ltrim((string) $asset, '/'))) {
                $problems[] = "It declares the asset [{$asset}], which does not exist.";
            }
        }

        return $problems;
    }

    /** @return list<string> packages the theme needs, such as the contracts version it was built for */
    private function requirementProblems(array $manifest): array
    {
        $problems = [];

        foreach ((array) ($manifest['requires'] ?? []) as $package => $constraint) {
            try {
                $compatible = InstalledVersions::isInstalled($package)
                    && Semver::satisfies((string) InstalledVersions::getPrettyVersion($package), (string) $constraint);
            } catch (Throwable) {
                $compatible = false;
            }

            if (! $compatible) {
                $problems[] = "It requires {$package} {$constraint}, which is not installed.";
            }
        }

        return $problems;
    }

    /** A theme with a composer.json is a package, so its metadata must route it to this directory. @return list<string> */
    private function composerProblems(string $directory): array
    {
        $file = "{$this->root}/{$directory}/composer.json";

        if (! File::isFile($file)) {
            return [];
        }

        $composer = json_decode((string) file_get_contents($file), true);

        return match (true) {
            ($composer['type'] ?? null) !== 'liberu-theme' => ['Its composer.json must have type liberu-theme.'],
            ($composer['extra']['liberu']['name'] ?? null) !== $directory => ["Its composer.json extra.liberu.name must be [{$directory}]."],
            default => [],
        };
    }
}
