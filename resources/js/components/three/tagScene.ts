/**
 * Procedural three.js scene for the Finisher Legacy Tag (no .glb exists):
 *
 * - body: a rounded-rectangle ExtrudeGeometry with a bevel and a real
 *   punched eyelet (shape hole), resin-like MeshPhysicalMaterial with
 *   clearcoat;
 * - FRONT / BACK: two decal caps (ShapeGeometry of the same outline) with
 *   the canvas artwork from tagArtwork.ts — the back also uses it as a
 *   subtle bump map so the copy reads engraved;
 * - a gold grommet (two tori + a lining cylinder), a woven strap loop that
 *   passes through the eyelet (custom ribbon geometry along a closed
 *   curve) and a crimp where it closes;
 * - a soft contact shadow, RoomEnvironment + PMREM lighting.
 *
 * Interaction: OrbitControls (damping, no pan, ctrl/pinch zoom only, limited
 * polar angle), gentle idle auto-rotate that stops on interaction and comes
 * back after a pause, animated Frente/Reverso, mouse hover tilt and a slow
 * float. Everything motion-related is off under reduced motion. Rendering
 * only runs while `setActive(true)` (the component pauses it offscreen).
 *
 * Loaded ONLY through a dynamic import from LegacyTag3D.vue, so three.js is
 * never part of the initial bundle and nothing touches `window` at import.
 */
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import type { TagArtworkOptions } from './tagArtwork';
import {
    drawBack,
    drawFront,
    drawShadow,
    drawStrap,
    loadArtworkFonts,
} from './tagArtwork';

export type TagFace = 'front' | 'back';

export type TagSceneOptions = TagArtworkOptions & {
    strapColor: string;
    metalColor: string;
    reducedMotion: boolean;
    autoRotate: boolean;
    initialFace: TagFace;
    onFaceChange?: (face: TagFace) => void;
};

export type TagSceneHandle = {
    setFace: (face: TagFace) => void;
    nudge: (radians: number) => void;
    setActive: (active: boolean) => void;
    dispose: () => void;
};

// Tag proportions follow the illustrative drawing (200×340, r=26).
const W = 2;
const H = 3.4;
const R = 0.26;
const DEPTH = 0.08;
const BEVEL = 0.028;
const HOLE_Y = H / 2 - 0.34;
const HOLE_R = 0.1;
const CAP_Z = DEPTH / 2 + BEVEL;
const TARGET = new THREE.Vector3(0, 0.55, 0);

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
function strapGeometry(): { geometry: THREE.BufferGeometry; length: number } {
    const y = HOLE_Y;
    const curve = new THREE.CatmullRomCurve3(
        [
            new THREE.Vector3(0, y - 0.045, 0),
            new THREE.Vector3(0, y + 0.06, 0.1),
            new THREE.Vector3(0, y + 0.22, 0.125),
            new THREE.Vector3(0, H / 2 + 0.1, 0.14),
            new THREE.Vector3(0, H / 2 + 0.75, 0.13),
            new THREE.Vector3(0, H / 2 + 1.18, 0.06),
            new THREE.Vector3(0, H / 2 + 1.28, 0),
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

    return { geometry, length };
}

const easeInOutCubic = (t: number) =>
    t < 0.5 ? 4 * t * t * t : 1 - (-2 * t + 2) ** 3 / 2;

/** Throws when WebGL is unavailable — the component shows its poster then. */
export async function createTagScene(
    host: HTMLElement,
    options: TagSceneOptions,
): Promise<TagSceneHandle> {
    await loadArtworkFonts();

    const renderer = new THREE.WebGLRenderer({
        antialias: true,
        alpha: true,
        powerPreference: 'high-performance',
    });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.NeutralToneMapping;
    renderer.toneMappingExposure = 1.05;
    renderer.setClearColor(0x000000, 0);

    const canvas = renderer.domElement;
    canvas.style.display = 'block';
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvas.style.cursor = 'grab';
    canvas.setAttribute('aria-hidden', 'true');
    host.appendChild(canvas);

    const scene = new THREE.Scene();
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

    const camera = new THREE.PerspectiveCamera(30, 1, 0.1, 100);
    camera.position.set(0, TARGET.y + 0.4, 10.6);

    const maxAniso = Math.min(renderer.capabilities.getMaxAnisotropy(), 8);
    const texture = (source: HTMLCanvasElement, color = true) => {
        const t = new THREE.CanvasTexture(source);
        t.colorSpace = color ? THREE.SRGBColorSpace : THREE.NoColorSpace;
        t.anisotropy = maxAniso;

        return t;
    };

    // ---- The tag ---------------------------------------------------------
    const tag = new THREE.Group();
    scene.add(tag);

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
    tag.add(new THREE.Mesh(bodyGeometry, bodyMaterial));

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
    tag.add(front);

    const backMaterial = new THREE.MeshPhysicalMaterial({
        map: backMap,
        bumpMap: backBump,
        bumpScale: 0.6,
        roughness: 0.6,
        metalness: 0,
        clearcoat: 0.4,
        clearcoatRoughness: 0.35,
    });
    const back = new THREE.Mesh(cap, backMaterial);
    back.rotation.y = Math.PI;
    back.position.z = -CAP_Z - 0.0015;
    tag.add(back);

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
    tag.add(ringFront, ringBack, lining);

    // Strap loop + crimp.
    const strap = strapGeometry();
    const strapMap = texture(drawStrap(options.strapColor, options.accent));
    strapMap.wrapS = THREE.RepeatWrapping;
    strapMap.wrapT = THREE.RepeatWrapping;
    const strapMaterial = new THREE.MeshStandardMaterial({
        map: strapMap,
        roughness: 0.85,
        metalness: 0,
        side: THREE.DoubleSide,
    });
    tag.add(new THREE.Mesh(strap.geometry, strapMaterial));
    const crimpGeometry = new THREE.BoxGeometry(0.2, 0.1, 0.07, 2, 2, 2);
    const crimp = new THREE.Mesh(crimpGeometry, metal);
    crimp.position.set(0, H / 2 + 1.25, 0);
    tag.add(crimp);

    // Contact shadow (static on the floor; the tag floats above it).
    const shadowMap = texture(drawShadow());
    const shadowMaterial = new THREE.MeshBasicMaterial({
        map: shadowMap,
        transparent: true,
        depthWrite: false,
        opacity: 0.55,
    });
    const shadowGeometry = new THREE.PlaneGeometry(3.4, 1.1);
    const shadow = new THREE.Mesh(shadowGeometry, shadowMaterial);
    shadow.rotation.x = -Math.PI / 2;
    shadow.position.y = -H / 2 - 0.42;
    scene.add(shadow);

    // ---- Controls --------------------------------------------------------
    const controls = new OrbitControls(camera, canvas);
    controls.target.copy(TARGET);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;
    controls.enablePan = false;
    controls.enableZoom = true;
    controls.minDistance = 8;
    controls.maxDistance = 13;
    controls.rotateSpeed = 0.7;
    controls.minPolarAngle = Math.PI / 2 - 0.55;
    controls.maxPolarAngle = Math.PI / 2 + 0.3;
    controls.autoRotate = options.autoRotate && !options.reducedMotion;
    controls.autoRotateSpeed = 1.4;
    controls.update();
    // Vertical swipes keep scrolling the page on phones; horizontal drags
    // rotate the tag. (OrbitControls sets touch-action: none.)
    canvas.style.touchAction = 'pan-y';

    // Wheel scrolls the page; only ctrl/⌘ + wheel (and trackpad pinch,
    // which arrives as ctrl+wheel) zooms the model.
    const onWheelCapture = (event: WheelEvent) => {
        if (!event.ctrlKey && !event.metaKey) {
            event.stopPropagation();
        }
    };
    host.addEventListener('wheel', onWheelCapture, { capture: true });

    let resumeTimer: ReturnType<typeof setTimeout> | null = null;
    let dragging = false;
    let currentFace: TagFace = 'front';

    const stopAutoRotate = () => {
        controls.autoRotate = false;

        if (resumeTimer !== null) {
            clearTimeout(resumeTimer);
            resumeTimer = null;
        }
    };
    const scheduleResume = () => {
        if (!options.autoRotate || options.reducedMotion) {
            return;
        }

        if (resumeTimer !== null) {
            clearTimeout(resumeTimer);
        }

        resumeTimer = setTimeout(() => {
            controls.autoRotate = true;
        }, 9000);
    };

    const onStart = () => {
        dragging = true;
        animation = null;
        stopAutoRotate();
        canvas.style.cursor = 'grabbing';
    };
    const onEnd = () => {
        dragging = false;
        canvas.style.cursor = 'grab';
        scheduleResume();
    };
    controls.addEventListener('start', onStart);
    controls.addEventListener('end', onEnd);

    // ---- Hover tilt ------------------------------------------------------
    const tilt = { x: 0, y: 0, tx: 0, ty: 0 };
    const onPointerMove = (event: PointerEvent) => {
        if (
            options.reducedMotion ||
            event.pointerType !== 'mouse' ||
            dragging
        ) {
            return;
        }

        const rect = canvas.getBoundingClientRect();
        tilt.ty = ((event.clientX - rect.left) / rect.width - 0.5) * 0.28;
        tilt.tx = ((event.clientY - rect.top) / rect.height - 0.5) * 0.18;
    };
    const onPointerLeave = () => {
        tilt.tx = 0;
        tilt.ty = 0;
    };
    canvas.addEventListener('pointermove', onPointerMove);
    canvas.addEventListener('pointerleave', onPointerLeave);

    // ---- Face animation --------------------------------------------------
    const spherical = new THREE.Spherical();
    const offset = new THREE.Vector3();
    let animation: {
        start: number;
        duration: number;
        fromAz: number;
        toAz: number;
        fromPolar: number;
        radius: number;
    } | null = null;

    const faceFromAzimuth = (azimuth: number): TagFace =>
        Math.cos(azimuth) >= 0 ? 'front' : 'back';

    const animateTo = (toAz: number) => {
        offset.copy(camera.position).sub(controls.target);
        spherical.setFromVector3(offset);
        const fromAz = spherical.theta;
        let delta = toAz - fromAz;
        delta = Math.atan2(Math.sin(delta), Math.cos(delta));

        if (options.reducedMotion) {
            spherical.theta = fromAz + delta;
            spherical.phi = Math.PI / 2;
            camera.position
                .copy(controls.target)
                .add(offset.setFromSpherical(spherical));
            camera.lookAt(controls.target);
            controls.update();
            requestRender();

            return;
        }

        animation = {
            start: performance.now(),
            duration: 950,
            fromAz,
            toAz: fromAz + delta,
            fromPolar: spherical.phi,
            radius: spherical.radius,
        };
        requestRender();
    };

    const emitFace = () => {
        const face = faceFromAzimuth(controls.getAzimuthalAngle());

        if (face !== currentFace) {
            currentFace = face;
            options.onFaceChange?.(face);
        }
    };

    // ---- Size + loop -----------------------------------------------------
    const resize = () => {
        const width = host.clientWidth || 1;
        const height = host.clientHeight || 1;
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        // Keep the whole tag in frame on narrow (portrait) hosts.
        camera.fov = width / height < 0.72 ? 34 : 30;
        camera.updateProjectionMatrix();
        requestRender();
    };
    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(host);

    let active = false;
    let frame = 0;
    let disposed = false;
    const startedAt = performance.now();

    function renderOnce() {
        renderer.render(scene, camera);
    }

    function tick() {
        frame = 0;

        if (disposed) {
            return;
        }

        const now = performance.now();
        const elapsed = (now - startedAt) / 1000;

        if (animation) {
            const t = Math.min((now - animation.start) / animation.duration, 1);
            const e = easeInOutCubic(t);
            spherical.set(
                animation.radius,
                animation.fromPolar + (Math.PI / 2 - animation.fromPolar) * e,
                animation.fromAz + (animation.toAz - animation.fromAz) * e,
            );
            camera.position
                .copy(controls.target)
                .add(offset.setFromSpherical(spherical));
            camera.lookAt(controls.target);

            if (t >= 1) {
                animation = null;
                controls.update();
            }
        } else {
            controls.update();
        }

        if (!options.reducedMotion) {
            tilt.x += (tilt.tx - tilt.x) * 0.08;
            tilt.y += (tilt.ty - tilt.y) * 0.08;
            tag.rotation.x = tilt.x;
            tag.rotation.y = tilt.y;
            const float = Math.sin(elapsed * 1.1) * 0.05;
            tag.position.y = float;
            shadow.scale.setScalar(1 - float * 0.8);
            shadowMaterial.opacity = 0.55 - float;
        }

        emitFace();
        renderOnce();

        if (active) {
            frame = requestAnimationFrame(tick);
        }
    }

    function requestRender() {
        if (!frame && !disposed) {
            frame = requestAnimationFrame(tick);
        }
    }

    const onVisibility = () => {
        if (document.hidden) {
            cancelAnimationFrame(frame);
            frame = 0;
        } else if (active) {
            requestRender();
        }
    };
    document.addEventListener('visibilitychange', onVisibility);

    if (options.initialFace === 'back') {
        offset.copy(camera.position).sub(controls.target);
        spherical.setFromVector3(offset);
        spherical.theta = Math.PI;
        camera.position
            .copy(controls.target)
            .add(offset.setFromSpherical(spherical));
        controls.update();
        currentFace = 'back';
    }

    resize();
    renderOnce();

    return {
        setFace(face) {
            stopAutoRotate();
            animateTo(face === 'front' ? 0 : Math.PI);
            scheduleResume();
        },
        nudge(radians) {
            stopAutoRotate();
            animateTo(controls.getAzimuthalAngle() + radians);
            scheduleResume();
        },
        setActive(next) {
            active = next;

            if (next) {
                requestRender();
            } else if (frame) {
                cancelAnimationFrame(frame);
                frame = 0;
            }
        },
        dispose() {
            disposed = true;
            active = false;
            cancelAnimationFrame(frame);

            if (resumeTimer !== null) {
                clearTimeout(resumeTimer);
            }

            resizeObserver.disconnect();
            document.removeEventListener('visibilitychange', onVisibility);
            host.removeEventListener('wheel', onWheelCapture, {
                capture: true,
            });
            canvas.removeEventListener('pointermove', onPointerMove);
            canvas.removeEventListener('pointerleave', onPointerLeave);
            controls.removeEventListener('start', onStart);
            controls.removeEventListener('end', onEnd);
            controls.dispose();

            const materials = new Set<THREE.Material>();
            scene.traverse((object) => {
                if (object instanceof THREE.Mesh) {
                    object.geometry.dispose();
                    materials.add(object.material as THREE.Material);
                }
            });
            materials.forEach((m) => m.dispose());
            [frontMap, backMap, backBump, strapMap, shadowMap].forEach((t) =>
                t.dispose(),
            );
            envTarget.dispose();
            pmrem.dispose();
            room.dispose();
            renderer.dispose();
            canvas.remove();
        },
    };
}
