<?php

namespace App\Filament\App\Pages;

use App\Models\Team;
use App\Support\Modules\ModuleActivationRefused;
use App\Support\Modules\TeamModules;
use App\Support\StoreAccess;
use App\Support\TeamPermission;
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
use Illuminate\Validation\ValidationException;
use Liberu\Ecommerce\Contracts\Modules\ProvidesConfigurationPage;
use Liberu\Ecommerce\Contracts\Modules\SettingField;

/** Where a team turns on and configures the modules the platform offers. Every store of the team inherits them. */
class Integrations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?int $navigationSort = 13;

    public static function canAccess(): bool
    {
        $team = Filament::getTenant();

        return $team instanceof Team && StoreAccess::allows(auth()->user(), TeamPermission::STORE_MANAGE, $team);
    }

    public function team(): Team
    {
        $team = Filament::getTenant();

        abort_unless($team instanceof Team, 404);
        StoreAccess::authorize(auth()->user(), TeamPermission::STORE_MANAGE, $team);

        return $team;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $modules = app(TeamModules::class);

        return $table
            ->records(function () use ($modules): array {
                $team = $this->team();
                $rows = [];

                foreach ($modules->available() as $key => $definition) {
                    $rows[$key] = [
                        'key' => $key,
                        'label' => $definition->label(),
                        'description' => $definition->description(),
                        'status' => $modules->status($key, $team->id),
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
            ->emptyStateHeading('No modules are available to your team')
            ->emptyStateDescription('The platform administrator decides which installed modules teams can use. What your team turns on applies to all of its stores.')
            ->recordActions([
                Action::make('configure')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(function (array $record) use ($modules) {
                        $definition = $modules->definition($record['key']);

                        return $definition instanceof ProvidesConfigurationPage ? $definition->configurationUrl($this->team()->id) : null;
                    })
                    ->fillForm(fn (array $record) => $modules->displayableSettings($record['key'], $this->team()->id))
                    ->schema(fn (array $record) => $this->fields($record['key']))
                    ->action(function (array $record, array $data, Action $action) use ($modules) {
                        try {
                            $modules->configure($record['key'], $this->team()->id, $data);
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
                            $modules->enable($record['key'], $this->team()->id);
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
                        $modules->disable($record['key'], $this->team()->id);
                        Notification::make()->title('Module disabled')->success()->send();
                    }),
            ]);
    }

    /** @return list<Component|Field> */
    private function fields(string $module): array
    {
        $modules = app(TeamModules::class);
        $definition = $modules->definition($module);
        $teamId = $this->team()->id;
        $rules = $definition === null ? [] : $modules->rules($definition);

        return array_map(function (SettingField $field) use ($modules, $module, $teamId, $rules) {
            $stored = $modules->hasStoredValue($module, $teamId, $field->key);

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
