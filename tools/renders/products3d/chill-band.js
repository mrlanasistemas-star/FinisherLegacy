/**
 * CHILL BAND — knit cooling band (a flattened fabric tube sewn into a loop)
 * standing on its edge on the studio floor, slightly ovalised and leaning
 * the way soft fabric does, freshly wetted. Units are metres.
 */
const W = 0.052; // band width
const TH = 0.0085; // flattened tube thickness
const R0 = 0.084;

export async function build(lib, angle) {
    const { THREE } = lib;
    const group = new THREE.Group();
    const twistAt = (th) => {
        // upright wall that leans in and out a little around the loop
        return Math.PI / 2 + 0.03 * Math.sin(2 * th + 0.3) + 0.02 * Math.sin(3 * th + 1.1);
    };
    const N = 220;
    const pts = [];

    for (let i = 0; i < N; i++) {
        const th = (i / N) * Math.PI * 2;
        const r = R0 * (1 + 0.04 * Math.sin(3 * th + 1.3) + 0.02 * Math.sin(5 * th + 2));
        const a = twistAt(th);
        const y = Math.abs((W / 2) * Math.sin(a)) + Math.abs((TH / 2) * Math.cos(a)) + 0.0003;
        pts.push(new THREE.Vector3(r * 1.12 * Math.cos(th), y, r * 0.9 * Math.sin(th)));
    }

    const curve = new THREE.CatmullRomCurve3(pts, true, 'centripetal');
    const len = curve.getLength();
    const section = lib.roundedRectEven(TH, W, TH * 0.49, 0.0008);
    const thetaAt = (t) => {
        const p = curve.getPointAt(t % 1);

        return Math.atan2(p.z, p.x);
    };
    const geo = lib.sweep(curve, section, {
        segments: 900,
        closed: true,
        up: [0, 1, 0],
        twist: (t) => twistAt(thetaAt(t)),
        scale: (t) => {
            const th = thetaAt(t);
            // [width, thickness] after the quarter-turn twist
            const puff = 1 + 0.18 * lib.noise3(Math.cos(th) * 2, Math.sin(th) * 2, 0.5);

            return [1 + 0.035 * lib.noise3(Math.cos(th) * 3, Math.sin(th) * 3, 2.5), puff];
        },
    });

    // soft fabric irregularity on the surface
    const p = geo.attributes.position;
    const n = geo.attributes.normal;

    for (let i = 0; i < p.count; i++) {
        const k = 0.0007 * lib.fbm3(p.getX(i) * 60, p.getY(i) * 60, p.getZ(i) * 60, 3) + 0.0011 * lib.noise3(p.getX(i) * 22, p.getY(i) * 6, p.getZ(i) * 22);
        p.setXYZ(i, p.getX(i) + n.getX(i) * k, p.getY(i) + n.getY(i) * k, p.getZ(i) + n.getZ(i) * k);
    }

    geo.computeVertexNormals();

    // colour map across the section: black knit with a thin gold rim on both edges
    const ns = section.length;
    const cc = document.createElement('canvas');
    cc.width = 2048;
    cc.height = 8;
    const g = cc.getContext('2d');

    for (let x = 0; x < cc.width; x++) {
        const u = (x / cc.width) * ns;
        const j = Math.floor(u);
        const f = u - j;
        const sy = section[j % ns][1] * (1 - f) + section[(j + 1) % ns][1] * f;
        const rim = Math.abs(sy) > W / 2 - 0.0016;
        g.fillStyle = rim ? '#A9823E' : '#121212';
        g.fillRect(x, 0, 1, 8);
    }

    const colorMap = lib.textureFrom(cc);
    colorMap.wrapS = colorMap.wrapT = THREE.RepeatWrapping;
    const kn = lib.knit(512, 16, 20);
    kn.normal.repeat.set(Math.round((2 * W + 2 * TH) / 0.014), Math.round(len / 0.014));
    const mat = lib.patch(new THREE.MeshPhysicalMaterial({
        map: colorMap, roughness: 0.82, sheen: 1, sheenColor: new THREE.Color('#5f5b55'), sheenRoughness: 0.5,
        normalMap: kn.normal, normalScale: new THREE.Vector2(0.75, 0.75),
    }), {
        extra: `
float flHash(vec3 p) { p = fract(p * 0.3183099 + 0.1); p *= 17.0; return fract(p.x * p.y * p.z * (p.x + p.y + p.z)); }
float flVnoise(vec3 x) { vec3 i = floor(x); vec3 f = fract(x); f = f * f * (3.0 - 2.0 * f);
  return mix(mix(mix(flHash(i), flHash(i + vec3(1,0,0)), f.x), mix(flHash(i + vec3(0,1,0)), flHash(i + vec3(1,1,0)), f.x), f.y),
             mix(mix(flHash(i + vec3(0,0,1)), flHash(i + vec3(1,0,1)), f.x), mix(flHash(i + vec3(0,1,1)), flHash(i + vec3(1,1,1)), f.x), f.y), f.z); }
float flWet(vec3 p) { return smoothstep(0.45, 0.62, flVnoise(p * 28.0) * 0.7 + flVnoise(p * 70.0) * 0.3); }`,
        colorFn: `vec3 flColor(vec3 p, vec3 base) { return base * mix(1.0, 0.55, flWet(p)) * (0.93 + 0.14 * flVnoise(p * 300.0)); }`,
        roughFn: `float flRough(vec3 p, float r) { return mix(r, 0.42, flWet(p)); }`,
    });
    const band = new THREE.Mesh(geo, mat);
    group.add(band);

    // --- printed FL + wordmark on the flat-lying front part -------------------
    const { DecalGeometry } = await import('three/addons/geometries/DecalGeometry.js');
    band.updateMatrixWorld(true);
    const ray = new THREE.Raycaster();
    const sideHit = (t, y) => {
        const c = curve.getPointAt(t);
        const radial = new THREE.Vector3(c.x, 0, c.z).normalize();
        ray.set(new THREE.Vector3(c.x, y, c.z).addScaledVector(radial, 0.3), radial.clone().negate());

        return ray.intersectObject(band, false)[0];
    };
    const printAt = (t, canvas, w, metal) => {
        const hit = sideHit(t, curve.getPointAt(t).y);

        if (!hit) {
            return;
        }

        const nrm = hit.face.normal.clone();
        const m = new THREE.Matrix4().lookAt(nrm, new THREE.Vector3(), new THREE.Vector3(0, 1, 0));
        const orient = new THREE.Euler().setFromRotationMatrix(m);
        const h = (w * canvas.height) / canvas.width;
        const mesh = new THREE.Mesh(new DecalGeometry(band, hit.point, orient, new THREE.Vector3(w, h, 0.03)), new THREE.MeshPhysicalMaterial({
            map: lib.textureFrom(canvas), transparent: true, alphaTest: 0.35, depthWrite: false, polygonOffset: true, polygonOffsetFactor: -4,
            metalness: metal, roughness: 0.38, clearcoat: 0.3,
        }));
        mesh.userData.noFrame = true;
        group.add(mesh);
    };
    // rotate the logo canvas so it reads along the band
    const logo = await lib.logoCanvas({ tone: 'gold', pad: 0.04, height: 512, color: '#D2AE62' });
    const word = document.createElement('canvas');
    word.width = 2048;
    word.height = 200;
    {
        const wg = word.getContext('2d');
        wg.fillStyle = '#C9A45C';
        wg.font = 'italic 800 120px "Arial Black", Arial, sans-serif';
        wg.textAlign = 'center';
        wg.textBaseline = 'middle';
        wg.letterSpacing = '30px';
        wg.fillText('FINISHER LEGACY', 1024, 104);
    }
    const tLogo = 0.25;
    printAt(tLogo, logo, 0.036, 0.8);
    printAt(tLogo + 0.115, word, 0.07, 0.6);
    printAt(tLogo - 0.115, word, 0.07, 0.6);

    // --- water droplets ---------------------------------------------------------
    const water = new THREE.MeshPhysicalMaterial({ color: '#ffffff', metalness: 0, roughness: 0.02, transmission: 1, ior: 1.333, thickness: 0.003, clearcoat: 1, clearcoatRoughness: 0.02, specularIntensity: 1 });
    const rnd = lib.rng(42);
    const drop = new THREE.SphereGeometry(1, 32, 16, 0, Math.PI * 2, 0, Math.PI / 2);
    let placed = 0;

    for (let i = 0; i < 900 && placed < 40; i++) {
        const t = rnd();
        const hit = sideHit(t, 0.004 + rnd() * (W - 0.008));

        if (!hit) {
            continue;
        }

        const r = 0.0007 + rnd() ** 2.6 * 0.0028;
        const d = new THREE.Mesh(drop, water);
        d.scale.set(r * (1 + rnd() * 0.2), r * 0.6, r * (1.1 + rnd() * 0.35));
        d.position.copy(hit.point).addScaledVector(hit.face.normal, -r * 0.08);
        d.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), hit.face.normal);
        d.rotateY(rnd() * Math.PI);
        d.userData.castShadow = false;
        d.userData.noContactShadow = true;
        d.userData.noFrame = true;
        group.add(d);
        placed++;
    }

    const views = {
        hero: { azimuth: 0.3, elevation: 0.32, fov: 22, fill: 0.84, offsetY: 0.0 },
        top: { azimuth: 0.2, elevation: 1.05, fov: 20, fill: 0.8, offsetY: 0.0 },
        detail: { azimuth: Math.PI / 2 - 0.25, elevation: 0.22, fov: 14, fill: 1.0 },
    };
    const view = { ...(views[angle] ?? views.hero) };

    if (angle === 'detail') {
        const c = curve.getPointAt(tLogo);
        const focus = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.05, 0.06));
        focus.position.set(c.x, 0.026, c.z);
        view.focus = focus;
    }

    return { object: group, view, studio: { contactOpacity: 0.85, contactBlur: 1.6, floorShadow: 0.2, shadowRadius: 10 } };
}
