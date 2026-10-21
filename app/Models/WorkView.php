<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkView extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [];
    }
}
