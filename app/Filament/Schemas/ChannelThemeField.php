<?php

namespace App\Filament\Schemas;

use App\Support\StorefrontTheme;
use App\Support\StorefrontThemes;
use Filament\Forms\Components\Select;

/** Which installed theme a channel's storefront uses. Installing one is Composer's job, not this form's. */
final class ChannelThemeField
{
    public static function make(): Select
    {
        return Select::make('theme')
            ->label('Theme')
            ->options(fn () => app(StorefrontThemes::class)->available())
            ->default(StorefrontTheme::DEFAULT)
            ->selectablePlaceholder(false)
            ->required();
    }
}
