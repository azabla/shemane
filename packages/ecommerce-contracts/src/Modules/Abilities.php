<?php

namespace Liberu\Ecommerce\Contracts\Modules;

/** Gate abilities the core defines. Modules ask these and never name a role. Each takes a store id, except MANAGE_MODULES. */
final class Abilities
{
    public const MANAGE_STORE = 'ecommerce.store.manage';

    public const MANAGE_CATALOGUE = 'ecommerce.catalogue.manage';

    public const MANAGE_ORDERS = 'ecommerce.orders.manage';

    /** Takes a team id: enabling and configuring the team's modules, as on its own configuration page. */
    public const MANAGE_MODULES = 'ecommerce.modules.manage';
}
