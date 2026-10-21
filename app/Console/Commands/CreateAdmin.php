<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'imary:admin {email}';

    protected $description = 'Create the initial administrator with a hidden password prompt';

    public function handle(): int
    {
        if (User::where('role', 'admin')->exists()) {
            $this->error('Use the administrator interface to manage additional administrators.');

            return self::FAILURE;
        }
        $data = ['email' => mb_strtolower($this->argument('email')), 'name' => $this->ask('Display name'), 'password' => $this->secret('Password (12+ characters, upper/lowercase and numbers)')];
        $v = Validator::make($data, ['email' => 'required|email|unique:users,email', 'name' => 'required|max:120', 'password' => ['required', Password::defaults()]]);
        if ($v->fails()) {
            $this->error($v->errors()->first());

            return self::FAILURE;
        }
        $u = User::create($data + ['locale' => 'ar']);
        $u->forceFill(['role' => 'admin', 'email_verified_at' => now()])->save();
        $this->info('Administrator created. Enable two-factor authentication before production administration.');

        return self::SUCCESS;
    }
}
