/**
 * Shared tag types: TagArt (a collection's artwork) and TagPose (face,
 * turn, tilt…) used by HeroTag3D and the home sections.
 */
export type TagArt = {
    name: string;
    subtitle: string | null;
    year: string | number | null;
    accent: string | null;
    /** Collection `tag_design` (deck pp. 7–8); absent → procedural front. */
    frontArt?: string | null;
    nameLine?: string | null;
    disciplineLine?: string | null;
    backLine?: string | null;
};

export type TagPose = {
    face?: 'front' | 'back';
    /** Extra yaw in radians (positive turns the right edge away). */
    turn?: number;
    /** Pitch in radians (positive leans the top away). */
    tilt?: number;
    /** Roll in radians. */
    roll?: number;
    /** Show the NFC "tap" pulse around the tag. */
    pulse?: boolean;
    /** Artwork for this stop; the stage's default collection otherwise. */
    art?: TagArt | null;
};
