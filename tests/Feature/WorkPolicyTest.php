<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Work;
use App\Policies\WorkPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function roles(): array
    {
        return ['reader' => ['reader', false, false], 'author' => ['author', true, false], 'moderator' => ['moderator', false, true], 'admin' => ['admin', true, true]];
    }

    #[DataProvider('roles')]
    public function test_roles_have_only_their_expected_permissions(string $role, bool $create, bool $staffView): void
    {
        $user = User::factory()->create(['role' => $role]);
        $work = Work::factory()->create();
        $p = new WorkPolicy;
        $this->assertSame($create, $p->create($user));
        $this->assertSame($staffView, $p->view($user, $work));
        $this->assertSame($role === 'admin', $p->update($user, $work));
        $this->assertFalse($p->download($user, $work));
    }

    public function test_suspended_owner_loses_edit_and_download_permissions(): void
    {
        $work = Work::factory()->published()->create();
        $owner = $work->owner;
        $owner->forceFill(['banned_at' => now()])->save();
        $p = new WorkPolicy;
        $this->assertFalse($p->create($owner));
        $this->assertFalse($p->update($owner, $work));
        $this->assertFalse($p->download($owner, $work));
    }
}
