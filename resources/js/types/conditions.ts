/**
 * The curated set a condition's colour is chosen from (D20), mirrored from the
 * PHP `ConditionHue`. A closed union rather than `string`: the ramp for a hue
 * outside this set does not exist, so a typo has to fail at compile time.
 */
export type ConditionHue =
    | 'clay'
    | 'ochre'
    | 'moss'
    | 'marine'
    | 'indigo'
    | 'violet'
    | 'plum';

/** A daily condition rating. 0 is "nothing today", which is a record, not a gap. */
export type ConditionIntensity = 0 | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10;
