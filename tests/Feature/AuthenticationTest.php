<?php

namespace Tests\Feature;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registration_never_accepts_admin_role_and_sends_verification(): void
    {
        Notification::fake([VerifyEmail::class]);
        $this->post('/register', ['name' => 'قارئ', 'email' => 'Reader@Example.org', 'password' => 'LongSecret1234', 'password_confirmation' => 'LongSecret1234', 'role' => 'admin', 'locale' => 'fr', 'terms' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $user = User::where('email', 'reader@example.org')->firstOrFail();
        $this->assertSame('reader', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('LongSecret1234', $user->password));
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_requires_strong_password_and_terms(): void
    {
        $this->postJson('/register', ['name' => 'Reader', 'email' => 'new@example.org', 'password' => 'weak', 'password_confirmation' => 'weak'])->assertUnprocessable()->assertJsonValidationErrors(['password', 'terms']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_accepts_valid_account_but_refuses_banned_account(): void
    {
        $user = User::factory()->create(['banned_at' => now()]);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
        $this->assertGuest();
        $user->forceFill(['banned_at' => null])->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/me');
        $this->assertAuthenticatedAs($user);
    }

    public function test_banned_existing_session_is_invalidated(): void
    {
        $user = User::factory()->create(['banned_at' => now()]);
        $this->actingAs($user)->get('/me')->assertForbidden();
        $this->assertGuest();
    }

    public function test_signed_email_verification_is_required_and_valid_link_verifies(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);
        $this->get('/email/verify/'.$user->id.'/'.sha1($user->email))->assertForbidden();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_password_recovery_response_does_not_disclose_account_existence(): void
    {
        $user = User::factory()->create();
        Notification::fake([ResetPassword::class]);
        $known = $this->postJson('/forgot-password', ['email' => $user->email])->assertOk();
        $unknown = $this->postJson('/forgot-password', ['email' => 'absent@example.org'])->assertOk();
        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_token_changes_password_only_once(): void
    {
        $user = User::factory()->create();
        Notification::fake([ResetPassword::class]);
        $this->post('/forgot-password', ['email' => $user->email]);
        $token = Notification::sent($user, ResetPassword::class)->first()->token;
        $payload = ['token' => $token, 'email' => $user->email, 'password' => 'DifferentSecret123', 'password_confirmation' => 'DifferentSecret123'];
        $this->post('/reset-password', $payload)->assertSessionHasNoErrors()->assertRedirect('/login');
        $this->assertTrue(Hash::check('DifferentSecret123', $user->fresh()->password));
        $this->postJson('/reset-password', $payload)->assertUnprocessable();
    }

    public function test_two_factor_setup_requires_password_confirmation_and_valid_code(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/user/two-factor-authentication')->assertRedirect('/user/confirm-password');
        $this->withSession(['auth.password_confirmed_at' => time()])->post('/user/two-factor-authentication')->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->postJson('/user/confirmed-two-factor-authentication', ['code' => 'invalid'])->assertUnprocessable();
        $code = (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret));
        $this->post('/user/confirmed-two-factor-authentication', ['code' => $code])->assertRedirect();
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_csrf_rejects_post_without_token_outside_testing_bypass(): void
    {
        $this->app->instance('env', 'local');
        $this->post('/locale', ['locale' => 'fr'])->assertStatus(419);
        $this->assertNotSame('fr', session('locale'));
    }

    public function test_login_rate_limit_blocks_repeated_bad_passwords(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'unknown@example.org', 'password' => 'invalid'])->assertUnprocessable();
        }
        $this->postJson('/login', ['email' => 'unknown@example.org', 'password' => 'invalid'])->assertTooManyRequests();
    }

    public function test_password_reset_revokes_existing_sessions_and_remember_token(): void
    {
        $user = User::factory()->create();
        $oldToken = $user->remember_token;
        DB::table('sessions')->insert(['id' => 'old-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        app(ResetUserPassword::class)->reset($user, ['password' => 'NewSecurePass123', 'password_confirmation' => 'NewSecurePass123']);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-device']);
        $this->assertNotSame($oldToken, $user->fresh()->remember_token);
    }

    public function test_production_rejects_untrusted_host_headers(): void
    {
        $this->app->instance('env', 'production');
        $this->get('https://attacker.example/login')->assertBadRequest();
    }
}
