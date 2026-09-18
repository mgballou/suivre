<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use App\Services\Journal\Actions\ResolveJournalBounds;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Reject a journal date the user cannot have lived through.
 *
 * The day routes carry the date in the path, so the only thing between a
 * mistyped year and a stored row is validation. The engine's read cost is
 * linear in first rating to last rating, which makes an unbounded date an
 * unbounded insights page — and until this rule the route's own ceiling was
 * 9999-12-31.
 *
 * The bound is applied at the edge rather than inside the Actions because the
 * Actions also write history on behalf of seeders and the test suite, which
 * legitimately plant months of journal before an account's `created_at`. What
 * is bounded is what a request may ask for.
 *
 * Pair it with `bail`: it parses the value, so it must not run on one that
 * failed `date_format` — which is also what leaves nothing but a string here.
 */
class WithinJournalBounds implements ValidationRule
{
    public function __construct(private readonly User $user) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $bounds = app(ResolveJournalBounds::class)($this->user, CarbonImmutable::now());
        $date = CarbonImmutable::parse($value);

        if ($bounds->covers($date)) {
            return;
        }

        $fail($bounds->follows($date)
            ? 'That day has not happened yet.'
            : 'Your journal starts on ' . $bounds->earliest->format('j F Y') . '.');
    }
}
