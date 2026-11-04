<?php

namespace Liberu\Ecommerce\Contracts\Modules;

/** Gate abilities the core defines, each taking a store id. Modules ask these and never name a role. */
final class Abilities
{
    public const MANAGE_STORE = 'ecommerce.store.manage';

    public const MANAGE_CATALOGUE = 'ecommerce.catalogue.manage';

    public const MANAGE_ORDERS = 'ecommerce.orders.manage';
}
