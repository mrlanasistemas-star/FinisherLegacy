/**
 * The procedural Finisher Legacy Tag mesh (no .glb exists), shared by the
 * interactive viewer (tagScene.ts) and the Home's travelling stage
 * (tagStage.ts):
 *
 * - body: a rounded-rectangle ExtrudeGeometry with a bevel and a real
 *   punched eyelet, resin-like MeshPhysicalMaterial with clearcoat;
 * - FRONT / BACK: two decal caps with the canvas artwork from
 *   tagArtwork.ts — the back also uses it as a subtle bump map so the copy
 *   reads engraved;
 * - a gold grommet, a woven strap loop through the eyelet and a crimp.
 *
 * `setArtwork` redraws the artwork into the SAME canvases/textures, so the
 * Home can swap collections without rebuilding the scene.
 */
import * as THREE from 'three';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import type { TagArtworkOptions } from './tagArtwork';
import { drawBack, drawFront, drawStrap } from './tagArtwork';

export const W = 2;
export const H = 3.4;
const R = 0.26;
const DEPTH = 0.08;
const BEVEL = 0.028;
export const HOLE_Y = H / 2 - 0.34;
const HOLE_R = 0.1;
export const CAP_Z = DEPTH / 2 + BEVEL;
/** Top of the strap loop (object spans −H/2 … STRAP_TOP). */
export const STRAP_TOP = H / 2 + 1.28;

export type TagModelOptions = TagArtworkOptions & {
    strapColor: string;
    metalColor: string;
};

export type TagModel = {
    group: THREE.Group;
    setArtwork: (options: TagModelOptions) => void;
    dispose: () => void;
};

function outline(withHole: boolean): THREE.Shape {
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

    if (withHole) {
        const hole = new THREE.Path();
        hole.absarc(0, HOLE_Y, HOLE_R, 0, Math.PI * 2, true);
        shape.holes.push(hole);
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

/** Flat woven strap along a closed curve that threads the eyelet. */
function strapGeometry(): THREE.BufferGeometry {
    const y = HOLE_Y;
    const curve = new THREE.CatmullRomCurve3(
        [
            new THREE.Vector3(0, y - 0.045, 0),
            new THREE.Vector3(0, y + 0.06, 0.1),
            new THREE.Vector3(0, y + 0.22, 0.125),
            new THREE.Vector3(0, H / 2 + 0.1, 0.14),
            new THREE.Vector3(0, H / 2 + 0.75, 0.13),
            new THREE.Vector3(0, H / 2 + 1.18, 0.06),
            new THREE.Vector3(0, STRAP_TOP, 0),
            new THREE.Vector3(0, H / 2 + 1.18, -0.06),
            new THREE.Vector3(0, H / 2 + 0.75, -0.13),
            new THREE.Vector3(0, H / 2 + 0.1, -0.14),
            new THREE.Vector3(0, y + 0.22, -0.125),
            new THREE.Vector3(0, y + 0.06, -0.1),
        ],
        true,
        'centripetal',
    );
    const segments = 160;
    const half = 0.075;
    const side = new THREE.Vector3(1, 0, 0);
    const positions: number[] = [];
    const normals: number[] = [];
    const uvs: number[] = [];
    const indices: number[] = [];
    const length = curve.getLength();

    for (let i = 0; i <= segments; i++) {
        const t = i / segments;
        const p = curve.getPointAt(t % 1);
        const tangent = curve.getTangentAt(t % 1);
        const normal = new THREE.Vector3()
            .crossVectors(side, tangent)
            .normalize();
        positions.push(p.x - half, p.y, p.z, p.x + half, p.y, p.z);
        normals.push(
            normal.x,
            normal.y,
            normal.z,
            normal.x,
            normal.y,
            normal.z,
        );
        uvs.push(0, t * length * 6, 1, t * length * 6);

        if (i < segments) {
            const a = i * 2;
            indices.push(a, a + 1, a + 2, a + 1, a + 3, a + 2);
        }
    }

    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute(
        'position',
        new THREE.Float32BufferAttribute(positions, 3),
    );
    geometry.setAttribute(
        'normal',
        new THREE.Float32BufferAttribute(normals, 3),
    );
    geometry.setAttribute('uv', new THREE.Float32BufferAttribute(uvs, 2));
    geometry.setIndex(indices);

    return geometry;
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
        bumpScale: 0.5,
        roughness: 0.6,
        metalness: 0,
        clearcoat: 0.4,
        clearcoatRoughness: 0.35,
    });
    const back = new THREE.Mesh(cap, backMaterial);
    back.rotation.y = Math.PI;
    back.position.z = -CAP_Z - 0.0015;
    group.add(back);

    // Grommet: a ring on each face + the lining of the eyelet.
    const metal = new THREE.MeshStandardMaterial({
        color: new THREE.Color(options.metalColor),
        metalness: 1,
        roughness: 0.24,
    });
    const ringGeometry = new THREE.TorusGeometry(HOLE_R + 0.035, 0.032, 18, 64);
    const ringFront = new THREE.Mesh(ringGeometry, metal);
    ringFront.position.set(0, HOLE_Y, CAP_Z + 0.004);
    const ringBack = ringFront.clone();
    ringBack.position.z = -CAP_Z - 0.004;
    const liningGeometry = new THREE.CylinderGeometry(
        HOLE_R + 0.004,
        HOLE_R + 0.004,
        CAP_Z * 2 + 0.01,
        48,
        1,
        true,
    );
    liningGeometry.rotateX(Math.PI / 2);
    const lining = new THREE.Mesh(liningGeometry, metal);
    lining.position.y = HOLE_Y;
    group.add(ringFront, ringBack, lining);

    // Strap loop + crimp.
    const strapCanvas = drawStrap(options.strapColor, options.accent);
    const strapMap = texture(strapCanvas);
    strapMap.wrapS = THREE.RepeatWrapping;
    strapMap.wrapT = THREE.RepeatWrapping;
    const strapMaterial = new THREE.MeshStandardMaterial({
        map: strapMap,
        roughness: 0.85,
        metalness: 0,
        side: THREE.DoubleSide,
    });
    group.add(new THREE.Mesh(strapGeometry(), strapMaterial));
    const crimp = new THREE.Mesh(
        new THREE.BoxGeometry(0.2, 0.1, 0.07, 2, 2, 2),
        metal,
    );
    crimp.position.set(0, H / 2 + 1.25, 0);
    group.add(crimp);

    return {
        group,
        setArtwork(next) {
            drawFront(next, undefined, frontCanvas);
            drawBack(next, undefined, backCanvas);
            frontMap.needsUpdate = true;
            backMap.needsUpdate = true;
            backBump.needsUpdate = true;
            bodyMaterial.color.set(next.bodyColor);
            const strap = drawStrap(next.strapColor, next.accent);
            strapCanvas.getContext('2d')?.drawImage(strap, 0, 0);
            strapMap.needsUpdate = true;
        },
        dispose() {
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
