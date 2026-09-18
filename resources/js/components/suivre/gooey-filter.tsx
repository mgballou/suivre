/**
 * The one gooey filter in the application, mounted at the app shell so any
 * descendant can reference `url(#gooey)`.
 *
 * Two shapes inside a filtered group are blurred together and then had their
 * alpha re-contrasted, so overlapping edges fuse into one substance instead of
 * reading as two rectangles. Tuned deliberately soft: at stdDeviation 3 with an
 * alpha slope of 20 the ligature is short enough to be felt at the boundary and
 * invisible once the shapes come to rest. Turned up, the technique has a lot of
 * personality; this app wants almost none of it.
 *
 * The filter itself is static, so `prefers-reduced-motion` has nothing to
 * remove here — the travel it acts on is what gets removed, at the call site.
 */
export function GooeyFilter() {
    return (
        <svg
            aria-hidden="true"
            focusable="false"
            width="0"
            height="0"
            className="absolute"
        >
            <defs>
                <filter id="gooey">
                    <feGaussianBlur
                        in="SourceGraphic"
                        stdDeviation="3"
                        result="blurred"
                    />
                    <feColorMatrix
                        in="blurred"
                        type="matrix"
                        values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 20 -9"
                    />
                </filter>
            </defs>
        </svg>
    );
}
