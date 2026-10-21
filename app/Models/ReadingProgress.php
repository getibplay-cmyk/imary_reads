<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReadingProgress extends Model
{
    protected $guarded = ['id'];

    protected $table = 'reading_progress';

    protected function casts(): array
    {
        return [];
    }

    public function work()
    {
        return $this->belongsTo(Work::class);
    }

    public function chapter()
    {
        return $this->belongsTo(Chapter::class);
    }
}
