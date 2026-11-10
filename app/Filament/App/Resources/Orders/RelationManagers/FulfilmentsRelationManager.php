<?php

namespace App\Filament\App\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Read-only: what each supplier was sent and answered. Retrying is an action on the order. */
class FulfilmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'fulfilments';

    protected static ?string $title = 'Supplier fulfilment';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('supplier'),
            TextColumn::make('status')->badge(),
            TextColumn::make('supplier_reference')->placeholder('—'),
            TextColumn::make('tracking_number')->placeholder('—')->url(fn ($record) => $record->tracking_url, shouldOpenInNewTab: true),
            TextColumn::make('carrier')->placeholder('—'),
            TextColumn::make('failure_reason')->placeholder('—')->wrap(),
        ]);
    }
}
