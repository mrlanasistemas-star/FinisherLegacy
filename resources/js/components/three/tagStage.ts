/**
 * Directed renderer for the Home hero tag (HeroTag3D.vue): ONE WebGL
 * context on a canvas that fills the hero stage. It only draws what it is
 * told — the director in HeroTag3D.vue decides rotation and artwork every
 * frame. No controls here: drag/keys are handled by the DOM wrapper.
 *
 * Loaded through a dynamic import only, like tagScene.ts.
 */
import * as THREE from 'three';
import { loadArtworkFonts, loadTagImages } from './tagArtwork';
import type { TagModelOptions } from './tagModel';
import { H, STRAP_TOP, addStudioLighting, createTagModel } from './tagModel';
import type { TagArt } from './tagStageContext';

export type StageRotation = { x: number; y: number; z: number; lift: number };

export type TagStageHandle = {
    canvas: HTMLCanvasElement;
    /** Body center, as a fraction of the box height from the top (0…1). */
    bodyCenter: number;
    /** Body height, as a fraction of the box height. */
    bodyHeight: number;
    setArtwork: (art: TagArt) => void;
    resize: (width: number, height: number) => void;
    render: (rotation: StageRotation) => void;
    dispose: () => void;
};

/** Visible frame around the object (world units). */
const FRAME_H = STRAP_TOP + H / 2 + 0.45;
const FRAME_W = 3;
const CENTER_Y = (-H / 2 + STRAP_TOP) / 2;

const HEX = /^#[0-9a-f]{3}([0-9a-f]{3})?$/i;
/** Negro Carbono (brand manual §5.2) — the resin body; the loop is black silicone. */
const CARBON = '#0b0b0c';
const LOOP = '#111113';

/** A collection's artwork → model options (carbon body + its tag design). */
function modelOptions(art: TagArt): TagModelOptions {
    const accent = art.accent && HEX.test(art.accent) ? art.accent : '#c9ae8b';

    return {
        name: art.name,
        subtitle: art.subtitle,
        year:
            art.year === null || art.year === undefined || art.year === ''
                ? null
                : String(art.year),
        accent,
        bodyColor: '#141416',
        backColor: CARBON,
        frontArt: art.frontArt ?? null,
        nameLine: art.nameLine ?? null,
        disciplineLine: art.disciplineLine ?? null,
        backLine: art.backLine ?? null,
        strapColor: LOOP,
        metalColor: '#c9ae8b',
    };
}

/** Throws when WebGL is unavailable — the stage stays on its SVG posters. */
export async function createTagStage(
    host: HTMLElement,
    art: TagArt,
    /** A late redraw happened (artwork loaded): render a frame. */
    onRedraw?: () => void,
): Promise<TagStageHandle> {
    const first = modelOptions(art);
    // Bounded: fonts + the first design's artwork, so frame one is final.
    await Promise.race([
        Promise.all([loadArtworkFonts(), loadTagImages(first)]),
        new Promise((resolve) => setTimeout(resolve, 2000)),
    ]);

    const renderer = new THREE.WebGLRenderer({
        antialias: true,
        alpha: true,
        powerPreference: 'high-performance',
    });
    // DPR capped: the hero canvas is large; 1.75 keeps it crisp and cheap.
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.NeutralToneMapping;
    renderer.toneMappingExposure = 1.05;
    renderer.setClearColor(0x000000, 0);

    const canvas = renderer.domElement;
    canvas.style.display = 'block';
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvas.setAttribute('aria-hidden', 'true');
    host.appendChild(canvas);

    const scene = new THREE.Scene();
    const disposeLighting = addStudioLighting(renderer, scene);
    const camera = new THREE.PerspectiveCamera(26, 1, 0.1, 100);
    const model = createTagModel(renderer, first, onRedraw);
    const pivot = new THREE.Group();
    pivot.add(model.group);
    scene.add(pivot);

    let visibleH = FRAME_H;

    const handle: TagStageHandle = {
        canvas,
        bodyCenter: 0.5,
        bodyHeight: H / FRAME_H,
        setArtwork(next) {
            model.setArtwork(modelOptions(next));
        },
        resize(width, height) {
            const w = Math.max(width, 1);
            const h = Math.max(height, 1);
            const aspect = w / h;
            renderer.setSize(w, h, false);
            camera.aspect = aspect;
            visibleH = Math.max(FRAME_H, FRAME_W / aspect);
            const distance =
                visibleH / 2 / Math.tan(THREE.MathUtils.degToRad(13));
            camera.position.set(0, CENTER_Y, distance);
            camera.lookAt(0, CENTER_Y, 0);
            camera.updateProjectionMatrix();
            handle.bodyCenter = 0.5 + CENTER_Y / visibleH;
            handle.bodyHeight = H / visibleH;
        },
        render({ x, y, z, lift }) {
            pivot.rotation.set(x, y, z, 'YXZ');
            pivot.position.y = lift;
            renderer.render(scene, camera);
        },
        dispose() {
            model.dispose();
            disposeLighting();
            renderer.dispose();
            canvas.remove();
        },
    };

    return handle;
}
