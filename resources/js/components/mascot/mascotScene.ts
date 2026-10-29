/**
 * mascotScene — the Finisher Legacy mascot, built procedurally in three.js
 * (there is no model file). A glossy "vinyl toy" read of
 * /images/brand/mascot/mascot-hero.png: golden skin with clearcoat, flame
 * hair made of bent lathe spikes, big eyeballs that actually rotate, a
 * painted open smile, black FL tee with a lanyard and the silver FL tag
 * (NFC product — no QR), black shorts with a gold stripe and chunky
 * sneakers with white soles.
 *
 * Loaded ONLY through a dynamic import from Mascot3D.vue, so three.js never
 * lands in the initial bundle and nothing touches `window` at import time.
 *
 * Units: 1 ≈ 500px of the reference art; the ground is y = 0 and the hair
 * tips reach y ≈ 4.
 */
import * as THREE from 'three';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js';

export type MascotMood = 'idle' | 'happy' | 'cheer' | 'wave';
export type MascotFraming = 'full' | 'bust';
export type MascotAction = 'wave' | 'cheer' | 'jump';

export interface MascotSceneOptions {
    framing: MascotFraming;
    mood: MascotMood;
    /** Frame cap while idling (actions always run at full rate). */
    fps?: number;
    onContextLost?: () => void;
}

export interface MascotSceneHandle {
    setSize(width: number, height: number): void;
    setMood(mood: MascotMood): void;
    setFraming(framing: MascotFraming): void;
    /** Where to look, both axes in -1…1 (x right, y up). */
    lookAt(x: number, y: number): void;
    play(action: MascotAction): void;
    start(): void;
    stop(): void;
    /** Renders one frame (used while stopped, e.g. after a resize). */
    renderOnce(): void;
    dispose(): void;
}

/* ------------------------------------------------------------------ */
/* Palette                                                              */
/* ------------------------------------------------------------------ */

const SKIN = '#F5BD16';
const SKIN_SHADE = '#D99211';
const HAIR = '#F8C424';
const SHIRT = '#1B1A18';
const GOLD = '#D4A84F';
const SOLE = '#F4F1EA';

/* ------------------------------------------------------------------ */
/* Canvas texture helpers                                               */
/* ------------------------------------------------------------------ */

function makeCanvas(width: number, height: number) {
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');

    if (!ctx) {
        throw new Error('2D canvas unavailable');
    }

    return { canvas, ctx };
}

function toTexture(
    canvas: HTMLCanvasElement,
    renderer: THREE.WebGLRenderer,
): THREE.CanvasTexture {
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());

    return texture;
}

/**
 * Maps a point given in "front view" coordinates of a unit sphere (fx right,
 * fy up, both -1…1, as seen by a camera on +Z) to pixel coordinates of an
 * equirectangular texture laid on THREE.SphereGeometry. Drawing shapes
 * point-by-point through this map projects them exactly onto the sphere.
 */
function sphereFrontToCanvas(
    fx: number,
    fy: number,
    width: number,
    height: number,
): [number, number] {
    const y = THREE.MathUtils.clamp(fy, -0.999, 0.999);
    const theta = Math.acos(y);
    const sinT = Math.sin(theta);
    const cosPhi = THREE.MathUtils.clamp(-fx / sinT, -1, 1);
    const phi = Math.acos(cosPhi);

    return [(phi / (Math.PI * 2)) * width, (theta / Math.PI) * height];
}

type Pt = [number, number];

function quad(a: Pt, c: Pt, b: Pt, steps = 24): Pt[] {
    const out: Pt[] = [];

    for (let i = 0; i <= steps; i++) {
        const t = i / steps;
        const u = 1 - t;
        out.push([
            u * u * a[0] + 2 * u * t * c[0] + t * t * b[0],
            u * u * a[1] + 2 * u * t * c[1] + t * t * b[1],
        ]);
    }

    return out;
}

function ellipsePts(
    cx: number,
    cy: number,
    rx: number,
    ry: number,
    steps = 48,
): Pt[] {
    const out: Pt[] = [];

    for (let i = 0; i <= steps; i++) {
        const a = (i / steps) * Math.PI * 2;
        out.push([cx + Math.cos(a) * rx, cy + Math.sin(a) * ry]);
    }

    return out;
}

/** Face of the head: skin, cheeks, brows and the big open smile. */
function paintFace(renderer: THREE.WebGLRenderer): THREE.CanvasTexture {
    const W = 2048;
    const H = 1024;
    const { canvas, ctx } = makeCanvas(W, H);
    const map = (p: Pt) => sphereFrontToCanvas(p[0], p[1], W, H);
    const trace = (pts: Pt[]) => {
        ctx.beginPath();
        pts.forEach((p, i) => {
            const [x, y] = map(p);

            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });
    };

    ctx.fillStyle = SKIN;
    ctx.fillRect(0, 0, W, H);

    // Soft warm shading low on the face (reads as volume + subsurface).
    const chin = ctx.createLinearGradient(0, H * 0.55, 0, H);
    chin.addColorStop(0, 'rgba(217,146,17,0)');
    chin.addColorStop(1, 'rgba(217,146,17,0.35)');
    ctx.fillStyle = chin;
    ctx.fillRect(0, H * 0.55, W, H * 0.45);

    // Cheeks.
    for (const side of [-1, 1]) {
        const [x, y] = map([side * 0.62, -0.2]);
        const g = ctx.createRadialGradient(x, y, 0, x, y, 70);
        g.addColorStop(0, 'rgba(240,120,40,0.32)');
        g.addColorStop(1, 'rgba(240,120,40,0)');
        ctx.fillStyle = g;
        ctx.fillRect(x - 80, y - 80, 160, 160);
    }

    // Brows: embossed arcs (shadow stroke + light stroke).
    for (const side of [-1, 1]) {
        const arc = quad(
            [side * 0.2, 0.47],
            [side * 0.4, 0.62],
            [side * 0.62, 0.47],
        );
        trace(arc);
        ctx.lineCap = 'round';
        ctx.strokeStyle = SKIN_SHADE;
        ctx.lineWidth = 16;
        ctx.stroke();
        trace(arc.map(([x, y]) => [x, y + 0.025] as Pt));
        ctx.strokeStyle = 'rgba(255,226,140,0.85)';
        ctx.lineWidth = 7;
        ctx.stroke();
    }

    // Mouth: wide open "D" smile — corners up, round bottom.
    const L: Pt = [-0.38, -0.3];
    const R: Pt = [0.38, -0.3];
    const top = quad(L, [0, -0.44], R);
    const bottom = quad(R, [0, -1.26], L);
    const mouth = [...top, ...bottom.slice(1)];

    // Lip shadow ring first, then the interior.
    trace(mouth);
    ctx.closePath();
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#B9740B';
    ctx.lineWidth = 14;
    ctx.stroke();
    ctx.fillStyle = '#4A130D';
    ctx.fill();

    ctx.save();
    trace(mouth);
    ctx.closePath();
    ctx.clip();

    // Upper teeth band.
    const teethBottom = quad([-0.45, -0.38], [0, -0.62], [0.45, -0.38]);
    trace([...top, ...teethBottom.slice().reverse()]);
    ctx.closePath();
    ctx.fillStyle = '#FFFDF8';
    ctx.fill();

    // Tongue.
    trace(ellipsePts(0.02, -0.74, 0.26, 0.15));
    ctx.closePath();
    const [tx, ty] = map([0.02, -0.72]);
    const tongue = ctx.createRadialGradient(tx, ty - 10, 4, tx, ty, 90);
    tongue.addColorStop(0, '#FF8A8A');
    tongue.addColorStop(1, '#E0524F');
    ctx.fillStyle = tongue;
    ctx.fill();
    ctx.restore();

    // Smile creases at the corners.
    for (const side of [-1, 1]) {
        trace(
            quad(
                [side * 0.4, -0.24],
                [side * 0.48, -0.3],
                [side * 0.45, -0.39],
                10,
            ),
        );
        ctx.strokeStyle = 'rgba(185,116,11,0.7)';
        ctx.lineWidth = 6;
        ctx.stroke();
    }

    return toTexture(canvas, renderer);
}

/** Eyeball texture: white sclera with an amber iris and a big pupil. */
function paintEye(renderer: THREE.WebGLRenderer): THREE.CanvasTexture {
    const W = 1024;
    const H = 512;
    const { canvas, ctx } = makeCanvas(W, H);
    const map = (p: Pt) => sphereFrontToCanvas(p[0], p[1], W, H);
    const fillPts = (pts: Pt[], style: string | CanvasGradient) => {
        ctx.beginPath();
        pts.forEach((p, i) => {
            const [x, y] = map(p);

            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });
        ctx.closePath();
        ctx.fillStyle = style;
        ctx.fill();
    };

    ctx.fillStyle = '#FFFDF9';
    ctx.fillRect(0, 0, W, H);

    const [cx, cy] = map([0, 0]);
    const iris = ctx.createRadialGradient(cx, cy, 10, cx, cy, 150);
    iris.addColorStop(0, '#3A1C05');
    iris.addColorStop(0.45, '#7A4310');
    iris.addColorStop(0.85, '#B8741C');
    iris.addColorStop(1, '#5A2E08');
    fillPts(ellipsePts(0, 0, 0.72, 0.76, 64), iris);
    fillPts(ellipsePts(0, 0, 0.38, 0.4, 48), '#120803');

    return toTexture(canvas, renderer);
}

/** Black tee with the gold FL mark and a soft V-neck rib. */
function paintShirt(
    renderer: THREE.WebGLRenderer,
    yTop: number,
    yBottom: number,
    chestRadius: number,
): THREE.CanvasTexture {
    const W = 2048;
    const H = 1024;
    const { canvas, ctx } = makeCanvas(W, H);
    const pxPerUnitX = W / (Math.PI * 2 * chestRadius);
    const pxPerUnitY = H / (yTop - yBottom);
    const at = (x: number, y: number): [number, number] => [
        W / 2 + x * pxPerUnitX,
        (yTop - y) * pxPerUnitY,
    ];

    ctx.fillStyle = SHIRT;
    ctx.fillRect(0, 0, W, H);

    // Fabric grain.
    for (let i = 0; i < 2600; i++) {
        ctx.fillStyle = `rgba(255,255,255,${Math.random() * 0.025})`;
        ctx.fillRect(Math.random() * W, Math.random() * H, 2, 2);
    }

    // V-neck rib.
    ctx.beginPath();
    ctx.moveTo(...at(-0.16, 2.1));
    ctx.lineTo(...at(0, 1.95));
    ctx.lineTo(...at(0.16, 2.1));
    ctx.strokeStyle = '#2B2925';
    ctx.lineWidth = 14;
    ctx.stroke();

    // Gold FL (italic, heavy), sized in body units.
    const [fx, fy] = at(0.01, 1.83);
    ctx.save();
    ctx.translate(fx, fy);
    ctx.scale(pxPerUnitX / pxPerUnitY, 1);
    ctx.transform(1, 0, -0.18, 1, 0, 0);
    ctx.font = `900 ${0.16 * pxPerUnitY}px "Arial Black", Arial, sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = GOLD;
    ctx.fillText('FL', 0, 0);
    ctx.restore();

    // Hem stitch.
    ctx.fillStyle = '#26241F';
    ctx.fillRect(0, H - 22, W, 6);

    return toTexture(canvas, renderer);
}

/** Lanyard ribbon: black with small gold FL marks along its length. */
function paintLanyard(renderer: THREE.WebGLRenderer): THREE.CanvasTexture {
    const { canvas, ctx } = makeCanvas(256, 64);
    ctx.fillStyle = '#141311';
    ctx.fillRect(0, 0, 256, 64);
    ctx.fillStyle = '#2A2824';
    ctx.fillRect(0, 0, 256, 4);
    ctx.fillRect(0, 60, 256, 4);
    ctx.font = 'italic 900 34px "Arial Black", Arial, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = GOLD;
    ctx.fillText('FL', 64, 33);
    ctx.fillText('FL', 192, 33);
    const texture = toTexture(canvas, renderer);
    texture.wrapS = THREE.RepeatWrapping;

    return texture;
}

/** Face of the silver tag: brushed metal, FL and the tagline. No QR. */
function paintTag(renderer: THREE.WebGLRenderer): THREE.CanvasTexture {
    const W = 512;
    const H = 560;
    const { canvas, ctx } = makeCanvas(W, H);
    const base = ctx.createLinearGradient(0, 0, W, H);
    base.addColorStop(0, '#DADCDF');
    base.addColorStop(0.5, '#B9BCC0');
    base.addColorStop(1, '#CDD0D3');
    ctx.fillStyle = base;
    ctx.fillRect(0, 0, W, H);

    // Brushed lines.
    for (let y = 0; y < H; y += 2) {
        ctx.fillStyle = `rgba(255,255,255,${Math.random() * 0.12})`;
        ctx.fillRect(0, y, W, 1);
    }

    // Screws.
    for (const [x, y] of [
        [34, 34],
        [W - 34, 34],
        [34, H - 34],
        [W - 34, H - 34],
    ]) {
        ctx.beginPath();
        ctx.arc(x, y, 9, 0, Math.PI * 2);
        ctx.fillStyle = '#8D8F93';
        ctx.fill();
    }

    ctx.save();
    ctx.translate(W / 2, H * 0.47);
    ctx.transform(1, 0, -0.2, 1, 0, 0);
    ctx.font = '900 190px "Arial Black", Arial, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#151412';
    ctx.fillText('FL', 0, 0);
    ctx.restore();

    ctx.font = '700 30px Arial, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillStyle = '#2B2A27';
    ctx.fillText('TU HISTORIA. TU LEGADO.', W / 2, H * 0.72);

    return toTexture(canvas, renderer);
}

function paintShadow(renderer: THREE.WebGLRenderer): THREE.CanvasTexture {
    const { canvas, ctx } = makeCanvas(256, 256);
    const g = ctx.createRadialGradient(128, 128, 0, 128, 128, 128);
    g.addColorStop(0, 'rgba(40,30,10,0.55)');
    g.addColorStop(0.5, 'rgba(40,30,10,0.22)');
    g.addColorStop(1, 'rgba(40,30,10,0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 256, 256);

    return toTexture(canvas, renderer);
}

/* ------------------------------------------------------------------ */
/* Geometry helpers                                                     */
/* ------------------------------------------------------------------ */

/** A flame-like hair spike: lathe cone along +Y whose tip curls to +X. */
function spikeGeometry(length: number, radius: number, bend: number) {
    const pts: THREE.Vector2[] = [];
    const steps = 14;

    for (let i = 0; i <= steps; i++) {
        const t = i / steps;
        // Full base, soft shoulder, sharp-but-rounded tip.
        const r =
            radius *
            Math.pow(1 - t, 0.85) *
            (1 + 0.25 * Math.sin(t * Math.PI * 0.9));
        pts.push(new THREE.Vector2(Math.max(r, 0.004), t * length));
    }

    const geometry = new THREE.LatheGeometry(pts, 20);
    const pos = geometry.attributes.position;

    for (let i = 0; i < pos.count; i++) {
        const y = pos.getY(i);
        const t = y / length;
        pos.setX(i, pos.getX(i) + bend * length * t * t);
        pos.setZ(i, pos.getZ(i) * 0.72);
    }

    geometry.computeVertexNormals();

    return geometry;
}

/** Orients a group so local +Y follows `dir` and the spike's bend curls up. */
function orientAlong(group: THREE.Object3D, dir: THREE.Vector3) {
    const y = dir.clone().normalize();
    const up = new THREE.Vector3(0, 1, 0);
    let x = up.clone().sub(y.clone().multiplyScalar(y.dot(up)));

    if (x.lengthSq() < 1e-4) {
        x = new THREE.Vector3(1, 0, 0);
    }

    x.normalize();
    const z = new THREE.Vector3().crossVectors(x, y).normalize();
    const m = new THREE.Matrix4().makeBasis(x, y, z);
    group.quaternion.setFromRotationMatrix(m);
}

/** Ribbon strip along a curve, facing away from the torso axis. */
function ribbonGeometry(
    curve: THREE.Curve<THREE.Vector3>,
    width: number,
    repeat: number,
) {
    const segments = 48;
    const positions: number[] = [];
    const uvs: number[] = [];
    const indices: number[] = [];

    for (let i = 0; i <= segments; i++) {
        const t = i / segments;
        const p = curve.getPointAt(t);
        const tangent = curve.getTangentAt(t);
        const out = new THREE.Vector3(p.x * 0.3, 0, 1).normalize();
        const side = new THREE.Vector3().crossVectors(tangent, out).normalize();
        const a = p.clone().addScaledVector(side, width / 2);
        const b = p.clone().addScaledVector(side, -width / 2);
        positions.push(a.x, a.y, a.z, b.x, b.y, b.z);
        uvs.push(t * repeat, 1, t * repeat, 0);

        if (i < segments) {
            const k = i * 2;
            indices.push(k, k + 1, k + 2, k + 1, k + 3, k + 2);
        }
    }

    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute(
        'position',
        new THREE.Float32BufferAttribute(positions, 3),
    );
    geometry.setAttribute('uv', new THREE.Float32BufferAttribute(uvs, 2));
    geometry.setIndex(indices);
    geometry.computeVertexNormals();

    return geometry;
}

function boltShape() {
    const s = new THREE.Shape();
    s.moveTo(0.1, 0.5);
    s.lineTo(-0.35, -0.05);
    s.lineTo(0.0, -0.05);
    s.lineTo(-0.15, -0.5);
    s.lineTo(0.35, 0.08);
    s.lineTo(0.02, 0.08);
    s.closePath();

    return s;
}

/* ------------------------------------------------------------------ */
/* Scene                                                                */
/* ------------------------------------------------------------------ */

interface Rig {
    root: THREE.Group;
    body: THREE.Group;
    torso: THREE.Mesh;
    headPivot: THREE.Group;
    eyes: { group: THREE.Group; ball: THREE.Mesh }[];
    spikes: THREE.Group[];
    armR: { shoulder: THREE.Group; elbow: THREE.Group };
    armL: { shoulder: THREE.Group; elbow: THREE.Group };
    shadow: THREE.Mesh;
}

// Rest pose (radians). R = the character's raised fist, on screen right.
const POSE = {
    rShoulderZ: 1.72,
    rShoulderX: -0.15,
    rElbowZ: 1.3,
    lShoulderZ: -0.42,
    lShoulderX: 0.1,
    lElbowX: -1.35,
    lElbowZ: -0.35,
};

function buildMascot(
    renderer: THREE.WebGLRenderer,
    track: (o: { dispose(): void }) => void,
): Rig {
    const t = <T extends { dispose(): void }>(o: T): T => {
        track(o);

        return o;
    };

    /* Materials ------------------------------------------------------- */
    const skin = t(
        new THREE.MeshPhysicalMaterial({
            color: SKIN,
            roughness: 0.36,
            clearcoat: 1,
            clearcoatRoughness: 0.16,
            sheen: 0.15,
            sheenColor: new THREE.Color('#FFE08A'),
            sheenRoughness: 0.5,
        }),
    );
    const faceMat = t(
        new THREE.MeshPhysicalMaterial({
            color: '#ffffff',
            map: t(paintFace(renderer)),
            roughness: 0.36,
            clearcoat: 1,
            clearcoatRoughness: 0.16,
            sheen: 0.15,
            sheenColor: new THREE.Color('#FFE08A'),
            sheenRoughness: 0.5,
        }),
    );
    const hair = t(
        new THREE.MeshPhysicalMaterial({
            color: HAIR,
            roughness: 0.3,
            clearcoat: 1,
            clearcoatRoughness: 0.1,
            sheen: 0.5,
            sheenColor: new THREE.Color('#FFE9A8'),
        }),
    );
    const eyeMat = t(
        new THREE.MeshPhysicalMaterial({
            color: '#ffffff',
            map: t(paintEye(renderer)),
            roughness: 0.12,
            clearcoat: 1,
            clearcoatRoughness: 0.03,
        }),
    );
    const black = t(
        new THREE.MeshPhysicalMaterial({
            color: SHIRT,
            roughness: 0.78,
            sheen: 0.8,
            sheenColor: new THREE.Color('#5A5650'),
            sheenRoughness: 0.6,
        }),
    );
    const leather = t(
        new THREE.MeshPhysicalMaterial({
            color: '#151412',
            roughness: 0.38,
            clearcoat: 0.7,
            clearcoatRoughness: 0.3,
        }),
    );
    const gold = t(
        new THREE.MeshPhysicalMaterial({
            color: GOLD,
            metalness: 0.75,
            roughness: 0.32,
        }),
    );
    const white = t(
        new THREE.MeshPhysicalMaterial({
            color: SOLE,
            roughness: 0.55,
            clearcoat: 0.3,
        }),
    );
    const silver = t(
        new THREE.MeshPhysicalMaterial({
            color: '#C9CBCE',
            metalness: 1,
            roughness: 0.3,
        }),
    );
    const lashMat = t(
        new THREE.MeshStandardMaterial({ color: '#1A120A', roughness: 0.5 }),
    );
    const glint = t(new THREE.MeshBasicMaterial({ color: '#ffffff' }));

    const mesh = (g: THREE.BufferGeometry, m: THREE.Material) => {
        const o = new THREE.Mesh(t(g), m);

        return o;
    };

    const root = new THREE.Group();
    const body = new THREE.Group();
    root.add(body);

    /* Shoes + legs ---------------------------------------------------- */
    const bolt = boltShape();

    for (const side of [-1, 1]) {
        const foot = new THREE.Group();
        foot.position.set(side * 0.3, 0, 0.06);
        foot.rotation.y = side * 0.1;

        const sole = mesh(
            new RoundedBoxGeometry(0.47, 0.16, 0.86, 4, 0.07),
            white,
        );
        sole.position.set(0, 0.08, 0.05);
        foot.add(sole);

        const upper = mesh(new THREE.SphereGeometry(1, 40, 24), leather);
        upper.scale.set(0.225, 0.24, 0.41);
        upper.position.set(0, 0.2, 0.03);
        foot.add(upper);

        const collar = mesh(
            new THREE.CylinderGeometry(0.17, 0.19, 0.22, 28),
            leather,
        );
        collar.position.set(0, 0.36, -0.1);
        foot.add(collar);

        for (let i = 0; i < 3; i++) {
            const lace = mesh(
                new THREE.CapsuleGeometry(0.016, 0.15, 4, 8),
                white,
            );
            lace.rotation.z = Math.PI / 2;
            lace.position.set(0, 0.43 - i * 0.04, 0.02 + i * 0.08);
            lace.rotation.x = -0.5;
            foot.add(lace);
        }

        const boltMesh = mesh(
            new THREE.ExtrudeGeometry(bolt, {
                depth: 0.08,
                bevelEnabled: false,
            }),
            gold,
        );
        boltMesh.scale.setScalar(0.16);
        boltMesh.position.set(side * 0.215, 0.21, 0.06);
        boltMesh.rotation.y = side * (Math.PI / 2 - 0.15);
        foot.add(boltMesh);

        body.add(foot);

        const leg = mesh(new THREE.CapsuleGeometry(0.13, 0.42, 6, 16), skin);
        leg.position.set(side * 0.27, 0.72, -0.06);
        body.add(leg);
    }

    /* Shorts + belt --------------------------------------------------- */
    const shorts = new THREE.Group();
    const hips = mesh(
        new THREE.LatheGeometry(
            [
                new THREE.Vector2(0.0, 1.0),
                new THREE.Vector2(0.4, 1.0),
                new THREE.Vector2(0.55, 1.08),
                new THREE.Vector2(0.55, 1.22),
                new THREE.Vector2(0.535, 1.34),
                new THREE.Vector2(0.0, 1.34),
            ],
            36,
        ),
        black,
    );
    hips.scale.z = 0.74;
    shorts.add(hips);

    for (const side of [-1, 1]) {
        const legHole = mesh(
            new THREE.CylinderGeometry(0.255, 0.285, 0.36, 28),
            black,
        );
        legHole.position.set(side * 0.27, 0.95, -0.03);
        legHole.scale.z = 0.92;
        shorts.add(legHole);

        const stripe = mesh(new THREE.BoxGeometry(0.035, 0.52, 0.06), gold);
        stripe.position.set(side * 0.545, 1.06, 0.02);
        stripe.rotation.z = side * -0.06;
        shorts.add(stripe);
    }

    const belt = mesh(
        new THREE.CylinderGeometry(0.545, 0.55, 0.08, 40, 1, true),
        leather,
    );
    belt.position.y = 1.32;
    belt.scale.z = 0.76;
    shorts.add(belt);

    for (const side of [-1, 1]) {
        const buckle = mesh(
            new RoundedBoxGeometry(0.06, 0.1, 0.04, 2, 0.012),
            gold,
        );
        buckle.position.set(side * 0.46, 1.32, 0.25);
        buckle.rotation.y = side * 0.9;
        shorts.add(buckle);
    }

    body.add(shorts);

    /* Torso (tee) ----------------------------------------------------- */
    const yTop = 2.11;
    const yBottom = 1.2;
    const torsoProfile = [
        [0.5, 1.2],
        [0.535, 1.26],
        [0.53, 1.45],
        [0.535, 1.64],
        [0.515, 1.8],
        [0.45, 1.93],
        [0.33, 2.03],
        [0.18, 2.09],
        [0.001, 2.11],
    ].map(([r, y]) => new THREE.Vector2(r, y));
    const torsoGeo = new THREE.LatheGeometry(torsoProfile, 48, Math.PI);
    {
        // Linear v in y so the decal can be painted in body units.
        const pos = torsoGeo.attributes.position;
        const uv = torsoGeo.attributes.uv;

        for (let i = 0; i < pos.count; i++) {
            uv.setY(i, (pos.getY(i) - yBottom) / (yTop - yBottom));
        }
    }
    const shirtMat = t(black.clone());
    shirtMat.map = t(paintShirt(renderer, yTop, yBottom, 0.535));
    shirtMat.color = new THREE.Color('#ffffff');
    const torso = mesh(torsoGeo, shirtMat);
    torso.scale.z = 0.74;
    body.add(torso);

    const neck = mesh(new THREE.CylinderGeometry(0.16, 0.18, 0.3, 24), skin);
    neck.position.y = 2.14;
    body.add(neck);

    /* Lanyard + tag --------------------------------------------------- */
    const lanyardTex = t(paintLanyard(renderer));
    const lanyardMat = t(
        new THREE.MeshStandardMaterial({
            map: lanyardTex,
            roughness: 0.7,
            side: THREE.DoubleSide,
        }),
    );

    for (const side of [-1, 1]) {
        const curve = new THREE.CatmullRomCurve3([
            new THREE.Vector3(side * 0.2, 2.1, -0.05),
            new THREE.Vector3(side * 0.26, 2.02, 0.22),
            new THREE.Vector3(side * 0.19, 1.86, 0.385),
            new THREE.Vector3(side * 0.05, 1.7, 0.45),
        ]);
        const strap = new THREE.Mesh(
            t(ribbonGeometry(curve, 0.075, 3)),
            lanyardMat,
        );
        body.add(strap);
    }

    const tagGroup = new THREE.Group();
    tagGroup.position.set(-0.02, 1.42, 0.465);
    tagGroup.rotation.x = -0.08;

    const tagBody = mesh(
        new RoundedBoxGeometry(0.46, 0.5, 0.035, 3, 0.03),
        silver,
    );
    tagGroup.add(tagBody);

    const tagFace = mesh(
        new THREE.PlaneGeometry(0.44, 0.48),
        t(
            new THREE.MeshPhysicalMaterial({
                map: t(paintTag(renderer)),
                metalness: 0.55,
                roughness: 0.38,
                clearcoat: 0.4,
            }),
        ),
    );
    tagFace.position.z = 0.0185;
    tagGroup.add(tagFace);

    const clip = mesh(
        new RoundedBoxGeometry(0.12, 0.07, 0.04, 2, 0.015),
        leather,
    );
    clip.position.set(0, 0.3, 0);
    tagGroup.add(clip);

    const ring = mesh(new THREE.TorusGeometry(0.05, 0.012, 10, 24), silver);
    ring.position.set(0, 0.255, 0);
    tagGroup.add(ring);

    body.add(tagGroup);

    /* Arms ------------------------------------------------------------ */
    const buildArm = (side: number) => {
        const shoulder = new THREE.Group();
        shoulder.position.set(side * 0.46, 1.86, 0);

        const sleeve = mesh(
            new THREE.CylinderGeometry(0.19, 0.22, 0.34, 28),
            black,
        );
        sleeve.position.y = -0.12;
        shoulder.add(sleeve);

        const upperArm = mesh(
            new THREE.CapsuleGeometry(0.12, 0.3, 6, 16),
            skin,
        );
        upperArm.position.y = -0.22;
        shoulder.add(upperArm);

        const elbow = new THREE.Group();
        elbow.position.y = -0.42;
        shoulder.add(elbow);

        const forearm = mesh(
            new THREE.CapsuleGeometry(0.12, 0.26, 6, 16),
            skin,
        );
        forearm.position.y = -0.18;
        elbow.add(forearm);

        const fist = new THREE.Group();
        fist.position.y = -0.44;
        elbow.add(fist);

        const palm = mesh(new THREE.SphereGeometry(0.2, 32, 24), skin);
        palm.scale.set(1, 0.95, 0.92);
        fist.add(palm);

        // Rolled fingers across the front, plus a thumb.
        for (let i = 0; i < 4; i++) {
            const finger = mesh(new THREE.SphereGeometry(0.075, 20, 14), skin);
            finger.position.set(
                -0.12 + i * 0.08,
                -0.05 + Math.abs(i - 1.5) * 0.012,
                0.15,
            );
            finger.scale.set(0.95, 1.1, 1);
            fist.add(finger);
        }

        const thumb = mesh(new THREE.CapsuleGeometry(0.055, 0.12, 4, 12), skin);
        thumb.rotation.z = Math.PI / 2 - 0.3;
        thumb.position.set(side * -0.02, 0.06, 0.18);
        fist.add(thumb);

        body.add(shoulder);

        return { shoulder, elbow };
    };

    const armR = buildArm(1);
    const armL = buildArm(-1);

    /* Head ------------------------------------------------------------ */
    const headPivot = new THREE.Group();
    headPivot.position.set(0, 2.1, 0);
    body.add(headPivot);

    const headGroup = new THREE.Group();
    headGroup.position.y = 0.6;
    headPivot.add(headGroup);

    const HX = 0.76;
    const HY = 0.67;
    const HZ = 0.64;
    const head = mesh(new THREE.SphereGeometry(1, 72, 48), faceMat);
    head.scale.set(HX, HY, HZ);
    headGroup.add(head);

    const onHead = (fx: number, fy: number, inset = 0) => {
        const fz = Math.sqrt(Math.max(0, 1 - fx * fx - fy * fy));
        const p = new THREE.Vector3(fx * HX, fy * HY, fz * HZ);
        const n = new THREE.Vector3(
            p.x / (HX * HX),
            p.y / (HY * HY),
            p.z / (HZ * HZ),
        ).normalize();

        return { p: p.addScaledVector(n, -inset), n };
    };

    // Nose.
    {
        const { p } = onHead(0, -0.15);
        const nose = mesh(new THREE.SphereGeometry(0.08, 24, 16), skin);
        nose.scale.set(1.15, 0.9, 0.9);
        nose.position.copy(p);
        headGroup.add(nose);
    }

    // Eyes.
    const eyes: Rig['eyes'] = [];

    for (const side of [-1, 1]) {
        const { p, n } = onHead(side * 0.4, 0.12);
        const group = new THREE.Group();
        group.position.copy(p);
        group.quaternion.setFromUnitVectors(new THREE.Vector3(0, 0, 1), n);
        headGroup.add(group);

        const ball = mesh(new THREE.SphereGeometry(0.22, 40, 28), eyeMat);
        ball.position.z = -0.115;
        group.add(ball);

        // Upper lash line + a soft skin lid rim.
        const lash = mesh(
            new THREE.TorusGeometry(0.19, 0.02, 8, 40, Math.PI * 0.95),
            lashMat,
        );
        lash.rotation.z = Math.PI * 0.025;
        lash.position.z = 0.03;
        group.add(lash);

        const rim = mesh(new THREE.TorusGeometry(0.2, 0.022, 10, 48), skin);
        rim.position.z = 0.0;
        group.add(rim);

        // Glints stay put while the eyeball rolls (they're reflections).
        const g1 = mesh(new THREE.SphereGeometry(0.045, 16, 12), glint);
        g1.position.set(0.06, 0.07, 0.1);
        group.add(g1);
        const g2 = mesh(new THREE.SphereGeometry(0.017, 12, 8), glint);
        g2.position.set(-0.055, -0.05, 0.1);
        group.add(g2);

        eyes.push({ group, ball });
    }

    // Hair cap + spikes.
    const cap = mesh(
        new THREE.SphereGeometry(1, 56, 28, 0, Math.PI * 2, 0, Math.PI * 0.4),
        hair,
    );
    cap.scale.set(HX * 1.04, HY * 1.06, HZ * 1.05);
    cap.rotation.x = -0.45;
    headGroup.add(cap);

    const spikes: THREE.Group[] = [];
    // [dir x, dir y, dir z, length, base radius, bend]
    const SPIKES: [number, number, number, number, number, number][] = [
        [-0.1, 1, 0.15, 1.05, 0.33, 0.3], // tall center
        [-0.62, 0.8, 0.1, 0.85, 0.3, 0.32],
        [0.5, 0.85, 0.1, 0.95, 0.31, 0.28],
        [0.15, 0.9, -0.4, 0.85, 0.3, 0.25],
        [-0.4, 0.75, -0.5, 0.7, 0.28, 0.25],
        [-0.92, 0.38, 0.05, 0.6, 0.24, 0.35],
        [0.92, 0.42, 0.0, 0.65, 0.25, 0.35],
        [-0.97, -0.05, 0.22, 0.32, 0.15, 0.3],
        [0.97, -0.02, 0.22, 0.34, 0.15, 0.3],
        [0, 0.4, -0.9, 0.55, 0.28, 0.3],
        [-0.65, 0.2, -0.7, 0.45, 0.24, 0.3],
        [0.65, 0.25, -0.7, 0.48, 0.24, 0.3],
    ];

    for (const [dx, dy, dz, len, rad, bend] of SPIKES) {
        const dir = new THREE.Vector3(dx, dy, dz).normalize();
        const surface = new THREE.Vector3(dir.x * HX, dir.y * HY, dir.z * HZ);
        const group = new THREE.Group();
        group.position.copy(surface.multiplyScalar(0.8));
        orientAlong(group, dir);
        const spike = mesh(spikeGeometry(len, rad, bend), hair);
        group.add(spike);
        headGroup.add(group);
        spikes.push(group);
    }

    /* Contact shadow -------------------------------------------------- */
    const shadow = new THREE.Mesh(
        t(new THREE.PlaneGeometry(2.4, 1.3)),
        t(
            new THREE.MeshBasicMaterial({
                map: t(paintShadow(renderer)),
                transparent: true,
                depthWrite: false,
            }),
        ),
    );
    shadow.rotation.x = -Math.PI / 2;
    shadow.position.y = 0.003;
    root.add(shadow);

    return { root, body, torso, headPivot, eyes, spikes, armR, armL, shadow };
}

/* ------------------------------------------------------------------ */

const FRAMES: Record<MascotFraming, { cy: number; h: number; w: number }> = {
    full: { cy: 2.1, h: 4.55, w: 2.8 },
    bust: { cy: 3.02, h: 2.2, w: 2.05 },
};

const ease = (x: number) =>
    x < 0.5 ? 2 * x * x : 1 - Math.pow(-2 * x + 2, 2) / 2;
const clamp01 = (x: number) => Math.min(1, Math.max(0, x));

export function createMascotScene(
    canvas: HTMLCanvasElement,
    options: MascotSceneOptions,
): MascotSceneHandle {
    const renderer = new THREE.WebGLRenderer({
        canvas,
        antialias: true,
        alpha: true,
        powerPreference: 'low-power',
    });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.setClearColor(0x000000, 0);
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.NeutralToneMapping;
    renderer.toneMappingExposure = 1.0;

    const disposables: { dispose(): void }[] = [];
    const track = (o: { dispose(): void }) => disposables.push(o);

    const scene = new THREE.Scene();
    const pmrem = new THREE.PMREMGenerator(renderer);
    const room = new RoomEnvironment();
    const envRT = pmrem.fromScene(room, 0.04);
    room.dispose();
    scene.environment = envRT.texture;
    scene.environmentIntensity = 0.75;

    const key = new THREE.DirectionalLight('#FFF3DC', 1.7);
    key.position.set(2.5, 5, 4);
    const rim = new THREE.DirectionalLight('#FFE2A6', 1.3);
    rim.position.set(-3.5, 3.5, -2.5);
    const fill = new THREE.HemisphereLight('#ffffff', '#D9CDB5', 0.55);
    scene.add(key, rim, fill);

    const camera = new THREE.PerspectiveCamera(24, 1, 0.1, 50);
    const rig = buildMascot(renderer, track);
    rig.root.rotation.y = -0.12;
    scene.add(rig.root);

    let framing = options.framing;
    let mood = options.mood;
    const fps = options.fps ?? 60;
    let width = 1;
    let height = 1;

    function frame() {
        const f = FRAMES[framing];
        const aspect = width / height;
        const tan = Math.tan(THREE.MathUtils.degToRad(camera.fov / 2));
        const dist = Math.max(f.h / 2 / tan, f.w / 2 / (tan * aspect));
        camera.aspect = aspect;
        camera.position.set(0, f.cy + 0.15, dist);
        camera.lookAt(0, f.cy, 0);
        camera.updateProjectionMatrix();
    }

    /* Animation state ------------------------------------------------- */
    const look = new THREE.Vector2();
    const lookTarget = new THREE.Vector2();
    let action: { type: MascotAction; t0: number; dur: number } | null = null;
    const DUR: Record<MascotAction, number> = {
        wave: 1.7,
        cheer: 1.25,
        jump: 1.05,
    };
    let time = 0;
    let nextBlink = 1.5 + Math.random() * 2;
    let blinkT = -1;
    let nextIdleAction = 6 + Math.random() * 4;

    function play(type: MascotAction) {
        action = { type, t0: time, dur: DUR[type] };
    }

    if (mood === 'wave') {
        play('wave');
    } else if (mood === 'cheer') {
        play('cheer');
    }

    function update(dt: number) {
        time += dt;
        const k = Math.min(1, dt * 5);
        look.x += (lookTarget.x - look.x) * k;
        look.y += (lookTarget.y - look.y) * k;

        // Idle: breathing + gentle bob + sway.
        const breath = Math.sin(time * 2.1);
        let rootY = Math.sin(time * 2.1 + 0.6) * 0.025;
        let spin = 0;
        let squash = 1;
        let rShoulderZ = POSE.rShoulderZ + Math.sin(time * 1.6) * 0.04;
        let rElbowZ = POSE.rElbowZ + Math.sin(time * 1.6 + 0.4) * 0.05;
        let lShoulderZ = POSE.lShoulderZ + Math.sin(time * 1.6) * 0.03;
        let headTilt = Math.sin(time * 1.1) * 0.045;
        let lElbowX = POSE.lElbowX;

        if (action) {
            const p = (time - action.t0) / action.dur;

            if (p >= 1) {
                action = null;
            } else {
                const env = Math.sin(Math.PI * clamp01(p));

                if (action.type === 'wave') {
                    rShoulderZ += 0.35 * env;
                    rElbowZ +=
                        -0.25 * env + Math.sin(p * Math.PI * 7) * 0.45 * env;
                    headTilt += 0.12 * env;
                } else if (action.type === 'cheer') {
                    const pump = Math.max(0, Math.sin(p * Math.PI * 4));
                    rShoulderZ += 0.55 * env + pump * 0.25;
                    rElbowZ -= 0.35 * env + pump * 0.3;
                    rootY += pump * 0.09;
                    headTilt -= 0.06 * env;
                } else {
                    // jump: anticipation → air (spin) → landing.
                    if (p < 0.18) {
                        squash =
                            1 - 0.12 * Math.sin((p / 0.18) * Math.PI * 0.5);
                    } else if (p < 0.82) {
                        const a = (p - 0.18) / 0.64;
                        rootY += Math.sin(a * Math.PI) * 0.62;
                        spin = ease(a) * Math.PI * 2;
                        squash = 1 + 0.06 * Math.sin(a * Math.PI);
                        rShoulderZ += 0.5 * Math.sin(a * Math.PI);
                        lShoulderZ -= 1.6 * Math.sin(a * Math.PI);
                        lElbowX += 1.1 * Math.sin(a * Math.PI);
                    } else {
                        squash =
                            1 - 0.1 * Math.sin(((p - 0.82) / 0.18) * Math.PI);
                    }
                }
            }
        } else if (mood === 'happy' || mood === 'wave' || mood === 'cheer') {
            nextIdleAction -= dt;

            if (nextIdleAction <= 0) {
                play(mood === 'cheer' ? 'cheer' : 'wave');
                nextIdleAction = 8 + Math.random() * 6;
            }
        }

        rig.body.position.y = rootY;
        rig.body.scale.set(
            1 + (1 - squash) * 0.5,
            squash,
            1 + (1 - squash) * 0.5,
        );
        rig.body.rotation.y = spin + look.x * 0.18;
        rig.torso.scale.set(
            1 + breath * 0.008,
            1 + breath * 0.012,
            0.74 + breath * 0.008,
        );

        // Shadow shrinks + fades as he leaves the ground.
        const air = Math.max(0, rootY);
        const s = 1 - air * 0.6;
        rig.shadow.scale.set(s, s, s);
        (rig.shadow.material as THREE.MeshBasicMaterial).opacity =
            1 - air * 0.9;

        rig.headPivot.rotation.set(
            -look.y * 0.32,
            look.x * 0.5,
            headTilt - look.x * 0.06,
        );

        // Eyes roll toward the target; blink now and then.
        nextBlink -= dt;

        if (nextBlink <= 0 && blinkT < 0) {
            blinkT = 0;
        }

        let lid = 1;

        if (blinkT >= 0) {
            blinkT += dt;
            const b = blinkT / 0.16;
            lid = 1 - Math.sin(Math.min(1, b) * Math.PI) * 0.92;

            if (b >= 1) {
                blinkT = -1;
                nextBlink = 2.2 + Math.random() * 3.2;
            }
        }

        for (const eye of rig.eyes) {
            eye.ball.rotation.set(-look.y * 0.35, look.x * 0.45, 0);
            eye.group.scale.y = lid;
        }

        // Hair jiggle (secondary motion).
        rig.spikes.forEach((spike, i) => {
            spike.children[0].rotation.z =
                Math.sin(time * 3 + i * 1.7) * 0.025 + (squash - 1) * 0.6;
        });

        rig.armR.shoulder.rotation.set(POSE.rShoulderX, 0, rShoulderZ);
        rig.armR.elbow.rotation.set(0, 0, rElbowZ);
        rig.armL.shoulder.rotation.set(POSE.lShoulderX, 0, lShoulderZ);
        rig.armL.elbow.rotation.set(lElbowX, 0, POSE.lElbowZ);
    }

    /* Loop ------------------------------------------------------------ */
    let raf = 0;
    let running = false;
    let last = 0;
    let acc = 0;
    const minStep = 1 / Math.max(1, fps);

    function tick(now: number) {
        raf = requestAnimationFrame(tick);
        const dt = Math.min(0.1, (now - last) / 1000 || 0);
        last = now;
        acc += dt;

        if (!action && acc < minStep - 0.002) {
            return;
        }

        update(acc);
        acc = 0;
        renderer.render(scene, camera);
    }

    const onLost = (event: Event) => {
        event.preventDefault();
        stop();
        options.onContextLost?.();
    };
    canvas.addEventListener('webglcontextlost', onLost);

    function start() {
        if (running) {
            return;
        }

        running = true;
        last = performance.now();
        raf = requestAnimationFrame(tick);
    }

    function stop() {
        running = false;
        cancelAnimationFrame(raf);
    }

    function setSize(w: number, h: number) {
        width = Math.max(1, w);
        height = Math.max(1, h);
        renderer.setSize(width, height, false);
        frame();
    }

    function renderOnce() {
        update(0);
        renderer.render(scene, camera);
    }

    function dispose() {
        stop();
        canvas.removeEventListener('webglcontextlost', onLost);
        disposables.forEach((d) => d.dispose());
        envRT.dispose();
        pmrem.dispose();
        renderer.dispose();
        renderer.forceContextLoss();
    }

    setSize(canvas.clientWidth || 1, canvas.clientHeight || 1);

    return {
        setSize,
        setMood(next) {
            mood = next;
        },
        setFraming(next) {
            framing = next;
            frame();
        },
        lookAt(x, y) {
            lookTarget.set(
                THREE.MathUtils.clamp(x, -1, 1),
                THREE.MathUtils.clamp(y, -1, 1),
            );
        },
        play,
        start,
        stop,
        renderOnce,
        dispose,
    };
}
