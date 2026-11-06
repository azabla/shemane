<?php

namespace Liberu\Ecommerce\Contracts\Modules;

/** Optional, on a ModuleDefinition: configure on the module's own page instead of the core's generated form. */
interface ProvidesConfigurationPage
{
    /** Settings belong to the team, so the page does too. */
    public function configurationUrl(int $teamId): string;
}
