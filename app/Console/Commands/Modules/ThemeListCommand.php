<?php

namespace App\Console\Commands\Modules;

use App\Support\StorefrontThemes;
use Illuminate\Console\Command;

class ThemeListCommand extends Command
{
    protected $signature = 'theme:list';

    protected $description = 'List the storefront themes in themes/ and whether each can be used';

    public function handle(StorefrontThemes $themes): int
    {
        $problems = $themes->validate();
        $rows = [];

        foreach ($themes->all() as $name => $manifest) {
            $rows[] = [$name, $manifest['display_name'] ?? '?', $manifest['parent'] ?? '—', isset($problems[$name]) ? 'invalid' : 'valid', implode(' ', $problems[$name] ?? [])];
        }

        $this->table(['Theme', 'Name', 'Parent', 'Status', 'Problems'], $rows);

        return self::SUCCESS;
    }
}
