<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One collapsible section of the day, as the server sees it.
 *
 * `summary` states what is on file — "3 items", "None", "Not recorded" — and
 * never what is outstanding. A completion count is the one thing this line must
 * not become: it would turn a journal into a checklist, which is the reading
 * D20 exists to refuse.
 *
 * `recorded` drives which card the server opens, not a tick in the UI.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class DaySection implements Arrayable
{
    public function __construct(
        public string $key,
        public string $title,
        public string $summary,
        public bool $recorded,
    ) {}

    /**
     * @return array{key: string, title: string, summary: string, recorded: bool}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'summary' => $this->summary,
            'recorded' => $this->recorded,
        ];
    }
}
