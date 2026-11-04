<?php

namespace App\Support\Modules;

use Liberu\Foundation\ModuleManager\Manifest;

/** What this deployment found, what it booted, and why anything else did not boot. */
final class ModuleInventory
{
    /**
     * @param  array<string, Manifest>  $manifests  every readable manifest, by module name
     * @param  array<string, Manifest>  $booted  in dependency order
     * @param  array<string, list<string>>  $problems  by module name, or directory when the manifest is unreadable
     * @param  list<string>  $required
     * @param  list<string>  $refusedDisables  required modules that MODULES_DISABLED named
     */
    public function __construct(
        public readonly array $manifests,
        public array $booted,
        public array $problems,
        public readonly array $required,
        public readonly array $refusedDisables = [],
    ) {}

    public function isBooted(string $name): bool
    {
        return isset($this->booted[$name]);
    }

    public function isRequired(string $name): bool
    {
        return in_array($name, $this->required, true);
    }

    /** @return 'booted'|'invalid'|'disabled' */
    public function status(string $name): string
    {
        return match (true) {
            $this->isBooted($name) => 'booted',
            isset($this->problems[$name]) => 'invalid',
            default => 'disabled',
        };
    }

    /** An optional module whose provider threw: it is out, and the reason is on record. */
    public function markFailed(string $name, string $problem): void
    {
        unset($this->booted[$name]);
        $this->problems[$name][] = $problem;
    }
}
