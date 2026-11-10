<?php

namespace App\Http\Middleware;

use App\Support\Modules\TeamModules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `store.module:<name>`: a module's route does not exist for a store that has not activated it. */
class EnsureStoreModuleActive
{
    public function __construct(private readonly TeamModules $modules) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless($this->modules->isActive($module), 404);

        return $next($request);
    }
}
