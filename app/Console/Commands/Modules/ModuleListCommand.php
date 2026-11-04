<?php

namespace App\Console\Commands\Modules;

use App\Support\Modules\ModuleInventory;
use Illuminate\Console\Command;

class ModuleListCommand extends Command
{
    protected $signature = 'module:list {--json : Machine-readable output}';

    protected $description = 'List discovered modules, whether each is required, and whether it booted';

    public function handle(ModuleInventory $inventory): int
    {
        $rows = [];

        foreach ($inventory->manifests as $name => $manifest) {
            $rows[$name] = [
                'module' => $name,
                'version' => $manifest->version(),
                'category' => $manifest->category(),
                'kind' => $inventory->isRequired($name) ? 'required' : 'optional',
                'status' => $inventory->status($name),
                'problems' => $inventory->problems[$name] ?? [],
            ];
        }

        foreach (array_diff_key($inventory->problems, $inventory->manifests) as $name => $problems) {
            $rows[$name] = ['module' => $name, 'version' => '?', 'category' => '?', 'kind' => 'optional', 'status' => 'invalid', 'problems' => $problems];
        }

        ksort($rows);

        if ($this->option('json')) {
            $this->line((string) json_encode(array_values($rows), JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->table(['Module', 'Version', 'Category', 'Kind', 'Status', 'Problems'], array_map(
            fn (array $row) => [...array_slice($row, 0, 5), implode(' ', $row['problems'])],
            array_values($rows),
        ));

        return self::SUCCESS;
    }
}
