<?php

namespace App\Console\Commands\Modules;

use App\Support\StorefrontThemes;
use Illuminate\Console\Command;

class ThemeValidateCommand extends Command
{
    protected $signature = 'theme:validate';

    protected $description = 'Check every theme in themes/; fails the build on any fault';

    public function handle(StorefrontThemes $themes): int
    {
        $problems = $themes->validate();

        foreach ($problems as $name => $faults) {
            foreach ($faults as $fault) {
                $this->error("[{$name}] {$fault}");
            }
        }

        if ($problems !== []) {
            return self::FAILURE;
        }

        $this->info(count($themes->all()).' themes are valid.');

        return self::SUCCESS;
    }
}
