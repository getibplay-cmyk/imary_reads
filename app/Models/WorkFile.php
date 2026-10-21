<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkFile extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'import_requested' => 'boolean', 'imported_at' => 'datetime'];
    }

    public function work()
    {
        return $this->belongsTo(Work::class);
    }
}
