<?php

declare(strict_types=1);

namespace App\Http\Requests\Conditions;

use App\Models\Condition;
use App\Models\ConditionLog;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ClearConditionRatingRequest extends FormRequest
{
    /**
     * Authorised against the rating itself rather than the condition, because
     * the rating is what is being removed and `ConditionLogPolicy` already owns
     * that question. Clearing is not gated on the condition still being tracked
     * either: a stopped condition's ratings stay in the journal, and a user who
     * wants one gone must be able to reach it.
     */
    public function authorize(): Response
    {
        return Gate::inspect('delete', $this->conditionLog());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * The rating named by the route's day and condition.
     *
     * Looked up by condition and date alone: scoping it to the signed-in user
     * would turn someone else's rating into a 404 and never reach the policy,
     * which is the thing that decides ownership.
     */
    public function conditionLog(): ConditionLog
    {
        $condition = $this->route('condition');
        $date = (string) $this->route('date');

        abort_unless($condition instanceof Condition, 404);
        abort_unless($this->isRealDate($date), 404);

        $log = ConditionLog::query()
            ->where('condition_id', $condition->getKey())
            ->where('date', $date)
            ->first();

        abort_unless($log instanceof ConditionLog, 404);

        return $log;
    }

    /**
     * The route's YYYY-MM-DD pattern matches days that do not exist, and the
     * lookup binds the value as a date. Checked here rather than by a rule
     * because `authorize()` runs before validation and needs the record.
     */
    private function isRealDate(string $date): bool
    {
        [$year, $month, $day] = array_map(intval(...), explode('-', $date));

        return checkdate($month, $day, $year);
    }
}
