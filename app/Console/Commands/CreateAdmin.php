<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'nai:create-admin {--name=} {--email=} {--password=}';
    protected $description = 'Create the initial NAi administrator account';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password (min 12 characters)');
        $validator = Validator::make(['name' => $name, 'email' => $email, 'password' => $password], ['name' => 'required|string|max:120', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:12']);
        if ($validator->fails()) { foreach ($validator->errors()->all() as $error) $this->error($error); return self::FAILURE; }
        User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'role' => 'admin', 'title' => 'Platform Administrator']);
        $this->info('Administrator created. Sign in at /login.');
        return self::SUCCESS;
    }
}
