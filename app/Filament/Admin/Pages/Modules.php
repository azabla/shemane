<?php

namespace App\Filament\Admin\Pages;

use App\Models\Store;
use App\Support\Modules\ModuleInventory;
use App\Support\Modules\StoreModules;
use App\Support\StoreAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * What this deployment has installed, and which stores may use it. Read-only about code on purpose:
 * installing, upgrading and removing go through Composer and a deploy, never a web request.
 */
class Modules extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    public static function canAccess(): bool
    {
        return StoreAccess::platform(auth()->user(), 'manage_modules');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $modules = app(StoreModules::class);

        return $table
            ->records(fn (): array => $this->rows())
            ->columns([
                TextColumn::make('module'),
                TextColumn::make('version'),
                TextColumn::make('kind'),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'booted' => 'success',
                    'invalid' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('stores')->label('Stores allowed'),
                TextColumn::make('note')->wrap()->color('gray'),
            ])
            ->recordActions([
                Action::make('stores')
                    ->label('Choose stores')
                    ->icon('heroicon-o-building-storefront')
                    ->visible(fn (array $record) => $modules->definition($record['module']) !== null)
                    ->fillForm(fn (array $record) => ['stores' => $modules->storesAllowing($record['module'])])
                    ->schema([
                        CheckboxList::make('stores')->options(fn () => Store::query()->orderBy('name')->pluck('name', 'id')->all()),
                    ])
                    ->action(function (array $record, array $data) use ($modules) {
                        abort_unless(static::canAccess(), 403);
                        $allowed = array_map('intval', $data['stores'] ?? []);

                        foreach (Store::query()->pluck('id') as $storeId) {
                            if (in_array($storeId, $allowed, true) || in_array($storeId, $modules->storesAllowing($record['module']), true)) {
                                $modules->setAvailable($record['module'], $storeId, in_array($storeId, $allowed, true));
                            }
                        }

                        Notification::make()->title('Stores updated')->success()->send();
                    }),
            ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function rows(): array
    {
        $inventory = app(ModuleInventory::class);
        $modules = app(StoreModules::class);
        $rows = [];

        foreach ($inventory->manifests as $name => $manifest) {
            $rows[$name] = [
                'module' => $name,
                'version' => $manifest->version(),
                'kind' => $inventory->isRequired($name) ? 'required' : 'optional',
                'status' => $inventory->status($name),
                'stores' => $modules->definition($name) === null ? '—' : count($modules->storesAllowing($name)),
                'note' => $this->note($name, $inventory),
            ];
        }

        foreach (array_diff_key($inventory->problems, $inventory->manifests) as $name => $problems) {
            $rows[$name] = ['module' => $name, 'version' => '?', 'kind' => 'optional', 'status' => 'invalid', 'stores' => '—', 'note' => implode(' ', $problems)];
        }

        foreach ($modules->definitions() as $name => $definition) {
            $rows[$name] ??= ['module' => $name, 'version' => 'core', 'kind' => 'built in', 'status' => 'booted', 'stores' => count($modules->storesAllowing($name)), 'note' => $definition->description()];
        }

        foreach ((array) config('ecommerce-modules.catalogue', []) as $name => $entry) {
            $rows[$name] ??= ['module' => $name, 'version' => '—', 'kind' => 'optional', 'status' => 'not installed', 'stores' => '—', 'note' => "{$entry['description']} Install with: composer require {$entry['package']}"];
        }

        ksort($rows);

        return $rows;
    }

    private function note(string $name, ModuleInventory $inventory): string
    {
        if (isset($inventory->problems[$name])) {
            return implode(' ', $inventory->problems[$name]);
        }

        return $inventory->status($name) === 'disabled' ? 'Installed, but not booted. Add it to MODULES_ENABLED and redeploy.' : '';
    }
}
