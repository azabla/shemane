<?php

namespace Liberu\Ecommerce\FixtureSupplier\Filament;

use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Liberu\Ecommerce\Contracts\Modules\Abilities;
use Liberu\Ecommerce\Contracts\Modules\ModuleState;

/** A module's own admin page, gated on the store having it and the person being allowed. */
class FixturePage extends Page
{
    protected static ?string $slug = 'fixture-supplier';

    public static function canAccess(): bool
    {
        $state = app(ModuleState::class);
        $storeId = $state->currentStoreId();

        return $storeId !== null && $state->isActive('fixture-supplier', $storeId) && Gate::allows(Abilities::MANAGE_STORE, $storeId);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([Text::make('Fixture Supplier is active.')]);
    }
}
