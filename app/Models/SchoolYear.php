<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolYear extends Model
{
    protected $fillable = ['label'];

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }
}
