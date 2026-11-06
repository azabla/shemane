<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One module's state for one team, inherited by all of its stores. Read and write it through App\Support\Modules\TeamModules. */
class TeamModule extends Model
{
    protected $fillable = ['team_id', 'module', 'enabled', 'settings'];

    protected $hidden = ['settings'];

    protected $casts = [
        'enabled' => 'boolean',
        'settings' => 'encrypted:array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
