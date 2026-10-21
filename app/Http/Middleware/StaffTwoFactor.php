<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class StaffTwoFactor
{
    public function handle(Request $r, Closure $next)
    {
        if (app()->environment('production') && ! $r->user()?->two_factor_confirmed_at) {
            return redirect()->route('account.security')->with('status', __('ui.staff_2fa_required'));
        }

        return $next($r);
    }
}
