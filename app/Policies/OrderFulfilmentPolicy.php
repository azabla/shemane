<?php

namespace App\Policies;

use App\Models\OrderFulfilment;
use App\Models\User;
use App\Support\StoreAccess;
use App\Support\TeamPermission;

/** Visible to whoever can see the order. Rows are written by the fulfilment service alone. */
class OrderFulfilmentPolicy
{
    public function viewAny(User $user): bool
    {
        return StoreAccess::allows($user, TeamPermission::ORDERS_VIEW, null, 'view_any_order');
    }

    public function view(User $user, OrderFulfilment $fulfilment): bool
    {
        return StoreAccess::allows($user, TeamPermission::ORDERS_VIEW, $fulfilment->store, 'view_any_order');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, OrderFulfilment $fulfilment): bool
    {
        return false;
    }

    public function delete(User $user, OrderFulfilment $fulfilment): bool
    {
        return false;
    }
}
