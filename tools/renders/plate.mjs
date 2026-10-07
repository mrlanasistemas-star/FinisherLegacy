/**
 * Legacy Plate V3 geometry for the render scenes. The drawing itself lives
 * in resources/js/lib/plate-art.ts — the same module the site uses — so a
 * render and the interactive showcase can never disagree.
 */
export {
    clipBack,
    DEFAULT_SPEC,
    flMark,
    FL_PATH,
    nfcGlyph,
    plateBack,
    plateBackBody,
    plateOnRibbonSvg,
    plateDefs,
    plateFront,
    plateProfileSvg,
    plateSvg,
    SAMPLE_FRONT,
} from '../../resources/js/lib/plate-art.ts';

export const PLATE = { w: 700, h: 450, r: 45 };
