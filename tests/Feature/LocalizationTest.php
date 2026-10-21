<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function locales(): array
    {
        return ['Arabic' => ['ar', 'rtl', 'المكتبة'], 'French' => ['fr', 'ltr', 'Bibliothèque'], 'English' => ['en', 'ltr', 'Library']];
    }

    #[DataProvider('locales')]
    public function test_locale_switch_changes_interface_and_direction(string $locale, string $dir, string $label): void
    {
        $this->post('/locale', ['locale' => $locale])->assertRedirect();
        $this->get('/library')->assertOk()->assertSee('lang="'.$locale.'"', false)->assertSee('dir="'.$dir.'"', false)->assertSee($label);
    }

    public function test_ui_translation_keys_match_across_languages(): void
    {
        $ar = require lang_path('ar/ui.php');
        $fr = require lang_path('fr/ui.php');
        $en = require lang_path('en/ui.php');
        $expected = array_keys($ar);
        sort($expected);
        foreach ([$fr, $en] as $other) {
            $keys = array_keys($other);
            sort($keys);
            $this->assertSame($expected, $keys);
        }
    }

    public function test_invalid_locale_cannot_escape_supported_languages(): void
    {
        $this->postJson('/locale', ['locale' => '../../env'])->assertUnprocessable()->assertJsonValidationErrors('locale');
        $this->get('/')->assertSee('lang="ar"', false);
    }

    public function test_arabic_and_french_validation_messages_are_localized(): void
    {
        $this->withSession(['locale' => 'ar'])->postJson('/locale', [])->assertUnprocessable()->assertJsonPath('errors.locale.0', 'حقل locale مطلوب.');
        $this->withSession(['locale' => 'fr'])->postJson('/locale', [])->assertUnprocessable()->assertJsonPath('errors.locale.0', 'Le champ locale est obligatoire.');
    }

    public function test_verification_email_uses_recipients_preferred_language(): void
    {
        $user = User::factory()->unverified()->create(['locale' => 'fr']);
        app()->setLocale('ar');
        $user->sendEmailVerificationNotification();
        $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
        $message = $messages->last()->getOriginalMessage();
        $this->assertSame('Vérifiez votre adresse e-mail', $message->getSubject());
        $this->assertStringContainsString('Vérifier mon adresse e-mail', $message->getHtmlBody());
        $this->assertSame('ar', app()->getLocale());
    }
}
