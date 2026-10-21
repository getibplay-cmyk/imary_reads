<?php

namespace App\Actions\Fortify;

use App\Models\Author;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        $input['email'] = mb_strtolower($input['email'] ?? '');
        Validator::make($input, ['name' => 'required|string|max:120', 'email' => 'required|email|max:255|unique:users,email', 'password' => $this->passwordRules(), 'locale' => ['nullable', Rule::in(['ar', 'fr', 'en'])], 'writer' => 'nullable|boolean', 'terms' => 'accepted'])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create(['name' => $input['name'], 'email' => $input['email'], 'password' => $input['password'], 'locale' => $input['locale'] ?? app()->getLocale()]);
            if (! empty($input['writer'])) {
                $user->forceFill(['role' => 'author'])->save();
                Author::create(['user_id' => $user->id, 'name' => $user->name, 'slug' => 'author-'.Str::ulid()]);
            }

            return $user;
        });
    }
}
