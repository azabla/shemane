<?php

namespace App\Filament\Admin\Pages;

use App\Support\Modules\TeamModules;
use App\Support\StoreAccess;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/** Which booted integrations the platform offers to teams. Turning modules on and off is the Modules page. */
class TeamModuleOffers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $title = 'Team integrations';

    protected static ?string $slug = 'team-integrations';

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
        $modules = app(TeamModules::class);

        return $table
            ->records(fn (): array => $this->rows())
            ->columns([
                TextColumn::make('label')->label('Module'),
                TextColumn::make('availability')->badge()->color(fn (string $state) => match ($state) {
                    'offered' => 'success',
                    default => 'warning',
                }),
                TextColumn::make('teams')->label('Teams using it'),
                TextColumn::make('note')->wrap()->color('gray'),
            ])
            ->recordActions([
                Action::make('offer')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (array $record) => $record['availability'] === 'not offered')
                    ->action(fn (array $record) => $this->setAvailable($modules, $record['module'], true)),
                Action::make('withdraw')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Every team using it loses it. Their settings are kept.')
                    ->visible(fn (array $record) => $record['availability'] === 'offered')
                    ->action(fn (array $record) => $this->setAvailable($modules, $record['module'], false)),
            ]);
    }

    private function setAvailable(TeamModules $modules, string $module, bool $available): void
    {
        abort_unless(static::canAccess(), 403);
        $modules->setAvailable($module, $available);
        $this->flushCachedTableRecords();

        Notification::make()->title($available ? 'Offered to teams' : 'Withdrawn from every team')->success()->send();
    }

    /** @return array<string, array<string, mixed>> */
    private function rows(): array
    {
        $modules = app(TeamModules::class);
        $rows = [];

        foreach ($modules->definitions() as $name => $definition) {
            $rows[$name] = [
                'module' => $name,
                'label' => $definition->label(),
                'availability' => $modules->isAvailable($name) ? 'offered' : 'not offered',
                'teams' => count($modules->teamsEnabling($name)),
                'note' => $definition->description(),
            ];
        }

        ksort($rows);

        return $rows;
    }
}
