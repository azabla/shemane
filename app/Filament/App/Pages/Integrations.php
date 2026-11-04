<?php

namespace App\Filament\App\Pages;

use App\Models\Store;
use App\Support\Modules\ModuleActivationRefused;
use App\Support\Modules\StoreModules;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Liberu\Ecommerce\Contracts\Modules\ProvidesConfigurationPage;
use Liberu\Ecommerce\Contracts\Modules\SettingField;
use Livewire\Attributes\Url;

/** Where a merchant turns on and configures the modules the platform made available to their store. */
class Integrations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?int $navigationSort = 13;

    /** Which of the team's stores, when it has several. */
    #[Url]
    public ?int $store = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && static::stores()->contains(fn (Store $store) => Gate::forUser($user)->allows('manageSettings', $store));
    }

    /** @return Collection<int, Store> */
    public static function stores(): Collection
    {
        return Store::query()->where('team_id', Filament::getTenant()?->getKey())->orderBy('name')->get();
    }

    public function record(): Store
    {
        $stores = static::stores();
        $record = $stores->firstWhere('id', $this->store) ?? $stores->first();

        abort_if($record === null, 404);
        Gate::authorize('manageSettings', $record);

        return $record;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $modules = app(StoreModules::class);

        return $table
            ->records(function () use ($modules): array {
                $store = $this->record();
                $rows = [];

                foreach ($modules->availableTo($store->id) as $key => $definition) {
                    $rows[$key] = [
                        'key' => $key,
                        'label' => $definition->label(),
                        'description' => $definition->description(),
                        'status' => $modules->status($key, $store->id),
                    ];
                }

                return $rows;
            })
            ->columns([
                TextColumn::make('label')->label('Module'),
                TextColumn::make('description')->wrap(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => match ($state) {
                    'active' => 'On',
                    'unconfigured' => 'Needs settings',
                    default => 'Off',
                })->color(fn (string $state) => $state === 'active' ? 'success' : ($state === 'unconfigured' ? 'warning' : 'gray')),
            ])
            ->emptyStateHeading('No modules are available to this store')
            ->emptyStateDescription('The platform administrator decides which installed modules each store can use.')
            ->recordActions([
                Action::make('configure')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(function (array $record) use ($modules) {
                        $definition = $modules->definition($record['key']);

                        return $definition instanceof ProvidesConfigurationPage ? $definition->configurationUrl($this->record()->id) : null;
                    })
                    ->fillForm(fn (array $record) => $modules->displayableSettings($record['key'], $this->record()->id))
                    ->schema(fn (array $record) => $this->fields($record['key']))
                    ->action(function (array $record, array $data, Action $action) use ($modules) {
                        try {
                            $modules->configure($record['key'], $this->record()->id, $data);
                        } catch (ValidationException|ModuleActivationRefused $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                            $action->halt();
                        }

                        Notification::make()->title('Settings saved')->success()->send();
                    }),
                Action::make('enable')
                    ->icon('heroicon-o-play')
                    ->visible(fn (array $record) => $record['status'] === 'available')
                    ->action(function (array $record) use ($modules) {
                        try {
                            $modules->enable($record['key'], $this->record()->id);
                            Notification::make()->title('Module enabled')->success()->send();
                        } catch (ModuleActivationRefused $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('disable')
                    ->icon('heroicon-o-pause')
                    ->requiresConfirmation()
                    ->visible(fn (array $record) => in_array($record['status'], ['active', 'unconfigured'], true))
                    ->action(function (array $record) use ($modules) {
                        $modules->disable($record['key'], $this->record()->id);
                        Notification::make()->title('Module disabled')->success()->send();
                    }),
            ]);
    }

    /** @return list<Component|Field> */
    private function fields(string $module): array
    {
        $modules = app(StoreModules::class);
        $definition = $modules->definition($module);
        $storeId = $this->record()->id;
        $rules = $definition === null ? [] : $modules->rules($definition);

        return array_map(function (SettingField $field) use ($modules, $module, $storeId, $rules) {
            $stored = $modules->hasStoredValue($module, $storeId, $field->key);

            $input = match ($field->type) {
                SettingField::BOOLEAN => Toggle::make($field->key),
                SettingField::SELECT => Select::make($field->key)->options($field->options),
                SettingField::SECRET => TextInput::make($field->key)->password()->placeholder($stored ? 'Saved. Leave blank to keep it.' : null),
                default => TextInput::make($field->key),
            };

            return $input->label($field->label)->helperText($field->help)
                ->required($field->required && $field->type !== SettingField::BOOLEAN && ! ($field->isSecret() && $stored))
                ->rules(array_values(array_diff($rules[$field->key] ?? [], ['required', 'nullable'])));
        }, $definition?->settings() ?? []);
    }
}
