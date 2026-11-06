<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Whether the platform offers an installed module to teams. Written only by the platform administrator. */
class ModuleAvailability extends Model
{
    protected $table = 'module_availability';

    protected $fillable = ['module', 'available'];

    protected $casts = ['available' => 'boolean'];
}
