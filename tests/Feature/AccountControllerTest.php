<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\User;
use App\Models\Work;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_verified_reader_can_become_author_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('become-writer'))->assertRedirect(route('writer.index'));
        $this->assertSame('author', $user->fresh()->role);
        $this->assertDatabaseHas('authors', ['user_id' => $user->id]);
        $this->postJson(route('become-writer'))->assertUnprocessable();
        $this->assertDatabaseCount('authors', 1);
    }

    public function test_settings_preserve_role_and_apply_locale(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('account.update'), ['name' => 'Lecteur', 'locale' => 'fr', 'role' => 'admin', 'email_verified_at' => now()])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Lecteur', 'locale' => 'fr', 'role' => 'reader']);
        $this->get('/settings')->assertOk()->assertSee('lang="fr"', false);
    }

    public function test_private_pages_and_export_do_not_expose_secrets_or_other_users_data(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
        foreach (['/me', '/settings', '/security', '/notifications'] as $url) {
            $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        }
        $response = $this->get('/account/export')->assertOk()->assertDownload('imary-account.json');
        $data = json_decode($response->streamedContent(), true);
        $this->assertSame($user->email, $data['account']['email']);
        $this->assertArrayNotHasKey('password', $data['account']);
        $this->assertStringNotContainsString($other->email, $response->streamedContent());
    }

    public function test_account_deletion_requires_password_and_withdraws_works(): void
    {
        $work = Work::factory()->published()->create();
        $user = $work->owner;
        $this->actingAs($user);
        $this->deleteJson('/account', ['password' => 'incorrect'])->assertUnprocessable();
        $this->assertModelExists($user);
        $this->delete('/account', ['password' => 'password'])->assertSessionHasNoErrors()->assertRedirect('/');
        $this->assertModelMissing($user);
        $this->assertGuest();
        $this->assertDatabaseHas('works', ['id' => $work->id, 'status' => 'archived', 'owner_id' => null, 'allow_download' => false]);
    }

    public function test_follower_notification_is_sent_once_after_scheduled_chapter_becomes_public(): void
    {
        $this->freezeTime();
        $work = Work::factory()->published()->create();
        $reader = User::factory()->create();
        $work->author->followers()->attach($reader);
        $chapter = Chapter::factory()->for($work)->published()->create(['published_at' => now()->addHour()]);
        $this->artisan('imary:notify-chapters')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 0);
        $this->travel(2)->hours();
        $this->artisan('imary:notify-chapters')->assertSuccessful();
        $this->artisan('imary:notify-chapters')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertNotNull($chapter->fresh()->notified_at);
    }
}
