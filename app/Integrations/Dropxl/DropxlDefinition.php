<?php

namespace App\Integrations\Dropxl;

use Liberu\Ecommerce\Contracts\Modules\ModuleDefinition;
use Liberu\Ecommerce\Contracts\Modules\SettingField;

class DropxlDefinition implements ModuleDefinition
{
    public const KEY = 'ecommerce-dropxl';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'DropXL';
    }

    public function description(): string
    {
        return 'Dropshipping fulfilment through DropXL, and importing its catalogue.';
    }

    public function settings(): array
    {
        return [
            new SettingField('api_key', 'API key', SettingField::SECRET, required: true),
            new SettingField('api_url', 'API URL', SettingField::URL, help: 'Leave blank for the default.'),
        ];
    }

    public function validateSettings(array $settings): array
    {
        return [];
    }
}
