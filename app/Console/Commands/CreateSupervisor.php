<?php

namespace App\Console\Commands;

use App\Actions\Fortify\CreateNewUser;
use App\Enums\Role;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Public sign-up is closed, so the first account is made here, on the
 * server. From then on the supervisor adds the team from the Team page.
 */
#[Signature('mirsalink:supervisor {--name= : The supervisor’s full name} {--email= : The email they log in with}')]
#[Description('Create a supervisor account (the first one, before the Team page exists)')]
class CreateSupervisor extends Command
{
    public function handle(CreateNewUser $createNewUser): int
    {
        $name = $this->option('name') ?: text('Name', required: true);
        $email = $this->option('email') ?: text('Email', required: true);
        $secret = password('Password', required: true, hint: 'At least 8 characters.');

        try {
            $user = $createNewUser->create([
                'name' => $name,
                'email' => $email,
                'password' => $secret,
                'password_confirmation' => $secret,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        // A supervisor made on the server needs no email round trip to log in.
        $user->forceFill([
            'role' => Role::Supervisor,
            'email_verified_at' => now(),
        ])->save();

        $this->components->info("Created {$user->email}. They can log in at ".route('login').'.');

        return self::SUCCESS;
    }
}
