<?php

namespace App\Console\Commands\Modules;

use App\Support\Modules\ModuleInventory;
use App\Support\Modules\ModuleLoader;
use Illuminate\Console\Command;
use Illuminate\Foundation\PackageManifest;

class ModuleValidateCommand extends Command
{
    protected $signature = 'module:validate';

    protected $description = 'Check every installed module, booted or not; fails the build on any fault';

    public function handle(ModuleLoader $loader, ModuleInventory $inventory, PackageManifest $packages): int
    {
        $errors = [];

        foreach ($loader->auditAll() as $name => $problems) {
            foreach ($problems as $problem) {
                $errors[] = "[{$name}] {$problem}";
            }
        }

        foreach ($inventory->refusedDisables as $name) {
            $errors[] = "[{$name}] MODULES_DISABLED names a required module; the application cannot run without it, so it stays on.";
        }

        foreach ($inventory->manifests as $name => $manifest) {
            if (in_array($manifest->provider(), $packages->providers(), true)) {
                $errors[] = "[{$name}] Laravel package discovery registers its provider, which bypasses the module manager. Add its package to extra.laravel.dont-discover.";
            }
        }

        foreach (array_unique($errors) as $error) {
            $this->error($error);
        }

        if ($errors !== []) {
            return self::FAILURE;
        }

        $this->info(count($inventory->manifests).' installed modules are valid.');

        return self::SUCCESS;
    }
}
