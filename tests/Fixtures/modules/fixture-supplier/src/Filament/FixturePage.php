<?php

namespace Liberu\Ecommerce\FixtureSupplier\Filament;

use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Liberu\Ecommerce\Contracts\Modules\Abilities;
use Liberu\Ecommerce\Contracts\Modules\ModuleState;

/** A module's own admin page, gated on the team having it and the person being allowed. */
class FixturePage extends Page
{
    protected static ?string $slug = 'fixture-supplier';

    public static function canAccess(): bool
    {
        $state = app(ModuleState::class);
        $teamId = $state->currentTeamId();

        return $teamId !== null && $state->isActiveForTeam('fixture-supplier', $teamId) && Gate::allows(Abilities::MANAGE_MODULES, $teamId);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([Text::make('Fixture Supplier is active.')]);
    }
}
