<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['name' => 'array'];
    }

    public function label(): string
    {
        return $this->name[app()->getLocale()] ?? $this->name['ar'] ?? $this->slug;
    }

    public function works()
    {
        return $this->hasMany(Work::class);
    }
}
