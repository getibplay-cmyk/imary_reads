<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\Work;
use App\Policies\WorkPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (config('imary.trusted_proxies')) {
            TrustProxies::at(config('imary.trusted_proxies'));
        }

        View::composer('components.layout', function ($view) {
            $view->with('siteAnnouncement', Setting::find('announcement')?->value['text'] ?? null);
        });
        Password::defaults(fn () => Password::min(12)->letters()->mixedCase()->numbers());
        Gate::policy(Work::class, WorkPolicy::class);
        RateLimiter::for('auth-forms', fn (Request $r) => $r->isMethod('GET') ? Limit::none() : Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('search', fn (Request $r) => Limit::perMinute(90)->by($r->user()?->id ?? $r->ip()));
        RateLimiter::for('write', fn (Request $r) => Limit::perMinute(45)->by($r->user()?->id ?? $r->ip()));
        RateLimiter::for('community', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?? $r->ip()));
        RateLimiter::for('downloads', fn (Request $r) => Limit::perHour(30)->by($r->user()?->id ?? $r->ip()));
    }
}
