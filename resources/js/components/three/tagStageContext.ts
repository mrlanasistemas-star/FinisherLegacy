/**
 * Contract between the Home's travelling tag (TagStage.vue) and the places
 * it visits (TagAnchor.vue). Sections drop a <TagAnchor :pose> where the tag
 * should sit; the stage flies ONE 3D tag between anchors with the scroll.
 */
import type { InjectionKey, Ref } from 'vue';

export type TagArt = {
    name: string;
    subtitle: string | null;
    year: string | number | null;
    accent: string | null;
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
