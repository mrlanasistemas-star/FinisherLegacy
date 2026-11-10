/**
 * The procedural Finisher Legacy Tag mesh (no .glb exists), shared by the
 * interactive viewer (tagScene.ts) and the Home hero stage (tagStage.ts).
 *
 * Proportions follow the real product in the strategy deck (pp. 6–8): a
 * luggage-style tag about 1 : 2, rounded corners, a horizontal SLOT near the
 * top (not a round eyelet, not a bank-card shape) and a black silicone LOOP
 * strap threaded through the slot:
 *
 * - body: a rounded-rectangle ExtrudeGeometry with a bevel and the slot cut
 *   through it, resin-like MeshPhysicalMaterial with clearcoat;
 * - FRONT / BACK: two decal caps with the canvas artwork from
 *   tagArtwork.ts (the canvas covers the whole body, header band included)
 *   — the back also uses it as a subtle bump map so the copy reads engraved;
 * - the loop: a round silicone tube along a closed curve that goes through
 *   the slot, pinches above the tag and opens into an oval loop.
 *
 * `setArtwork` redraws the artwork into the SAME canvases/textures, so the
 * Home can swap collections without rebuilding the scene.
 */
import * as THREE from 'three';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import type { TagArtworkOptions } from './tagArtwork';
import {
    ART_H,
    ART_W,
    drawBack,
    drawFront,
    loadTagImages,
    tagImagesReady,
} from './tagArtwork';

/** World units: 1 unit = 100 artwork units (ART_W = 200 → W = 2). */
export const W = 2;
export const H = W * (ART_H / ART_W);
const R = 0.3;
const DEPTH = 0.08;
const BEVEL = 0.028;
/** Slot center (horizontal stadium cut near the top edge). */
export const SLOT_Y = H / 2 - 0.22;
const SLOT_HALF_W = 0.3;
const SLOT_HALF_H = 0.085;
/** Kept for callers that still read the old eyelet position. */
export const HOLE_Y = SLOT_Y;
export const CAP_Z = DEPTH / 2 + BEVEL;
const STRAP_R = 0.055;
/** Top of the strap loop (object spans −H/2 … STRAP_TOP). */
export const STRAP_TOP = H / 2 + 1.32 + STRAP_R;

export type TagModelOptions = TagArtworkOptions & {
    strapColor: string;
    /** Accepted for compatibility; the tag has no metal parts any more. */
    metalColor: string;
};

export type TagModel = {
    group: THREE.Group;
    setArtwork: (options: TagModelOptions) => void;
    dispose: () => void;
};

function outline(withSlot: boolean): THREE.Shape {
    const x = -W / 2;
    const y = -H / 2;
    const shape = new THREE.Shape();
    shape.moveTo(x + R, y);
    shape.lineTo(x + W - R, y);
    shape.quadraticCurveTo(x + W, y, x + W, y + R);
    shape.lineTo(x + W, y + H - R);
    shape.quadraticCurveTo(x + W, y + H, x + W - R, y + H);
    shape.lineTo(x + R, y + H);
    shape.quadraticCurveTo(x, y + H, x, y + H - R);
    shape.lineTo(x, y + R);
    shape.quadraticCurveTo(x, y, x + R, y);

    if (withSlot) {
        const c = SLOT_HALF_W - SLOT_HALF_H;
        const slot = new THREE.Path();
        slot.moveTo(-c, SLOT_Y - SLOT_HALF_H);
        slot.lineTo(c, SLOT_Y - SLOT_HALF_H);
        slot.absarc(c, SLOT_Y, SLOT_HALF_H, -Math.PI / 2, Math.PI / 2, false);
        slot.lineTo(-c, SLOT_Y + SLOT_HALF_H);
        slot.absarc(
            -c,
            SLOT_Y,
            SLOT_HALF_H,
            Math.PI / 2,
            (Math.PI * 3) / 2,
            false,
        );
        shape.holes.push(slot);
    }

    return shape;
}

/** Shape geometry with UVs normalized to the tag's 0…1 artwork space. */
function capGeometry(): THREE.ShapeGeometry {
    const geometry = new THREE.ShapeGeometry(outline(true), 24);
    const pos = geometry.attributes.position;
    const uv = geometry.attributes.uv;

    for (let i = 0; i < pos.count; i++) {
        uv.setXY(i, (pos.getX(i) + W / 2) / W, (pos.getY(i) + H / 2) / H);
    }

    uv.needsUpdate = true;

    return geometry;
}

/**
 * The silicone loop: through the slot (front → back), up both faces, a
 * pinch just above the top edge, then an oval loop in the tag's plane.
 */
function strapGeometry(): THREE.TubeGeometry {
    const top = H / 2;
    const face = CAP_Z + STRAP_R + 0.012;
    const curve = new THREE.CatmullRomCurve3(
        [
            new THREE.Vector3(0, SLOT_Y - 0.01, 0),
            new THREE.Vector3(0, SLOT_Y + 0.1, face),
            new THREE.Vector3(0, top + 0.02, face),
            new THREE.Vector3(0.03, top + 0.3, 0.07),
            new THREE.Vector3(0.26, top + 0.7, 0),
            new THREE.Vector3(0.3, top + 1.05, 0),
            new THREE.Vector3(0, top + 1.32, 0),
            new THREE.Vector3(-0.3, top + 1.05, 0),
            new THREE.Vector3(-0.26, top + 0.7, 0),
            new THREE.Vector3(-0.03, top + 0.3, -0.07),
            new THREE.Vector3(0, top + 0.02, -face),
            new THREE.Vector3(0, SLOT_Y + 0.1, -face),
        ],
        true,
        'centripetal',
    );

    return new THREE.TubeGeometry(curve, 220, STRAP_R, 14, true);
}

/** RoomEnvironment + PMREM image-based lighting and two soft lights. */
export function addStudioLighting(
    renderer: THREE.WebGLRenderer,
    scene: THREE.Scene,
): () => void {
    const pmrem = new THREE.PMREMGenerator(renderer);
    const room = new RoomEnvironment();
    const envTarget = pmrem.fromScene(room, 0.04);
    scene.environment = envTarget.texture;
    scene.environmentIntensity = 0.85;

    const key = new THREE.DirectionalLight(0xffffff, 1.4);
    key.position.set(3, 5, 6);
    scene.add(key);
    const rim = new THREE.DirectionalLight(0xfff1d6, 0.8);
    rim.position.set(-4, 2, -5);
    scene.add(rim);

    return () => {
        envTarget.dispose();
        pmrem.dispose();
        room.dispose();
    };
}

export function createTagModel(
    renderer: THREE.WebGLRenderer,
    options: TagModelOptions,
    /** Called after a late redraw (artwork image loaded). */
    onRedraw?: () => void,
): TagModel {
    const maxAniso = Math.min(renderer.capabilities.getMaxAnisotropy(), 16);
    const textures: THREE.Texture[] = [];
    const texture = (source: HTMLCanvasElement, color = true) => {
        const t = new THREE.CanvasTexture(source);
        t.colorSpace = color ? THREE.SRGBColorSpace : THREE.NoColorSpace;
        t.anisotropy = maxAniso;
        t.minFilter = THREE.LinearMipmapLinearFilter;
        t.generateMipmaps = true;
        textures.push(t);

        return t;
    };

    const group = new THREE.Group();

    const bodyGeometry = new THREE.ExtrudeGeometry(outline(true), {
        depth: DEPTH,
        bevelEnabled: true,
        bevelThickness: BEVEL,
        bevelSize: BEVEL,
        bevelSegments: 5,
        curveSegments: 28,
    });
    bodyGeometry.translate(0, 0, -DEPTH / 2);
    const bodyMaterial = new THREE.MeshPhysicalMaterial({
        color: new THREE.Color(options.bodyColor),
        roughness: 0.38,
        metalness: 0.05,
        clearcoat: 1,
        clearcoatRoughness: 0.12,
    });
    group.add(new THREE.Mesh(bodyGeometry, bodyMaterial));

    const frontCanvas = drawFront(options);
    const backCanvas = drawBack(options);
    const frontMap = texture(frontCanvas);
    const backMap = texture(backCanvas);
    const backBump = texture(backCanvas, false);
    const cap = capGeometry();

    const frontMaterial = new THREE.MeshPhysicalMaterial({
        map: frontMap,
        roughness: 0.42,
        metalness: 0.05,
        clearcoat: 1,
        clearcoatRoughness: 0.08,
    });
    const front = new THREE.Mesh(cap, frontMaterial);
    front.position.z = CAP_Z + 0.0015;
    group.add(front);

    const backMaterial = new THREE.MeshPhysicalMaterial({
        map: backMap,
        bumpMap: backBump,
        bumpScale: 0.18,
        roughness: 0.6,
        metalness: 0,
        clearcoat: 0.4,
        clearcoatRoughness: 0.35,
    });
    const back = new THREE.Mesh(cap, backMaterial);
    back.rotation.y = Math.PI;
    back.position.z = -CAP_Z - 0.0015;
    group.add(back);

    // Silicone loop strap through the slot.
    const strapMaterial = new THREE.MeshPhysicalMaterial({
        color: new THREE.Color(options.strapColor),
        roughness: 0.55,
        metalness: 0,
        clearcoat: 0.35,
        clearcoatRoughness: 0.4,
    });
    group.add(new THREE.Mesh(strapGeometry(), strapMaterial));

    const redraw = (next: TagModelOptions) => {
        drawFront(next, undefined, frontCanvas);
        drawBack(next, undefined, backCanvas);
        frontMap.needsUpdate = true;
        backMap.needsUpdate = true;
        backBump.needsUpdate = true;
    };

    // Artwork images arrive asynchronously: draw now, redraw once loaded.
    let current = options;
    let disposed = false;
    const ensureImages = (next: TagModelOptions) => {
        if (tagImagesReady(next)) {
            return;
        }

        void loadTagImages(next).then(() => {
            if (!disposed && current === next) {
                redraw(next);
                onRedraw?.();
            }
        });
    };
    ensureImages(options);

    return {
        group,
        setArtwork(next) {
            current = next;
            redraw(next);
            ensureImages(next);
            bodyMaterial.color.set(next.bodyColor);
            strapMaterial.color.set(next.strapColor);
        },
        dispose() {
            disposed = true;
            const materials = new Set<THREE.Material>();
            group.traverse((object) => {
                if (object instanceof THREE.Mesh) {
                    object.geometry.dispose();
                    materials.add(object.material as THREE.Material);
                }
            });
            materials.forEach((m) => m.dispose());
            textures.forEach((t) => t.dispose());
        },
    };
}
