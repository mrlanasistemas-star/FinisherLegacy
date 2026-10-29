/**
 * Shared studio for the product renders: renderer, softbox environment,
 * shadows, auto-framing, procedural fabric textures, a triplanar/bump
 * material patcher and an SDF mesher (surface nets). Each product module
 * exports `build(lib, angle)` returning { object, view } — see racepack.js.
 */
import * as THREE from 'three';
import { HorizontalBlurShader } from 'three/addons/shaders/HorizontalBlurShader.js';
import { VerticalBlurShader } from 'three/addons/shaders/VerticalBlurShader.js';

export { THREE };

export const GOLD = new THREE.Color('#C9A45C');
export const PAPER_TOP = '#F4F1EA';
export const PAPER_BOTTOM = '#ECE7DC';

// --- deterministic noise -----------------------------------------------------
export function rng(seed = 1) {
    let s = seed >>> 0;

    return () => {
        s = (s + 0x6d2b79f5) >>> 0;
        let t = s;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);

        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

const perm = (() => {
    const r = rng(7);
    const p = [...Array(256).keys()];

    for (let i = 255; i > 0; i--) {
        const j = Math.floor(r() * (i + 1));
        [p[i], p[j]] = [p[j], p[i]];
    }

    return Uint8Array.from([...p, ...p]);
})();
const fade = (t) => t * t * t * (t * (t * 6 - 15) + 10);
const grad3 = (h, x, y, z) => {
    const u = h < 8 ? x : y;
    const v = h < 4 ? y : h === 12 || h === 14 ? x : z;

    return ((h & 1) ? -u : u) + ((h & 2) ? -v : v);
};

/** Classic 3D Perlin noise in [-1, 1]. */
export function noise3(x, y, z) {
    const X = Math.floor(x) & 255;
    const Y = Math.floor(y) & 255;
    const Z = Math.floor(z) & 255;
    x -= Math.floor(x);
    y -= Math.floor(y);
    z -= Math.floor(z);
    const u = fade(x);
    const v = fade(y);
    const w = fade(z);
    const A = perm[X] + Y;
    const AA = perm[A] + Z;
    const AB = perm[A + 1] + Z;
    const B = perm[X + 1] + Y;
    const BA = perm[B] + Z;
    const BB = perm[B + 1] + Z;
    const L = (a, b, t) => a + t * (b - a);

    return L(
        L(L(grad3(perm[AA] & 15, x, y, z), grad3(perm[BA] & 15, x - 1, y, z), u), L(grad3(perm[AB] & 15, x, y - 1, z), grad3(perm[BB] & 15, x - 1, y - 1, z), u), v),
        L(L(grad3(perm[AA + 1] & 15, x, y, z - 1), grad3(perm[BA + 1] & 15, x - 1, y, z - 1), u), L(grad3(perm[AB + 1] & 15, x, y - 1, z - 1), grad3(perm[BB + 1] & 15, x - 1, y - 1, z - 1), u), v),
        w,
    );
}

export function fbm3(x, y, z, oct = 4) {
    let a = 0.5;
    let s = 0;

    for (let i = 0; i < oct; i++) {
        s += a * noise3(x, y, z);
        x *= 2.03;
        y *= 2.03;
        z *= 2.03;
        a *= 0.5;
    }

    return s;
}

// --- procedural textures -----------------------------------------------------
/**
 * Builds a tileable height field (fn(x, y) over integer pixels of an N×N
 * tile) and returns { normal, height } textures; normal is a tangent-space
 * normal map, height is a greyscale CanvasTexture (usable as roughness/AO).
 */
export function heightTextures(N, fn, strength = 2) {
    const h = new Float32Array(N * N);

    for (let y = 0; y < N; y++) {
        for (let x = 0; x < N; x++) {
            h[y * N + x] = fn(x, y);
        }
    }

    let min = Infinity;
    let max = -Infinity;

    for (const v of h) {
        min = Math.min(min, v);
        max = Math.max(max, v);
    }

    const nc = document.createElement('canvas');
    nc.width = nc.height = N;
    const hc = document.createElement('canvas');
    hc.width = hc.height = N;
    const nd = nc.getContext('2d').createImageData(N, N);
    const hd = hc.getContext('2d').createImageData(N, N);
    const at = (x, y) => h[((y + N) % N) * N + ((x + N) % N)];

    for (let y = 0; y < N; y++) {
        for (let x = 0; x < N; x++) {
            const dx = (at(x + 1, y) - at(x - 1, y)) * strength;
            const dy = (at(x, y + 1) - at(x, y - 1)) * strength;
            const l = Math.hypot(dx, dy, 1);
            const i = (y * N + x) * 4;
            nd.data[i] = ((-dx / l) * 0.5 + 0.5) * 255;
            nd.data[i + 1] = ((dy / l) * 0.5 + 0.5) * 255;
            nd.data[i + 2] = ((1 / l) * 0.5 + 0.5) * 255;
            nd.data[i + 3] = 255;
            const g = ((h[y * N + x] - min) / (max - min || 1)) * 255;
            hd.data[i] = hd.data[i + 1] = hd.data[i + 2] = g;
            hd.data[i + 3] = 255;
        }
    }

    nc.getContext('2d').putImageData(nd, 0, 0);
    hc.getContext('2d').putImageData(hd, 0, 0);
    const wrap = (c) => {
        const t = new THREE.CanvasTexture(c);
        t.wrapS = t.wrapT = THREE.RepeatWrapping;
        t.anisotropy = 16;
        t.colorSpace = THREE.NoColorSpace;

        return t;
    };

    return { normal: wrap(nc), height: wrap(hc) };
}

/** Ripstop nylon: fine plain weave with a raised double-thread grid. */
export function ripstop(N = 512, grid = 32) {
    const r = rng(3);
    const jitter = new Float32Array(N * N).map(() => r());

    return heightTextures(N, (x, y) => {
        const cx = Math.floor(x / 4);
        const cy = Math.floor(y / 4);
        const over = (cx + cy) & 1;
        const weave = over ? Math.sin((Math.PI * ((x % 4) + 0.5)) / 4) : Math.sin((Math.PI * ((y % 4) + 0.5)) / 4);
        const gx = x % grid;
        const gy = y % grid;
        const line = (g) => (g < 2 ? 0.5 : g < 4 ? 0.42 : 0);

        return weave * 0.35 + Math.max(line(gx), line(gy)) + jitter[y * N + x] * 0.12;
    }, 1.6);
}

/** Stockinette knit: interlocking V loops, columns along x. */
export function knit(N = 512, cols = 16, rows = 22) {
    const cw = N / cols;
    const rh = N / rows;
    const r = rng(11);
    const jitter = new Float32Array(N * N).map(() => r());

    return heightTextures(N, (x, y) => {
        const lx = (x % cw) / cw;
        const ly = (y % rh) / rh;
        const side = lx < 0.5 ? lx * 2 : (1 - lx) * 2; // 0 at column edge, 1 at centre
        const v = ly - side * 0.55;
        const loop = Math.max(0, Math.sin(Math.PI * (((v % 1) + 1) % 1)));
        const legs = Math.sin(Math.PI * (lx < 0.5 ? lx * 2 : lx * 2 - 1));

        return loop * 0.7 * Math.pow(legs, 0.6) + jitter[y * N + x] * 0.08;
    }, 3);
}

/** Lycra / compression jersey: very fine tricot ribs with soft noise. */
export function jersey(N = 512) {
    const r = rng(5);
    const jitter = new Float32Array(N * N).map(() => r());

    return heightTextures(N, (x, y) => {
        const rib = Math.sin((x / N) * Math.PI * 2 * 96 + Math.sin((y / N) * Math.PI * 2 * 128) * 0.6);
        const course = Math.sin((y / N) * Math.PI * 2 * 128);

        return rib * 0.5 + course * 0.2 + jitter[y * N + x] * 0.15;
    }, 1.2);
}

/** Hex stretch mesh: height field + alpha-like hole mask. */
export function hexMesh(N = 512, cells = 16) {
    const s = N / cells;
    const holeAt = (x, y) => {
        const qx = x / s;
        const qy = y / (s * 0.866);
        const row = Math.floor(qy);
        const off = row & 1 ? 0.5 : 0;
        const cx = Math.floor(qx - off) + off + 0.5;
        let best = 9;

        for (const [dx, dy] of [[0, 0], [1, 0], [-1, 0], [0.5, 1], [-0.5, 1], [0.5, -1], [-0.5, -1]]) {
            const hx = cx + dx;
            const hy = row + 0.5 + dy;
            best = Math.min(best, Math.hypot(qx - hx, (qy - hy) * 0.866));
        }

        return best;
    };

    return heightTextures(N, (x, y) => {
        const d = holeAt(x, y);

        return d < 0.3 ? -1 : Math.min(1, (d - 0.3) * 4);
    }, 1.2);
}

export function textureFrom(canvas, srgb = true) {
    const t = new THREE.CanvasTexture(canvas);
    t.colorSpace = srgb ? THREE.SRGBColorSpace : THREE.NoColorSpace;
    t.anisotropy = 16;

    return t;
}

export async function loadImage(url) {
    const img = new Image();
    img.src = url;
    await img.decode();

    return img;
}

/** The gold FL monogram (from public/images) as a padded canvas. */
export async function logoCanvas({ tone = 'gold', pad = 0.08, height = 512, color = null } = {}) {
    const img = await loadImage(`/public/images/brand/logo/logo-mark-${tone}.png`);
    const ratio = img.width / img.height;
    const c = document.createElement('canvas');
    c.height = height;
    c.width = Math.round(height * ratio);
    const g = c.getContext('2d');
    const p = height * pad;
    g.drawImage(img, p * ratio, p, c.width - 2 * p * ratio, c.height - 2 * p);

    if (color) {
        g.globalCompositeOperation = 'source-in';
        g.fillStyle = color;
        g.fillRect(0, 0, c.width, c.height);
    }

    return c;
}

// --- material patcher ----------------------------------------------------------
/**
 * Adds world-space triplanar normal mapping (whiteout blend), plus optional
 * GLSL hooks evaluated on the object-space position (vWPos; vWN is the
 * world normal — patched meshes are never rotated):
 *   colorFn:  vec3 flColor(vec3 p, vec3 base)
 *   heightFn: float flHeight(vec3 p)            (bump via screen derivatives)
 *   roughFn:  float flRough(vec3 p, float r)
 *   extra:    helper GLSL prepended to the hooks
 */
export function patch(material, { normalTex = null, scale = 100, strength = 1, colorFn = null, heightFn = null, roughFn = null, aoFn = null, sheenFn = null, extra = '', bump = 1 } = {}) {
    material.onBeforeCompile = (shader) => {
        shader.uniforms.flNormal = { value: normalTex };
        shader.uniforms.flScale = { value: scale };
        shader.uniforms.flStrength = { value: strength };
        shader.uniforms.flBump = { value: bump };
        shader.vertexShader = shader.vertexShader
            .replace('#include <common>', '#include <common>\nvarying vec3 vWPos;\nvarying vec3 vWN;')
            .replace('#include <worldpos_vertex>', '#include <worldpos_vertex>\nvWPos = transformed;\nvWN = normalize(mat3(modelMatrix) * objectNormal);');
        const defs = `
varying vec3 vWPos;
varying vec3 vWN;
uniform sampler2D flNormal;
uniform float flScale;
uniform float flStrength;
uniform float flBump;
${extra}
${colorFn ?? ''}
${heightFn ?? ''}
${roughFn ?? ''}
${aoFn ?? ''}
${sheenFn ?? ''}
vec3 flTriplanar(vec3 p, vec3 n) {
    vec3 b = pow(abs(n), vec3(4.0)); b /= dot(b, vec3(1.0));
    vec3 tx = texture2D(flNormal, p.zy * flScale).xyz * 2.0 - 1.0;
    vec3 ty = texture2D(flNormal, p.xz * flScale).xyz * 2.0 - 1.0;
    vec3 tz = texture2D(flNormal, p.xy * flScale).xyz * 2.0 - 1.0;
    tx.xy *= flStrength; ty.xy *= flStrength; tz.xy *= flStrength;
    tx = vec3(tx.xy + n.zy, abs(tx.z) * n.x);
    ty = vec3(ty.xy + n.xz, abs(ty.z) * n.y);
    tz = vec3(tz.xy + n.xy, abs(tz.z) * n.z);
    return normalize(tx.zyx * b.x + ty.xzy * b.y + tz.xyz * b.z);
}
vec3 flPerturb(vec3 surfPos, vec3 n, float h, float fd) {
    vec3 sx = dFdx(surfPos); vec3 sy = dFdy(surfPos);
    vec3 r1 = cross(sy, n); vec3 r2 = cross(n, sx);
    float det = dot(sx, r1) * fd;
    vec3 grad = sign(det) * (dFdx(h) * r1 + dFdy(h) * r2);
    return normalize(abs(det) * n - grad);
}
`;
        let fs = shader.fragmentShader.replace('#include <common>', `#include <common>\n${defs}`);

        if (colorFn) {
            fs = fs.replace('#include <color_fragment>', '#include <color_fragment>\ndiffuseColor.rgb = flColor(vWPos, diffuseColor.rgb);');
        }

        if (roughFn) {
            fs = fs.replace('#include <roughnessmap_fragment>', '#include <roughnessmap_fragment>\nroughnessFactor = flRough(vWPos, roughnessFactor);');
        }

        let normalCode = '';

        if (normalTex) {
            normalCode += `{ vec3 wn = normalize(vWN) * faceDirection; wn = flTriplanar(vWPos, wn); normal = normalize((viewMatrix * vec4(wn, 0.0)).xyz); }\n`;
        }

        if (heightFn) {
            normalCode += 'normal = flPerturb(-vViewPosition, normal, flHeight(vWPos) * flBump, faceDirection);\n';
        }

        if (sheenFn) {
            fs = fs.replace('#include <lights_physical_fragment>', '#include <lights_physical_fragment>\nmaterial.sheenColor *= flSheen(vWPos);');
        }

        if (aoFn) {
            fs = fs.replace('#include <aomap_fragment>', '#include <aomap_fragment>\n{ float flAo = flOcclusion(vWPos); reflectedLight.indirectDiffuse *= flAo; reflectedLight.indirectSpecular *= flAo; reflectedLight.directDiffuse *= mix(1.0, flAo, 0.6); reflectedLight.directSpecular *= mix(1.0, flAo, 0.6); }');
        }

        fs = fs.replace('#include <normal_fragment_maps>', `#include <normal_fragment_maps>\n${normalCode}`);
        shader.fragmentShader = fs;
    };
    material.customProgramCacheKey = () => `${Math.random()}`;

    return material;
}

export function fabric({ color = '#141414', roughness = 0.82, sheen = 1, sheenColor = '#4a4a4a', sheenRoughness = 0.55, ...rest } = {}) {
    return new THREE.MeshPhysicalMaterial({ color, roughness, metalness: 0, sheen, sheenColor: new THREE.Color(sheenColor), sheenRoughness, ...rest });
}

export function goldMetal({ roughness = 0.28, clearcoat = 0.6 } = {}) {
    return new THREE.MeshPhysicalMaterial({ color: '#D9B46A', metalness: 1, roughness, clearcoat, clearcoatRoughness: 0.15 });
}

// --- geometry helpers ---------------------------------------------------------
/** Sweeps a 2D cross-section (array of [x, y], closed) along a curve with stable frames. */
export function sweep(curve, section, { segments = 200, closed = false, twist = () => 0, scale = () => 1, up = null } = {}) {
    const frames = curve.computeFrenetFrames(segments, closed);
    const pos = [];
    const uv = [];
    const idx = [];
    const ns = section.length;
    const rows = segments + (closed ? 0 : 1);
    const upV = up ? new THREE.Vector3(...up) : null;

    for (let i = 0; i < rows; i++) {
        const t = i / segments;
        const p = curve.getPointAt(Math.min(t, 1));
        const T = frames.tangents[i];
        let N;
        let B;

        if (upV) {
            B = new THREE.Vector3().crossVectors(T, upV).normalize();
            N = new THREE.Vector3().crossVectors(B, T).normalize();
        } else {
            N = frames.normals[i];
            B = frames.binormals[i];
        }

        const a = twist(t);
        const ca = Math.cos(a);
        const sa = Math.sin(a);
        const sc = scale(t);

        for (let j = 0; j <= ns; j++) {
            const [sx0, sy0] = section[j % ns];
            const sx = (sx0 * ca - sy0 * sa) * (Array.isArray(sc) ? sc[0] : sc);
            const sy = (sx0 * sa + sy0 * ca) * (Array.isArray(sc) ? sc[1] : sc);
            pos.push(p.x + N.x * sx + B.x * sy, p.y + N.y * sx + B.y * sy, p.z + N.z * sx + B.z * sy);
            uv.push(j / ns, t);
        }
    }

    const ringCount = closed ? rows : rows - 1;

    for (let i = 0; i < ringCount; i++) {
        const i2 = (i + 1) % rows;

        for (let j = 0; j < ns; j++) {
            const a = i * (ns + 1) + j;
            const b = i2 * (ns + 1) + j;
            idx.push(a, b, a + 1, b, b + 1, a + 1);
        }
    }

    // keep faces pointing away from the curve whatever the section winding is
    {
        const c = curve.getPointAt(0);
        let score = 0;

        for (let t = 0; t < ns * 6; t += 3) {
            const [a, b, d] = [idx[t], idx[t + 1], idx[t + 2]];
            const A = new THREE.Vector3(pos[a * 3], pos[a * 3 + 1], pos[a * 3 + 2]);
            const B = new THREE.Vector3(pos[b * 3], pos[b * 3 + 1], pos[b * 3 + 2]).sub(A);
            const D = new THREE.Vector3(pos[d * 3], pos[d * 3 + 1], pos[d * 3 + 2]).sub(A);
            score += B.cross(D).dot(A.clone().sub(c));
        }

        if (score < 0) {
            for (let t = 0; t < idx.length; t += 3) {
                [idx[t + 1], idx[t + 2]] = [idx[t + 2], idx[t + 1]];
            }
        }
    }

    const g = new THREE.BufferGeometry();
    g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
    g.setAttribute('uv', new THREE.Float32BufferAttribute(uv, 2));
    g.setIndex(idx);
    g.computeVertexNormals();

    return g;
}

/** Rounded-rectangle cross-section (w × h, corner radius r). */
export function roundedRect(w, h, r, seg = 6) {
    const pts = [];
    const cs = [[w / 2 - r, h / 2 - r, 0], [-w / 2 + r, h / 2 - r, Math.PI / 2], [-w / 2 + r, -h / 2 + r, Math.PI], [w / 2 - r, -h / 2 + r, (3 * Math.PI) / 2]];

    for (const [cx, cy, a0] of cs) {
        for (let i = 0; i <= seg; i++) {
            const a = a0 + (i / seg) * (Math.PI / 2);
            pts.push([cx + Math.cos(a) * r, cy + Math.sin(a) * r]);
        }
    }

    return pts;
}

/** Rounded 2D shape for ExtrudeGeometry. */
export function roundedShape(w, h, r) {
    const s = new THREE.Shape();
    s.moveTo(-w / 2 + r, -h / 2);
    s.lineTo(w / 2 - r, -h / 2);
    s.quadraticCurveTo(w / 2, -h / 2, w / 2, -h / 2 + r);
    s.lineTo(w / 2, h / 2 - r);
    s.quadraticCurveTo(w / 2, h / 2, w / 2 - r, h / 2);
    s.lineTo(-w / 2 + r, h / 2);
    s.quadraticCurveTo(-w / 2, h / 2, -w / 2, h / 2 - r);
    s.lineTo(-w / 2, -h / 2 + r);
    s.quadraticCurveTo(-w / 2, -h / 2, -w / 2 + r, -h / 2);

    return s;
}

/** Parametric surface f(u, v) -> [x,y,z] on a (nu × nv) grid; UVs in [0,1]. */
export function parametric(f, nu, nv, { wrapU = false } = {}) {
    const pos = [];
    const uv = [];
    const idx = [];

    for (let j = 0; j <= nv; j++) {
        for (let i = 0; i <= nu; i++) {
            const p = f(i / nu, j / nv);
            pos.push(p[0], p[1], p[2]);
            uv.push(i / nu, j / nv);
        }
    }

    for (let j = 0; j < nv; j++) {
        for (let i = 0; i < nu; i++) {
            const a = j * (nu + 1) + i;
            const b = a + nu + 1;
            idx.push(a, b, a + 1, b, b + 1, a + 1);
        }
    }

    let g = new THREE.BufferGeometry();
    g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
    g.setAttribute('uv', new THREE.Float32BufferAttribute(uv, 2));
    g.setIndex(idx);

    if (wrapU) {
        // weld the seam so normals are continuous
        g.computeVertexNormals();
        const n = g.attributes.normal;

        for (let j = 0; j <= nv; j++) {
            const a = j * (nu + 1);
            const b = a + nu;
            const v = new THREE.Vector3(n.getX(a) + n.getX(b), n.getY(a) + n.getY(b), n.getZ(a) + n.getZ(b)).normalize();
            n.setXYZ(a, v.x, v.y, v.z);
            n.setXYZ(b, v.x, v.y, v.z);
        }
    } else {
        g.computeVertexNormals();
    }

    return g;
}

// --- SDF mesher (naive surface nets + projection) ----------------------------
export function sdfMesh(sdf, min, max, cell) {
    const nx = Math.ceil((max[0] - min[0]) / cell) + 1;
    const ny = Math.ceil((max[1] - min[1]) / cell) + 1;
    const nz = Math.ceil((max[2] - min[2]) / cell) + 1;
    const field = new Float32Array(nx * ny * nz);
    const I = (x, y, z) => (z * ny + y) * nx + x;

    for (let z = 0; z < nz; z++) {
        for (let y = 0; y < ny; y++) {
            for (let x = 0; x < nx; x++) {
                field[I(x, y, z)] = sdf(min[0] + x * cell, min[1] + y * cell, min[2] + z * cell);
            }
        }
    }

    const vid = new Int32Array(nx * ny * nz).fill(-1);
    const pos = [];
    const corners = [[0, 0, 0], [1, 0, 0], [0, 1, 0], [1, 1, 0], [0, 0, 1], [1, 0, 1], [0, 1, 1], [1, 1, 1]];
    const edges = [[0, 1], [2, 3], [4, 5], [6, 7], [0, 2], [1, 3], [4, 6], [5, 7], [0, 4], [1, 5], [2, 6], [3, 7]];
    const vals = new Float32Array(8);

    for (let z = 0; z < nz - 1; z++) {
        for (let y = 0; y < ny - 1; y++) {
            for (let x = 0; x < nx - 1; x++) {
                let mask = 0;

                for (let c = 0; c < 8; c++) {
                    vals[c] = field[I(x + corners[c][0], y + corners[c][1], z + corners[c][2])];

                    if (vals[c] < 0) {
                        mask |= 1 << c;
                    }
                }

                if (mask === 0 || mask === 255) {
                    continue;
                }

                let sx = 0;
                let sy = 0;
                let sz = 0;
                let n = 0;

                for (const [a, b] of edges) {
                    if ((vals[a] < 0) !== (vals[b] < 0)) {
                        const t = vals[a] / (vals[a] - vals[b]);
                        sx += corners[a][0] + t * (corners[b][0] - corners[a][0]);
                        sy += corners[a][1] + t * (corners[b][1] - corners[a][1]);
                        sz += corners[a][2] + t * (corners[b][2] - corners[a][2]);
                        n++;
                    }
                }

                vid[I(x, y, z)] = pos.length / 3;
                pos.push(min[0] + (x + sx / n) * cell, min[1] + (y + sy / n) * cell, min[2] + (z + sz / n) * cell);
            }
        }
    }

    const idx = [];
    const quad = (a, b, c, d, flip) => {
        if (a < 0 || b < 0 || c < 0 || d < 0) {
            return;
        }

        if (flip) {
            idx.push(a, c, b, a, d, c);
        } else {
            idx.push(a, b, c, a, c, d);
        }
    };

    for (let z = 1; z < nz - 1; z++) {
        for (let y = 1; y < ny - 1; y++) {
            for (let x = 1; x < nx - 1; x++) {
                const inside = field[I(x, y, z)] < 0;

                if (inside !== field[I(x + 1, y, z)] < 0) {
                    quad(vid[I(x, y - 1, z - 1)], vid[I(x, y, z - 1)], vid[I(x, y, z)], vid[I(x, y - 1, z)], !inside);
                }

                if (inside !== field[I(x, y + 1, z)] < 0) {
                    quad(vid[I(x - 1, y, z - 1)], vid[I(x - 1, y, z)], vid[I(x, y, z)], vid[I(x, y, z - 1)], !inside);
                }

                if (inside !== field[I(x, y, z + 1)] < 0) {
                    quad(vid[I(x - 1, y - 1, z)], vid[I(x, y - 1, z)], vid[I(x, y, z)], vid[I(x - 1, y, z)], !inside);
                }
            }
        }
    }

    // project vertices onto the exact surface and take normals from the gradient
    const e = cell * 0.25;
    const normals = new Float32Array(pos.length);

    for (let i = 0; i < pos.length; i += 3) {
        let [x, y, z] = [pos[i], pos[i + 1], pos[i + 2]];
        let gx = 0;
        let gy = 0;
        let gz = 0;

        for (let k = 0; k < 4; k++) {
            const d = sdf(x, y, z);
            gx = sdf(x + e, y, z) - sdf(x - e, y, z);
            gy = sdf(x, y + e, z) - sdf(x, y - e, z);
            gz = sdf(x, y, z + e) - sdf(x, y, z - e);
            const l2 = (gx * gx + gy * gy + gz * gz) / (4 * e * e) || 1;
            const step = d / l2 / (2 * e);

            if (k < 3) {
                const m = Math.min(1, cell / (Math.abs(step) * Math.hypot(gx, gy, gz) + 1e-12));
                x -= step * gx * m;
                y -= step * gy * m;
                z -= step * gz * m;
            }
        }

        const l = Math.hypot(gx, gy, gz) || 1;
        pos[i] = x;
        pos[i + 1] = y;
        pos[i + 2] = z;
        normals[i] = gx / l;
        normals[i + 1] = gy / l;
        normals[i + 2] = gz / l;
    }

    const g = new THREE.BufferGeometry();
    g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
    g.setAttribute('normal', new THREE.BufferAttribute(normals, 3));
    g.setIndex(idx);

    // make winding agree with the gradient normals
    const ix = g.index.array;
    const p = g.attributes.position.array;
    const A = new THREE.Vector3();
    const B = new THREE.Vector3();
    const C = new THREE.Vector3();

    for (let t = 0; t < ix.length; t += 3) {
        A.fromArray(p, ix[t] * 3);
        B.fromArray(p, ix[t + 1] * 3).sub(A);
        C.fromArray(p, ix[t + 2] * 3).sub(A);
        const fn = B.cross(C);
        const nn = normals[ix[t] * 3] * fn.x + normals[ix[t] * 3 + 1] * fn.y + normals[ix[t] * 3 + 2] * fn.z;

        if (nn < 0) {
            const tmp = ix[t + 1];
            ix[t + 1] = ix[t + 2];
            ix[t + 2] = tmp;
        }
    }

    return g;
}

export const smin = (a, b, k) => {
    const h = Math.max(k - Math.abs(a - b), 0) / k;

    return Math.min(a, b) - h * h * k * 0.25;
};

export const smax = (a, b, k) => -smin(-a, -b, k);

// --- studio -------------------------------------------------------------------
function studioEnvironment(renderer, { key = 7, rim = 9, fill = 1.6, top = 3 } = {}) {
    const env = new THREE.Scene();
    const room = new THREE.SphereGeometry(20, 64, 32);
    const col = [];
    const p = room.attributes.position;

    for (let i = 0; i < p.count; i++) {
        const y = p.getY(i) / 20;
        const v = y > 0 ? 0.42 + y * 0.25 : 0.42 + y * 0.3;
        col.push(v * 1.0, v * 0.98, v * 0.94);
    }

    room.setAttribute('color', new THREE.Float32BufferAttribute(col, 3));
    env.add(new THREE.Mesh(room, new THREE.MeshBasicMaterial({ side: THREE.BackSide, vertexColors: true })));
    const box = (w, h, pos, intensity, tint = [1, 1, 1]) => {
        const m = new THREE.Mesh(new THREE.PlaneGeometry(w, h), new THREE.MeshBasicMaterial({ color: new THREE.Color(tint[0] * intensity, tint[1] * intensity, tint[2] * intensity), side: THREE.DoubleSide }));
        m.position.set(...pos);
        m.lookAt(0, 0.5, 0);
        env.add(m);
    };
    box(7, 9, [-9, 7, 9], key, [1, 0.98, 0.95]); // big key softbox, front-left high
    box(3, 12, [11, 4, -6], rim, [1, 0.97, 0.92]); // right rim strip
    box(3, 12, [-11, 4, -7], rim * 0.7, [0.95, 0.97, 1]); // left rim strip
    box(6, 10, [10, 3, 8], fill, [1, 1, 1]); // fill card
    box(10, 10, [0, 16, 0], top, [1, 1, 1]); // overhead
    const pmrem = new THREE.PMREMGenerator(renderer);
    const tex = pmrem.fromScene(env, 0.035).texture;
    pmrem.dispose();

    return tex;
}

class ContactShadow {
    constructor(renderer, size, { darkness = 1.4, blur = 3.2, opacity = 0.6, res = 1024, height = 0.5 } = {}) {
        this.renderer = renderer;
        this.group = new THREE.Group();
        this.rt = new THREE.WebGLRenderTarget(res, res);
        this.rtBlur = new THREE.WebGLRenderTarget(res, res);
        this.rt.texture.generateMipmaps = this.rtBlur.texture.generateMipmaps = false;
        const geo = new THREE.PlaneGeometry(size, size).rotateX(Math.PI / 2);
        this.plane = new THREE.Mesh(geo, new THREE.MeshBasicMaterial({ map: this.rt.texture, opacity, transparent: true, depthWrite: false, toneMapped: false }));
        this.plane.renderOrder = 1;
        this.plane.scale.y = -1;
        this.blurPlane = new THREE.Mesh(geo);
        this.blurPlane.visible = false;
        this.group.add(this.plane, this.blurPlane);
        this.cam = new THREE.OrthographicCamera(-size / 2, size / 2, size / 2, -size / 2, 0, height);
        this.cam.rotation.x = Math.PI / 2;
        this.group.add(this.cam);
        this.depth = new THREE.MeshDepthMaterial();
        this.depth.onBeforeCompile = (shader) => {
            shader.uniforms.darkness = { value: darkness };
            shader.fragmentShader = `uniform float darkness;\n${shader.fragmentShader.replace('gl_FragColor = vec4( vec3( 1.0 - fragCoordZ ), opacity );', 'gl_FragColor = vec4( vec3( 0.0 ), pow( 1.0 - fragCoordZ, 1.6 ) * darkness );')}`;
        };
        this.depth.depthTest = this.depth.depthWrite = false;
        this.depth.side = THREE.DoubleSide;
        this.h = new THREE.ShaderMaterial(HorizontalBlurShader);
        this.v = new THREE.ShaderMaterial(VerticalBlurShader);
        this.h.depthTest = this.v.depthTest = false;
        this.blur = blur;
        this.res = res;
    }

    blurOnce(amount) {
        const r = this.renderer;
        this.blurPlane.visible = true;
        this.blurPlane.material = this.h;
        this.h.uniforms.tDiffuse.value = this.rt.texture;
        this.h.uniforms.h.value = amount / this.res;
        r.setRenderTarget(this.rtBlur);
        r.render(this.blurPlane, this.cam);
        this.blurPlane.material = this.v;
        this.v.uniforms.tDiffuse.value = this.rtBlur.texture;
        this.v.uniforms.v.value = amount / this.res;
        r.setRenderTarget(this.rt);
        r.render(this.blurPlane, this.cam);
        this.blurPlane.visible = false;
    }

    update(scene) {
        const r = this.renderer;
        const hidden = [];
        scene.traverse((o) => {
            if (o.userData.noContactShadow && o.visible) {
                o.visible = false;
                hidden.push(o);
            }
        });
        this.plane.visible = false;
        scene.overrideMaterial = this.depth;
        const clear = r.getClearAlpha();
        r.setClearAlpha(0);
        r.setRenderTarget(this.rt);
        r.clear();
        r.render(scene, this.cam);
        scene.overrideMaterial = null;
        // several widening passes ≈ a soft, physically plausible falloff
        this.blurOnce(this.blur);
        this.blurOnce(this.blur * 0.6);
        this.blurOnce(this.blur * 2);
        this.blurOnce(this.blur * 0.4);
        r.setRenderTarget(null);
        r.setClearAlpha(clear);
        this.plane.visible = true;
        hidden.forEach((o) => (o.visible = true));
    }
}

function frame(camera, object, { azimuth, elevation, fov, fill = 0.8, offsetY = 0.03, aspect, focus = null }) {
    camera.fov = fov;
    camera.aspect = aspect;
    camera.updateProjectionMatrix();
    const pts = [];
    object.updateMatrixWorld(true);
    focus?.updateMatrixWorld(true);
    (focus ?? object).traverse((o) => {
        if (!o.isMesh || !o.visible || o.userData.noFrame) {
            return;
        }

        const p = o.geometry.attributes.position;
        const step = Math.max(1, Math.floor(p.count / 6000));

        for (let i = 0; i < p.count; i += step) {
            pts.push(new THREE.Vector3().fromBufferAttribute(p, i).applyMatrix4(o.matrixWorld));
        }
    });
    const box = new THREE.Box3().setFromPoints(pts);
    const target = box.getCenter(new THREE.Vector3());
    const dir = new THREE.Vector3(Math.sin(azimuth) * Math.cos(elevation), Math.sin(elevation), Math.cos(azimuth) * Math.cos(elevation));
    let dist = box.getSize(new THREE.Vector3()).length() * 2;

    for (let it = 0; it < 12; it++) {
        camera.position.copy(target).addScaledVector(dir, dist);
        camera.lookAt(target);
        camera.updateMatrixWorld();
        let x0 = Infinity;
        let x1 = -Infinity;
        let y0 = Infinity;
        let y1 = -Infinity;

        for (const p of pts) {
            const q = p.clone().project(camera);
            x0 = Math.min(x0, q.x);
            x1 = Math.max(x1, q.x);
            y0 = Math.min(y0, q.y);
            y1 = Math.max(y1, q.y);
        }

        // recentre: move target along camera right/up by the projected offset
        const right = new THREE.Vector3().setFromMatrixColumn(camera.matrixWorld, 0);
        const up = new THREE.Vector3().setFromMatrixColumn(camera.matrixWorld, 1);
        const halfH = Math.tan(THREE.MathUtils.degToRad(fov / 2)) * dist;
        const halfW = halfH * aspect;
        target.addScaledVector(right, ((x0 + x1) / 2) * halfW).addScaledVector(up, ((y0 + y1) / 2 + offsetY) * halfH);
        const ext = Math.max((x1 - x0) / 2 / fill, (y1 - y0) / 2 / fill);
        dist *= 0.5 + 0.5 * ext;
    }

    camera.position.copy(target).addScaledVector(dir, dist);
    camera.lookAt(target);
}

export async function renderProduct(mod, angle, width, height) {
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });
    renderer.setPixelRatio(1);
    renderer.setSize(width, height, false);
    renderer.setClearColor(0x000000, 0);
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.0;
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.VSMShadowMap;
    document.body.appendChild(renderer.domElement);

    const lib = await import('./lib.js');
    const { object, view, studio = {} } = await mod.build(lib, angle);
    const scene = new THREE.Scene();
    scene.environment = studioEnvironment(renderer, studio.env);
    scene.environmentIntensity = studio.envIntensity ?? 1;
    scene.add(object);

    // drop to the floor
    object.updateMatrixWorld(true);
    const bb = new THREE.Box3().setFromObject(object, true);
    object.position.y -= bb.min.y - (studio.float ?? 0);
    object.updateMatrixWorld(true);
    const size = new THREE.Box3().setFromObject(object, true).getSize(new THREE.Vector3());
    const span = Math.max(size.x, size.z, size.y * 0.6);

    object.traverse((o) => {
        if (o.isMesh) {
            o.castShadow = o.userData.castShadow ?? true;
            o.receiveShadow = o.userData.receiveShadow ?? true;
        }
    });

    // key light (soft VSM shadows) + rim
    const key = new THREE.DirectionalLight(0xfff6ea, studio.key ?? 1.6);
    const keyDir = new THREE.Vector3(...(studio.keyDir ?? [-0.8, 1.6, 1.1])).normalize();
    key.position.copy(keyDir.multiplyScalar(span * 4));
    key.castShadow = true;
    key.shadow.mapSize.set(4096, 4096);
    key.shadow.radius = studio.shadowRadius ?? 18;
    key.shadow.blurSamples = 25;
    key.shadow.bias = -0.0004;
    key.shadow.normalBias = 0.002;
    const sc = key.shadow.camera;
    sc.left = sc.bottom = -span * 1.6;
    sc.right = sc.top = span * 1.6;
    sc.near = span;
    sc.far = span * 9;
    scene.add(key);
    const rim = new THREE.DirectionalLight(0xf2ecff, studio.rim ?? 1.2);
    rim.position.set(...(studio.rimDir ?? [1.2, 1.0, -1.4]).map((v) => v * span * 4));
    scene.add(rim);

    // floor: soft cast shadow + tight contact shadow, both transparent
    const floor = new THREE.Mesh(new THREE.PlaneGeometry(span * 12, span * 12).rotateX(-Math.PI / 2), new THREE.ShadowMaterial({ opacity: studio.floorShadow ?? 0.16, color: 0x3a2f20 }));
    floor.receiveShadow = true;
    floor.castShadow = false;
    floor.userData.noFrame = true;
    floor.userData.noContactShadow = true;
    scene.add(floor);
    const contact = new ContactShadow(renderer, span * 2.6, { height: Math.max(size.y * 0.35, 0.05) + (studio.float ?? 0), blur: studio.contactBlur ?? 2.4, opacity: studio.contactOpacity ?? 0.75, darkness: 1.5 });
    contact.group.position.y = 0.0005;
    scene.add(contact.group);
    contact.update(scene);

    const camera = new THREE.PerspectiveCamera(view.fov ?? 24, width / height, 0.01, 100);
    frame(camera, object, { aspect: width / height, ...view });

    if (mod.afterFrame) {
        mod.afterFrame(lib, { scene, camera, object, angle });
    }

    renderer.render(scene, camera);

    // composite over the paper backdrop
    const out = document.createElement('canvas');
    out.width = width;
    out.height = height;
    const g = out.getContext('2d');
    const lin = g.createLinearGradient(0, 0, 0, height);
    lin.addColorStop(0, PAPER_TOP);
    lin.addColorStop(1, PAPER_BOTTOM);
    g.fillStyle = lin;
    g.fillRect(0, 0, width, height);
    const rad = g.createRadialGradient(width * 0.5, height * 0.42, 0, width * 0.5, height * 0.42, height * 0.62);
    rad.addColorStop(0, 'rgba(255,253,248,0.55)');
    rad.addColorStop(1, 'rgba(255,253,248,0)');
    g.fillStyle = rad;
    g.fillRect(0, 0, width, height);
    const vig = g.createRadialGradient(width * 0.5, height * 0.5, height * 0.35, width * 0.5, height * 0.5, height * 0.85);
    vig.addColorStop(0, 'rgba(120,100,70,0)');
    vig.addColorStop(1, 'rgba(120,100,70,0.07)');
    g.fillStyle = vig;
    g.fillRect(0, 0, width, height);
    g.drawImage(renderer.domElement, 0, 0);

    // fine grain so gradients don't band after WebP encoding
    const img = g.getImageData(0, 0, width, height);
    const r = rng(99);

    for (let i = 0; i < img.data.length; i += 4) {
        const n = (r() - 0.5) * 3;
        img.data[i] += n;
        img.data[i + 1] += n;
        img.data[i + 2] += n;
    }

    g.putImageData(img, 0, 0);

    return out.toDataURL('image/png');
}

/**
 * Projects 2D points onto a mesh along an axis ('x' | 'z', sign ±1 = side the
 * ray comes from). Returns [{ p, n }] in world space; misses are dropped.
 */
export function projectPoints(mesh, pts, axis = 'z', sign = 1) {
    const ray = new THREE.Raycaster();
    const out = [];
    mesh.updateMatrixWorld(true);

    for (const [a, b] of pts) {
        const origin = axis === 'z' ? new THREE.Vector3(a, b, sign * 5) : new THREE.Vector3(sign * 5, b, a);
        const dir = axis === 'z' ? new THREE.Vector3(0, 0, -sign) : new THREE.Vector3(-sign, 0, 0);
        ray.set(origin, dir);
        const hit = ray.intersectObject(mesh, false)[0];

        if (hit) {
            out.push({ p: hit.point.clone(), n: hit.face.normal.clone().transformDirection(mesh.matrixWorld) });
        }
    }

    return out;
}

/** Projects a 2D polyline and returns a smooth curve offset along the normals. */
export function projectCurve(mesh, pts, axis, sign, offset = 0.002, closed = false) {
    const hits = projectPoints(mesh, pts, axis, sign);

    return new THREE.CatmullRomCurve3(hits.map(({ p, n }) => p.addScaledVector(n, offset)), closed, 'centripetal');
}

/** Rounded rectangle sampled evenly along its perimeter (so u ∝ arc length). */
export function roundedRectEven(w, h, r, step = 0.001) {
    const dense = roundedRect(w, h, r, 24);
    const lens = [0];

    for (let i = 1; i <= dense.length; i++) {
        const a = dense[i - 1];
        const b = dense[i % dense.length];
        lens.push(lens[i - 1] + Math.hypot(b[0] - a[0], b[1] - a[1]));
    }

    const total = lens[lens.length - 1];
    const n = Math.max(8, Math.round(total / step));
    const out = [];
    let k = 0;

    for (let i = 0; i < n; i++) {
        const s = (i / n) * total;

        while (lens[k + 1] < s) {
            k++;
        }

        const t = (s - lens[k]) / (lens[k + 1] - lens[k] || 1);
        const a = dense[k];
        const b = dense[(k + 1) % dense.length];
        out.push([a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t]);
    }

    return out;
}
