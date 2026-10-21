<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Work;

class WorkPolicy
{
    public function view(?User $user, Work $work): bool
    {
        return $work->isPublic() || ($user && ! $user->banned_at && ($user->id === $work->owner_id || $user->isStaff()));
    }

    public function create(User $user): bool
    {
        return $user->canWrite() && $user->hasVerifiedEmail();
    }

    public function update(User $user, Work $work): bool
    {
        return ! $user->banned_at && ($user->role === 'admin' || ($user->canWrite() && $user->id === $work->owner_id));
    }

    public function delete(User $user, Work $work): bool
    {
        return $this->update($user, $work);
    }

    public function download(User $user, Work $work): bool
    {
        return ! $user->banned_at && $user->hasVerifiedEmail() && $work->isPublic() && $work->allow_download && $work->rights_confirmed;
    }
}
