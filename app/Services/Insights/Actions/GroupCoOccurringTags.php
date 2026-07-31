<?php

declare(strict_types=1);

namespace App\Services\Insights\Actions;

use App\Services\Insights\CorrelationThresholds;
use App\Services\Insights\Data\DayMask;
use App\Services\Insights\Data\TagClusters;

class GroupCoOccurringTags
{
    /**
     * Partition tags into what may be named alone and what may only be named as
     * a pattern (D24, stage 1).
     *
     * Only pairs that actually travel together are tested — overlap is Jaccard
     * on occurrence days, so two tags that rarely coincide are separable by
     * construction and never dragged into a cluster by a stray estimate. A
     * co-occurring pair is linked when *either* direction fails
     * `CheckTagsAreSeparable`: an asymmetric failure means at least one of the
     * two cannot be named on its own, and pulling both up to the pattern level
     * is the conservative reading D24 asks for. Clusters are then the connected
     * components of that link graph, so a chain of co-travellers surfaces as
     * one pattern rather than several overlapping ones.
     *
     * @param  array<int, int|null>  $intensities  indexed by day, null where unrated
     * @param  array<int, DayMask>  $presence  keyed by category id
     * @param  array<int, float>  $marginalLifts  keyed by category id
     */
    public function __invoke(
        array $intensities,
        array $presence,
        array $marginalLifts,
        int $windowDays,
    ): TagClusters {
        $tagIds = array_keys($presence);
        sort($tagIds);

        $parent = array_combine($tagIds, $tagIds);

        foreach ($tagIds as $subjectIndex => $subjectId) {
            foreach (array_slice($tagIds, $subjectIndex + 1) as $partnerId) {
                $subject = $presence[$subjectId];
                $partner = $presence[$partnerId];

                if ($subject->overlap($partner) < CorrelationThresholds::CO_OCCURRENCE_OVERLAP) {
                    continue;
                }

                $separable = app(CheckTagsAreSeparable::class)(
                    $intensities,
                    $subject,
                    $partner,
                    $marginalLifts[$subjectId] ?? 0.0,
                    $windowDays,
                ) && app(CheckTagsAreSeparable::class)(
                    $intensities,
                    $partner,
                    $subject,
                    $marginalLifts[$partnerId] ?? 0.0,
                    $windowDays,
                );

                if (! $separable) {
                    $parent = $this->link($parent, $subjectId, $partnerId);
                }
            }
        }

        $groups = [];

        foreach ($tagIds as $tagId) {
            $groups[$this->rootOf($parent, $tagId)][] = $tagId;
        }

        ksort($groups);

        return new TagClusters(array_values($groups));
    }

    /**
     * @param  array<int, int>  $parent
     * @return array<int, int>
     */
    private function link(array $parent, int $left, int $right): array
    {
        $leftRoot = $this->rootOf($parent, $left);
        $rightRoot = $this->rootOf($parent, $right);

        if ($leftRoot !== $rightRoot) {
            $parent[max($leftRoot, $rightRoot)] = min($leftRoot, $rightRoot);
        }

        return $parent;
    }

    /**
     * @param  array<int, int>  $parent
     */
    private function rootOf(array $parent, int $tagId): int
    {
        while ($parent[$tagId] !== $tagId) {
            $tagId = $parent[$tagId];
        }

        return $tagId;
    }
}
