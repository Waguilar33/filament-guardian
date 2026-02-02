<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Console\Attribute\AsCommand;
use Waguilar\FilamentGuardian\Facades\Guardian;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[AsCommand(name: 'guardian:create-user', description: 'Create a new user')]
class CreateUserCommand extends Command
{
    /** @var string */
    public $signature = 'guardian:create-user
        {--name= : User name}
        {--email= : User email}
        {--password= : User password}';

    /** @var string */
    public $description = 'Create a new user';

    public function handle(): int
    {
        $userModel = $this->getUserModel();

        $userData = $this->collectUserData($userModel);
        if ($userData === null) {
            return self::FAILURE;
        }

        try {
            Guardian::createUser($userModel, $userData);
        } catch (\Throwable $e) {
            $this->components->error('Failed to create user: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('User created successfully!');
        $this->components->twoColumnDetail('Name', $userData['name']);
        $this->components->twoColumnDetail('Email', $userData['email']);

        return self::SUCCESS;
    }

    /**
     * @return class-string<Model>
     */
    protected function getUserModel(): string
    {
        /** @var class-string<Model> $model */
        $model = config('auth.providers.users.model');

        return $model;
    }

    /**
     * @param  class-string<Model>  $userModel
     * @return array{name: string, email: string, password: string}|null
     */
    protected function collectUserData(string $userModel): ?array
    {
        /** @var string|null $name */
        $name = $this->option('name');

        /** @var string|null $email */
        $email = $this->option('email');

        /** @var string|null $password */
        $password = $this->option('password');

        if ($name === null) {
            $name = text(
                label: 'What is the user\'s name?',
                required: true,
            );
        }

        if ($email === null) {
            $email = text(
                label: 'What is the user\'s email?',
                required: true,
                validate: function (string $value) use ($userModel): ?string {
                    $validator = Validator::make(
                        ['email' => $value],
                        ['email' => ['required', 'email']]
                    );

                    if ($validator->fails()) {
                        return 'Please enter a valid email address.';
                    }

                    if ($userModel::query()->whereRaw('email = ?', [$value])->exists()) {
                        return 'A user with this email already exists.';
                    }

                    return null;
                },
            );
        } else {
            $validator = Validator::make(
                ['email' => $email],
                ['email' => ['required', 'email']]
            );

            if ($validator->fails()) {
                $this->components->error('Please provide a valid email address.');

                return null;
            }

            if ($userModel::query()->whereRaw('email = ?', [$email])->exists()) {
                $this->components->error("A user with email '{$email}' already exists.");

                return null;
            }
        }

        if ($password === null) {
            $password = password(
                label: 'What is the user\'s password?',
                required: true,
            );
        }

        return [
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ];
    }
}
