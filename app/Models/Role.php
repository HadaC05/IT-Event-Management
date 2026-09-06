<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const HIERARCHY = [
        'SBO Adviser' => 4,
        'SBO' => 3,
        'Faculty' => 2,
        'Student' => 1,
    ];

    protected $fillable = ['name'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
