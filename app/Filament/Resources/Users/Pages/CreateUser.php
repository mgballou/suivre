<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Services\Users\Actions\CreateUserAccount;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * With public registration closed, this page is the only way an account is
 * created. Creation is handed to the domain Action rather than left to
 * Filament's default `Model::create()`, so the role and the verified stamp are
 * settled in one place whoever asks for an account.
 */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        // A Select built from the enum hands back the case itself, not its value.
        $role = $data['role'];

        return app(CreateUserAccount::class)(
            name: Arr::string($data, 'name'),
            email: Arr::string($data, 'email'),
            password: Arr::string($data, 'password'),
            timezone: Arr::string($data, 'timezone'),
            role: $role instanceof UserRole ? $role : UserRole::from(Arr::string($data, 'role')),
        );
    }
}
