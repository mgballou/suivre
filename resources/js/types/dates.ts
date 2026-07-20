/**
 * Dates arrive from the server already formatted — the client never derives one,
 * because `new Date()` reads the device timezone rather than the user's
 * configured one. These types carry that format through the component tree so a
 * plain `string` cannot be passed where a calendar date is expected.
 */
export type IsoDate = `${number}-${number}-${number}`;

export type IsoMonth = `${number}-${number}`;
