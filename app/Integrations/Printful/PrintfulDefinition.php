<?php

namespace App\Integrations\Printful;

use Liberu\Ecommerce\Contracts\Modules\ModuleDefinition;
use Liberu\Ecommerce\Contracts\Modules\SettingField;

class PrintfulDefinition implements ModuleDefinition
{
    public const KEY = 'ecommerce-printful';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Printful';
    }

    public function description(): string
    {
        return 'Print-on-demand fulfilment, and importing your Printful store as products.';
    }

    public function settings(): array
    {
        return [
            new SettingField('api_token', 'API token', SettingField::SECRET, required: true),
            new SettingField('store_id', 'Printful store ID', help: 'Only for an account-level token. A store token names its own store.'),
        ];
    }

    public function validateSettings(array $settings): array
    {
        return [];
    }
}
