/*! Kekeli Motion. Moteur d'animations en SVG de Kekeli (Lomé IA School).
 * Contient du code repris de line-art-animation (https://github.com/JDhruv14/line-art-animation),
 * licence MIT, Copyright (c) 2026 JDhruv14 : voir LICENCES.md. Fichier fabriqué, ne pas modifier à la main :
 * les sources TypeScript sont dans le dossier src/. */
const __modules = {};
__modules["lib/ink"] = (() => {
// Repris de line-art-animation (https://github.com/JDhruv14/line-art-animation, licence MIT, Copyright (c) 2026 JDhruv14),
// version du dépôt au commit c43d698, adapté pour Kekeli Motion (voir MENTIONS.md).
// ink.ts — pen, paper and the drawing primitives every line-art figure shares.
//
// Conventions
//  - Draw each figure in its own space: ~95 units tall, feet on y = 120, facing +x,
//    standing on x = 40. Place it in a scene with `place()`.
//  - Ink is `currentColor`; paper fills get class "pp". Set `color` and `--paper`
//    on the <svg> (with `.pp { fill: var(--paper) }` in CSS) to retint everything,
//    e.g. pale ink on indigo paper at night.
//  - Create nodes once, then update `d`/attributes each frame. Nothing here allocates DOM per frame.
/** A point produced by the library. */
/** Any point you pass in: a tuple or a plain [x, y] array. */
const NS = 'http://www.w3.org/2000/svg';
const INK = 'currentColor';
const PAPER = '#fdfdfc';
const INK_DAY = '#1c1c1c';
/** Pen width in figure units: ≈1.4 % of a 95-unit figure. */
const PEN = 1.35;
function el                                      (tag   , attrs        = {}, parent                 )                          {
  const n = document.createElementNS(NS, tag);
  for (const k in attrs) n.setAttribute(k, String(attrs[k]));
  if (attrs.fill === PAPER) n.classList.add('pp');
  if (parent) parent.appendChild(n);
  return n;
}
const f = (n        )         => n.toFixed(2);
const pt = (p                   )         => `${f(p[0])} ${f(p[1])}`;
/** Open pen stroke. */
const pen = (parent         , w = PEN)                 =>
  el('path', { fill: 'none', stroke: INK, 'stroke-width': w, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }, parent);
/** Closed outline filled with paper — hides what is behind it (coats, heads, walls, roofs). */
const shape = (parent         , w = PEN)                 =>
  el('path', { fill: PAPER, stroke: INK, 'stroke-width': w, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }, parent);
/** Solid ink: shoes, hair, feet, small dark details. */
const blot = (parent         )                 => el('path', { fill: INK }, parent);
/** Closed outline with a flat colour fill (clothes, props). Re-set `fill` per frame if it should darken at night. */
const paint = (parent         , colour        , w = PEN)                 =>
  el('path', { fill: colour, stroke: INK, 'stroke-width': w, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }, parent);
/**
 * A limb drawn as two separate pen lines either side of a soft curve through the
 * joints, open at the ends, paper between them. This open double stroke is the
 * single most recognisable trait of the style — never draw limbs as closed tubes.
 */
function limb(parent         , width        , w = PEN)       {
  const g = el('g', {}, parent);
  const fill = el('path', { fill: PAPER }, g);
  const a = pen(g, w), b = pen(g, w);
  const half = (width - w) / 2;
  return {
    g,
    draw(joints, endGap = [0.4, 0.9]) {
      const s = joints[0], e = joints[joints.length - 1];
      let c      = [(s[0] + e[0]) / 2, (s[1] + e[1]) / 2];
      if (joints.length === 3) { const m = joints[1]; c = [m[0] + 0.55 * (m[0] - c[0]), m[1] + 0.55 * (m[1] - c[1])]; }
      const P        = [];
      for (let i = 0; i <= 16; i++) {
        const t = i / 16, u = 1 - t;
        P.push([u * u * s[0] + 2 * u * t * c[0] + t * t * e[0], u * u * s[1] + 2 * u * t * c[1] + t * t * e[1]]);
      }
      const side = (sgn        )        => P.map((p, i) => {
        const q = P[Math.min(P.length - 1, i + 1)], o = P[Math.max(0, i - 1)];
        const dx = q[0] - o[0], dy = q[1] - o[1], l = Math.hypot(dx, dy) || 1;
        return [p[0] - (dy / l) * half * sgn, p[1] + (dx / l) * half * sgn];
      });
      const L = side(1), R = side(-1);
      fill.setAttribute('d', 'M' + L.map(pt).join('L') + 'L' + R.slice().reverse().map(pt).join('L') + 'Z');
      const cut = (arr       , gap        ) => arr.slice(0, arr.length - Math.round(gap * 2));
      a.setAttribute('d', 'M' + cut(L, endGap[0]).map(pt).join('L'));
      b.setAttribute('d', 'M' + cut(R, endGap[1]).map(pt).join('L'));
    },
  };
}
/** Give a limb a solid fill (black trousers, sleeves). Drops the paper class so CSS can't override it. */
function solid(l      , colour = INK)       {
  const n = l.g.querySelector('path');
  if (n) { n.classList.remove('pp'); n.setAttribute('fill', colour); }
  return l;
}
/** Two-bone IK. bend = +1 puts the middle joint toward +x (knees), -1 toward -x. */
function joint(a    , b    , l1        , l2        , bend         = 1)      {
  const dx = b[0] - a[0], dy = b[1] - a[1];
  const d = Math.min(Math.hypot(dx, dy), l1 + l2 - 0.01);
  const th = Math.atan2(dy, dx);
  const al = Math.acos(Math.max(-1, Math.min(1, (l1 * l1 + d * d - l2 * l2) / (2 * l1 * d))));
  const c1      = [a[0] + l1 * Math.cos(th - al), a[1] + l1 * Math.sin(th - al)];
  const c2      = [a[0] + l1 * Math.cos(th + al), a[1] + l1 * Math.sin(th + al)];
  return (c1[0] - c2[0]) * bend > 0 ? c1 : c2;
}
/** Point `len` away at `deg` from straight down (forward = positive). */
const hang = (from    , len        , deg        )      => {
  const r = (deg * Math.PI) / 180;
  return [from[0] + len * Math.sin(r), from[1] + len * Math.cos(r)];
};
/** Periodic Catmull-Rom through [time, value] keys — the place for keyed or measured poses. */
function curve(keys               , period        )                        {
  return (x) => {
    x = ((x % period) + period) % period;
    const n = keys.length;
    let i = n - 1;
    for (let j = 0; j < n; j++) if (keys[j][0] <= x) i = j;
    const k = (j        )      => { const w = Math.floor(j / n); const e = keys[((j % n) + n) % n]; return [e[0] + w * period, e[1]]; };
    const p0 = k(i - 1), p1 = k(i), p2 = k(i + 1), p3 = k(i + 2);
    const span = p2[0] - p1[0];
    const u = (x - p1[0]) / span;
    const m1 = ((p2[1] - p0[1]) / (p2[0] - p0[0])) * span;
    const m2 = ((p3[1] - p1[1]) / (p3[0] - p1[0])) * span;
    const u2 = u * u, u3 = u2 * u;
    return (2 * u3 - 3 * u2 + 1) * p1[1] + (u3 - 2 * u2 + u) * m1 + (-2 * u3 + 3 * u2) * p2[1] + (u3 - u2) * m2;
  };
}
/** Shoe: a fat ink wedge at the ankle, pitched by `toe` degrees (toe down positive). */
function shoe(node                , ankle    , toe = 0)       {
  const r = (toe * Math.PI) / 180, c = Math.cos(r), s = Math.sin(r);
  const S = (x        , y        )      => [ankle[0] + x * c - y * s, ankle[1] + x * s + y * c];
  node.setAttribute('d', `M${pt(S(-1.6, -0.6))} L${pt(S(4.6, -0.2))} Q${pt(S(6.6, 0.4))} ${pt(S(5.6, 1.6))} L${pt(S(-1.4, 1.5))} Q${pt(S(-2.2, 0.5))} ${pt(S(-1.6, -0.6))}Z`);
}
/** Place a figure group: its local (40, 120) lands on scene (x, y); dir ±1 mirrors it; s scales it. */
const place = (g             , x        , y        , dir         = 1, s = 0.5)       =>
  g.setAttribute('transform', `translate(${f(x)} ${f(y)}) scale(${f(dir * s)} ${f(s)}) translate(-40 -120)`);
/** Scene coordinates of a figure-local point (for glows or effects drawn outside the figure). */
const toScene = (x        , y        , dir        , lx        , ly        , s = 0.5)      => [x + dir * s * (lx - 40), y + s * (ly - 120)];
/** Wobble filter (so lines read as ink, not vector) and a stripe hatch for striped clothes. */
function inkDefs(svg               , id = 'ink')          {
  if (!svg.style.color) svg.style.color = INK_DAY;
  const defs = el('defs', {}, svg);
  const flt = el('filter', { id: `${id}-edge`, x: '-2%', y: '-2%', width: '104%', height: '104%' }, defs);
  el('feTurbulence', { type: 'fractalNoise', baseFrequency: '0.6', numOctaves: 1, seed: 7, result: 'n' }, flt);
  el('feDisplacementMap', { in: 'SourceGraphic', in2: 'n', scale: 0.45, xChannelSelector: 'R', yChannelSelector: 'G', result: 'd' }, flt);
  el('feGaussianBlur', { in: 'd', stdDeviation: 0.12 }, flt);
  const stripes = el('pattern', { id: `${id}-stripes`, width: 4, height: 2.2, patternUnits: 'userSpaceOnUse', patternTransform: 'rotate(-12)' }, defs);
  el('rect', { width: 4, height: 2.2, fill: PAPER }, stripes);
  el('rect', { width: 4, height: 1.0, fill: INK }, stripes);
  return { defs, filter: `url(#${id}-edge)`, stripes: `url(#${id}-stripes)` };
}
/**
 * Animation loop calling draw(t, dt) with t and dt in seconds.
 *  ?t=1.25  freeze at a time (check a pose)
 *  ?debug   no rAF; exposes window.__step(seconds) to advance in exact 1/60 s steps —
 *           for headless checks and frame capture (background tabs throttle rAF)
 */
function run(draw                                 )             {
  const q = new URLSearchParams(location.search);
  if (q.has('debug')) {
    let t = 0;
    draw(t, 1 / 60);
    window.__step = (s = 1) => { for (let i = 0, n = Math.round(s * 60); i < n; i++) draw((t += 1 / 60), 1 / 60); return t; };
    return () => {};
  }
  const frozen = q.has('t') ? Number(q.get('t')) : null;
  const still = frozen !== null || matchMedia('(prefers-reduced-motion: reduce)').matches;
  const t0 = performance.now();
  let id = 0, prev = 0;
  const tick = (now        ) => {
    const t = frozen ?? (now - t0) / 1000;
    draw(t, Math.min(0.05, t - prev)); prev = t;
    if (!still) id = requestAnimationFrame(tick);
  };
  id = requestAnimationFrame(tick);
  return () => cancelAnimationFrame(id);
}
return { NS, INK, PAPER, INK_DAY, PEN, el, f, pt, pen, shape, blot, paint, limb, solid, joint, hang, curve, shoe, place, toScene, inkDefs, run };
})();
__modules["lib/gait"] = (() => {
// Repris de line-art-animation (https://github.com/JDhruv14/line-art-animation, licence MIT, Copyright (c) 2026 JDhruv14),
// version du dépôt au commit c43d698, adapté pour Kekeli Motion (voir MENTIONS.md).
// gait.ts — walk and run solvers for a figure drawn in ink.ts figure space
// (feet on y = 120, facing +x, hip above x = 40).
//
// A believable gait comes from four rules, all built in here:
//  1. The planted foot slides back at exactly the travel speed (`speed`), so it
//     stays still on the ground while the figure moves by `speed`.
//  2. The swinging foot follows a minimum-jerk path: low for walking, with a heel
//     kick for running.
//  3. The heel peels off before the swing: the foot pivots on its toe.
//  4. Hip height comes from what the planted legs can reach, so a walker rises over
//     each step and dips when both feet are down. A runner compresses over the
//     planted foot and is highest in flight. No sine bobbing.
//
//   const walk = gait({ kind: 'walk' });
//   phase += dt / walk.period;  x += dir * walk.speed * scale * dt;
//   const p = walk.pose(phase);  // hip, feet[0..1] { ankle, knee, pitch, stance }, front, bob
const clamp = (x        , a        , b        ) => Math.max(a, Math.min(b, x));
const smooth = (e0        , e1        , x        ) => { const t = clamp((x - e0) / (e1 - e0), 0, 1); return t * t * (3 - 2 * t); };
const minJerk = (s        ) => s * s * s * (10 - 15 * s + 6 * s * s);
function knee(hip     , ankle     , l1        , l2        )      {
  const dx = ankle[0] - hip[0], dy = ankle[1] - hip[1];
  const d = Math.min(Math.hypot(dx, dy), l1 + l2 - 0.01);
  const th = Math.atan2(dy, dx);
  const al = Math.acos(clamp((l1 * l1 + d * d - l2 * l2) / (2 * l1 * d), -1, 1));
  const c1      = [hip[0] + l1 * Math.cos(th - al), hip[1] + l1 * Math.sin(th - al)];
  const c2      = [hip[0] + l1 * Math.cos(th + al), hip[1] + l1 * Math.sin(th + al)];
  return c1[0] > c2[0] ? c1 : c2; // knees bend forward
}
const GAIT_PRESETS                                                        = {
  // natural adult walk, ~2 steps/s
  walk: { period: 1.0, duty: 0.62, stride: 24, lift: 3.4, heelOff: 15, heelKick: 0, thigh: 23, shin: 23, ankleY: 118.4, ground: 120, toe: 4.2 },
  // kimono, tight skirt, geta: shorter stride, very low lift
  shuffle: { period: 0.9, duty: 0.64, stride: 18, lift: 2.4, heelOff: 12, heelKick: 0, thigh: 23, shin: 23, ankleY: 116, ground: 120, toe: 4.2 },
  // child's or adult's run: short ground contact, flight phase, heel kick
  run: { period: 0.5, duty: 0.36, stride: 16, lift: 12, heelOff: 10, heelKick: 3.5, thigh: 13, shin: 13, ankleY: 118, ground: 120, toe: 3 },
};
function gait(opts              = {})       {
  const kind = opts.kind ?? 'walk';
  const o = { ...GAIT_PRESETS[kind], ...opts, kind };
  const { period, duty, stride: S, lift, heelOff, heelKick, thigh, shin, ankleY, ground, toe } = o;
  const reach = (thigh + shin) * 0.983; // a planted leg is nearly, not perfectly, straight
  const restHip = ankleY - reach;
  const speed = S / (duty * period);
  const foot = (u        )                     => {
    u = ((u % 1) + 1) % 1;
    if (u < duty) {
      const x = 40 + S / 2 - (S * u) / duty;
      const peel = smooth(duty - 0.14, duty, u);
      const th = (heelOff * peel * Math.PI) / 180;
      const px = x + toe, py = ground, ox = -toe, oy = ankleY - ground; // pivot on the toe, on the ground
      return { ankle: [px + ox * Math.cos(th) - oy * Math.sin(th), py + ox * Math.sin(th) + oy * Math.cos(th)], pitch: heelOff * peel, stance: true };
    }
    const s = (u - duty) / (1 - duty);
    const x = 40 - S / 2 + S * minJerk(s) - heelKick * Math.sin(Math.PI * Math.min(1, s * 1.6));
    const y = ankleY - lift * Math.pow(Math.sin(Math.PI * Math.pow(s, heelKick ? 0.7 : 1)), 1.2);
    return { ankle: [x, y], pitch: heelOff * Math.pow(1 - smooth(0, 0.55, s), 1.4), stance: false };
  };
  return {
    ...o,
    speed,
    pose(phase, go = 1) {
      const raw = [foot(phase), foot(phase + 0.5)];
      if (go < 1) raw.forEach((fo, i) => {
        const rest = 40 + (i ? -2 : 2);
        fo.ankle = [rest + (fo.ankle[0] - rest) * go, ankleY + (fo.ankle[1] - ankleY) * go];
        fo.pitch *= go; fo.stance = true;
      });
      let hipY = restHip;
      const half = (((phase % 0.5) + 0.5) % 0.5);
      if (heelKick && go >= 1) {
        // run: compress over the planted foot, highest mid-flight
        hipY = half < duty ? restHip + 1.4 * Math.sin((Math.PI * half) / duty) : restHip - 2.6 * Math.sin((Math.PI * (half - duty)) / (0.5 - duty));
      } else {
        // walk: lowest at double support, highest as the legs pass
        for (const fo of raw) if (fo.stance) {
          const dx = fo.ankle[0] - 40;
          hipY = Math.max(hipY, fo.ankle[1] - Math.sqrt(Math.max(1, reach * reach - dx * dx)));
        }
      }
      const hip      = [40, hipY];
      const feet = raw.map((fo) => ({ ...fo, knee: knee(hip, fo.ankle, thigh, shin) }))                ;
      const front        = feet[0].ankle[0] >= feet[1].ankle[0] ? 0 : 1;
      return { hip, feet, front, bob: hipY - restHip };
    },
  };
}
return { GAIT_PRESETS, gait };
})();
__modules["lib/motion"] = (() => {
// Repris de line-art-animation (https://github.com/JDhruv14/line-art-animation, licence MIT, Copyright (c) 2026 JDhruv14),
// version du dépôt au commit c43d698, adapté pour Kekeli Motion (voir MENTIONS.md).
// motion.ts — the small physics that make things feel alive without looking procedural.
const clamp = (x        , a        , b        )         => Math.max(a, Math.min(b, x));
const lerp = (a        , b        , t        )         => a + (b - a) * t;
const smooth = (e0        , e1        , x        )         => { const t = clamp((x - e0) / (e1 - e0), 0, 1); return t * t * (3 - 2 * t); };
const ease = (t        )         => smooth(0, 1, t);
const minJerk = (s        )         => s * s * s * (10 - 15 * s + 6 * s * s);
const rnd = (a        , b        )         => a + Math.random() * (b - a);
/** Seeded random for static drawing, so a scene looks the same on every load. */
function seeded(seed = 11)                                     {
  let s = seed;
  return (a = 0, b = 1) => { s = (s * 16807) % 2147483647; return a + ((s - 1) / 2147483646) * (b - a); };
}
/** Layered sines: organic drift for idle motion that doesn't read as a loop. */
const wobble = (t        , k = 1)         => Math.sin(t * 1.3 * k) * 0.6 + Math.sin(t * 2.7 * k + 1.7) * 0.3 + Math.sin(t * 5.1 * k + 4.1) * 0.1;
/**
 * Damped spring toward a moving target. Use for anything attached to a moving body:
 * sleeves, bags, ears, a parasol's tilt, arms swinging opposite the legs.
 */
function spring(stiffness = 40, damping = 6, x = 0)         {
  return {
    x, v: 0,
    step(target, dt) { this.v += (stiffness * (target - this.x) - damping * this.v) * dt; this.x += this.v * dt; return this.x; },
    kick(v) { this.v += v; },
  };
}
/**
 * Pendulum (angle in radians) for hanging things: lanterns, curtains, chimes, signs, a briefcase.
 * `force` is this frame's push — wind, the carrier's acceleration, a click.
 */
function pendulum(stiffness = 10, damping = 1.1, a = 0)           {
  return {
    a, v: 0,
    step(force, dt) { this.v += (-this.a * stiffness + force - this.v * damping) * dt; this.a += this.v * dt; return this.a; },
    kick(v) { this.v += v; },
  };
}
/** Wind from pointer movement plus a slow breeze; `value` ≈ -2.4…2.4. A quick sideways swipe gusts it. */
function pointerWind(target             , base = 0.3)       {
  let aim = base, lastX                = null, lastT = 0;
  const w       = {
    value: base,
    step(t) {
      aim = lerp(aim, base + 0.25 * Math.sin(t * 0.21) + 0.15 * Math.sin(t * 0.67), 0.012);
      w.value = lerp(w.value, aim, 0.05);
      return w.value;
    },
  };
  target.addEventListener('pointermove', (e) => {
    const { clientX } = e                ;
    const now = performance.now();
    if (lastX !== null) aim = clamp(aim + ((clientX - lastX) / Math.max(8, now - lastT)) * 0.8, -2.4, 2.4);
    lastX = clientX; lastT = now;
  });
  return w;
}
/** A timed sequence of named poses for an interaction (sleep → sit → stretch …). Returns null when finished. */
function sequence(steps                                        )                                     {
  const total = steps.reduce((a, s) => a + s[1], 0);
  return (t) => {
    if (t < 0 || t >= total) return null;
    let acc = 0, prev = steps[0][0];
    for (const [name, d] of steps) { if (t < acc + d) return { from: prev, to: name, u: (t - acc) / d }; acc += d; prev = name; }
    return null;
  };
}
return { clamp, lerp, smooth, ease, minJerk, rnd, seeded, wobble, spring, pendulum, pointerWind, sequence };
})();
__modules["lib/daynight"] = (() => {
// Repris de line-art-animation (https://github.com/JDhruv14/line-art-animation, licence MIT, Copyright (c) 2026 JDhruv14),
// version du dépôt au commit c43d698, adapté pour Kekeli Motion (voir MENTIONS.md).
// daynight.ts — time-of-day palette for line-art scenes.
// τ ∈ [0,1): 0 midnight · 0.25 dawn · 0.5 noon · 0.75 dusk.
//
// Day is dark ink on warm paper. Night inverts it: pale ink on indigo paper, with
// warm light from windows and lanterns. The ink flips while the paper is mid-dark,
// so the low-contrast moment lasts about a second of a three-minute day.
const toRGB = (h        )      => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16))       ;
const rgb = (c     )         => `rgb(${c.map((v) => Math.round(v)).join(',')})`;
const mix = (a     , b     , t        )      => a.map((v, i) => v + (b[i] - v) * t)       ;
const lum = (c     ) => (0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]) / 255;
const smooth = (e0        , e1        , x        ) => { const t = Math.max(0, Math.min(1, (x - e0) / (e1 - e0))); return t * t * (3 - 2 * t); };
/** Sample a cyclic colour ramp at u ∈ [0,1). */
function ramp(keys      , u        )      {
  u = ((u % 1) + 1) % 1;
  const n = keys.length;
  let i = n - 1;
  for (let j = 0; j < n; j++) if (keys[j][0] <= u) i = j;
  const [p0, c0] = keys[i], [p1, c1] = keys[(i + 1) % n];
  const span = (i + 1 < n ? p1 : p1 + 1) - p0;
  let x = u - p0; if (x < 0) x += 1;
  return mix(toRGB(c0), toRGB(c1), smooth(0, 1, x / span));
}
const SKY_TOP       = [[0, '#0d1222'], [0.19, '#131a30'], [0.235, '#3d4066'], [0.27, '#cfd8e2'], [0.33, '#f3f2ee'], [0.5, '#f6f5f1'], [0.68, '#f3eee4'], [0.735, '#e3c6a6'], [0.762, '#5a5279'], [0.8, '#1b2140'], [0.86, '#0d1222']];
const SKY_BOTTOM       = [[0, '#1a2240'], [0.19, '#283050'], [0.235, '#d49884'], [0.27, '#f3d0b3'], [0.33, '#faf6ee'], [0.5, '#fcfbf7'], [0.68, '#f9efdc'], [0.735, '#f0a379'], [0.762, '#d47f68'], [0.8, '#383a60'], [0.86, '#1a2240']];
const PAPERS       = [[0, '#1a2036'], [0.19, '#1f263e'], [0.235, '#4a4561'], [0.27, '#ede4d8'], [0.33, '#fbf8f1'], [0.5, '#fdfcf8'], [0.68, '#faf4e8'], [0.735, '#f0dbc4'], [0.756, '#5d5571'], [0.8, '#262c48'], [0.86, '#1a2036']];
const INK_DARK = toRGB('#1c1c1c'), INK_LIGHT = toRGB('#efe6cf');
function palette(tau        )          {
  const top = ramp(SKY_TOP, tau), bottom = ramp(SKY_BOTTOM, tau), paper = ramp(PAPERS, tau);
  const L = lum(paper);
  const inkT = smooth(0.56, 0.36, L);
  return {
    top, bottom, paper, inkT,
    ink: mix(INK_DARK, INK_LIGHT, inkT),
    lamp: smooth(0.78, 0.4, L),
    night: smooth(0.42, 0.1, lum(top)),
    sunset: Math.max(smooth(0.67, 0.735, tau) * smooth(0.79, 0.745, tau), smooth(0.215, 0.245, tau) * smooth(0.3, 0.26, tau)),
  };
}
/** Apply a palette to an <svg> drawn with ink.ts (needs `.pp { fill: var(--paper) }` in CSS). */
function applyPalette(svg               , P         )       {
  svg.style.color = rgb(P.ink);
  svg.style.setProperty('--paper', rgb(P.paper));
}
/** Darken a flat colour (clothes, props) toward night so it sits in the inverted palette. */
const nightTint = (colour        , P         , amount = 0.55)         =>
  rgb(mix(toRGB(colour), mix(toRGB(colour), [12, 16, 30], 0.6), P.inkT * amount));
return { toRGB, rgb, mix, ramp, SKY_TOP, SKY_BOTTOM, PAPERS, palette, applyPalette, nightTint };
})();
__modules["kekeli/types"] = (() => {
// Kekeli Motion : la description d'une scène, écrite par le modèle en JSON (jamais du code), vérifiée par le Runner
// (runner/src/motion/scene.js, scenePropre) puis relue ici une seconde fois avant le dessin.
const DIMENSIONS                                   = { paysage: [960, 540], carre: [720, 720], vertical: [540, 960] };
/** Moment de la journée → τ de daynight.ts (0 minuit, 0,25 aube, 0,5 midi, 0,75 crépuscule). */
const MOMENTS                         = { aube: 0.255, matin: 0.32, midi: 0.5, apres_midi: 0.62, coucher: 0.735, soir: 0.78, nuit: 0.95 };
const PEAUX                       = { fonce: '#6b4430', moyen: '#a8724f', clair: '#e3bfa0' };
return { DIMENSIONS, MOMENTS, PEAUX };
})();
__modules["kekeli/normaliser"] = (() => {
// Kekeli Motion : relit une description de scène et la rend sûre. Seules les valeurs prévues passent (listes fermées,
// nombres bornés, couleurs #rrggbb, textes courts) ; tout le reste prend une valeur par défaut. Utilisé par le Runner
// (après le modèle) et par le navigateur (avant le dessin) : une scène modifiée à la main reste toujours dessinable.
const FORMATS           = ['paysage', 'carre', 'vertical'];
const DECORS          = ['jardin', 'rue', 'village', 'plage', 'parc', 'campagne', 'marche', 'ville', 'vide'];
const MOMENTS           = ['aube', 'matin', 'midi', 'apres_midi', 'coucher', 'soir', 'nuit'];
const TYPES_ELEMENTS                = ['arbre', 'palmier', 'maison', 'case', 'lampadaire', 'banc', 'fleurs', 'buisson', 'nuage', 'oiseaux', 'etal', 'boutique', 'chat', 'collines', 'mer', 'immeubles', 'cloture'];
/** Au plus 6 personnages et 18 éléments : la scène reste fluide sur un téléphone. */
const PERSONNAGES_MAX = 6;
const ELEMENTS_MAX = 18;
const objet = (v         )       => (v && typeof v === 'object' && !Array.isArray(v) ? (v        ) : {});
const parmi =                   (v         , liste              , defaut   )    => (liste.includes(String(v)     ) ? (String(v)     ) : defaut);
const nombre = (v         , min        , max        , defaut        )         => {
  const n = typeof v === 'number' ? v : Number(v);
  return Number.isFinite(n) ? Math.min(max, Math.max(min, n)) : defaut;
};
const couleur = (v         , defaut        )         => (typeof v === 'string' && /^#[0-9a-fA-F]{6}$/.test(v.trim()) ? v.trim().toLowerCase() : defaut);
/** Un texte court, sans caractère de contrôle ni balise (il est toujours posé comme texte, jamais comme HTML). */
const texte = (v         , max        )         =>
  typeof v === 'string' ? v.replace(/[\u0000-\u001f\u007f<>]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, max) : '';
const HAUTS = ['#d9534f', '#2f6f8f', '#f2c94c', '#7fb069', '#e8833a', '#8e6bbf', '#f4f1ea'];
const BAS = ['#3a4a5f', '#5b4636', '#1f2a36', '#e8833a', '#2f6f8f'];
function personnage(v         , i        )             {
  const p = objet(v);
  const genre = parmi(p.genre, ['homme', 'femme', 'enfant']         , i % 2 ? 'femme' : 'homme');
  const bas = parmi(p.bas, ['pantalon', 'jupe', 'pagne', 'short']         , genre === 'femme' ? 'jupe' : genre === 'enfant' ? 'short' : 'pantalon');
  return {
    genre,
    action: parmi(p.action, ['marche', 'court', 'debout', 'salue']         , 'marche'),
    direction: parmi(p.direction, ['droite', 'gauche']         , 'droite'),
    x: nombre(p.x, 0, 1, (0.15 + i * 0.27) % 1),
    vitesse: nombre(p.vitesse, 0.6, 1.4, 1),
    peau: parmi(p.peau, ['fonce', 'moyen', 'clair']         , 'fonce'),
    cheveux: parmi(p.cheveux, ['courts', 'longs', 'tresses', 'foulard', 'ras']         , genre === 'femme' ? 'tresses' : 'courts'),
    haut: couleur(p.haut, HAUTS[i % HAUTS.length]),
    bas,
    couleur_bas: couleur(p.couleur_bas, BAS[i % BAS.length]),
    accessoire: parmi(p.accessoire, ['aucun', 'sac', 'chapeau', 'panier', 'parapluie', 'cartable']         , 'aucun'),
    couleur_accessoire: couleur(p.couleur_accessoire, '#c9a46a'),
    reaction: parmi(p.reaction, ['salue', 'saute', 'chapeau', 'tourne', 'parle']         , 'salue'),
    bulle: texte(p.bulle, 40),
  };
}
function element(v         )                 {
  const e = objet(v);
  const type = parmi(e.type, TYPES_ELEMENTS, 'arbre');
  if (!TYPES_ELEMENTS.includes(e.type               )) return null;
  return {
    type,
    x: nombre(e.x, -0.1, 1.1, 0.5),
    taille: nombre(e.taille, 0.6, 1.6, 1),
    couleur: couleur(e.couleur, ''),
    plan: parmi(e.plan, ['fond', 'milieu', 'devant']         , 'milieu'),
    texte: texte(e.texte, 24),
  };
}
/** Le décor par défaut de chaque lieu, quand la description n'en donne aucun. */
const DEFAUTS                                                                 = {
  jardin: [{ type: 'collines' }, { type: 'arbre', x: 0.14 }, { type: 'buisson', x: 0.36 }, { type: 'arbre', x: 0.62, plan: 'fond' }, { type: 'fleurs', x: 0.84 }, { type: 'fleurs', x: 0.1, plan: 'devant' }, { type: 'nuage', x: 0.3 }, { type: 'oiseaux', x: 0.2 }],
  parc: [{ type: 'arbre', x: 0.1 }, { type: 'banc', x: 0.34 }, { type: 'lampadaire', x: 0.52 }, { type: 'arbre', x: 0.78 }, { type: 'fleurs', x: 0.92, plan: 'devant' }, { type: 'nuage', x: 0.6 }, { type: 'oiseaux', x: 0.4 }],
  rue: [{ type: 'immeubles' }, { type: 'maison', x: 0.16 }, { type: 'lampadaire', x: 0.42 }, { type: 'boutique', x: 0.68 }, { type: 'lampadaire', x: 0.94 }, { type: 'nuage', x: 0.5 }],
  ville: [{ type: 'immeubles' }, { type: 'boutique', x: 0.26 }, { type: 'lampadaire', x: 0.52 }, { type: 'maison', x: 0.8 }, { type: 'nuage', x: 0.2 }],
  village: [{ type: 'collines' }, { type: 'case', x: 0.14 }, { type: 'case', x: 0.4, plan: 'fond' }, { type: 'palmier', x: 0.6 }, { type: 'case', x: 0.84 }, { type: 'oiseaux', x: 0.3 }],
  plage: [{ type: 'mer' }, { type: 'palmier', x: 0.1 }, { type: 'palmier', x: 0.86, plan: 'fond' }, { type: 'nuage', x: 0.4 }, { type: 'oiseaux', x: 0.6 }],
  campagne: [{ type: 'collines' }, { type: 'arbre', x: 0.2, plan: 'fond' }, { type: 'cloture', x: 0.5 }, { type: 'arbre', x: 0.82 }, { type: 'nuage', x: 0.35 }],
  marche: [{ type: 'etal', x: 0.18 }, { type: 'etal', x: 0.5, couleur: '#2f6f8f' }, { type: 'palmier', x: 0.84 }, { type: 'etal', x: 0.34, plan: 'fond', couleur: '#f2c94c' }, { type: 'nuage', x: 0.6 }],
  vide: [],
};
function normaliser(brute         )        {
  const s = objet(brute);
  const ciel = objet(s.ciel);
  const decor = parmi(s.decor, DECORS, 'jardin');
  const personnages = (Array.isArray(s.personnages) ? s.personnages : []).slice(0, PERSONNAGES_MAX).map(personnage);
  let elements = (Array.isArray(s.elements) ? s.elements : []).map(element).filter((e)               => e !== null);
  if (elements.length === 0) elements = DEFAUTS[decor].map((e) => element({ x: 0.5, ...e }) ).filter(Boolean);
  // Les grands fonds d'abord (derrière tout le reste), une seule fois chacun.
  const vus = new Set        ();
  elements = elements.filter((e) => !['collines', 'mer', 'immeubles'].includes(e.type) || (!vus.has(e.type) && vus.add(e.type)));
  elements.sort((a, b) => Number(['collines', 'mer', 'immeubles'].includes(b.type)) - Number(['collines', 'mer', 'immeubles'].includes(a.type)));
  return {
    version: 1,
    titre: texte(s.titre, 80) || 'Animation',
    format: parmi(s.format, FORMATS, 'paysage'),
    decor,
    ciel: {
      moment: parmi(ciel.moment, MOMENTS, 'apres_midi'),
      anime: ciel.anime === undefined ? true : Boolean(ciel.anime),
      duree_jour: nombre(ciel.duree_jour, 20, 300, 60),
    },
    personnages,
    elements: elements.slice(0, ELEMENTS_MAX),
    indication: texte(s.indication, 70),
    papier: couleur(s.papier, '#fbf7ef'),
  };
}
return { TYPES_ELEMENTS, PERSONNAGES_MAX, ELEMENTS_MAX, texte, normaliser };
})();
__modules["kekeli/personnage"] = (() => {
// Kekeli Motion : un personnage dessiné à l'encre, qui marche, court, attend ou salue, et réagit quand on le touche.
// Construit avec la bibliothèque de line-art-animation (licence MIT) : jambes résolues par gait(), membres en deux
// traits ouverts (limb), vêtements dessinés autour des genoux, ressorts pour ce qui balance.
// Le personnage est dessiné dans son propre espace (environ 95 unités de haut, pieds sur y = 120, tourné vers +x).
const { el, pen, blot, paint, limb, solid, shoe, place, toScene, f, pt, PEN, PAPER } = __modules["lib/ink"];
const { gait } = __modules["lib/gait"];
const { spring, pendulum, ease, clamp, wobble } = __modules["lib/motion"];
const { nightTint } = __modules["lib/daynight"];
const { PEAUX } = __modules["kekeli/types"];
/** Durée de chaque réaction, en secondes. */
const DUREES                         = { salue: 1.9, saute: 0.9, chapeau: 1.7, tourne: 1.1, parle: 2.8 };
const lerpP = (a     , b     , t        )      => [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t];
const tourner = (p     , c     , a        )      => {
  const s = Math.sin(a), co = Math.cos(a);
  return [c[0] + (p[0] - c[0]) * co - (p[1] - c[1]) * s, c[1] + (p[0] - c[0]) * s + (p[1] - c[1]) * co];
};
function creerPersonnage(d             , { couche, bulles, largeur, sol, echelle }           )             {
  const enfant = d.genre === 'enfant';
  const s = echelle * (enfant ? 0.68 : 1);
  const court = d.action === 'court';
  const allure       = court
    ? gait({ kind: 'run', thigh: 23, shin: 23, stride: 30, lift: 13, period: 0.66, duty: 0.38, heelKick: 4, ankleY: 118.4 })
    : gait({ kind: 'walk' });
  const avance = d.action === 'marche' || court;
  const peau = PEAUX[d.peau];
  const jupe = d.bas === 'jupe' || d.bas === 'pagne';
  const jambesNues = jupe || d.bas === 'short';
  const fig = el('g', { class: 'kekeli-personnage', style: 'cursor:pointer', role: 'button', 'aria-label': 'Personnage' }, couche);
  // Une zone à toucher plus grande que le dessin (au moins 40 pixels sur un téléphone).
  el('rect', { x: 18, y: 8, width: 46, height: 116, fill: 'transparent', 'pointer-events': 'all' }, fig);
  const dos = el('g', {}, fig);
  const brasLoin       = solid(limb(fig, 3.6), d.haut);
  const jambes         = [limb(fig, 4.4), limb(fig, 4.4)];
  jambes.forEach((j) => solid(j, jambesNues ? peau : d.couleur_bas));
  const shorts         = d.bas === 'short' ? [solid(limb(fig, 5.6), d.couleur_bas), solid(limb(fig, 5.6), d.couleur_bas)] : [];
  const chaussures = [blot(fig), blot(fig)];
  const bas = jupe ? paint(fig, d.couleur_bas) : null;
  const motif = d.bas === 'pagne' ? pen(fig, PEN * 0.55) : null;
  const torse = paint(fig, d.haut);
  const col = pen(fig, PEN * 0.6);
  const cheveuxArriere = d.cheveux === 'longs' || d.cheveux === 'tresses' ? (d.cheveux === 'tresses' ? pen(fig, 1.8) : blot(fig)) : null;
  const tete = el('ellipse', { rx: 3.7, ry: 4.7, fill: peau, stroke: 'currentColor', 'stroke-width': PEN }, fig);
  const cheveux = d.cheveux === 'foulard' ? paint(fig, d.couleur_accessoire) : blot(fig);
  const accessoire = el('g', {}, fig);
  const brasPres       = solid(limb(fig, 3.6), d.haut);
  const mains = [el('circle', { r: 1.5, fill: peau, stroke: 'currentColor', 'stroke-width': PEN * 0.6 }, fig), el('circle', { r: 1.5, fill: peau, stroke: 'currentColor', 'stroke-width': PEN * 0.6 }, fig)];
  fig.insertBefore(mains[0], brasLoin.g.nextSibling);
  const bulle = el('g', { opacity: 0 }, bulles);
  const bulleForme = el('path', { fill: PAPER, stroke: 'currentColor', 'stroke-width': 1.2, 'stroke-linejoin': 'round' }, bulle);
  const bulleTexte = el('text', { 'font-size': 15, 'font-family': 'Inter, system-ui, sans-serif', fill: 'currentColor', 'text-anchor': 'middle' }, bulle);
  bulleTexte.textContent = d.bulle || 'Bonjour';
  // Accessoires, dessinés une fois puis bougés.
  let sac                        = null, sangle                        = null, chapeau                        = null;
  let panier                        = null, tressage                        = null, parapluie                        = null, manche                        = null;
  if (d.accessoire === 'sac' || d.accessoire === 'cartable') { sangle = pen(d.accessoire === 'cartable' ? dos : accessoire, PEN * 0.7); sac = paint(d.accessoire === 'cartable' ? dos : accessoire, d.couleur_accessoire); }
  if (d.accessoire === 'chapeau') chapeau = paint(accessoire, d.couleur_accessoire);
  if (d.accessoire === 'panier') { panier = paint(accessoire, d.couleur_accessoire); tressage = pen(accessoire, PEN * 0.5); }
  if (d.accessoire === 'parapluie') { manche = pen(accessoire, PEN * 0.9); parapluie = paint(accessoire, d.couleur_accessoire); }
  const balancier = [spring(60, 9), spring(60, 9)];
  const pendule = pendulum(14, 1.8);
  const meche = spring(30, 4);
  let dir         = d.direction === 'gauche' ? -1 : 1;
  let x = clamp(d.x, 0, 1) * largeur;
  let phase = d.x * 3.1;
  let reaction = -1;
  const vitesse = allure.speed * s * d.vitesse;
  fig.addEventListener('pointerdown', (e) => { e.stopPropagation(); if (reaction < 0) reaction = 0; });
  return {
    g: fig,
    reagir() { if (reaction < 0) reaction = 0; },
    dessiner(t, dt, P, vent) {
      // La réaction en cours : quand elle ralentit le personnage (saluer, parler…), quand il saute ou se retourne.
      let r = 0, saut = 0;
      const type = d.reaction;
      if (reaction >= 0) {
        reaction += dt;
        const total = DUREES[type] ?? 1.5;
        r = reaction < 0.3 ? ease(reaction / 0.3) : reaction < total - 0.4 ? 1 : 1 - ease((reaction - total + 0.4) / 0.4);
        if (type === 'saute') saut = Math.sin(Math.PI * clamp((reaction - 0.12) / 0.62, 0, 1)) * 26;
        if (type === 'tourne' && reaction >= total / 2 && reaction - dt < total / 2) dir = (dir === 1 ? -1 : 1);
        if (reaction > total) reaction = -1;
      }
      const freine = type === 'saute' ? 0 : r * (type === 'tourne' ? 1 : 0.92);
      const go = avance ? 1 - freine : 0;
      phase += (dt / allure.period) * go;
      if (avance) {
        x += dir * vitesse * dt * go;
        if (x > largeur + 70) x = -70;
        if (x < -70) x = largeur + 70;
      }
      const p = allure.pose(phase, go);
      const b = p.bob;
      place(fig, x, sol - saut, dir, s);
      // Jambes : la jambe de devant est dessinée par-dessus. Un saut replie un peu les genoux.
      const rentre = saut > 0 ? Math.min(1, saut / 18) : 0;
      p.feet.forEach((pied, i) => {
        const genou      = [pied.knee[0] + 4 * rentre, pied.knee[1] - 2 * rentre];
        const cheville      = [pied.ankle[0] - 2 * rentre, pied.ankle[1] - 6 * rentre];
        jambes[i].draw([p.hip, genou, cheville], [0.3, 0.8]);
        shoe(chaussures[i], cheville, pied.pitch);
        if (shorts[i]) shorts[i].draw([p.hip, lerpP(p.hip, genou, 0.62)], [0, 0]);
      });
      const devant = p.front;
      fig.insertBefore(jambes[devant].g, bas ?? torse);
      fig.insertBefore(chaussures[devant], bas ?? torse);
      if (shorts[devant]) fig.insertBefore(shorts[devant].g, bas ?? torse);
      // Le haut du corps, penché en avant quand il court.
      const penche = court ? 0.16 * go : 0;
      const hanche      = [p.hip[0], p.hip[1]];
      const R = (q     )      => tourner(q, hanche, penche);
      const epaule = R([41, 41 + b]);
      const tc = R([42.7, 31.4 + b]);
      const taille      = R([40.5, p.hip[1] - 1.5]);
      if (bas) {
        // Jupe ou pagne : l'ourlet s'ouvre et se ferme avec la foulée, autour du genou de devant.
        const kF = p.feet[devant].knee, aF = p.feet[devant].ankle;
        const ourlet = d.bas === 'pagne' ? 110 : 98;
        const avantX = Math.max(kF[0] + 2.4, 46.6);
        const arriere = Math.min(p.feet[1 - devant].knee[0] - 2.2, 33.5);
        bas.setAttribute('d', `M${f(taille[0] - 6)} ${f(taille[1] - 1)} L${f(taille[0] + 6)} ${f(taille[1] - 1)} Q${f(avantX)} ${f(taille[1] + 14)} ${f(Math.max(avantX, Math.min(aF[0] - 3, avantX + 6)))} ${ourlet + b * 0.3} L${f(arriere - 1)} ${ourlet + 1 + b * 0.3} Q${f(arriere + 1)} ${f(taille[1] + 12)} ${f(taille[0] - 6)} ${f(taille[1] - 1)}Z`);
        bas.setAttribute('fill', nightTint(d.couleur_bas, P));
        if (motif) motif.setAttribute('d', `M${f(taille[0] - 3)} ${f(taille[1] + 8)} L${f(taille[0] + 4)} ${f(taille[1] + 8)} M${f(arriere + 3)} ${f(ourlet - 6)} L${f(avantX - 1)} ${f(ourlet - 6)}`);
      }
      torse.setAttribute('d', `M${f(epaule[0] - 5.6)} ${f(epaule[1] + 1.4)} Q${f(epaule[0] - 1)} ${f(epaule[1] - 2.4)} ${f(epaule[0] + 5)} ${f(epaule[1] + 1)} Q${f(epaule[0] + 6.6)} ${f(epaule[1] + 14)} ${f(taille[0] + 6)} ${f(taille[1])} L${f(taille[0] - 6.2)} ${f(taille[1] + 0.6)} Q${f(epaule[0] - 7)} ${f(epaule[1] + 14)} ${f(epaule[0] - 5.6)} ${f(epaule[1] + 1.4)}Z`);
      torse.setAttribute('fill', nightTint(d.haut, P));
      col.setAttribute('d', `M${f(epaule[0] - 1.6)} ${f(epaule[1] - 0.6)} Q${f(epaule[0] + 0.6)} ${f(epaule[1] + 2.4)} ${f(epaule[0] + 2.8)} ${f(epaule[1] - 0.4)}`);
      // Tête et cheveux.
      tete.setAttribute('cx', f(tc[0])); tete.setAttribute('cy', f(tc[1]));
      const [hx, hy] = tc;
      if (d.cheveux === 'foulard') {
        cheveux.setAttribute('d', `M${f(hx - 4.6)} ${f(hy + 0.6)} Q${f(hx - 5.4)} ${f(hy - 7.4)} ${f(hx + 0.4)} ${f(hy - 8.2)} Q${f(hx + 5.6)} ${f(hy - 7.8)} ${f(hx + 4.4)} ${f(hy - 1.8)} Q${f(hx)} ${f(hy - 3.6)} ${f(hx - 4.6)} ${f(hy + 0.6)}Z M${f(hx - 1.6)} ${f(hy - 7.6)} Q${f(hx - 5)} ${f(hy - 12.6)} ${f(hx + 1.6)} ${f(hy - 11)} Q${f(hx + 3.4)} ${f(hy - 9.6)} ${f(hx + 1.2)} ${f(hy - 7.8)}Z`);
        cheveux.setAttribute('fill', nightTint(d.couleur_accessoire, P));
      } else {
        const epais = d.cheveux === 'ras' ? 0.8 : 1.6;
        cheveux.setAttribute('d', `M${f(hx - 3.9)} ${f(hy + 0.8)} Q${f(hx - 4.8 - epais * 0.3)} ${f(hy - 5.6 - epais)} ${f(hx + 0.4)} ${f(hy - 5.2 - epais)} Q${f(hx + 4.4)} ${f(hy - 4.6 - epais * 0.6)} ${f(hx + 3.7)} ${f(hy - 1.6)} Q${f(hx + 1)} ${f(hy - 3.4)} ${f(hx - 1.6)} ${f(hy - 2.2)} Q${f(hx - 2.6)} ${f(hy - 0.4)} ${f(hx - 3.9)} ${f(hy + 0.8)}Z`);
      }
      const flot = meche.step(-2 - (avance ? allure.speed * 0.05 * go : 0) - vent * 3, dt);
      if (cheveuxArriere && d.cheveux === 'longs') cheveuxArriere.setAttribute('d', `M${f(hx - 3.6)} ${f(hy - 2)} Q${f(hx - 6 + flot * 0.3)} ${f(hy + 4)} ${f(hx - 4.6 + flot * 0.6)} ${f(hy + 10)} L${f(hx - 1.6 + flot * 0.4)} ${f(hy + 9)} Q${f(hx - 2.4)} ${f(hy + 3)} ${f(hx - 1)} ${f(hy - 1)}Z`);
      if (cheveuxArriere && d.cheveux === 'tresses') cheveuxArriere.setAttribute('d', `M${f(hx - 3)} ${f(hy - 1)} Q${f(hx - 5.4 + flot * 0.4)} ${f(hy + 4)} ${f(hx - 4.4 + flot * 0.7)} ${f(hy + 9)} M${f(hx - 1.2)} ${f(hy - 1.4)} Q${f(hx - 3 + flot * 0.4)} ${f(hy + 4)} ${f(hx - 2 + flot * 0.7)} ${f(hy + 8.6)}`);
      // Bras : ils balancent à l'opposé des jambes (ressorts), plus fort et pliés quand il court.
      const jambe = (p.feet[0].ankle[0] - 40) / (allure.stride / 2);
      const ampleur = court ? 34 : 15;
      const aLoin = (balancier[0].step(-jambe * ampleur * go, dt) * Math.PI) / 180;
      const aPres = (balancier[1].step(jambe * (ampleur - 2) * go + wobble(t * 0.7 + d.x * 9) * (avance ? 0 : 2), dt) * Math.PI) / 180;
      const plie = court ? 1.25 : 0.12;
      const eLoin      = [epaule[0] - 1 + 15 * Math.sin(aLoin), epaule[1] + 2 + 15 * Math.cos(aLoin)];
      const mLoin      = [eLoin[0] + 14 * Math.sin(aLoin + plie), eLoin[1] + 14 * Math.cos(aLoin + plie)];
      brasLoin.draw([[epaule[0] - 1, epaule[1] + 2], eLoin, mLoin], [0.2, 0.5]);
      mains[0].setAttribute('cx', f(mLoin[0])); mains[0].setAttribute('cy', f(mLoin[1]));
      const ePres      = [epaule[0] + 1.6, epaule[1] + 2.4];
      let coude      = [ePres[0] + 15 * Math.sin(aPres), ePres[1] + 15 * Math.cos(aPres)];
      let main      = [coude[0] + 14 * Math.sin(aPres + plie), coude[1] + 14 * Math.cos(aPres + plie)];
      const geste = reaction >= 0 && (type === 'salue' || type === 'chapeau' || type === 'parle') ? r : 0;
      const souleve = type === 'chapeau' && d.accessoire === 'chapeau' ? geste * 5 : 0;
      if (geste > 0.01) {
        // Saluer (la main s'agite au-dessus de la tête), soulever son chapeau, ou parler (la main s'ouvre devant lui).
        const cible      = type === 'parle'
          ? [epaule[0] + 15, epaule[1] + 8 + Math.sin(reaction * 7) * 1.4]
          : type === 'chapeau' && d.accessoire === 'chapeau' ? [hx + 4, hy - 5 - souleve] : [epaule[0] + 9 + Math.sin(reaction * 13) * 4.2, epaule[1] - 17];
        const milieu      = type === 'parle' ? [epaule[0] + 6, epaule[1] + 13] : [epaule[0] + 10, epaule[1] - 2];
        coude = lerpP(coude, milieu, geste);
        main = lerpP(main, cible, geste);
      } else if (d.accessoire === 'parapluie') {
        coude = [ePres[0] + 7, ePres[1] + 9];
        main = [ePres[0] + 11, ePres[1] - 2];
      } else if (d.action === 'salue' && reaction < 0) {
        coude = [epaule[0] + 10, epaule[1] - 2];
        main = [epaule[0] + 9 + Math.sin(t * 9) * 4, epaule[1] - 17];
      }
      brasPres.draw([ePres, coude, main], [0.2, 0.4]);
      mains[1].setAttribute('cx', f(main[0])); mains[1].setAttribute('cy', f(main[1]));
      // Les accessoires.
      const secousse = Math.cos(2 * Math.PI * phase * 2) * 1.6 * go;
      if (sac && sangle) {
        if (d.accessoire === 'cartable') {
          sangle.setAttribute('d', `M${f(epaule[0] - 3)} ${f(epaule[1] + 1)} L${f(epaule[0] + 2)} ${f(epaule[1] + 10)}`);
          sac.setAttribute('d', `M${f(epaule[0] - 12)} ${f(epaule[1] + 3)} L${f(epaule[0] - 4)} ${f(epaule[1] + 2)} L${f(epaule[0] - 4)} ${f(epaule[1] + 20)} L${f(epaule[0] - 12.4)} ${f(epaule[1] + 19)}Z`);
        } else {
          const a = pendule.step(-secousse - vent * 0.3, dt);
          const attache      = [taille[0] - 3, taille[1] + 3];
          sangle.setAttribute('d', `M${f(epaule[0] + 3)} ${f(epaule[1])} L${pt(attache)}`);
          accessoire.setAttribute('transform', `rotate(${f(a * 30)} ${pt(attache)})`);
          sac.setAttribute('d', `M${f(attache[0] - 5)} ${f(attache[1])} L${f(attache[0] + 4)} ${f(attache[1])} L${f(attache[0] + 4.6)} ${f(attache[1] + 9)} L${f(attache[0] - 5.6)} ${f(attache[1] + 9)}Z`);
        }
        sac.setAttribute('fill', nightTint(d.couleur_accessoire, P));
      }
      if (chapeau) {
        const l = souleve;
        chapeau.setAttribute('d', `M${f(hx - 6.6)} ${f(hy - 3.6 - l)} L${f(hx + 6.8)} ${f(hy - 3.6 - l)} Q${f(hx + 4.4)} ${f(hy - 5 - l)} ${f(hx + 4)} ${f(hy - 8 - l)} Q${f(hx)} ${f(hy - 11 - l)} ${f(hx - 3.8)} ${f(hy - 8 - l)} Q${f(hx - 4.2)} ${f(hy - 5 - l)} ${f(hx - 6.6)} ${f(hy - 3.6 - l)}Z`);
        chapeau.setAttribute('fill', nightTint(d.couleur_accessoire, P));
      }
      if (panier && tressage) {
        // Un panier porté sur la tête, en équilibre.
        const y0 = hy - 5.6 - (d.cheveux === 'foulard' ? 2.6 : 0);
        panier.setAttribute('d', `M${f(hx - 8)} ${f(y0)} L${f(hx + 8)} ${f(y0)} Q${f(hx + 7)} ${f(y0 - 6)} ${f(hx + 5)} ${f(y0 - 7)} L${f(hx - 5)} ${f(y0 - 7)} Q${f(hx - 7)} ${f(y0 - 6)} ${f(hx - 8)} ${f(y0)}Z`);
        panier.setAttribute('fill', nightTint(d.couleur_accessoire, P));
        tressage.setAttribute('d', `M${f(hx - 6.6)} ${f(y0 - 3.4)} L${f(hx + 6.6)} ${f(y0 - 3.4)} M${f(hx - 3)} ${f(y0)} L${f(hx - 2.4)} ${f(y0 - 7)} M${f(hx + 2)} ${f(y0)} L${f(hx + 2.4)} ${f(y0 - 7)}`);
      }
      if (parapluie && manche) {
        const haut      = [main[0] + 0.6, main[1] - 22];
        manche.setAttribute('d', `M${f(main[0])} ${f(main[1] + 2)} L${pt(haut)}`);
        parapluie.setAttribute('d', `M${f(haut[0] - 15)} ${f(haut[1] + 5)} Q${f(haut[0] - 13)} ${f(haut[1] - 9)} ${f(haut[0])} ${f(haut[1] - 10)} Q${f(haut[0] + 13)} ${f(haut[1] - 9)} ${f(haut[0] + 15)} ${f(haut[1] + 5)} Q${f(haut[0] + 10)} ${f(haut[1] + 2)} ${f(haut[0] + 5)} ${f(haut[1] + 5)} Q${f(haut[0])} ${f(haut[1] + 2)} ${f(haut[0] - 5)} ${f(haut[1] + 5)} Q${f(haut[0] - 10)} ${f(haut[1] + 2)} ${f(haut[0] - 15)} ${f(haut[1] + 5)}Z`);
        parapluie.setAttribute('fill', nightTint(d.couleur_accessoire, P));
      }
      // La bulle, dans l'espace de la scène (jamais retournée).
      const visible = type === 'parle' && reaction >= 0 ? r : 0;
      bulle.setAttribute('opacity', f(visible));
      if (visible > 0) {
        const [bx, by] = toScene(x, sol - saut, dir, hx, hy - 9, s);
        const largeurTexte = Math.max(46, Math.min(320, bulleTexte.textContent .length * 8.2 + 22));
        const hauteur = 30;
        const gauche = clamp(bx - largeurTexte / 2, 6, largeur - largeurTexte - 6);
        const haut = by - hauteur - 10;
        bulleForme.setAttribute('d', `M${f(gauche + 8)} ${f(haut)} L${f(gauche + largeurTexte - 8)} ${f(haut)} Q${f(gauche + largeurTexte)} ${f(haut)} ${f(gauche + largeurTexte)} ${f(haut + 8)} L${f(gauche + largeurTexte)} ${f(haut + hauteur - 8)} Q${f(gauche + largeurTexte)} ${f(haut + hauteur)} ${f(gauche + largeurTexte - 8)} ${f(haut + hauteur)} L${f(bx + 6)} ${f(haut + hauteur)} L${f(bx)} ${f(haut + hauteur + 8)} L${f(bx - 4)} ${f(haut + hauteur)} L${f(gauche + 8)} ${f(haut + hauteur)} Q${f(gauche)} ${f(haut + hauteur)} ${f(gauche)} ${f(haut + hauteur - 8)} L${f(gauche)} ${f(haut + 8)} Q${f(gauche)} ${f(haut)} ${f(gauche + 8)} ${f(haut)}Z`);
        bulleTexte.setAttribute('x', f(gauche + largeurTexte / 2));
        bulleTexte.setAttribute('y', f(haut + hauteur / 2 + 5));
      }
    },
  };
}
return { creerPersonnage };
})();
__modules["kekeli/decor"] = (() => {
// Kekeli Motion : les éléments du décor (arbres, palmiers, maisons, cases, lampadaires, étals, oiseaux, nuages…),
// dessinés à l'encre avec la bibliothèque de line-art-animation (licence MIT). Chacun réagit au vent (le doigt ou la
// souris qui passe) et à un toucher : le monde réagit, jamais un bouton.
const { el, pen, shape, paint, limb, f, PEN, PAPER } = __modules["lib/ink"];
const { pendulum, spring, seeded, wobble, clamp } = __modules["lib/motion"];
const { nightTint, mix, toRGB, rgb } = __modules["lib/daynight"];
const LUMIERE = '#f4c867';
const vert = (c        ) => c || '#a9c48a';
/** Une fenêtre éclairée la nuit : la couleur passe du papier à une lumière chaude. */
const fenetre = (P         , allumee        ) => rgb(mix(P.paper, toRGB(LUMIERE), clamp(allumee, 0, 1)));
/** Place un groupe : son point (0, 0) sur le sol, à la taille voulue. */
function groupe(parent         , x        , y        , k        , toucher             )              {
  const g = el('g', { transform: `translate(${f(x)} ${f(y)}) scale(${f(k)})` }, parent);
  if (toucher) {
    g.setAttribute('style', 'cursor:pointer');
    g.addEventListener('pointerdown', (e) => { e.stopPropagation(); toucher(); });
  }
  return g;
}
/** Contour en lobes (feuillage, buisson, nuage), toujours le même pour une même graine. */
function lobes(cx        , cy        , rx        , ry        , n        , graine        )         {
  const r = seeded(graine);
  const pts                          = [];
  for (let i = 0; i < n; i++) {
    const a = (i / n) * Math.PI * 2 + r(-0.1, 0.1);
    pts.push([cx + Math.cos(a) * rx * r(0.86, 1.04), cy + Math.sin(a) * ry * r(0.86, 1.04)]);
  }
  let d = `M${f(pts[0][0])} ${f(pts[0][1])}`;
  for (let i = 0; i < n; i++) {
    const a = pts[i], b = pts[(i + 1) % n];
    const mx = (a[0] + b[0]) / 2, my = (a[1] + b[1]) / 2;
    const ox = mx - cx, oy = my - cy, l = Math.hypot(ox, oy) || 1;
    d += ` Q${f(mx + (ox / l) * rx * 0.28)} ${f(my + (oy / l) * ry * 0.28)} ${f(b[0])} ${f(b[1])}`;
  }
  return d + 'Z';
}
function creerElement(d             , parent             , ctx          , graine        )             {
  const plan = d.plan === 'fond' ? 0.62 : d.plan === 'devant' ? 1.18 : 1;
  const k = ctx.echelle * d.taille * plan;
  const x = d.x * ctx.largeur;
  const y = d.plan === 'fond' ? ctx.sol - ctx.hauteur * 0.035 : d.plan === 'devant' ? ctx.sol + (ctx.hauteur - ctx.sol) * 0.62 : ctx.sol;
  const fabrique = FABRIQUES[d.type];
  return fabrique ? fabrique(d, parent, ctx, { x, y, k }, graine) : { maj() {} };
}
const FABRIQUES                                                 = {
  arbre(d, parent, _ctx, { x, y, k }, graine) {
    const balance = pendulum(6, 1.6);
    const feuilles                                                                = [];
    const g = groupe(parent, x, y, k, () => { balance.kick(1.4); for (const fe of feuilles) if (fe.t < 0) { fe.t = 0; fe.x = (Math.random() - 0.5) * 50; fe.v = 0.6 + Math.random(); } });
    const tronc = el('g', {}, g);
    shape(tronc).setAttribute('d', 'M-5 0 Q-3 -26 -4 -48 L4 -48 Q3 -26 6 0Z');
    pen(tronc, PEN * 0.8).setAttribute('d', 'M-2 -40 Q-10 -52 -16 -58 M2 -44 Q10 -55 15 -62');
    const couronne = el('g', {}, g);
    const masse = paint(couronne, vert(d.couleur));
    masse.setAttribute('d', lobes(0, -78, 38, 30, 11, graine));
    pen(couronne, PEN * 0.6).setAttribute('d', 'M-18 -80 q4 -3 8 0 M6 -92 q4 -3 8 0 M-4 -66 q4 -3 8 0 M14 -72 q4 -3 8 0');
    for (let i = 0; i < 4; i++) feuilles.push({ g: paint(g, vert(d.couleur), PEN * 0.6), t: -1, x: 0, v: 1 });
    return {
      maj(t, dt, P, vent) {
        const a = balance.step(vent * 0.5 + wobble(t * 0.6 + graine) * 0.15, dt);
        couronne.setAttribute('transform', `rotate(${f(a * 4)} 0 -48)`);
        masse.setAttribute('fill', nightTint(vert(d.couleur), P, 0.7));
        for (const fe of feuilles) {
          if (fe.t < 0) { fe.g.setAttribute('d', ''); continue; }
          fe.t += dt;
          const yy = -70 + fe.t * 34 * fe.v, xx = fe.x + Math.sin(fe.t * 4) * 6 + vent * fe.t * 12;
          fe.g.setAttribute('d', `M${f(xx)} ${f(yy)} q3 -3 6 0 q-3 3 -6 0Z`);
          if (yy > -2) fe.t = -1;
        }
      },
    };
  },
  palmier(d, parent, _ctx, { x, y, k }, graine) {
    const balance = pendulum(5, 1.2);
    const g = groupe(parent, x, y, k, () => balance.kick(1.6));
    const tronc = limb(g, 7, PEN);
    tronc.draw([[0, 0], [5, -60], [14, -118]], [0, 0]);
    const anneaux = pen(g, PEN * 0.6);
    anneaux.setAttribute('d', Array.from({ length: 9 }, (_, i) => { const u = (i + 1) / 10, cx = 14 * u * u + 2 * u, cy = -118 * u; return `M${f(cx - 3)} ${f(cy + 1)} q3 2 6 0`; }).join(' '));
    const tete = el('g', {}, g);
    const palmes = Array.from({ length: 6 }, () => paint(tete, vert(d.couleur || '#8db36f'), PEN * 0.8));
    return {
      maj(t, dt, P, vent) {
        const a = balance.step(vent * 0.6 + wobble(t * 0.5 + graine) * 0.2, dt);
        palmes.forEach((p, i) => {
          const angle = -2.6 + i * 0.95 + a * 0.25 + Math.sin(t * 1.3 + i) * 0.03;
          const l = 46 - Math.abs(i - 2.5) * 3;
          const tx = 14 + Math.cos(angle) * l, ty = -118 + Math.sin(angle) * l * 0.55 + 18;
          const mx = 14 + Math.cos(angle) * l * 0.5, my = -118 + Math.sin(angle) * l * 0.5 - 8;
          p.setAttribute('d', `M14 -118 Q${f(mx)} ${f(my - 5)} ${f(tx)} ${f(ty)} Q${f(mx)} ${f(my + 4)} 14 -116Z`);
          p.setAttribute('fill', nightTint(vert(d.couleur || '#8db36f'), P, 0.7));
        });
      },
    };
  },
  maison(d, parent, _ctx, { x, y, k }) {
    let allumee = 0;
    const g = groupe(parent, x, y, k, () => { allumee = allumee > 0.5 ? 0 : 1; });
    shape(g).setAttribute('d', 'M-40 0 L-39 -54 L39 -55 L40 0Z');
    const toit = paint(g, d.couleur || '#b5563c');
    toit.setAttribute('d', 'M-48 -52 L0 -88 L48 -53 Z');
    shape(g, PEN * 0.9).setAttribute('d', 'M20 -74 L20 -88 L28 -88 L28 -68');
    const porte = paint(g, '#7a5236', PEN * 0.9);
    porte.setAttribute('d', 'M-8 0 L-8 -30 Q0 -34 8 -30 L8 0Z');
    const vitres = [paint(g, PAPER, PEN * 0.9), paint(g, PAPER, PEN * 0.9)];
    vitres[0].setAttribute('d', 'M-32 -40 L-18 -40 L-18 -26 L-32 -26Z');
    vitres[1].setAttribute('d', 'M18 -40 L32 -40 L32 -26 L18 -26Z');
    pen(g, PEN * 0.6).setAttribute('d', 'M-25 -40 L-25 -26 M-32 -33 L-18 -33 M25 -40 L25 -26 M18 -33 L32 -33');
    return {
      maj(_t, _dt, P) {
        toit.setAttribute('fill', nightTint(d.couleur || '#b5563c', P));
        vitres.forEach((v) => v.setAttribute('fill', fenetre(P, Math.max(P.lamp, allumee))));
      },
    };
  },
  case(d, parent, _ctx, { x, y, k }, graine) {
    let allumee = 0;
    const g = groupe(parent, x, y, k, () => { allumee = allumee > 0.5 ? 0 : 1; });
    shape(g).setAttribute('d', 'M-30 0 Q-33 -20 -29 -36 L29 -36 Q33 -20 30 0Z');
    const porte = paint(g, PAPER, PEN * 0.9);
    porte.setAttribute('d', 'M-7 0 L-7 -18 Q0 -26 7 -18 L7 0Z');
    const toit = paint(g, d.couleur || '#c9a46a');
    toit.setAttribute('d', 'M-42 -30 Q-20 -60 0 -76 Q20 -60 42 -30 Q0 -40 -42 -30Z');
    const r = seeded(graine);
    pen(g, PEN * 0.5).setAttribute('d', Array.from({ length: 9 }, (_, i) => { const u = -32 + i * 8 + r(-1, 1); return `M${f(u)} ${f(-33 + Math.abs(u) * 0.05)} L${f(u * 0.45)} ${f(-56 + Math.abs(u) * 0.3)}`; }).join(' '));
    return {
      maj(_t, _dt, P) {
        toit.setAttribute('fill', nightTint(d.couleur || '#c9a46a', P));
        porte.setAttribute('fill', fenetre(P, Math.max(P.lamp * 0.8, allumee)));
      },
    };
  },
  lampadaire(_d, parent, ctx, { x, y, k }) {
    let force = 1;
    const halo = el('circle', { cx: f(x), cy: f(y - 92 * k), r: f(46 * k), fill: `url(#${ctx.id}-halo)`, opacity: 0, 'pointer-events': 'none' }, parent);
    const g = groupe(parent, x, y, k, () => { force = force > 0.5 ? 0 : 1; });
    shape(g, PEN * 0.9).setAttribute('d', 'M-3 0 L-1.6 -84 L1.6 -84 L3 0Z');
    const verre = paint(g, PAPER, PEN * 0.9);
    verre.setAttribute('d', 'M-8 -84 L8 -84 L6 -98 L-6 -98Z');
    shape(g, PEN * 0.9).setAttribute('d', 'M-10 -98 L10 -98 L0 -106Z');
    pen(g, PEN * 0.7).setAttribute('d', 'M-6 0 L6 0');
    return {
      maj(_t, _dt, P) {
        const l = P.lamp * force;
        halo.setAttribute('opacity', f(l * 0.85));
        verre.setAttribute('fill', fenetre(P, l));
      },
    };
  },
  banc(_d, parent, _ctx, { x, y, k }) {
    const g = groupe(parent, x, y, k);
    shape(g, PEN * 0.9).setAttribute('d', 'M-30 -16 L30 -16 L30 -12 L-30 -12Z');
    shape(g, PEN * 0.9).setAttribute('d', 'M-30 -30 L30 -30 L30 -25 L-30 -25Z');
    pen(g, PEN).setAttribute('d', 'M-26 -12 L-27 0 M26 -12 L27 0 M-24 -25 L-24 -16 M24 -25 L24 -16');
    return { maj() {} };
  },
  fleurs(d, parent, _ctx, { x, y, k }, graine) {
    const r = seeded(graine);
    const couleurs = d.couleur ? [d.couleur, d.couleur, '#f2d16b'] : ['#e07a5f', '#f2d16b', '#c46aa5', '#f4a259'];
    const ressorts = Array.from({ length: 6 }, () => spring(40, 4));
    const g = groupe(parent, x, y, k, () => ressorts.forEach((s, i) => s.kick((i % 2 ? 1 : -1) * 18)));
    const tiges = ressorts.map(() => pen(g, PEN * 0.8));
    const tetes = ressorts.map((_, i) => paint(g, couleurs[i % couleurs.length], PEN * 0.7));
    const pieds = ressorts.map((_, i) => [-22 + i * 9 + r(-2, 2), 18 + r(0, 12)]         );
    return {
      maj(t, dt, P, vent) {
        ressorts.forEach((s, i) => {
          const a = s.step(vent * 6 + Math.sin(t * 1.7 + i) * 2, dt);
          const [px, h] = pieds[i];
          const tx = px + a * 0.5, ty = -h;
          tiges[i].setAttribute('d', `M${f(px)} 0 Q${f(px + a * 0.1)} ${f(-h / 2)} ${f(tx)} ${f(ty)}`);
          tetes[i].setAttribute('d', lobes(tx, ty - 2, 3.6, 3.2, 5, graine + i));
          tetes[i].setAttribute('fill', nightTint(couleurs[i % couleurs.length], P));
        });
      },
    };
  },
  buisson(d, parent, _ctx, { x, y, k }, graine) {
    const s = spring(30, 3);
    const g = groupe(parent, x, y, k, () => s.kick(0.8));
    const masse = paint(g, vert(d.couleur));
    masse.setAttribute('d', lobes(0, -14, 26, 15, 9, graine));
    return {
      maj(t, dt, P, vent) {
        const e = s.step(vent * 0.05, dt);
        masse.setAttribute('transform', `translate(0 0) scale(${f(1 + e * 0.08 + wobble(t + graine) * 0.006)} ${f(1 - e * 0.05)})`);
        masse.setAttribute('fill', nightTint(vert(d.couleur), P, 0.7));
      },
    };
  },
  etal(d, parent, _ctx, { x, y, k }, graine) {
    const balance = pendulum(10, 1.4);
    const g = groupe(parent, x, y, k, () => balance.kick(2));
    pen(g, PEN).setAttribute('d', 'M-34 0 L-34 -62 M34 0 L34 -62');
    shape(g).setAttribute('d', 'M-38 -24 L38 -24 L36 -18 L-36 -18Z');
    pen(g, PEN * 0.8).setAttribute('d', 'M-32 -18 L-30 0 M32 -18 L30 0');
    const r = seeded(graine);
    const fruits = ['#e8833a', '#d64933', '#f2c94c', '#7fb069'];
    for (let i = 0; i < 9; i++) paint(g, fruits[i % 4], PEN * 0.6).setAttribute('d', lobes(-28 + i * 7 + r(-1, 1), -28 - (i % 2) * 3, 3.6, 3.4, 6, graine + i));
    const auvent = el('g', {}, g);
    const toile = paint(auvent, d.couleur || '#d9534f');
    const rayures = pen(auvent, PEN * 0.6);
    return {
      maj(t, dt, P, vent) {
        const a = balance.step(vent * 0.4, dt);
        const bord = -54 + a * 3 + Math.sin(t * 3) * vent * 0.6;
        toile.setAttribute('d', `M-42 -66 L42 -66 L44 ${f(bord)} Q33 ${f(bord + 5)} 22 ${f(bord)} Q11 ${f(bord + 5)} 0 ${f(bord)} Q-11 ${f(bord + 5)} -22 ${f(bord)} Q-33 ${f(bord + 5)} -44 ${f(bord)}Z`);
        rayures.setAttribute('d', `M-22 -66 L-22 ${f(bord)} M0 -66 L0 ${f(bord)} M22 -66 L22 ${f(bord)}`);
        toile.setAttribute('fill', nightTint(d.couleur || '#d9534f', P));
      },
    };
  },
  boutique(d, parent, _ctx, { x, y, k }) {
    let allumee = 0;
    const g = groupe(parent, x, y, k, () => { allumee = allumee > 0.5 ? 0 : 1; });
    shape(g).setAttribute('d', 'M-46 0 L-45 -78 L45 -78 L46 0Z');
    const vitrine = paint(g, PAPER, PEN * 0.9);
    vitrine.setAttribute('d', 'M-38 -8 L-38 -40 L4 -40 L4 -8Z');
    paint(g, '#7a5236', PEN * 0.9).setAttribute('d', 'M14 0 L14 -38 L34 -38 L34 0Z');
    const auvent = paint(g, d.couleur || '#2f6f8f');
    auvent.setAttribute('d', 'M-50 -52 L50 -52 L54 -42 Q45 -38 36 -42 Q27 -38 18 -42 Q9 -38 0 -42 Q-9 -38 -18 -42 Q-27 -38 -36 -42 Q-45 -38 -54 -42Z');
    shape(g, PEN * 0.9).setAttribute('d', 'M-36 -74 L36 -74 L36 -58 L-36 -58Z');
    const enseigne = el('text', { x: 0, y: -62.4, 'font-size': 10, 'font-family': 'Inter, system-ui, sans-serif', 'font-weight': 500, fill: 'currentColor', 'text-anchor': 'middle' }, g);
    enseigne.textContent = d.texte || 'Boutique';
    return {
      maj(_t, _dt, P) {
        vitrine.setAttribute('fill', fenetre(P, Math.max(P.lamp, allumee)));
        auvent.setAttribute('fill', nightTint(d.couleur || '#2f6f8f', P));
      },
    };
  },
  chat(d, parent, _ctx, { x, y, k }) {
    const queue = pendulum(8, 0.9);
    const etire = spring(30, 4);
    const g = groupe(parent, x, y, k * 0.8, () => { queue.kick(5); etire.kick(4); });
    const corps = el('g', {}, g);
    const pelage = d.couleur || '#3a3a3a';
    const dos = paint(corps, pelage);
    dos.setAttribute('d', 'M-14 0 Q-17 -14 -6 -20 Q4 -24 8 -14 Q10 -6 9 0Z');
    const tete = paint(corps, pelage);
    tete.setAttribute('d', 'M2 -20 Q2 -28 8 -28 L9 -33 L12 -28 L15 -33 L16 -27 Q19 -22 15 -18 Q9 -15 4 -17Z');
    const qu = pen(g, 2.2);
    return {
      maj(t, dt, P, vent) {
        const a = queue.step(vent * 0.3 + Math.sin(t * 1.1) * 0.4, dt);
        const e = etire.step(0, dt);
        corps.setAttribute('transform', `scale(1 ${f(1 + e * 0.02)})`);
        qu.setAttribute('d', `M-13 -2 Q${f(-24 + a * 4)} ${f(-4)} ${f(-22 + a * 8)} ${f(-16 - a * 2)}`);
        dos.setAttribute('fill', nightTint(pelage, P, 0.4));
        tete.setAttribute('fill', nightTint(pelage, P, 0.4));
      },
    };
  },
  cloture(_d, parent, _ctx, { x, y, k }) {
    const g = groupe(parent, x, y, k);
    pen(g, PEN).setAttribute('d', Array.from({ length: 7 }, (_, i) => `M${-42 + i * 14} 0 L${-42 + i * 14} -30 L${-39 + i * 14} -34`).join(' ') + ' M-46 -10 L46 -10 M-46 -22 L46 -22');
    return { maj() {} };
  },
  // Les grands fonds : ils couvrent toute la largeur, leur position x est ignorée.
  collines(d, parent, ctx, _pose, graine) {
    const r = seeded(graine);
    const h = ctx.sol;
    let dd = `M0 ${f(h)} L0 ${f(h - ctx.hauteur * 0.08)}`;
    for (let i = 1; i <= 4; i++) dd += ` Q${f(ctx.largeur * (i - 0.5) / 4)} ${f(h - ctx.hauteur * r(0.12, 0.2))} ${f(ctx.largeur * i / 4)} ${f(h - ctx.hauteur * r(0.05, 0.1))}`;
    const forme = paint(parent, d.couleur || '#c9d8b0', PEN);
    forme.setAttribute('d', dd + ` L${f(ctx.largeur)} ${f(h)}Z`);
    return { maj(_t, _dt, P) { forme.setAttribute('fill', nightTint(d.couleur || '#c9d8b0', P, 0.8)); } };
  },
  mer(d, parent, ctx) {
    const haut = ctx.sol - ctx.hauteur * 0.07;
    const eau = paint(parent, d.couleur || '#9cc3d5', PEN);
    eau.setAttribute('d', `M0 ${f(haut)} L${f(ctx.largeur)} ${f(haut)} L${f(ctx.largeur)} ${f(ctx.sol)} L0 ${f(ctx.sol)}Z`);
    const vagues = pen(parent, PEN * 0.8);
    return {
      maj(t, _dt, P) {
        eau.setAttribute('fill', nightTint(d.couleur || '#9cc3d5', P, 0.8));
        const decalage = (t * 18) % 40;
        let v = '';
        for (let rang = 0; rang < 2; rang++) {
          const yy = haut + (ctx.sol - haut) * (0.35 + rang * 0.35);
          for (let xx = -40 + decalage + rang * 20; xx < ctx.largeur; xx += 40) v += `M${f(xx)} ${f(yy)} q5 -3 10 0 `;
        }
        vagues.setAttribute('d', v);
      },
    };
  },
  immeubles(_d, parent, ctx, _pose, graine) {
    const r = seeded(graine);
    const g = el('g', {}, parent);
    const vitres                   = [];
    let xx = 0;
    while (xx < ctx.largeur) {
      const l = ctx.largeur * r(0.06, 0.11), h = ctx.hauteur * r(0.14, 0.32);
      shape(g, PEN * 0.8).setAttribute('d', `M${f(xx)} ${f(ctx.sol)} L${f(xx)} ${f(ctx.sol - h)} L${f(xx + l)} ${f(ctx.sol - h)} L${f(xx + l)} ${f(ctx.sol)}Z`);
      let fen = '';
      for (let fy = ctx.sol - h + 10; fy < ctx.sol - 14; fy += 16) for (let fx = xx + 6; fx < xx + l - 8; fx += 12) if (r() > 0.45) fen += `M${f(fx)} ${f(fy)} l5 0 l0 7 l-5 0Z`;
      const v = el('path', { d: fen, fill: LUMIERE, opacity: 0 }, g);
      vitres.push(v);
      xx += l + ctx.largeur * r(0.005, 0.02);
    }
    return { maj(_t, _dt, P) { vitres.forEach((v) => v.setAttribute('opacity', f(P.lamp))); } };
  },
  nuage(_d, parent, ctx, { x, k }, graine) {
    const gonfle = spring(30, 4);
    const yy = ctx.sol * (0.18 + seeded(graine)() * 0.22);
    let xx = x;
    const g = groupe(parent, xx, yy, k * 0.9, () => gonfle.kick(2));
    const forme = shape(g, PEN * 0.9);
    forme.setAttribute('d', lobes(0, 0, 34, 12, 8, graine));
    return {
      maj(_t, dt, _P, vent) {
        xx += (6 + vent * 10) * dt;
        if (xx > ctx.largeur + 80) xx = -80;
        if (xx < -80) xx = ctx.largeur + 80;
        const e = gonfle.step(0, dt);
        g.setAttribute('transform', `translate(${f(xx)} ${f(yy)}) scale(${f(k * 0.9 * (1 + e * 0.04))})`);
      },
    };
  },
  oiseaux(_d, parent, ctx, { x, k }, graine) {
    const r = seeded(graine);
    let fuite = 0;
    const g = el('g', { style: 'cursor:pointer' }, parent);
    g.addEventListener('pointerdown', (e) => { e.stopPropagation(); fuite = 2.4; });
    const vol = Array.from({ length: 4 }, () => ({ trait: pen(g, PEN * 1.1), dx: r(-30, 30), dy: r(-14, 14), ph: r(0, 6) }));
    // Une zone à toucher autour de la volée.
    const zone = el('rect', { width: 90, height: 60, fill: 'transparent', 'pointer-events': 'all' }, g);
    let xx = x, yy = ctx.sol * 0.3;
    return {
      maj(t, dt) {
        fuite = Math.max(0, fuite - dt);
        xx += (28 + fuite * 50) * k * 0.5 * dt;
        yy += (-fuite * 18 + Math.sin(t * 0.4 + graine) * 3) * dt;
        if (xx > ctx.largeur + 60) { xx = -60; yy = ctx.sol * r(0.2, 0.4); }
        zone.setAttribute('x', f(xx - 45)); zone.setAttribute('y', f(yy - 30));
        vol.forEach((o) => {
          const a = Math.sin(t * (9 + fuite * 4) + o.ph) * 4 * k * 0.6;
          const bx = xx + o.dx * k * 0.6, by = yy + o.dy * k * 0.6, l = 6 * k * 0.6;
          o.trait.setAttribute('d', `M${f(bx - l)} ${f(by - a)} Q${f(bx - l / 2)} ${f(by - a * 0.3 - 1)} ${f(bx)} ${f(by)} Q${f(bx + l / 2)} ${f(by - a * 0.3 - 1)} ${f(bx + l)} ${f(by - a)}`);
        });
      },
    };
  },
};
/** Les éléments posés au fond, sur toute la largeur. */
const FONDS_LARGES = ['collines', 'mer', 'immeubles'];
/** Les éléments du ciel. */
const DU_CIEL = ['nuage', 'oiseaux'];
return { creerElement, FONDS_LARGES, DU_CIEL };
})();
__modules["kekeli/scene"] = (() => {
// Kekeli Motion : la scène entière, montée dans une page à partir de sa description (JSON). Une carte qui se suffit
// à elle-même : la page autour ne change jamais de couleur. Le ciel suit l'heure (jour, coucher, nuit), le soleil et
// la lune se touchent pour passer du jour à la nuit, le doigt ou la souris font le vent.
const { el, pen, shape, paint, inkDefs, f, PEN } = __modules["lib/ink"];
const { pointerWind, seeded, clamp, spring } = __modules["lib/motion"];
const { palette, applyPalette, rgb, mix, toRGB } = __modules["lib/daynight"];
const { creerPersonnage } = __modules["kekeli/personnage"];
const { creerElement, FONDS_LARGES, DU_CIEL } = __modules["kekeli/decor"];
const { normaliser } = __modules["kekeli/normaliser"];
const { DIMENSIONS, MOMENTS } = __modules["kekeli/types"];
let compteur = 0;
function monter(conteneur         , brute         , options          = {})            {
  const scene        = normaliser(brute);
  const id = options.id ?? `kekeli-motion-${++compteur}`;
  const [W, H] = DIMENSIONS[scene.format];
  const sol = Math.round(H * (scene.format === 'vertical' ? 0.74 : 0.7));
  const echelle = Math.min(W, H) * (scene.format === 'paysage' ? 0.0042 : 0.0036);
  const svg = el('svg', {
    viewBox: `0 0 ${W} ${H}`,
    width: W,
    height: H,
    class: 'kekeli-motion',
    role: 'img',
    'aria-label': scene.titre,
    style: 'display:block;width:100%;height:auto;border-radius:12px;touch-action:manipulation;-webkit-tap-highlight-color:transparent',
  });
  conteneur.appendChild(svg);
  el('style', {}, svg).textContent = '.pp{fill:var(--paper)}text{user-select:none;-webkit-user-select:none}';
  const { defs, filter } = inkDefs(svg, id);
  const degrade = el('linearGradient', { id: `${id}-ciel`, x1: 0, y1: 0, x2: 0, y2: 1 }, defs);
  const haut = el('stop', { offset: 0 }, degrade);
  const bas = el('stop', { offset: 1 }, degrade);
  const halo = el('radialGradient', { id: `${id}-halo` }, defs);
  el('stop', { offset: 0, 'stop-color': '#ffd98a', 'stop-opacity': 0.75 }, halo);
  el('stop', { offset: 1, 'stop-color': '#ffd98a', 'stop-opacity': 0 }, halo);
  const decoupe = el('clipPath', { id: `${id}-horizon` }, defs);
  el('rect', { x: 0, y: 0, width: W, height: sol }, decoupe);
  // Les couches, du fond vers le devant.
  const ciel = el('rect', { x: 0, y: 0, width: W, height: sol + 2, fill: `url(#${id}-ciel)` }, svg);
  const etoiles = el('g', {}, svg);
  const astres = el('g', { 'clip-path': `url(#${id}-horizon)` }, svg);
  const coucheCiel = el('g', {}, svg);
  const fond = el('g', { filter }, svg);
  const terre = el('g', { filter }, svg);
  const milieu = el('g', { filter }, svg);
  const personnages = el('g', { filter }, svg);
  const devant = el('g', { filter }, svg);
  const bulles = el('g', {}, svg);
  // Les étoiles, toujours les mêmes.
  const r = seeded(7);
  const points = Array.from({ length: 46 }, () => `M${f(r(0, W))} ${f(r(0, sol * 0.62))} m-0.9 0 a0.9 0.9 0 1 0 1.8 0 a0.9 0.9 0 1 0 -1.8 0`).join('');
  const ciblesEtoiles = el('path', { d: points, fill: 'currentColor', opacity: 0 }, etoiles);
  // Le soleil et la lune, sur un arc au-dessus de l'horizon ; les toucher fait passer au jour ou à la nuit.
  const rayon = Math.min(W, H) * 0.045;
  const soleil = el('g', { style: 'cursor:pointer', role: 'button', 'aria-label': 'Soleil' }, astres);
  const rayons = pen(soleil, PEN * 1.2);
  const disque = paint(soleil, '#f3c35b', PEN * 1.2);
  el('circle', { r: rayon * 2, fill: 'transparent', 'pointer-events': 'all' }, soleil);
  const lune = el('g', { style: 'cursor:pointer', role: 'button', 'aria-label': 'Lune' }, astres);
  shape(lune, PEN * 1.2).setAttribute('d', `M0 ${f(-rayon * 0.85)} a${f(rayon * 0.85)} ${f(rayon * 0.85)} 0 1 0 0.01 0Z`);
  pen(lune, PEN).setAttribute('d', `M${f(-rayon * 0.3)} ${f(-rayon * 0.2)} a${f(rayon * 0.16)} ${f(rayon * 0.16)} 0 1 0 0.01 0 M${f(rayon * 0.25)} ${f(rayon * 0.3)} a${f(rayon * 0.1)} ${f(rayon * 0.1)} 0 1 0 0.01 0`);
  el('circle', { r: rayon * 2, fill: 'transparent', 'pointer-events': 'all' }, lune);
  let decalage = 0, cible = 0;
  const basculer = () => { cible += 0.5; reveiller(); };
  soleil.addEventListener('pointerdown', (e) => { e.stopPropagation(); basculer(); });
  lune.addEventListener('pointerdown', (e) => { e.stopPropagation(); basculer(); });
  // Le sol : le papier de la carte, une ligne d'horizon et une texture selon le décor.
  el('rect', { x: 0, y: sol, width: W, height: H - sol, fill: '#fdfdfc' }, terre);
  pen(terre, PEN * 1.1).setAttribute('d', `M0 ${sol} L${W} ${sol}`);
  const t = seeded(13);
  let texture = '';
  if (['jardin', 'parc', 'campagne'].includes(scene.decor)) {
    for (let i = 0; i < 34; i++) { const x = t(0, W), y = t(sol + 8, H - H * 0.08); texture += `M${f(x)} ${f(y)} q1 -3 3 -6 M${f(x + 3)} ${f(y)} q0 -3 -1 -5 `; }
  } else if (['rue', 'ville'].includes(scene.decor)) {
    texture = `M0 ${f(sol + 12)} L${W} ${f(sol + 12)} ` + Array.from({ length: Math.ceil(W / 36) }, (_, i) => `M${i * 36 + 12} ${sol} l0 12`).join(' ');
  } else if (['plage'].includes(scene.decor)) {
    for (let i = 0; i < 90; i++) { const x = t(0, W), y = t(sol + 6, H - H * 0.08); texture += `M${f(x)} ${f(y)} l0.6 0 `; }
  } else if (['village', 'marche'].includes(scene.decor)) {
    for (let i = 0; i < 26; i++) { const x = t(0, W), y = t(sol + 10, H - H * 0.08); texture += `M${f(x)} ${f(y)} q3 -2 6 0 `; }
  }
  if (texture) pen(terre, PEN * 0.6).setAttribute('d', texture);
  // Les éléments du décor, puis les personnages sur deux rangs.
  const contexte = { largeur: W, hauteur: H, sol, echelle, id };
  const objets               = scene.elements.map((e, i) => {
    const parent = DU_CIEL.includes(e.type) ? coucheCiel : FONDS_LARGES.includes(e.type) || e.plan === 'fond' ? fond : e.plan === 'devant' ? devant : milieu;
    return creerElement(e, parent, contexte, 31 + i * 17);
  });
  const rangs = [el('g', {}, personnages), el('g', {}, personnages)];
  const acteurs               = scene.personnages.map((p, i) => creerPersonnage(p, {
    couche: rangs[i % 2],
    bulles,
    largeur: W,
    sol: sol + (H - sol) * (i % 2 ? 0.34 : 0.1),
    echelle: echelle * (i % 2 ? 1.08 : 1),
  }));
  // Une seule ligne discrète en bas, jamais un bouton.
  if (scene.indication) {
    const ligne = el('text', { x: W / 2, y: H - Math.max(14, H * 0.03), 'font-size': Math.round(Math.min(W, H) * 0.027), 'font-family': 'Inter, system-ui, sans-serif', 'text-anchor': 'middle', fill: 'currentColor', opacity: 0.55 }, svg);
    ligne.textContent = scene.indication;
  }
  const vent = pointerWind(svg, 0.25);
  const papier = toRGB(scene.papier);
  const gonfle = spring(40, 6);
  const dessiner = (temps        , dt        ) => {
    decalage += (cible - decalage) * Math.min(1, dt * 1.6);
    const tau = (MOMENTS[scene.ciel.moment] + (scene.ciel.anime ? temps / scene.ciel.duree_jour : 0) + decalage) % 1;
    const P          = palette(tau);
    // Un papier chaud le jour (celui choisi), qui garde la couleur de la nuit.
    P.paper = mix(P.paper, papier, (1 - P.inkT) * 0.8);
    applyPalette(svg, P);
    haut.setAttribute('stop-color', rgb(P.top));
    bas.setAttribute('stop-color', rgb(P.bottom));
    ciblesEtoiles.setAttribute('opacity', f(P.night * 0.9));
    const v = vent.step(temps);
    // Le soleil à τ, la lune à l'opposé ; au-dessus de l'horizon seulement (découpe).
    const place = (u        )                   => {
      const a = (u - 0.25) * Math.PI * 2;
      return [W / 2 - Math.cos(a) * W * 0.42, sol - Math.sin(a) * sol * 0.78];
    };
    const [sx, sy] = place(tau);
    const g = 1 + gonfle.step(0, dt) * 0.04;
    soleil.setAttribute('transform', `translate(${f(sx)} ${f(sy)}) scale(${f(g)})`);
    disque.setAttribute('d', `M0 ${f(-rayon)} a${f(rayon)} ${f(rayon)} 0 1 0 0.01 0Z`);
    disque.setAttribute('fill', rgb(mix(toRGB('#f3c35b'), toRGB('#e9845a'), P.sunset)));
    rayons.setAttribute('d', Array.from({ length: 8 }, (_, i) => {
      const a = i * Math.PI / 4 + temps * 0.15;
      return `M${f(Math.cos(a) * rayon * 1.3)} ${f(Math.sin(a) * rayon * 1.3)} L${f(Math.cos(a) * rayon * 1.65)} ${f(Math.sin(a) * rayon * 1.65)}`;
    }).join(' '));
    const [lx, ly] = place(tau + 0.5);
    lune.setAttribute('transform', `translate(${f(lx)} ${f(ly)})`);
    for (const o of objets) o.maj(temps, dt, P, v);
    for (const a of acteurs) a.dessiner(temps, dt, P, v);
  };
  // La boucle : seulement quand la carte est visible et l'onglet ouvert (batterie du téléphone).
  let temps = 0, prec = 0, idBoucle = 0, visible = true, arretee = false, eveilJusqua = 0;
  const reduit = typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion: reduce)').matches;
  const tourner = (now        ) => {
    idBoucle = 0;
    const dt = prec ? Math.min(0.05, (now - prec) / 1000) : 1 / 60;
    prec = now;
    temps += dt;
    dessiner(temps, dt);
    if (!arretee && visible && (!reduit || temps < eveilJusqua) && !(typeof document !== 'undefined' && document.hidden)) idBoucle = requestAnimationFrame(tourner);
    else prec = 0;
  };
  function reveiller() {
    if (reduit) eveilJusqua = temps + 3;
    if (!options.statique && !idBoucle && !arretee) idBoucle = requestAnimationFrame(tourner);
  }
  svg.addEventListener('pointerdown', () => { gonfle.kick(0); reveiller(); });
  const avancer = (secondes        ) => {
    for (let i = 0, n = Math.round(secondes * 30); i < n; i++) dessiner((temps += 1 / 30), 1 / 30);
  };
  if (options.statique || reduit) {
    avancer(clamp(options.temps ?? 2.5, 0, 120));
  } else {
    dessiner(0, 1 / 60);
    idBoucle = requestAnimationFrame(tourner);
    if (typeof IntersectionObserver === 'function') {
      new IntersectionObserver((entrees) => { visible = entrees.some((e) => e.isIntersecting); if (visible) reveiller(); }).observe(svg);
    }
    if (typeof document !== 'undefined') document.addEventListener('visibilitychange', () => { if (!document.hidden) reveiller(); });
  }
  return {
    svg,
    arreter() { arretee = true; if (idBoucle) cancelAnimationFrame(idBoucle); idBoucle = 0; },
    avancer,
  };
}
return { monter };
})();
__modules["kekeli/index"] = (() => {
// Kekeli Motion : le point d'entrée. Dans une page, chaque <div data-kekeli-motion="nom"> reçoit l'animation
// enregistrée sous ce nom (KekeliMotion.enregistrer), ou celle écrite dans un <script type="application/json"> à
// l'intérieur du div. Rien d'autre n'est nécessaire : aucune bibliothèque, aucun service extérieur.
const { monter } = __modules["kekeli/scene"];
const { normaliser } = __modules["kekeli/normaliser"];
const scenes                          = {};
const montees = new WeakSet         ();
function enregistrer(nom        , scene         )       {
  scenes[nom] = scene;
  if (typeof document !== 'undefined' && document.readyState !== 'loading') monterTout();
}
/** Monte toutes les animations de la page qui ne le sont pas encore. */
function monterTout(racine             = document)              {
  const faites              = [];
  racine.querySelectorAll('[data-kekeli-motion]').forEach((noeud) => {
    if (montees.has(noeud)) return;
    const nom = noeud.getAttribute('data-kekeli-motion') ?? '';
    const ecrite = noeud.querySelector('script[type="application/json"]');
    let scene          = scenes[nom];
    if (!scene && ecrite) {
      try { scene = JSON.parse(ecrite.textContent ?? ''); } catch { scene = null; }
    }
    if (!scene) return;
    montees.add(noeud);
    const options          = { statique: noeud.hasAttribute('data-statique') };
    faites.push(monter(noeud, scene, options));
  });
  return faites;
}
if (typeof window !== 'undefined' && typeof document !== 'undefined') {
  window.KekeliMotion = { monter, monterTout, enregistrer, normaliser };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => monterTout());
  else monterTout();
}
return { monter, normaliser, enregistrer, monterTout };
})();
export const { monter, normaliser, monterTout } = __modules["kekeli/index"];
export const { TYPES_ELEMENTS, PERSONNAGES_MAX, ELEMENTS_MAX } = __modules["kekeli/normaliser"];
