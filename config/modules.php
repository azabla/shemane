<?php

/*
| Which installed modules boot in this deployment. The manager's provider reads this
| once, at boot. See docs/MODULE_ARCHITECTURE.md and ADR 0018.
|
| A module boots when it is required, or its manifest sets default_enabled, or MODULES_ENABLED
| names it. MODULES_DISABLED overrides the last two, and cannot switch off a required module.
*/

return [
    'paths' => [base_path('modules'), ...array_filter(explode(',', (string) env('MODULES_EXTRA_PATHS', '')))],

    // One broken optional module is left out and reported rather than taking down every store.
    'isolate_failures' => true,

    // The core's own classes need these, so a deployment cannot run without them.
    'required' => ['module-manager', 'tenancy', 'users-roles'],

    'enabled' => array_values(array_filter(explode(',', (string) env('MODULES_ENABLED', '')))),
    'disabled' => array_values(array_filter(explode(',', (string) env('MODULES_DISABLED', '')))),

    'cache' => (bool) env('MODULES_CACHE', false),
    'cache_path' => base_path('bootstrap/cache/liberu-modules.php.cache'),
];
