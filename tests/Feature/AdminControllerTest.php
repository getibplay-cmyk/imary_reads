<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\User;
use App\Models\Work;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        return $user;
    }

    public function test_admin_pages_render_and_readers_are_refused(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->admin();
        foreach (['/admin', '/admin/users', '/admin/taxonomy', '/admin/posts', '/admin/files', '/admin/audit', '/admin/settings'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_publishing_requires_rights_confirmation_and_available_content(): void
    {
        $work = Work::factory()->create(['rights_confirmed' => false]);
        $this->admin();
        $this->patchJson(route('admin.works', $work), ['status' => 'published'])->assertUnprocessable();
        $work->update(['rights_confirmed' => true]);
        $this->patchJson(route('admin.works', $work), ['status' => 'published'])->assertUnprocessable();
        Chapter::factory()->for($work)->published()->create();
        $this->patch(route('admin.works', $work), ['status' => 'published', 'featured' => 1])->assertRedirect();
        $this->assertDatabaseHas('works', ['id' => $work->id, 'status' => 'published', 'featured' => true]);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $work->id, 'action' => 'work.moderated']);
    }

    public function test_moderator_cannot_change_accounts_or_configuration(): void
    {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'moderator']))->withSession(['auth.password_confirmed_at' => time()]);
        $this->patch(route('admin.user', $user), ['role' => 'admin'])->assertForbidden();
        $this->put(route('admin.settings.update'), ['contact_email' => 'test@example.org'])->assertForbidden();
        $this->assertSame('reader', $user->fresh()->role);
    }

    public function test_admin_cannot_disable_or_demote_own_access(): void
    {
        $admin = $this->admin();
        $this->patchJson(route('admin.user', $admin), ['role' => 'reader'])->assertUnprocessable();
        $this->patchJson(route('admin.user', $admin), ['role' => 'admin', 'banned' => 1])->assertUnprocessable();
        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertNull($admin->fresh()->banned_at);
    }

    public function test_banning_author_hides_works_and_records_an_audit(): void
    {
        $work = Work::factory()->published()->create();
        $this->admin();
        $this->patch(route('admin.user', $work->owner), ['role' => 'author', 'banned' => 1])->assertRedirect();
        $this->assertNotNull($work->owner->fresh()->banned_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.access_updated', 'subject_id' => $work->owner_id]);
        $this->assertFalse($work->fresh()->isPublic());
    }

    public function test_production_admin_requires_confirmed_two_factor_authentication(): void
    {
        $this->admin();
        $this->app->instance('env', 'production');
        $this->get('/admin')->assertRedirect('/security');
    }

    public function test_external_author_and_multilingual_category_are_managed_without_user_accounts(): void
    {
        $this->admin();
        $users = User::count();
        $this->post(route('admin.author'), ['name' => 'مؤلف خارجي', 'bio' => 'محتوى مرخص للنشر.'])->assertRedirect();
        $this->assertDatabaseHas('authors', ['name' => 'مؤلف خارجي', 'user_id' => null]);
        $this->assertSame($users, User::count());
        $this->post(route('admin.category'), ['slug' => 'history', 'name' => ['ar' => 'تاريخ', 'fr' => 'Histoire', 'en' => 'History']])->assertRedirect();
        $this->assertDatabaseHas('categories', ['slug' => 'history']);
    }

    public function test_admin_settings_store_without_exposing_raw_html(): void
    {
        $this->admin();
        $this->put(route('admin.settings.update'), ['announcement' => '<script>unsafe</script>', 'contact_email' => 'contact@example.org'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('settings', ['key' => 'announcement']);
        $this->get('/admin/settings')->assertOk()->assertDontSee('<script>unsafe</script>', false);
    }
}
