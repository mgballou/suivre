<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\Models\Role;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    /**
     * Validate and create a newly registered user. Registration always mints a
     * member — the backstage admin role is never self-assignable here.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'timezone' => $this->registrationTimezoneRules(),
        ])->validate();

        $attributes = [
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ];

        /*
         * The whole journal is keyed on the user's local day (D5), so a wrong
         * timezone silently files meals and ratings against the wrong date —
         * for someone thirteen hours from UTC, most of an evening. Nobody would
         * think to go and correct a setting they never saw, so registration
         * takes the browser's own answer and the profile screen stays the
         * override rather than the only source.
         */
        if (! empty($input['timezone'])) {
            $attributes['timezone'] = $input['timezone'];
        }

        $user = User::create($attributes);

        $user->assignRole(Role::findOrCreate(UserRole::Member->value));

        return $user;
    }
}
