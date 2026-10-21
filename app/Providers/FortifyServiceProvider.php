<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\PasswordResetLinkResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::loginView(fn () => view('auth.form', ['type' => 'login']));
        Fortify::registerView(fn () => view('auth.form', ['type' => 'register']));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.form', ['type' => 'forgot']));
        Fortify::resetPasswordView(fn ($request) => view('auth.form', ['type' => 'reset', 'request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.form', ['type' => 'verify']));
        Fortify::confirmPasswordView(fn () => view('auth.form', ['type' => 'confirm']));
        Fortify::twoFactorChallengeView(fn () => view('auth.form', ['type' => 'twofactor']));
        Fortify::authenticateUsing(function (Request $r) {
            $u = User::where('email', mb_strtolower((string) $r->input('email')))->first();

            return $u && ! $u->banned_at && Hash::check((string) $r->input('password'), $u->password) ? $u : null;
        });
        RateLimiter::for('login', fn (Request $r) => [Limit::perMinute(5)->by(mb_strtolower((string) $r->input('email')).'|'.$r->ip()), Limit::perMinute(30)->by($r->ip())]);
        RateLimiter::for('two-factor', fn (Request $r) => Limit::perMinute(5)->by($r->session()->get('login.id').':'.$r->ip()));
    }
}
