<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $attributes = ['role' => 'reader', 'locale' => 'ar'];

    protected $fillable = ['name', 'email', 'password', 'locale'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'banned_at' => 'datetime', 'two_factor_confirmed_at' => 'datetime'];
    }

    public function author()
    {
        return $this->hasOne(Author::class);
    }

    public function works()
    {
        return $this->hasMany(Work::class, 'owner_id');
    }

    public function favorites()
    {
        return $this->belongsToMany(Work::class, 'favorites')->withTimestamps();
    }

    public function shelves()
    {
        return $this->hasMany(Shelf::class);
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'moderator'], true);
    }

    public function canWrite(): bool
    {
        return in_array($this->role, ['author', 'admin'], true) && ! $this->banned_at;
    }

    public function preferredLocale(): string
    {
        return $this->locale;
    }
}
