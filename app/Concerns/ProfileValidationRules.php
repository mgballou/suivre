<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|Unique|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user timezones.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function timezoneRules(): array
    {
        return ['required', 'string', 'timezone:all'];
    }

    /**
     * The same rules at registration, where the value is guessed by the browser
     * rather than chosen.
     *
     * Optional because it must be: the field is filled by JavaScript reading
     * `Intl.DateTimeFormat`, and a client that cannot supply it has to be able
     * to register anyway. Missing falls back to the column default, which the
     * profile screen can then correct.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function registrationTimezoneRules(): array
    {
        return ['nullable', 'string', 'timezone:all'];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|Unique|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
