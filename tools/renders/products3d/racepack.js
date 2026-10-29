/**
 * RACEPACK — technical race-day backpack. Front faces +z, the back panel
 * (straps) faces -z, floor at y = 0. Units are metres.
 */
const H = 0.5;
const W = 0.3;

const spow = (v, e) => Math.sign(v) * Math.abs(v) ** e;

function bodyPoint(lib, u, v, displace = true) {
    const th = u * Math.PI * 2;
    const ph = -Math.PI / 2 + v * Math.PI;
    const e1 = ph < 0 ? 0.26 : 0.58;
    const cy = spow(Math.cos(ph), e1);
    const sy = spow(Math.sin(ph), e1);
    const y = 0.16 + (ph < 0 ? 0.16 : 0.34) * sy;
    const t = Math.min(1, Math.max(0, y / H));
    const hw = (W / 2) * (1 - 0.12 * t * t);
    const st = Math.sin(th);
    const ct = Math.cos(th);
    const front = 0.082 - 0.026 * t + 0.012 * Math.sin(Math.PI * t);
    const back = 0.046;
    const e2 = st > 0 ? 0.42 : 0.2;
    let x = hw * cy * spow(ct, e2);
    let z = (st > 0 ? front : back) * cy * spow(st, e2);

    if (displace) {
        // soft fabric irregularity + gentle compression folds near the base
        const n = lib.fbm3(x * 7, y * 7, z * 7, 4) * 0.0034 + lib.noise3(x * 40, y * 40, z * 40) * 0.0004;
        const fold = Math.sin(y * 70 + x * 12) * 0.0009 * Math.exp(-((y - 0.07) ** 2) / 0.003) * (st > 0 ? 1 : 0.3) + (st > 0 ? creases(x, y) : creases(x, y) * 0.4);
        const r = Math.hypot(x, z) || 1;
        x += (x / r) * (n + fold);
        z += (z / r) * (n + fold);
    }

    return [x, y, z];
}

/** Soft fabric creases: segments in the front (x, y) plane that dent the panel. */
const CREASES = [
    [[-0.1, 0.44], [-0.04, 0.33], 0.0016, 0.012],
    [[0.1, 0.44], [0.05, 0.36], 0.0014, 0.01],
    [[-0.13, 0.3], [-0.08, 0.27], 0.0012, 0.008],
    [[0.13, 0.29], [0.07, 0.31], 0.0013, 0.009],
    [[-0.02, 0.31], [0.04, 0.29], 0.0009, 0.014],
    [[-0.13, 0.12], [-0.11, 0.05], 0.001, 0.008],
    [[0.12, 0.13], [0.105, 0.05], 0.001, 0.008],
];

function creases(x, y) {
    let d = 0;

    for (const [[ax, ay], [bx, by], amp, w] of CREASES) {
        const vx = bx - ax;
        const vy = by - ay;
        const t = Math.max(0, Math.min(1, ((x - ax) * vx + (y - ay) * vy) / (vx * vx + vy * vy)));
        const dist = Math.hypot(x - ax - t * vx, y - ay - t * vy);
        const taper = Math.sin(Math.PI * Math.min(1, Math.max(0, t * 0.9 + 0.05)));
        d -= amp * taper * Math.exp(-((dist / w) ** 2));
    }

    return d;
}

function ellipsePts(cx, cy, a, b, e, n, a0 = 0, a1 = Math.PI * 2) {
    const pts = [];

    for (let i = 0; i <= n; i++) {
        const ang = a0 + ((a1 - a0) * i) / n;
        pts.push([cx + a * spow(Math.cos(ang), e), cy + b * spow(Math.sin(ang), e)]);
    }

    return pts;
}

/** A patch (pocket, pad) floating over the body along `axis`. */
function buildPatch(lib, proxy, { axis, sign, cx, cy, a, b, e = 0.35, bulge, nr = 40, na = 96, uvScale = 20 }) {
    const { THREE } = lib;
    const pos = [];
    const uv = [];
    const idx = [];
    const ray = new THREE.Raycaster();
    const grid = [];
    const last = [];

    for (let i = 0; i <= nr; i++) {
        const r = i / nr;
        const row = [];

        for (let j = 0; j <= na; j++) {
            const ang = (j / na) * Math.PI * 2;
            const A = cx + a * r * spow(Math.cos(ang), e);
            const B = cy + b * r * spow(Math.sin(ang), e);
            const origin = axis === 'z' ? new THREE.Vector3(A, B, sign * 5) : new THREE.Vector3(sign * 5, B, A);
            ray.set(origin, axis === 'z' ? new THREE.Vector3(0, 0, -sign) : new THREE.Vector3(-sign, 0, 0));
            const hit = ray.intersectObject(proxy, false)[0];
            let p;
            let nrm;

            if (hit) {
                p = hit.point.clone();
                nrm = hit.face.normal.clone();
                last[j] = { p: p.clone(), n: nrm.clone() };
            } else {
                // edge rays that graze past the body reuse the ring inside
                p = (last[j] ?? last.find(Boolean)).p.clone();
                nrm = (last[j] ?? last.find(Boolean)).n.clone();
            }

            p.addScaledVector(nrm, bulge(r, ang, A, B));
            row.push(pos.length / 3);
            pos.push(p.x, p.y, p.z);
            uv.push(A * uvScale, B * uvScale);
        }

        grid.push(row);
    }

    for (let i = 0; i < nr; i++) {
        for (let j = 0; j < na; j++) {
            const a0 = grid[i][j];
            const b0 = grid[i + 1][j];
            const c0 = grid[i + 1][j + 1];
            const d0 = grid[i][j + 1];
            idx.push(a0, b0, c0, a0, c0, d0);
        }
    }

    const g = new THREE.BufferGeometry();
    g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
    g.setAttribute('uv', new THREE.Float32BufferAttribute(uv, 2));
    g.setIndex(idx);
    g.computeVertexNormals();

    // flip so normals face away from the body
    const n = g.attributes.normal;
    const outward = axis === 'z' ? n.getZ(Math.floor(n.count / 2)) * sign : n.getX(Math.floor(n.count / 2)) * sign;

    if (outward < 0) {
        const ix = g.index.array;

        for (let t = 0; t < ix.length; t += 3) {
            [ix[t + 1], ix[t + 2]] = [ix[t + 2], ix[t + 1]];
        }

        g.computeVertexNormals();
    }

    return g;
}

export async function build(lib, angle) {
    const { THREE } = lib;
    const group = new THREE.Group();

    // --- materials ---------------------------------------------------------
    const rip = lib.ripstop(512, 24);
    const noiseGLSL = `
float flHash(vec3 p) { p = fract(p * 0.3183099 + 0.1); p *= 17.0; return fract(p.x * p.y * p.z * (p.x + p.y + p.z)); }
float flVnoise(vec3 x) { vec3 i = floor(x); vec3 f = fract(x); f = f * f * (3.0 - 2.0 * f);
  return mix(mix(mix(flHash(i), flHash(i + vec3(1,0,0)), f.x), mix(flHash(i + vec3(0,1,0)), flHash(i + vec3(1,1,0)), f.x), f.y),
             mix(mix(flHash(i + vec3(0,0,1)), flHash(i + vec3(1,0,1)), f.x), mix(flHash(i + vec3(0,1,1)), flHash(i + vec3(1,1,1)), f.x), f.y), f.z); }`;
    const shell = lib.patch(lib.fabric({ color: '#161616', roughness: 0.8, sheenColor: '#5a5650', sheenRoughness: 0.5 }), {
        normalTex: rip.normal,
        scale: 12,
        strength: 0.22,
        extra: noiseGLSL,
        colorFn: `vec3 flColor(vec3 p, vec3 base) {
            float basePanel = 1.0 - smoothstep(0.052, 0.054, p.y);
            vec3 c = mix(base, vec3(0.012), basePanel);
            return c * (0.92 + 0.16 * flVnoise(p * 60.0));
        }`,
        roughFn: `float flRough(vec3 p, float r) {
            float basePanel = 1.0 - smoothstep(0.052, 0.054, p.y);
            return mix(r * (0.9 + 0.2 * flVnoise(p * 25.0)), 0.5, basePanel);
        }`,
        heightFn: `float flHeight(vec3 p) {
            float seam = -0.0006 * exp(-pow((p.y - 0.053) / 0.0012, 2.0));
            float stitch = 0.00018 * step(0.55, fract(atan(p.z, p.x) * 95.0)) * exp(-pow((p.y - 0.061) / 0.0006, 2.0));
            return seam + stitch;
        }`,
    });
    const strapMat = lib.patch(lib.fabric({ color: '#141414', roughness: 0.82, sheenColor: '#55514b' }), { normalTex: rip.normal, scale: 12, strength: 0.22 });
    const hex = lib.hexMesh(256, 8);
    hex.normal.repeat.set(1, 1);
    const meshMat = new THREE.MeshPhysicalMaterial({
        color: '#2a2a2a', roughness: 0.62, sheen: 0.8, sheenColor: new THREE.Color('#6a665e'), sheenRoughness: 0.4,
        normalMap: hex.normal, normalScale: new THREE.Vector2(1.2, 1.2), alphaMap: hex.height, alphaTest: 0.35, side: THREE.DoubleSide,
    });
    const airMat = new THREE.MeshPhysicalMaterial({
        color: '#1c1c1c', roughness: 0.85, sheen: 1, sheenColor: new THREE.Color('#5b5852'), sheenRoughness: 0.45,
        normalMap: hex.normal, normalScale: new THREE.Vector2(2, 2),
    });
    const tapeMat = new THREE.MeshPhysicalMaterial({ color: '#0e0e0e', roughness: 0.55, clearcoat: 0.2, clearcoatRoughness: 0.4 });
    const teeth = lib.heightTextures(64, (x, y) => Math.sin((Math.PI * (y + 0.5)) / 64) ** 0.7 * Math.min(1, Math.sin((Math.PI * (x + 0.5)) / 64) * 2.5), 6);
    teeth.normal.repeat.set(1, 260);
    const teethMat = new THREE.MeshPhysicalMaterial({ color: '#141414', roughness: 0.32, clearcoat: 0.6, clearcoatRoughness: 0.2, normalMap: teeth.normal, normalScale: new THREE.Vector2(1.4, 1.4) });
    const gold = new THREE.MeshPhysicalMaterial({ color: '#C39645', metalness: 1, roughness: 0.32, clearcoat: 0.4, clearcoatRoughness: 0.2 });
    const reflective = new THREE.MeshPhysicalMaterial({ color: '#8a8a88', metalness: 0.55, roughness: 0.42, clearcoat: 0.2 });
    const plastic = new THREE.MeshPhysicalMaterial({ color: '#111111', roughness: 0.42, clearcoat: 0.35, clearcoatRoughness: 0.3 });
    const webbingTex = lib.heightTextures(128, (x, y) => Math.sin((x / 128) * Math.PI * 2 * 16) * 0.5 + Math.sin((y / 128) * Math.PI * 2 * 8 + Math.sin((x / 128) * Math.PI * 2 * 16) * 1.2) * 0.5, 2);
    webbingTex.normal.repeat.set(1, 60);
    const webbing = new THREE.MeshPhysicalMaterial({ color: '#121212', roughness: 0.62, sheen: 0.7, sheenColor: new THREE.Color('#5d5a54'), normalMap: webbingTex.normal, normalScale: new THREE.Vector2(0.9, 0.9) });

    // --- body --------------------------------------------------------------
    const body = new THREE.Mesh(lib.parametric((u, v) => bodyPoint(lib, u, v), 320, 280, { wrapU: true }), shell);
    group.add(body);
    const proxy = new THREE.Mesh(lib.parametric((u, v) => bodyPoint(lib, u, v, false), 120, 110, { wrapU: true }), new THREE.MeshBasicMaterial({ side: THREE.DoubleSide }));
    proxy.updateMatrixWorld(true);

    // --- main clamshell zipper ---------------------------------------------
    const zx = 0.118;
    const zipPts = [
        ...Array.from({ length: 14 }, (_, i) => [-zx, 0.1 + (i / 14) * 0.24]),
        ...ellipsePts(0, 0.34, zx, 0.128, 1, 40, Math.PI, 0).map(([x, y]) => [x, y]).slice(0),
        ...Array.from({ length: 15 }, (_, i) => [zx, 0.34 - (i / 14) * 0.24]),
    ];
    const zipCurve = lib.projectCurve(proxy, zipPts, 'z', 1, 0.0028);
    const zipLen = zipCurve.getLength();
    teeth.normal.repeat.set(1, zipLen / 0.0024);
    const zipCurveIn = lib.projectCurve(proxy, zipPts, 'z', 1, 0.0012);
    group.add(new THREE.Mesh(lib.sweep(zipCurveIn, lib.roundedRect(0.016, 0.0022, 0.001, 3), { segments: 500, up: [0, 0, 1] }), tapeMat));
    group.add(new THREE.Mesh(lib.sweep(zipCurve, lib.roundedRect(0.0058, 0.0026, 0.0012, 3), { segments: 900, up: [0, 0, 1] }), teethMat));

    // zipper garage / piping along both edges of the tape
    for (const side of [-1, 1]) {
        const pts = zipPts.map(([x, y], i) => {
            const prev = zipPts[Math.max(0, i - 1)];
            const next = zipPts[Math.min(zipPts.length - 1, i + 1)];
            const tx = next[0] - prev[0];
            const ty = next[1] - prev[1];
            const l = Math.hypot(tx, ty) || 1;

            return [x + (-ty / l) * 0.0095 * side, y + (tx / l) * 0.0095 * side];
        });
        group.add(new THREE.Mesh(lib.sweep(lib.projectCurve(proxy, pts, 'z', 1, 0.0016), lib.roundedRect(0.004, 0.0034, 0.0016, 4), { segments: 500, up: [0, 0, 1] }), shell));
    }

    // sliders + pullers at the top right
    const addPuller = (t, tilt) => {
        const p = zipCurve.getPointAt(t);
        const tan = zipCurve.getTangentAt(t);
        const nrm = lib.projectPoints(proxy, [[p.x, p.y]], 'z', 1)[0]?.n ?? new THREE.Vector3(0, 0, 1);
        const slider = new THREE.Mesh(new (THREE.CapsuleGeometry)(0.0042, 0.012, 6, 16), gold);
        slider.scale.set(1, 1, 0.55);
        const m = new THREE.Matrix4().lookAt(new THREE.Vector3(), nrm, tan);
        slider.quaternion.setFromRotationMatrix(m);
        slider.position.copy(p).addScaledVector(nrm, 0.0025);
        group.add(slider);
        // puller tab: rounded slab with a slot, hanging with gravity
        const shape = lib.roundedShape(0.011, 0.036, 0.0045);
        const holePath = new THREE.Path(lib.roundedShape(0.0045, 0.012, 0.002).getPoints().map((pt) => new THREE.Vector2(pt.x, pt.y + 0.009)));
        shape.holes.push(holePath);
        const tab = new THREE.Mesh(new THREE.ExtrudeGeometry(shape, { depth: 0.0012, bevelEnabled: true, bevelThickness: 0.0007, bevelSize: 0.0007, bevelSegments: 4, curveSegments: 16 }), gold);
        tab.geometry.translate(0, -0.016, -0.0006);
        const down = new THREE.Vector3(0, -1, 0).addScaledVector(nrm, -nrm.dot(new THREE.Vector3(0, -1, 0))).normalize();
        const right = new THREE.Vector3().crossVectors(down, nrm).normalize();
        const basis = new THREE.Matrix4().makeBasis(right, down.clone().negate(), nrm);
        tab.quaternion.setFromRotationMatrix(basis);
        tab.rotateZ(tilt);
        tab.rotateX(-0.18);
        tab.position.copy(p).addScaledVector(nrm, 0.0042).addScaledVector(down, 0.002);
        group.add(tab);
    };
    addPuller(0.6, 0.12);
    addPuller(0.655, -0.08);

    // --- front stretch-mesh pocket ----------------------------------------
    const pk = { cx: 0, cy: 0.175, a: 0.098, b: 0.105 };
    const pocketBulge = (r, ang) => 0.003 + 0.013 * (1 - r ** 6) + Math.max(0, Math.sin(ang)) ** 3 * 0.007 * r ** 4;
    const pocket = new THREE.Mesh(buildPatch(lib, proxy, { axis: 'z', sign: 1, ...pk, e: 0.32, bulge: pocketBulge, uvScale: 1 / 0.034 }), meshMat);
    pocket.userData.receiveShadow = true;
    group.add(pocket);
    // binding around the pocket and an elastic + reflective band at its mouth
    const rimPts = (r) => ellipsePts(pk.cx, pk.cy, pk.a * r, pk.b * r, 0.32, 160);
    const rimCurve = (() => {
        const ray = lib.projectPoints(proxy, rimPts(1), 'z', 1);
        const pts = ray.map(({ p, n }, i) => {
            const ang = (i / 160) * Math.PI * 2;

            return p.addScaledVector(n, pocketBulge(1, ang));
        });

        return new THREE.CatmullRomCurve3(pts.slice(0, -1), true, 'centripetal');
    })();
    group.add(new THREE.Mesh(lib.sweep(rimCurve, lib.roundedRect(0.0055, 0.004, 0.0019, 4), { segments: 400, closed: true, up: [0, 0, 1] }), webbing));
    const mouth = (() => {
        const pts = [];

        for (let i = 0; i <= 60; i++) {
            const ang = 0.12 + (i / 60) * (Math.PI - 0.24);
            pts.push([ang, pk.cx + pk.a * spow(Math.cos(ang), 0.32), pk.cy + pk.b * spow(Math.sin(ang), 0.32) - 0.006]);
        }

        const hits = lib.projectPoints(proxy, pts.map(([, x, y]) => [x, y]), 'z', 1);

        return new THREE.CatmullRomCurve3(hits.map(({ p, n }, i) => p.addScaledVector(n, pocketBulge(1, pts[i][0]) + 0.0012)), false, 'centripetal');
    })();
    group.add(new THREE.Mesh(lib.sweep(mouth, lib.roundedRect(0.014, 0.003, 0.0012, 3), { segments: 200, up: [0, 0, 1] }), webbing));
    const refl = new THREE.Mesh(lib.sweep(mouth, lib.roundedRect(0.0035, 0.0034, 0.0006, 2).map(([x, y]) => [x - 0.0005, y + 0.0004]), { segments: 200, up: [0, 0, 1] }), reflective);
    group.add(refl);

    // --- side bottle pockets ---------------------------------------------
    for (const sign of [1, -1]) {
        const sp = { cx: 0.0, cy: 0.1, a: 0.04, b: 0.084 };
        const bul = (r, ang) => 0.002 + 0.011 * (1 - r ** 4) + Math.max(0, Math.sin(ang)) ** 2 * 0.007 * r ** 3;
        const side = new THREE.Mesh(buildPatch(lib, proxy, { axis: 'x', sign, ...sp, e: 0.4, bulge: bul, uvScale: 1 / 0.034 }), meshMat);
        group.add(side);
        const top = [];

        for (let i = 0; i <= 40; i++) {
            const ang = 0.15 + (i / 40) * (Math.PI - 0.3);
            top.push([ang, sp.cx + sp.a * spow(Math.cos(ang), 0.4), sp.cy + sp.b * spow(Math.sin(ang), 0.4) - 0.005]);
        }

        const hits = lib.projectPoints(proxy, top.map(([, z, y]) => [z, y]), 'x', sign);
        const c = new THREE.CatmullRomCurve3(hits.map(({ p, n }, i) => p.addScaledVector(n, bul(1, top[i][0]) + 0.001)), false, 'centripetal');
        group.add(new THREE.Mesh(lib.sweep(c, lib.roundedRect(0.012, 0.004, 0.0018, 3), { segments: 120, up: [1, 0, 0] }), webbing));
        const rim = lib.projectPoints(proxy, ellipsePts(sp.cx, sp.cy, sp.a, sp.b, 0.4, 120), 'x', sign).map(({ p, n }, i) => p.addScaledVector(n, bul(1, (i / 120) * Math.PI * 2)));
        group.add(new THREE.Mesh(lib.sweep(new THREE.CatmullRomCurve3(rim.slice(0, -1), true, 'centripetal'), lib.roundedRect(0.005, 0.004, 0.0018, 3), { segments: 300, closed: true, up: [1, 0, 0] }), webbing));
    }

    // --- back panel pads ----------------------------------------------------
    for (const sx of [-1, 1]) {
        const pad = buildPatch(lib, proxy, { axis: 'z', sign: -1, cx: sx * 0.058, cy: 0.27, a: 0.042, b: 0.11, e: 0.3, uvScale: 1 / 0.03, bulge: (r) => 0.0015 + 0.013 * Math.pow(Math.max(0, 1 - r ** 8), 0.35) });
        group.add(new THREE.Mesh(pad, airMat));
    }

    group.add(new THREE.Mesh(buildPatch(lib, proxy, { axis: 'z', sign: -1, cx: 0, cy: 0.095, a: 0.11, b: 0.035, e: 0.3, uvScale: 1 / 0.03, bulge: (r) => 0.0015 + 0.011 * Math.pow(Math.max(0, 1 - r ** 8), 0.35) }), airMat));

    // --- shoulder straps ------------------------------------------------------
    const strapCurves = [];

    for (const sx of [1, -1]) {
        const pts = [
            [0.04, 0.445, -0.03], [0.058, 0.43, -0.07], [0.078, 0.39, -0.088], [0.094, 0.31, -0.1],
            [0.104, 0.22, -0.094], [0.112, 0.14, -0.078], [0.12, 0.08, -0.064], [0.126, 0.035, -0.052],
        ].map(([x, y, z]) => new THREE.Vector3(x * sx, y, z));
        const curve = new THREE.CatmullRomCurve3(pts, false, 'centripetal');
        strapCurves.push(curve);
        const scale = (t) => {
            const k = THREE.MathUtils.smoothstep(t, 0.66, 0.76);
            const top = THREE.MathUtils.smoothstep(t, 0, 0.06);

            return [THREE.MathUtils.lerp(1, 0.42, k) * (0.85 + 0.15 * top), THREE.MathUtils.lerp(1, 0.16, k) * (0.6 + 0.4 * top)];
        };
        group.add(new THREE.Mesh(lib.sweep(curve, lib.roundedRect(0.056, 0.021, 0.0095, 8), { segments: 260, up: [1, 0, 0], scale }), strapMat));
        // reflective piping along the padded part
        const sub = new THREE.CatmullRomCurve3(Array.from({ length: 30 }, (_, i) => curve.getPointAt(0.08 + (i / 29) * 0.52)), false, 'centripetal');
        group.add(new THREE.Mesh(lib.sweep(sub, lib.roundedRect(0.004, 0.0012, 0.0005, 2).map(([x, y]) => [x + sx * 0.0, y - 0.0106]), { segments: 120, up: [1, 0, 0] }), reflective));
        // ladder-lock buckle where padding turns into webbing
        const bp = curve.getPointAt(0.78);
        const bt = curve.getTangentAt(0.78);
        const buckle = new THREE.Mesh(new RoundedBox(THREE, 0.03, 0.018, 0.005, 0.002), plastic);
        buckle.position.copy(bp);
        buckle.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), bt.clone().negate());
        group.add(buckle);
    }

    // sternum strap with buckle
    {
        const a = strapCurves[0].getPointAt(0.32);
        const b = strapCurves[1].getPointAt(0.32);
        a.z -= 0.009;
        b.z -= 0.009;
        const mid = a.clone().add(b).multiplyScalar(0.5);
        mid.z -= 0.004;
        const c = new THREE.CatmullRomCurve3([a, a.clone().lerp(mid, 0.5), mid, b.clone().lerp(mid, 0.5), b]);
        group.add(new THREE.Mesh(lib.sweep(c, lib.roundedRect(0.02, 0.0022, 0.0009, 2), { segments: 60, up: [0, 1, 0] }), webbing));
        const buckle = new THREE.Mesh(new RoundedBox(THREE, 0.042, 0.024, 0.008, 0.003), plastic);
        buckle.position.copy(mid).add(new THREE.Vector3(0, 0, -0.003));
        group.add(buckle);
        const inlay = new THREE.Mesh(new RoundedBox(THREE, 0.012, 0.012, 0.0012, 0.0005), gold);
        inlay.position.copy(buckle.position).add(new THREE.Vector3(0, 0, -0.0046));
        group.add(inlay);
    }

    // gold tag ring on the right strap (where the Finisher Legacy Tag hangs)
    {
        const p = strapCurves[0].getPointAt(0.22);
        const ring = new THREE.Mesh(new THREE.TorusGeometry(0.0085, 0.0016, 16, 48), gold);
        ring.position.copy(p).add(new THREE.Vector3(0.022, -0.012, -0.004));
        ring.rotation.set(0.2, Math.PI / 2 - 0.35, 0);
        group.add(ring);
    }

    // --- top grab handle ----------------------------------------------------
    {
        const pts = [[-0.04, 0.46, -0.026], [-0.034, 0.488, -0.027], [-0.016, 0.505, -0.028], [0.016, 0.505, -0.028], [0.034, 0.488, -0.027], [0.04, 0.46, -0.026]].map((p) => new THREE.Vector3(...p));
        const c = new THREE.CatmullRomCurve3(pts, false, 'centripetal');
        group.add(new THREE.Mesh(lib.sweep(c, lib.roundedRect(0.024, 0.0035, 0.0015, 3), { segments: 80, up: [0, 0, 1] }), webbing));
    }

    // --- logo patch (rubber, gold FL) ----------------------------------------
    {
        const logo = await lib.logoCanvas({ tone: 'gold', pad: 0.0, height: 400 });
        const cw = 1024;
        const ch = 512;
        const mk = (draw) => {
            const c = document.createElement('canvas');
            c.width = cw;
            c.height = ch;
            draw(c.getContext('2d'));

            return c;
        };
        const rr = (g, inset, r) => {
            g.beginPath();
            g.roundRect(inset, inset, cw - 2 * inset, ch - 2 * inset, r);
        };
        const lw = 560;
        const lh = (lw * logo.height) / logo.width;
        const color = mk((g) => {
            rr(g, 8, 120);
            g.fillStyle = '#C9A45C';
            g.fill();
            rr(g, 26, 104);
            g.fillStyle = '#0c0c0c';
            g.fill();
            g.drawImage(logo, (cw - lw) / 2, (ch - lh) / 2, lw, lh);
        });
        const metal = mk((g) => {
            g.fillStyle = '#000';
            g.fillRect(0, 0, cw, ch);
            rr(g, 8, 120);
            g.fillStyle = '#fff';
            g.fill();
            rr(g, 26, 104);
            g.fillStyle = '#000';
            g.fill();
            g.filter = 'brightness(0) invert(1)';
            g.drawImage(logo, (cw - lw) / 2, (ch - lh) / 2, lw, lh);
        });
        const alpha = mk((g) => {
            g.fillStyle = '#000';
            g.fillRect(0, 0, cw, ch);
            rr(g, 8, 120);
            g.fillStyle = '#fff';
            g.fill();
        });
        const rough = mk((g) => {
            g.drawImage(metal, 0, 0);
            g.globalCompositeOperation = 'difference';
            g.fillStyle = '#fff';
            g.fillRect(0, 0, cw, ch);
            g.globalCompositeOperation = 'source-over';
            g.fillStyle = 'rgba(0,0,0,0.45)';
            g.fillRect(0, 0, cw, ch);
        });
        const mat = new THREE.MeshPhysicalMaterial({
            map: lib.textureFrom(color), metalnessMap: lib.textureFrom(metal, false), metalness: 1, roughness: 1, roughnessMap: lib.textureFrom(rough, false),
            alphaMap: lib.textureFrom(alpha, false), alphaTest: 0.5, clearcoat: 0.25, clearcoatRoughness: 0.4,
            bumpMap: lib.textureFrom(metal, false), bumpScale: 0.6, polygonOffset: true, polygonOffsetFactor: -4,
        });
        const { DecalGeometry } = await import('three/addons/geometries/DecalGeometry.js');
        body.updateMatrixWorld(true);
        const hit = lib.projectPoints(body, [[0, 0.392]], 'z', 1)[0];
        const orient = new THREE.Euler().setFromRotationMatrix(new THREE.Matrix4().lookAt(hit.n.clone(), new THREE.Vector3(), new THREE.Vector3(0, 1, 0)));
        const decal = new THREE.Mesh(new DecalGeometry(body, hit.p, orient, new THREE.Vector3(0.076, 0.038, 0.06)), mat);
        decal.userData.noFrame = true;
        group.add(decal);
    }

    // --- views -------------------------------------------------------------------
    const views = {
        hero: { azimuth: 0.62, elevation: 0.14, fov: 22, fill: 0.8 },
        front: { azimuth: 0.0, elevation: 0.08, fov: 22, fill: 0.78 },
        back: { azimuth: Math.PI + 0.62, elevation: 0.14, fov: 22, fill: 0.8 },
        detail: { azimuth: 0.35, elevation: 0.2, fov: 16, fill: 1.05 },
    };
    const view = views[angle] ?? views.hero;

    if (angle === 'detail') {
        // frame the logo + top of the zipper
        const focus = new THREE.Mesh(new THREE.BoxGeometry(0.2, 0.18, 0.05));
        focus.position.set(0.02, 0.4, 0.06);
        focus.visible = true;
        focus.userData.noFrame = false;
        view.focus = focus;
    }

    return { object: group, view, studio: { contactOpacity: 0.85, floorShadow: 0.18 } };
}

function RoundedBox(THREE, w, h, d, r) {
    const shape = new THREE.Shape();
    const x = -w / 2;
    const y = -h / 2;
    shape.moveTo(x + r, y);
    shape.lineTo(x + w - r, y);
    shape.quadraticCurveTo(x + w, y, x + w, y + r);
    shape.lineTo(x + w, y + h - r);
    shape.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
    shape.lineTo(x + r, y + h);
    shape.quadraticCurveTo(x, y + h, x, y + h - r);
    shape.lineTo(x, y + r);
    shape.quadraticCurveTo(x, y, x + r, y);
    const g = new THREE.ExtrudeGeometry(shape, { depth: d - r, bevelEnabled: true, bevelThickness: r / 2, bevelSize: r / 2, bevelSegments: 3, curveSegments: 6 });
    g.translate(0, 0, -(d - r) / 2);

    return g;
}
