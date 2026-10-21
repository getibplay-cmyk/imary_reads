<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChapterVersion extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [];
    }

    public function chapter()
    {
        return $this->belongsTo(Chapter::class);
    }
}
