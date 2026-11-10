/**
 * Shared tag types: TagArt (a collection's artwork) and TagPose (face,
 * turn, tilt…) used by HeroTag3D and the TagAnchor posters. TAG_STAGE is
 * an optional provider: outside one, TagAnchor shows its SVG poster.
 */
import type { InjectionKey, Ref } from 'vue';

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

export type AnchorEntry = {
    id: number;
    el: HTMLElement;
    pose: () => TagPose;
    /** Also travels to this anchor on phones (< lg). */
    mobile: boolean;
    /** The one anchor the tag stays on under reduced motion (the hero). */
    primary: boolean;
};

export type TagStageContext = {
    register: (entry: AnchorEntry) => void;
    unregister: (id: number) => void;
    /** Live anchors render nothing (the 3D tag is there); others a poster. */
    isLive: (entry: Pick<AnchorEntry, 'mobile' | 'primary'>) => boolean;
    /** Hero drag: add yaw to the tag while it sits on a primary anchor. */
    spin: (radians: number) => void;
    /** Undo the hero drag (e.g. when Frente / Reverso is chosen). */
    resetSpin: () => void;
    ready: Ref<boolean>;
    defaultArt: Ref<TagArt>;
};

export const TAG_STAGE: InjectionKey<TagStageContext> = Symbol('tag-stage');
