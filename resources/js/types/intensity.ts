/**
 * The petrol intensity ramp step (D20). Step 0 is an unlogged day; 1–5 climb
 * the ramp. Shared by every surface that encodes condition intensity — the
 * calendar day cell and the heatmap read the one scale so a second, drifting
 * one cannot appear.
 */
export type IntensityLevel = 0 | 1 | 2 | 3 | 4 | 5;
