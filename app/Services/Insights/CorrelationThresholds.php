<?php

declare(strict_types=1);

namespace App\Services\Insights;

/**
 * Every tunable number the lag-lift engine leans on, in one place with the
 * SUI-36 spike finding that justifies it.
 *
 * These are deliberately constants and not configuration: the MVP computes
 * correlations on demand with no operator knobs. E5 (SUI-25) promotes the set
 * to Spatie runtime settings so the backstage can tune the lag window and the
 * separability bar without a deploy — at which point this class becomes the
 * settings object's defaults rather than the source of truth. Nothing here is
 * read from `config()` today on purpose; a knob that exists in two places
 * drifts (D16).
 */
final class CorrelationThresholds
{
    /**
     * Days after a tag occurrence that count as exposed, the occurrence day
     * included — so a window of 3 marks days t … t+3.
     *
     * D11 defaulted to 0–2. SUI-36 finding 5 showed the observed lift peaks
     * around lag 3 and stays elevated out to day 7, so 0–2 is provably a little
     * narrow. The whole lag profile is returned regardless; this only sets the
     * window the headline lift is measured over.
     */
    public const EXPOSURE_WINDOW_DAYS = 3;

    /**
     * How far the returned lag profile reaches. SUI-36 finding 5: the effect
     * smears to roughly a week, so the shape is more honest than any single N.
     */
    public const LAG_PROFILE_DAYS = 7;

    /**
     * Distinct local days carrying a rating for the condition, below which the
     * report refuses to rank at all.
     *
     * SUI-36 findings 1 and 6: hit-rate for a ≥1.5-point trigger only reaches
     * 0.8 around 75–90 days, and the softest possible surface — a single
     * tentative hint — is right just 0.58 of the time at 30 days and 0.66 at
     * 60. Only 90 days clears 0.7.
     */
    public const MINIMUM_LOGGED_DAYS = 90;

    /**
     * Rated exposed days a tag needs before it is ranked. SUI-36 finding 2: on
     * a single 90-day draw pure-noise tags routinely out-rank real ones, and
     * the thinnest tags are where that happens. A tag we cannot measure is not
     * a suspect — it is left out rather than ranked badly.
     */
    public const MINIMUM_EXPOSED_DAYS = 10;

    /**
     * Rated tag-free days a tag's baseline needs, for the same reason.
     */
    public const MINIMUM_BASELINE_DAYS = 10;

    /**
     * The percentile of a tag's own null distribution that its lift must beat
     * to be flagged as clearing the noise band — the detection criterion SUI-36
     * used throughout (`sweep.is_hit`, `alerts.alert_precision`).
     */
    public const NOISE_BAND_PERCENTILE = 95.0;

    /**
     * How many circular shifts of a tag's occurrence series are used to build
     * that null distribution. Shifting rather than resampling keeps the tag's
     * rate and its day-to-day clumping intact, which matters because flares are
     * sticky (AR(1)) and an i.i.d. null would understate the band.
     */
    public const MAXIMUM_NOISE_BAND_SHIFTS = 60;

    /**
     * Fewer usable shifts than this and no band can be estimated; the tag is
     * reported as not clearing rather than clearing on a guess.
     */
    public const MINIMUM_NOISE_BAND_SHIFTS = 8;

    /**
     * Jaccard overlap of two tags' occurrence days above which they count as
     * travelling together and their attribution has to be defended.
     *
     * Calibrated against the SUI-36 dessert scenario, where the planted latent
     * factor drives dairy and sugar to an overlap near 0.6 while independent
     * tags at comparable base rates sit near 0.17. 0.4 sits in that gap.
     */
    public const CO_OCCURRENCE_OVERLAP = 0.4;

    /**
     * Days a tag appears without its co-traveller (and days clean of both) that
     * the stratified estimate needs before it is believed. Below this the
     * stratified lift is noise, and reading noise as "separable" would hand
     * back exactly the single-tag accusation D24 forbids.
     */
    public const MINIMUM_DISCORDANT_DAYS = 10;

    /**
     * Fraction of its marginal lift a tag must retain once its co-traveller is
     * stratified out. Collapsing toward zero is the signature of a tag riding
     * on its neighbour rather than carrying an effect of its own; holding half
     * the lift on discordant days is the bar for naming it alone.
     */
    public const SEPARABLE_LIFT_RETENTION = 0.5;
}
