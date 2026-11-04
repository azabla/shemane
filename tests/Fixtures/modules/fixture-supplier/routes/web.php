<?php

use Illuminate\Support\Facades\Route;
use Liberu\Ecommerce\FixtureSupplier\FixtureClient;

Route::middleware(['web', 'store.module:fixture-supplier'])
    ->get('/fixture-supplier/region', fn (FixtureClient $client) => ['region' => $client->regionFor()]);
