/**
 * Interactive three.js viewer for the Finisher Legacy Tag (store, product
 * pages). The mesh itself (body, artwork caps, grommet, strap) lives in
 * tagModel.ts and is shared with the Home's travelling stage
 * (tagStage.ts); this file adds the contact shadow, camera and controls.
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
import type { TagArtworkOptions } from './tagArtwork';
import { drawShadow, loadArtworkFonts } from './tagArtwork';
import { H, addStudioLighting, createTagModel } from './tagModel';

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

const TARGET = new THREE.Vector3(0, 0.55, 0);

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
    const disposeLighting = addStudioLighting(renderer, scene);

    const camera = new THREE.PerspectiveCamera(30, 1, 0.1, 100);
    camera.position.set(0, TARGET.y + 0.4, 10.6);

    // ---- The tag ---------------------------------------------------------
    const model = createTagModel(renderer, options);
    const tag = model.group;
    scene.add(tag);

    const shadowMap = new THREE.CanvasTexture(drawShadow());
    shadowMap.colorSpace = THREE.SRGBColorSpace;

    // Contact shadow (static on the floor; the tag floats above it).
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

            model.dispose();
            shadow.geometry.dispose();
            shadowMaterial.dispose();
            shadowMap.dispose();
            disposeLighting();
            renderer.dispose();
            canvas.remove();
        },
    };
}
