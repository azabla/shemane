<?php

namespace App\Console\Commands\Modules;

use App\Support\Modules\ModuleLoader;
use Illuminate\Console\Command;
use Liberu\Foundation\ModuleManager\Cache\RegistryCache;
use Liberu\Foundation\ModuleManager\ModuleRegistry;

class ModuleCacheCommand extends Command
{
    protected $signature = 'module:cache';

    protected $description = 'Validate, then cache the module registry; a cached boot skips validation';

    public function handle(ModuleLoader $loader, RegistryCache $cache): int
    {
        foreach ($loader->auditAll() as $name => $problems) {
            $this->error("[{$name}] ".implode(' ', $problems));

            return self::FAILURE;
        }

        [$manifests] = $loader->discover();
        $cache->write(new ModuleRegistry($manifests), (string) config('modules.cache_path'));
        $this->info('Module registry cached.');

        return self::SUCCESS;
    }
}
