<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    public const GENERAL = 'general';

    public const SPECIFIC = 'specific';

    protected $fillable = ['name', 'type'];
}
