<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Locale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->session()->get('locale', $request->user()?->locale ?? 'ar');
        app()->setLocale(in_array($locale, ['ar', 'fr', 'en'], true) ? $locale : 'ar');

        return $next($request);
    }
}
