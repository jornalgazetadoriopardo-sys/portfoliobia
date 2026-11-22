/* Scanner de fotos — processamento com OpenCV.js, fora da tela principal (Web Worker).
   Mensagens: { id, cmd, ...dados } -> { id, ok, ...resultado } */
'use strict';

let cv = null;
let srcFull = null;     // foto como veio da câmera (até ~3000 px)
let srcSmall = null;    // cópia reduzida para detectar bordas e mostrar na tela
let warped = null;      // foto recortada e endireitada (até 2400 px)
let warpedPrev = null;  // a mesma, reduzida para a pré-visualização

const PREVIEW_MAX = 1100;
const OUTPUT_MAX = 2400;

const free = (...mats) => mats.forEach(m => { if (m && !m.isDeleted()) m.delete(); });
const toImageData = mat => new ImageData(new Uint8ClampedArray(mat.data), mat.cols, mat.rows);

function resized(mat, maxSide) {
  const scale = Math.min(1, maxSide / Math.max(mat.cols, mat.rows));
  const out = new cv.Mat();
  if (scale === 1) mat.copyTo(out);
  else cv.resize(mat, out, new cv.Size(Math.round(mat.cols * scale), Math.round(mat.rows * scale)), 0, 0, cv.INTER_AREA);
  return out;
}

/* ---------- Detecção das bordas ---------- */

/** Ordena quatro pontos: superior esquerdo, superior direito, inferior direito, inferior esquerdo. */
function orderPoints(p) {
  const sum = p.map(q => q.x + q.y), diff = p.map(q => q.y - q.x);
  const at = (arr, fn) => p[arr.indexOf(fn(...arr))];
  return [at(sum, Math.min), at(diff, Math.min), at(sum, Math.max), at(diff, Math.max)];
}

/** Maior quadrilátero convexo de uma imagem binária; guarda também o maior contorno para o plano B. */
function findQuad(bin, minArea, maxArea, state) {
  const contours = new cv.MatVector(), hierarchy = new cv.Mat();
  cv.findContours(bin, contours, hierarchy, cv.RETR_LIST, cv.CHAIN_APPROX_SIMPLE);
  let best = null, bestArea = 0;
  for (let i = 0; i < contours.size(); i++) {
    const c = contours.get(i);
    const area = cv.contourArea(c);
    if (area >= minArea && area <= maxArea) {
      if (area > state.largestArea) {
        state.largestArea = area;
        const rect = cv.minAreaRect(c);
        state.largestRect = cv.RotatedRect.points(rect).map(q => ({ x: q.x, y: q.y }));
      }
      const hull = new cv.Mat();
      cv.convexHull(c, hull, false, true);
      const peri = cv.arcLength(hull, true);
      for (const eps of [0.02, 0.035, 0.05]) {
        const approx = new cv.Mat();
        cv.approxPolyDP(hull, approx, eps * peri, true);
        const ok = approx.rows === 4 && cv.isContourConvex(approx);
        const quadArea = ok ? cv.contourArea(approx) : 0;
        if (ok && quadArea > bestArea && quadArea >= minArea) {
          bestArea = quadArea;
          const d = approx.data32S;
          best = [0, 1, 2, 3].map(k => ({ x: d[k * 2], y: d[k * 2 + 1] }));
        }
        approx.delete();
        if (ok) break;
      }
      hull.delete();
    }
    c.delete();
  }
  free(contours, hierarchy);
  return best;
}

function detect() {
  const W = srcSmall.cols, H = srcSmall.rows, total = W * H;
  const minArea = total * 0.08, maxArea = total * 0.985;
  const state = { largestArea: 0, largestRect: null };
  const gray = new cv.Mat(), blur = new cv.Mat(), bin = new cv.Mat();
  const kernel = cv.getStructuringElement(cv.MORPH_RECT, new cv.Size(3, 3));
  const anchor = new cv.Point(-1, -1);
  let quad = null, method = '';
  try {
    cv.cvtColor(srcSmall, gray, cv.COLOR_RGBA2GRAY);
    cv.GaussianBlur(gray, blur, new cv.Size(5, 5), 0);

    // 1) Bordas (Canny) engrossadas para fechar o contorno da foto
    cv.Canny(blur, bin, 40, 120);
    cv.dilate(bin, bin, kernel, anchor, 2);
    quad = findQuad(bin, minArea, maxArea, state);
    if (quad) method = 'bordas';

    // 2) Foto clara sobre fundo escuro (ou o contrário): separação por brilho
    if (!quad) {
      for (const type of [cv.THRESH_BINARY, cv.THRESH_BINARY_INV]) {
        cv.threshold(blur, bin, 0, 255, type + cv.THRESH_OTSU);
        cv.morphologyEx(bin, bin, cv.MORPH_CLOSE, kernel, anchor, 3);
        quad = findQuad(bin, minArea, maxArea, state);
        if (quad) { method = 'brilho'; break; }
      }
    }

    // 3) Iluminação desigual: limiar adaptativo
    if (!quad) {
      cv.adaptiveThreshold(blur, bin, 255, cv.ADAPTIVE_THRESH_GAUSSIAN_C, cv.THRESH_BINARY_INV, 21, 5);
      cv.dilate(bin, bin, kernel, anchor, 2);
      quad = findQuad(bin, minArea, maxArea, state);
      if (quad) method = 'adaptativo';
    }
  } finally {
    free(gray, blur, bin, kernel);
  }

  // Plano B: retângulo inclinado em volta do maior contorno; plano C: imagem com 5% de margem.
  if (!quad && state.largestRect) { quad = state.largestRect; method = 'retangulo'; }
  if (!quad) {
    const mx = W * 0.05, my = H * 0.05;
    quad = [{ x: mx, y: my }, { x: W - mx, y: my }, { x: W - mx, y: H - my }, { x: mx, y: H - my }];
    method = 'margem';
  }
  const clamp01 = v => Math.min(1, Math.max(0, v));
  return {
    corners: orderPoints(quad).map(q => ({ x: clamp01(q.x / W), y: clamp01(q.y / H) })),
    found: method !== 'margem' && method !== 'retangulo',
    method,
  };
}

/* ---------- Perspectiva ---------- */
function warp(corners) {
  const W = srcFull.cols, H = srcFull.rows;
  const p = corners.map(c => ({ x: c.x * W, y: c.y * H }));
  const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
  let w = Math.max(dist(p[0], p[1]), dist(p[3], p[2]));
  let h = Math.max(dist(p[0], p[3]), dist(p[1], p[2]));
  const scale = Math.min(1, OUTPUT_MAX / Math.max(w, h));
  w = Math.max(8, Math.round(w * scale));
  h = Math.max(8, Math.round(h * scale));

  const from = cv.matFromArray(4, 1, cv.CV_32FC2, p.flatMap(q => [q.x, q.y]));
  const to = cv.matFromArray(4, 1, cv.CV_32FC2, [0, 0, w, 0, w, h, 0, h]);
  const M = cv.getPerspectiveTransform(from, to);
  free(warped, warpedPrev);
  warped = new cv.Mat();
  cv.warpPerspective(srcFull, warped, M, new cv.Size(w, h), cv.INTER_LINEAR, cv.BORDER_REPLICATE, new cv.Scalar());
  free(from, to, M);
  warpedPrev = resized(warped, PREVIEW_MAX);
}

/* ---------- Tratamento ---------- */

/** Percentis 1% e 99% de cada canal, medidos numa cópia pequena. */
function channelRange(rgb) {
  const small = resized(rgb, 320);
  const d = small.data, n = small.cols * small.rows;
  const hist = [new Uint32Array(256), new Uint32Array(256), new Uint32Array(256)];
  for (let i = 0; i < n; i++) { hist[0][d[i * 3]]++; hist[1][d[i * 3 + 1]]++; hist[2][d[i * 3 + 2]]++; }
  small.delete();
  return hist.map(h => {
    let lo = 0, hi = 255, acc = 0;
    for (let v = 0; v < 256; v++) { acc += h[v]; if (acc >= n * 0.01) { lo = v; break; } }
    acc = 0;
    for (let v = 255; v >= 0; v--) { acc += h[v]; if (acc >= n * 0.01) { hi = v; break; } }
    return { lo, hi: Math.max(hi, lo + 1), mean: h.reduce((s, c, v) => s + c * v, 0) / n };
  });
}

/** Sugere a intensidade de cada ajuste a partir da própria foto (modo automático). */
function suggest() {
  const rgb = new cv.Mat(), gray = new cv.Mat(), illum = new cv.Mat();
  const mean = new cv.Mat(), std = new cv.Mat();
  const lim = (v, a, b) => Math.round(Math.min(b, Math.max(a, v)) * 100);
  try {
    cv.cvtColor(warpedPrev, rgb, cv.COLOR_RGBA2RGB);
    const r = channelRange(rgb);
    const means = r.map(c => c.mean), avg = (means[0] + means[1] + means[2]) / 3 || 1;
    const cast = (Math.max(...means) - Math.min(...means)) / avg;
    const range = (r.reduce((s, c) => s + (c.hi - c.lo), 0) / 3) / 255;

    cv.cvtColor(rgb, gray, cv.COLOR_RGB2GRAY);
    cv.resize(gray, illum, new cv.Size(48, 48), 0, 0, cv.INTER_AREA);
    cv.GaussianBlur(illum, illum, new cv.Size(0, 0), 8);
    cv.meanStdDev(illum, mean, std);
    const uneven = std.doubleAt(0, 0) / (mean.doubleAt(0, 0) || 1);

    return {
      sombra: lim(0.25 + uneven * 1.6, 0.25, 0.7),
      cor: lim(0.35 + cast * 2.2, 0.35, 0.8),
      contraste: lim(1.15 - range, 0.25, 0.75),
      nitidez: 50,
    };
  } finally {
    free(rgb, gray, illum, mean, std);
  }
}

/** Aplica os ajustes. Cada opção vai de 0 (desligado) a 1. */
function enhance(src, o) {
  const tmp = [];
  const T = m => { tmp.push(m); return m; };
  const out = new cv.Mat();
  try {
    const rgb = T(new cv.Mat());
    cv.cvtColor(src, rgb, cv.COLOR_RGBA2RGB);

    // Cor: tira o amarelado esticando cada canal entre os percentis 1% e 99%
    if (o.cor > 0) {
      const ranges = channelRange(rgb);
      const ch = T(new cv.MatVector());
      cv.split(rgb, ch);
      for (let i = 0; i < 3; i++) {
        const full = Math.min(1.8, 255 / (ranges[i].hi - ranges[i].lo));
        const alpha = 1 + o.cor * (full - 1);
        const beta = -ranges[i].lo * full * o.cor;
        const c = ch.get(i);
        c.convertTo(c, -1, alpha, beta);
        c.delete();
      }
      cv.merge(ch, rgb);
    }

    if (o.sombra > 0 || o.contraste > 0 || o.cor > 0) {
      const lab = T(new cv.Mat());
      cv.cvtColor(rgb, lab, cv.COLOR_RGB2Lab);
      const ch = T(new cv.MatVector());
      cv.split(lab, ch);
      const L = ch.get(0), A = ch.get(1), B = ch.get(2);
      tmp.push(L, A, B);

      // Sombra: estima a iluminação (versão muito borrada da luminosidade) e nivela,
      // misturando com o original para não "lavar" a foto.
      if (o.sombra > 0) {
        const tiny = T(new cv.Mat()), illum = T(new cv.Mat()), Lf = T(new cv.Mat()), If = T(new cv.Mat()), flat = T(new cv.Mat());
        const k = Math.min(1, 160 / Math.max(L.cols, L.rows));
        const tw = Math.max(8, Math.round(L.cols * k)), th = Math.max(8, Math.round(L.rows * k));
        cv.resize(L, tiny, new cv.Size(tw, th), 0, 0, cv.INTER_AREA);
        const kernel = T(cv.getStructuringElement(cv.MORPH_ELLIPSE, new cv.Size(9, 9)));
        cv.dilate(tiny, tiny, kernel);
        cv.GaussianBlur(tiny, tiny, new cv.Size(0, 0), Math.max(tw, th) / 6);
        cv.resize(tiny, illum, new cv.Size(L.cols, L.rows), 0, 0, cv.INTER_LINEAR);
        const avg = cv.mean(illum)[0] || 128;
        L.convertTo(Lf, cv.CV_32F);
        illum.convertTo(If, cv.CV_32F, 1, 1);
        cv.divide(Lf, If, flat, avg);
        cv.addWeighted(Lf, 1 - o.sombra, flat, o.sombra, 0, Lf);
        Lf.convertTo(L, cv.CV_8U);
      }

      // Contraste local (CLAHE) só na luminosidade
      if (o.contraste > 0) {
        if (typeof cv.CLAHE === 'function') {
          const clahe = new cv.CLAHE(1 + 1.6 * o.contraste, new cv.Size(8, 8));
          const eq = T(new cv.Mat());
          clahe.apply(L, eq);
          clahe.delete();
          cv.addWeighted(L, 0.35, eq, 0.65, 0, L);
        } else {
          L.convertTo(L, -1, 1 + 0.25 * o.contraste, -16 * o.contraste);
        }
      }

      // Leve reforço de saturação: afasta os canais de cor do cinza (128)
      if (o.cor > 0) {
        const sat = 1 + 0.14 * o.cor;
        A.convertTo(A, -1, sat, 128 * (1 - sat));
        B.convertTo(B, -1, sat, 128 * (1 - sat));
      }
      cv.merge(ch, lab);
      cv.cvtColor(lab, rgb, cv.COLOR_Lab2RGB);
    }

    // Nitidez: reduz o granulado e depois realça os contornos (unsharp mask)
    if (o.nitidez > 0) {
      const res = Math.max(src.cols, src.rows) / PREVIEW_MAX;
      if (typeof cv.bilateralFilter === 'function') {
        const smooth = T(new cv.Mat());
        cv.bilateralFilter(rgb, smooth, 5, 22, 5, cv.BORDER_DEFAULT);
        smooth.copyTo(rgb);
      } else {
        cv.medianBlur(rgb, rgb, 3);
      }
      const soft = T(new cv.Mat());
      cv.GaussianBlur(rgb, soft, new cv.Size(0, 0), Math.max(0.8, res * 1.1));
      const amount = 0.25 + 0.9 * o.nitidez;
      cv.addWeighted(rgb, 1 + amount, soft, -amount, 0, rgb);
    }

    cv.cvtColor(rgb, out, cv.COLOR_RGB2RGBA);
    return out;
  } catch (err) {
    out.delete();
    throw err;
  } finally {
    tmp.forEach(m => { try { m.delete(); } catch (_) { /* já liberado */ } });
  }
}

/* ---------- Comandos ---------- */
function smallReply() {
  const img = toImageData(srcSmall);
  return { data: { image: img, ...detect() }, transfer: [img.data.buffer] };
}

const commands = {
  async init({ opencv }) {
    importScripts(opencv);
    let mod = self.cv;
    if (mod && typeof mod.then === 'function') mod = await mod;
    if (!mod.Mat) await new Promise(resolve => { mod.onRuntimeInitialized = resolve; });
    cv = mod;
    return { data: {} };
  },

  load({ buffer, width, height }) {
    free(srcFull, srcSmall, warped, warpedPrev);
    warped = warpedPrev = null;
    srcFull = cv.matFromImageData(new ImageData(new Uint8ClampedArray(buffer), width, height));
    srcSmall = resized(srcFull, PREVIEW_MAX);
    return smallReply();
  },

  detect() {
    return { data: detect() };
  },

  rotate() {
    const turned = new cv.Mat();
    cv.rotate(srcFull, turned, cv.ROTATE_90_CLOCKWISE);
    free(srcFull, srcSmall);
    srcFull = turned;
    srcSmall = resized(srcFull, PREVIEW_MAX);
    return smallReply();
  },

  warp({ corners }) {
    warp(corners);
    const img = toImageData(warpedPrev);
    return { data: { image: img, suggestion: suggest(), width: warped.cols, height: warped.rows }, transfer: [img.data.buffer] };
  },

  enhance({ target, options }) {
    const out = enhance(target === 'full' ? warped : warpedPrev, options);
    const img = toImageData(out);
    out.delete();
    return { data: { image: img }, transfer: [img.data.buffer] };
  },
};

self.onmessage = async ev => {
  const { id, cmd, ...args } = ev.data;
  try {
    const result = await commands[cmd](args);
    self.postMessage({ id, ok: true, ...result.data }, result.transfer || []);
  } catch (err) {
    self.postMessage({ id, ok: false, error: String((err && err.message) || err) });
  }
};
