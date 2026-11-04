<?php

namespace Liberu\Ecommerce\Contracts\Modules;

/**
 * What a module tells the core so that stores can activate and configure it. A module provider
 * registers one with `$this->app->tag(MyDefinition::class, ModuleDefinition::class)`.
 */
interface ModuleDefinition
{
    /** The module's name, as in module.json. */
    public function key(): string;

    public function label(): string;

    public function description(): string;

    /** @return list<SettingField> */
    public function settings(): array;

    /**
     * Checks the field rules cannot express. Called with the merged settings after those rules pass.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, string> a message per invalid field; empty when valid
     */
    public function validateSettings(array $settings): array;
}
