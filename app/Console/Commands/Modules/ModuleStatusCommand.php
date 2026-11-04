<?php

namespace App\Console\Commands\Modules;

use App\Support\Modules\ModuleInventory;
use Illuminate\Console\Command;

class ModuleStatusCommand extends Command
{
    protected $signature = 'module:status {name}';

    protected $description = 'Show one module: its state, dependencies, capabilities and any problems';

    public function handle(ModuleInventory $inventory): int
    {
        $name = (string) $this->argument('name');
        $manifest = $inventory->manifests[$name] ?? null;

        if ($manifest === null && ! isset($inventory->problems[$name])) {
            $this->error("Module [{$name}] is not installed.");

            return self::FAILURE;
        }

        $this->table(['Property', 'Value'], [
            ['status', $inventory->status($name)],
            ['kind', $inventory->isRequired($name) ? 'required' : 'optional'],
            ['version', $manifest?->version() ?? '?'],
            ['provider', $manifest?->provider() ?? '?'],
            ['dependencies', json_encode($manifest?->requiredPackages() ?? [], JSON_THROW_ON_ERROR)],
            ['capabilities', implode(', ', $manifest?->capabilities() ?? [])],
            ['path', $manifest?->path ?? '?'],
            ['problems', implode(PHP_EOL, $inventory->problems[$name] ?? [])],
        ]);

        return self::SUCCESS;
    }
}
