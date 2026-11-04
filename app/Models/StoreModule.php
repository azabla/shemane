<?php

namespace App\Models;

use App\Traits\IsStoreScoped;
use Illuminate\Database\Eloquent\Model;

/** One module's state in one store. No row means off. Read and write it through App\Support\Modules\StoreModules. */
class StoreModule extends Model
{
    use IsStoreScoped;

    protected $fillable = ['store_id', 'module', 'available', 'enabled', 'settings'];

    protected $hidden = ['settings'];

    protected $casts = [
        'available' => 'boolean',
        'enabled' => 'boolean',
        'settings' => 'encrypted:array',
    ];
}
