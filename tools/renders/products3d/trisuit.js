/**
 * TRISUIT — one-piece triathlon suit as a "ghost mannequin" shot: the
 * garment's own 3D form (an SDF shell meshed with surface nets), open at
 * the neck, armholes and legs. Front faces +z. Units are metres.
 */

// torso cross-sections: [y, half-width, half-depth, z-centre]
const TORSO = [
    [0.16, 0.15, 0.092, -0.01],
    [0.22, 0.16, 0.1, -0.012],
    [0.29, 0.162, 0.102, -0.008],
    [0.37, 0.158, 0.096, 0.0],
    [0.45, 0.146, 0.092, 0.006],
    [0.52, 0.15, 0.096, 0.008],
    [0.59, 0.162, 0.104, 0.012],
    [0.66, 0.17, 0.106, 0.012],
    [0.72, 0.172, 0.098, 0.006],
    [0.77, 0.166, 0.086, -0.002],
    [0.81, 0.15, 0.074, -0.006],
    [0.85, 0.12, 0.064, -0.008],
    [0.9, 0.1, 0.058, -0.008],
];
// one leg: [y, centre-x, half-width, half-depth, z-centre]
const LEG = [
    [-0.03, 0.09, 0.077, 0.08, 0.004],
    [0.06, 0.087, 0.083, 0.087, 0.004],
    [0.15, 0.083, 0.09, 0.094, 0.0],
    [0.24, 0.08, 0.096, 0.1, -0.006],
    [0.32, 0.078, 0.1, 0.1, -0.008],
];

function table(rows, step = 0.001) {
    const y0 = rows[0][0];
    const y1 = rows[rows.length - 1][0];
    const n = Math.round((y1 - y0) / step) + 1;
    const cols = rows[0].length - 1;
    const out = new Float32Array(n * cols);

    for (let i = 0; i < n; i++) {
        const y = y0 + i * step;
        let k = 0;

        while (k < rows.length - 2 && rows[k + 1][0] < y) {
            k++;
        }

        const p0 = rows[Math.max(0, k - 1)];
        const p1 = rows[k];
        const p2 = rows[k + 1];
        const p3 = rows[Math.min(rows.length - 1, k + 2)];
        const t = Math.min(1, Math.max(0, (y - p1[0]) / (p2[0] - p1[0])));

        for (let c = 1; c <= cols; c++) {
            // Catmull-Rom
            const v = 0.5 * (2 * p1[c] + (-p0[c] + p2[c]) * t + (2 * p0[c] - 5 * p1[c] + 4 * p2[c] - p3[c]) * t * t + (-p0[c] + 3 * p1[c] - 3 * p2[c] + p3[c]) * t * t * t);
            out[i * cols + c - 1] = v;
        }
    }

    return (y) => {
        const f = Math.min(n - 1.001, Math.max(0, (y - y0) / step));
        const i = Math.floor(f);
        const t = f - i;
        const r = [];

        for (let c = 0; c < cols; c++) {
            r.push(out[i * cols + c] * (1 - t) + out[(i + 1) * cols + c] * t);
        }

        return r;
    };
}

const torsoAt = table(TORSO);
const legAt = table(LEG);

function ellipsoid(x, y, z, cx, cy, cz, rx, ry, rz) {
    const qx = (x - cx) / rx;
    const qy = (y - cy) / ry;
    const qz = (z - cz) / rz;

    return (Math.sqrt(qx * qx + qy * qy + qz * qz) - 1) * Math.min(rx, ry, rz);
}

function makeSdf(lib) {
    const { smin, smax } = lib;
    const T = 0.0055; // shell thickness

    const solid = (x, y, z) => {
        const [a, b, c] = torsoAt(y);
        let torso = (Math.hypot(x / Math.max(a, 1e-3), (z - c) / b) - 1) * Math.min(Math.max(a, 0.01), b);
        torso = smax(torso, 0.17 - y, 0.07);
        torso = smax(torso, y - (0.875 - 1.6 * x * x), 0.035);
        const [lx, la, lb, lc] = legAt(y);
        let legs = 1;

        for (const s of [-1, 1]) {
            let leg = (Math.hypot((x - s * lx) / la, (z - lc) / lb) - 1) * Math.min(la, lb);
            leg = smax(leg, y - 0.32, 0.04);
            legs = Math.min(legs, leg);
        }

        let d = smin(torso, legs, 0.045);
        // compression-fabric relief: waist + crotch folds, gentle irregularity
        const waist = Math.exp(-(((y - 0.44) / 0.05) ** 2));
        d += 0.00015 * waist * Math.sin(y * 210 + lib.noise3(x * 12, y * 4, z * 12) * 3);
        const crotch = Math.exp(-((x / 0.06) ** 2) - (((y - 0.2) / 0.06) ** 2));
        d += 0.0012 * crotch * Math.sin((Math.abs(x) * 0.7 + y) * 160);
        const armpit = Math.exp(-(((Math.abs(x) - 0.15) / 0.03) ** 2) - (((y - 0.585) / 0.04) ** 2));
        d += 0.001 * armpit * Math.sin((y - Math.abs(x) * 0.6) * 260);
        d += 0.0006 * lib.fbm3(x * 18, y * 18, z * 18, 3);

        return d;
    };

    return (x, y, z) => {
        const d = solid(x, y, z);
        let s = Math.max(d, -(d + T));
        // openings: neck (with front scoop for the zip collar), armholes, leg hems
        const neck = Math.min(
            ellipsoid(x, y, z, 0, 0.885, -0.006, 0.07, 0.07, 0.072),
            ellipsoid(x, y, z, 0, 0.83, 0.09, 0.05, 0.042, 0.06),
        );
        s = lib.smax(s, -neck, 0.003);

        for (const sx of [-1, 1]) {
            s = lib.smax(s, -ellipsoid(x, y, z, sx * 0.218, 0.7, -0.004, 0.1, 0.15, 0.11), 0.003);
        }

        s = lib.smax(s, 0.004 + 0.03 * Math.max(0, 0.09 - Math.abs(x)) - y, 0.003);

        return s;
    };
}

const GLSL = `
float flHash(vec3 p) { p = fract(p * 0.3183099 + 0.1); p *= 17.0; return fract(p.x * p.y * p.z * (p.x + p.y + p.z)); }
float flVnoise(vec3 x) { vec3 i = floor(x); vec3 f = fract(x); f = f * f * (3.0 - 2.0 * f);
  return mix(mix(mix(flHash(i), flHash(i + vec3(1,0,0)), f.x), mix(flHash(i + vec3(0,1,0)), flHash(i + vec3(1,1,0)), f.x), f.y),
             mix(mix(flHash(i + vec3(0,0,1)), flHash(i + vec3(1,0,1)), f.x), mix(flHash(i + vec3(0,1,1)), flHash(i + vec3(1,1,1)), f.x), f.y), f.z); }
float flEll(vec3 p, vec3 c, vec3 r) { return (length((p - c) / r) - 1.0) * min(r.x, min(r.y, r.z)); }
// side panel: signed distance (negative inside) to the gold side stripe
float flPanel(vec3 p) {
    float zc = 0.012 * sin(p.y * 7.0 + 0.6) - 0.004;
    float hw = mix(0.018, 0.05, smoothstep(0.0, 0.3, p.y)) * mix(1.0, 0.55, smoothstep(0.5, 0.7, p.y));
    float d = abs(p.z - zc) - hw;
    // torso: both flanks; legs: only the outer side
    float legX = 0.085;
    float side = p.y > 0.26 ? 0.06 - abs(p.x) : legX - abs(p.x);
    return max(d, side);
}
float flNeck(vec3 p) { return min(flEll(p, vec3(0.0, 0.885, -0.006), vec3(0.07, 0.07, 0.072)), flEll(p, vec3(0.0, 0.83, 0.09), vec3(0.05, 0.042, 0.06))); }
float flArm(vec3 p) { return flEll(vec3(abs(p.x), p.y, p.z), vec3(0.218, 0.7, -0.004), vec3(0.1, 0.15, 0.11)); }
float flInside(vec3 p) {
    // 1 on the inner surface of the shell (normal pointing towards the body axis)
    vec2 axis = p.y > 0.24 ? vec2(0.0, 0.0) : vec2(sign(p.x) * 0.085, 0.0);
    vec2 r = p.xz - axis;
    return smoothstep(0.1, -0.1, dot(normalize(vWN.xz + 1e-4), normalize(r + 1e-4)));
}
`;

export async function build(lib, angle) {
    const { THREE } = lib;
    const group = new THREE.Group();
    const colorway = angle === 'carbon' ? 'carbon' : 'gold';

    const geo = lib.sdfMesh(makeSdf(lib), [-0.22, -0.01, -0.13], [0.22, 0.9, 0.13], 0.0021);
    const jer = lib.jersey(512);
    const accent = colorway === 'gold' ? 'vec3(0.36, 0.205, 0.045)' : 'vec3(0.20, 0.21, 0.22)';
    const mat = lib.patch(new THREE.MeshPhysicalMaterial({
        color: '#0f0f10', roughness: 0.6, sheen: 0.7, sheenColor: new THREE.Color('#4d4a46'), sheenRoughness: 0.4, side: THREE.DoubleSide,
    }), {
        normalTex: jer.normal,
        scale: 15,
        strength: 0.26,
        extra: GLSL,
        colorFn: `vec3 flColor(vec3 p, vec3 base) {
            vec3 accent = ${accent};
            float panel = 1.0 - smoothstep(-0.0006, 0.0006, flPanel(p));
            vec3 c = mix(base, accent, panel);
            // thin gold piping: neck binding, armholes and leg hems
            float neckEdge = 1.0 - smoothstep(0.0035, 0.0045, abs(flNeck(p) - 0.003));
            float armEdge = 1.0 - smoothstep(0.0025, 0.0033, abs(flArm(p) - 0.0025));
            float gripper = smoothstep(0.034, 0.032, p.y - 0.03 * max(0.0, 0.09 - abs(p.x)));
            float hemLine = 1.0 - smoothstep(0.0012, 0.0018, abs(p.y - 0.03 * max(0.0, 0.09 - abs(p.x)) - 0.036));
            c = mix(c, vec3(0.006), gripper);
            c = mix(c, vec3(0.47, 0.30, 0.085), max(max(neckEdge, armEdge), hemLine));
            c *= 0.94 + 0.12 * flVnoise(p * 80.0);
            return c * mix(1.0, 0.35, flInside(p));
        }`,
        roughFn: `float flRough(vec3 p, float r) {
            float panel = 1.0 - smoothstep(-0.0006, 0.0006, flPanel(p));
            float gripper = smoothstep(0.034, 0.032, p.y - 0.03 * max(0.0, 0.09 - abs(p.x)));
            return mix(mix(r, 0.6, panel), 0.3, gripper);
        }`,
        heightFn: `float flHeight(vec3 p) {
            float e = flPanel(p);
            float seam = 0.00035 * exp(-pow(e / 0.0014, 2.0)) - 0.0002 * exp(-pow((e - 0.0028) / 0.0008, 2.0));
            float gy = p.y - 0.03 * max(0.0, 0.09 - abs(p.x));
            float dots = gy < 0.033 ? 0.00025 * smoothstep(0.42, 0.3, length(fract(vec2(atan(p.z, p.x - sign(p.x) * 0.085) * 14.0, gy * 260.0)) - 0.5)) : 0.0;
            return seam + dots;
        }`,
        sheenFn: `vec3 flSheen(vec3 p) {
            float panel = 1.0 - smoothstep(-0.0006, 0.0006, flPanel(p));
            return mix(vec3(1.0), vec3(0.55, 0.38, 0.16), panel);
        }`,
        aoFn: `float flOcclusion(vec3 p) {
            float inside = flInside(p);
            float crotch = 1.0 - 0.35 * exp(-pow(p.x / 0.03, 2.0) - pow((p.y - 0.2) / 0.03, 2.0));
            return mix(1.0, 0.18, inside) * crotch;
        }`,
    });
    const suit = new THREE.Mesh(geo, mat);
    group.add(suit);

    // --- front zip -------------------------------------------------------------
    const proxyMesh = suit;
    const zipTop = 0.786;
    const zipBottom = 0.5;
    const zipPts = Array.from({ length: 40 }, (_, i) => [0, zipTop - ((zipTop - zipBottom) * i) / 39]);
    const hits = lib.projectPoints(proxyMesh, zipPts, 'z', 1);
    const zipCurve = new THREE.CatmullRomCurve3(hits.map(({ p, n }) => p.addScaledVector(n, 0.0012)), false, 'centripetal');
    const teeth = lib.heightTextures(64, (x, y) => Math.sin((Math.PI * (y + 0.5)) / 64) ** 0.7 * Math.min(1, Math.sin((Math.PI * (x + 0.5)) / 64) * 2.5), 6);
    teeth.normal.repeat.set(1, zipCurve.getLength() / 0.0018);
    group.add(new THREE.Mesh(lib.sweep(zipCurve, lib.roundedRect(0.0058, 0.0018, 0.0008, 3), { segments: 300, up: [0, 0, 1] }), new THREE.MeshPhysicalMaterial({ color: '#121212', roughness: 0.3, clearcoat: 0.6, normalMap: teeth.normal })));
    const gold = new THREE.MeshPhysicalMaterial({ color: '#C39645', metalness: 1, roughness: 0.3, clearcoat: 0.4, clearcoatRoughness: 0.2 });
    {
        const p = zipCurve.getPointAt(0.04);
        const n = hits[2].n;
        const slider = new THREE.Mesh(new THREE.CapsuleGeometry(0.0034, 0.008, 6, 16), gold);
        slider.scale.set(1, 1, 0.5);
        slider.position.copy(p).addScaledVector(n, 0.0018);
        slider.lookAt(slider.position.clone().add(n));
        slider.rotateX(Math.PI / 2);
        slider.rotation.set(0, 0, 0);
        group.add(slider);
        const shape = lib.roundedShape(0.011, 0.032, 0.0045);
        shape.holes.push(new THREE.Path(lib.roundedShape(0.0036, 0.008, 0.0016).getPoints().map((pt) => new THREE.Vector2(pt.x, pt.y + 0.0065))));
        const tab = new THREE.Mesh(new THREE.ExtrudeGeometry(shape, { depth: 0.001, bevelEnabled: true, bevelThickness: 0.0005, bevelSize: 0.0005, bevelSegments: 3, curveSegments: 16 }), gold);
        tab.geometry.translate(0, -0.012, 0);
        tab.position.copy(p).addScaledVector(n, 0.0035).add(new THREE.Vector3(0, -0.003, 0));
        tab.rotation.set(-0.35, 0, 0.05);
        group.add(tab);
    }

    // --- logos -----------------------------------------------------------------
    const { DecalGeometry } = await import('three/addons/geometries/DecalGeometry.js');
    suit.updateMatrixWorld(true);
    const decal = async (x, y, axis, sign, w, canvas, metal = 0.85) => {
        const hit = lib.projectPoints(suit, [[x, y]], axis, sign)[0];
        const orient = new THREE.Euler().setFromRotationMatrix(new THREE.Matrix4().lookAt(hit.n.clone(), new THREE.Vector3(), new THREE.Vector3(0, 1, 0)));
        const h = (w * canvas.height) / canvas.width;
        const m = new THREE.MeshPhysicalMaterial({
            map: lib.textureFrom(canvas), transparent: true, alphaTest: 0.4, metalness: metal, roughness: 0.32, clearcoat: 0.5, clearcoatRoughness: 0.2,
            polygonOffset: true, polygonOffsetFactor: -4, depthWrite: false,
        });
        const mesh = new THREE.Mesh(new DecalGeometry(suit, hit.p, orient, new THREE.Vector3(w, h, 0.04)), m);
        mesh.userData.noFrame = true;
        group.add(mesh);
    };
    const logo = await lib.logoCanvas({ tone: 'gold', pad: 0.02, height: 512, color: colorway === 'gold' ? '#D2AE62' : '#C9C9C9' });
    await decal(0.068, 0.672, 'z', 1, 0.062, logo);
    // wordmark across the upper back
    {
        const c = document.createElement('canvas');
        c.width = 2048;
        c.height = 256;
        const g = c.getContext('2d');
        g.fillStyle = '#D2AE62';
        g.font = 'italic 900 150px "Arial Black", Arial, sans-serif';
        g.textAlign = 'center';
        g.textBaseline = 'middle';
        g.letterSpacing = '24px';
        g.fillText('FINISHER LEGACY', 1024, 132);
        await decal(0, 0.735, 'z', -1, 0.2, c, 0.6);
    }
    // small FL on the left thigh, inside the gold panel's neighbourhood
    await decal(0.1, 0.075, 'z', 1, 0.042, await lib.logoCanvas({ tone: 'gold', pad: 0.02, height: 256, color: '#D2AE62' }));

    const views = {
        hero: { azimuth: 0.42, elevation: 0.1, fov: 22, fill: 0.84 },
        carbon: { azimuth: 0.42, elevation: 0.1, fov: 22, fill: 0.84 },
        back: { azimuth: Math.PI - 0.38, elevation: 0.1, fov: 22, fill: 0.84 },
        side: { azimuth: Math.PI / 2 + 0.12, elevation: 0.06, fov: 22, fill: 0.84 },
        detail: { azimuth: 0.32, elevation: 0.14, fov: 16, fill: 0.86 },
    };
    const view = { ...(views[angle] ?? views.hero) };

    if (angle === 'detail') {
        const focus = new THREE.Mesh(new THREE.BoxGeometry(0.36, 0.3, 0.06));
        focus.position.set(0.04, 0.74, 0.06);
        view.focus = focus;
    }

    return { object: group, view, studio: { float: 0.07, contactOpacity: 0.35, contactBlur: 4, floorShadow: 0.1 } };
}
