/* Scanner de fotos — tela do painel.
   Etapas: 1 fotografa, 2 alinha, 3 trata, 4 publica. O processamento pesado fica no worker. */
(() => {
  'use strict';
  const root = document.getElementById('scanner');
  if (!root) return;
  const $ = (sel, el = root) => el.querySelector(sel);
  const $$ = (sel, el = root) => [...el.querySelectorAll(sel)];

  const CAPTURE_MAX = 3000;   // lado maior enviado ao processamento (memória do celular)
  const HANDLE_COLOR = '#ff4b4b';
  const maxPost = Number(root.dataset.maxPost) || 8 * 1024 * 1024;
  const maxFile = Number(root.dataset.maxFile) || 2 * 1024 * 1024;

  /* ---------- Conversa com o worker ---------- */
  const worker = new Worker(root.dataset.worker);
  const waiting = new Map();
  let seq = 0;
  worker.onmessage = ev => {
    const job = waiting.get(ev.data.id);
    if (!job) return;
    waiting.delete(ev.data.id);
    ev.data.ok ? job.resolve(ev.data) : job.reject(new Error(ev.data.error));
  };
  const call = (cmd, data = {}, transfer = []) => new Promise((resolve, reject) => {
    const id = ++seq;
    waiting.set(id, { resolve, reject });
    worker.postMessage({ id, cmd, ...data }, transfer);
  });

  const motor = $('#sc-motor');
  const ready = call('init', { opencv: new URL(root.dataset.opencv, location.href).href }).then(() => {
    motor.textContent = 'Scanner pronto.';
    motor.classList.add('pronto');
  });
  ready.catch(() => {
    motor.textContent = 'O scanner não carregou neste aparelho. Atualize a página; se continuar, use "Nova foto" no menu Fotos.';
    motor.classList.add('erro');
  });
  worker.onerror = () => { waiting.forEach(j => j.reject(new Error('worker'))); waiting.clear(); };

  /* ---------- Navegação entre etapas ---------- */
  let step = 1;
  function go(n) {
    step = n;
    $$('.etapa').forEach(s => { s.hidden = Number(s.dataset.etapa) !== n; });
    $$('.passos li').forEach(li => {
      const k = Number(li.dataset.passo);
      li.classList.toggle('feito', k < n);
      k === Math.min(n, 4) && n < 5 ? li.setAttribute('aria-current', 'step') : li.removeAttribute('aria-current');
    });
    $('.passos').hidden = n === 5;
    window.scrollTo({ top: 0 });
    if (n === 2) requestAnimationFrame(drawAlign);
  }
  $$('[data-ir]').forEach(b => b.addEventListener('click', () => go(Number(b.dataset.ir))));
  addEventListener('beforeunload', ev => { if (step > 1 && step < 5) { ev.preventDefault(); ev.returnValue = ''; } });

  /* ---------- Etapa 1: captura ---------- */
  let originalFile = null;
  let small = null;                 // ImageData reduzida mostrada no alinhamento
  let corners = [];                 // quatro cantos, de 0 a 1, na ordem SE, SD, ID, IE

  async function decode(file) {
    try {
      return await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
      // Navegadores antigos: carrega por <img>, que já respeita a orientação da foto
      const url = URL.createObjectURL(file);
      try {
        const img = new Image();
        img.src = url;
        await img.decode();
        return img;
      } finally {
        setTimeout(() => URL.revokeObjectURL(url), 5000);
      }
    }
  }

  async function onFile(file) {
    if (!file) return;
    motor.classList.remove('erro');
    motor.textContent = 'Abrindo a foto…';
    try {
      await ready;
      const bmp = await decode(file);
      const w0 = bmp.width || bmp.naturalWidth, h0 = bmp.height || bmp.naturalHeight;
      const k = Math.min(1, CAPTURE_MAX / Math.max(w0, h0));
      const w = Math.round(w0 * k), h = Math.round(h0 * k);
      const c = document.createElement('canvas');
      c.width = w; c.height = h;
      const ctx = c.getContext('2d', { willReadFrequently: true });
      ctx.drawImage(bmp, 0, 0, w, h);
      bmp.close?.();
      const data = ctx.getImageData(0, 0, w, h);
      c.width = c.height = 0; // libera a memória do canvas temporário
      originalFile = file;
      applySmall(await call('load', { buffer: data.data.buffer, width: w, height: h }, [data.data.buffer]));
      motor.textContent = 'Scanner pronto.';
      go(2);
    } catch (err) {
      motor.textContent = 'Não foi possível abrir esta imagem. Tente fotografar de novo.';
      motor.classList.add('erro');
    }
  }
  ['#sc-camera', '#sc-arquivo'].forEach(sel => $(sel).addEventListener('change', ev => {
    const f = ev.target.files[0];
    ev.target.value = '';
    onFile(f);
  }));

  /* ---------- Etapa 2: alinhamento ---------- */
  const canvas = $('#sc-canvas'), actx = canvas.getContext('2d');
  const alignHint = $('#sc-alinha-dica');
  let smallBitmap = null, dragging = -1, pointer = null;

  function applySmall(res) {
    small = res.image;
    root.dataset.metodo = res.method;
    corners = res.corners;
    canvas.width = small.width; canvas.height = small.height;
    const off = document.createElement('canvas');
    off.width = small.width; off.height = small.height;
    off.getContext('2d').putImageData(small, 0, 0);
    smallBitmap = off;
    alignHint.textContent = res.found
      ? 'As bordas foram encontradas. Confira os cantos e arraste se precisar.'
      : 'Não foi possível achar as bordas sozinho. Arraste os quatro cantos até as pontas da foto.';
    drawAlign();
  }

  function drawAlign() {
    if (!smallBitmap) return;
    const W = canvas.width, H = canvas.height;
    const scale = W / (canvas.getBoundingClientRect().width || W); // pixels do canvas por pixel de tela
    const pts = corners.map(c => ({ x: c.x * W, y: c.y * H }));
    actx.clearRect(0, 0, W, H);
    actx.drawImage(smallBitmap, 0, 0);

    // escurece o que fica de fora do recorte
    actx.save();
    actx.fillStyle = 'rgba(10, 14, 30, .55)';
    actx.beginPath();
    actx.rect(0, 0, W, H);
    actx.moveTo(pts[0].x, pts[0].y);
    for (const i of [3, 2, 1]) actx.lineTo(pts[i].x, pts[i].y);
    actx.closePath();
    actx.fill('evenodd');
    actx.restore();

    actx.lineWidth = 3 * scale; actx.strokeStyle = HANDLE_COLOR; actx.lineJoin = 'round';
    actx.beginPath();
    pts.forEach((p, i) => (i ? actx.lineTo(p.x, p.y) : actx.moveTo(p.x, p.y)));
    actx.closePath(); actx.stroke();
    pts.forEach((p, i) => {
      actx.beginPath(); actx.arc(p.x, p.y, (i === dragging ? 20 : 15) * scale, 0, Math.PI * 2);
      actx.fillStyle = 'rgba(255, 75, 75, .3)'; actx.fill();
      actx.lineWidth = 3 * scale; actx.strokeStyle = '#fff'; actx.stroke();
      actx.beginPath(); actx.arc(p.x, p.y, 4 * scale, 0, Math.PI * 2);
      actx.fillStyle = HANDLE_COLOR; actx.fill();
    });

    // lupa: o dedo cobre o canto, então o detalhe aparece ampliado no lado oposto
    if (dragging >= 0) {
      const p = pts[dragging], r = Math.min(W, H) * 0.17, zoom = 2.4;
      const cx = p.x < W / 2 ? W - r - 10 * scale : r + 10 * scale, cy = r + 10 * scale;
      actx.save();
      actx.beginPath(); actx.arc(cx, cy, r, 0, Math.PI * 2); actx.clip();
      actx.fillStyle = '#11162a'; actx.fillRect(cx - r, cy - r, r * 2, r * 2);
      actx.drawImage(smallBitmap, p.x - r / zoom, p.y - r / zoom, r * 2 / zoom, r * 2 / zoom, cx - r, cy - r, r * 2, r * 2);
      actx.strokeStyle = HANDLE_COLOR; actx.lineWidth = 2 * scale;
      actx.beginPath(); actx.moveTo(cx - r, cy); actx.lineTo(cx + r, cy); actx.moveTo(cx, cy - r); actx.lineTo(cx, cy + r); actx.stroke();
      actx.restore();
      actx.beginPath(); actx.arc(cx, cy, r, 0, Math.PI * 2);
      actx.lineWidth = 4 * scale; actx.strokeStyle = '#fff'; actx.stroke();
    }
  }

  const toCanvas = ev => {
    const r = canvas.getBoundingClientRect();
    return { x: (ev.clientX - r.left) / r.width, y: (ev.clientY - r.top) / r.height, px: r.width, py: r.height };
  };
  canvas.addEventListener('pointerdown', ev => {
    const p = toCanvas(ev);
    let best = -1, bestD = Infinity;
    corners.forEach((c, i) => {
      const d = Math.hypot((c.x - p.x) * p.px, (c.y - p.y) * p.py);
      if (d < bestD) { bestD = d; best = i; }
    });
    if (bestD > 64) return;              // toque longe de qualquer canto
    dragging = best;
    pointer = ev.pointerId;
    canvas.setPointerCapture(ev.pointerId);
    ev.preventDefault();
    drawAlign();
  });
  canvas.addEventListener('pointermove', ev => {
    if (dragging < 0 || ev.pointerId !== pointer) return;
    const p = toCanvas(ev);
    corners[dragging] = { x: Math.min(1, Math.max(0, p.x)), y: Math.min(1, Math.max(0, p.y)) };
    drawAlign();
  });
  const endDrag = () => { if (dragging >= 0) { dragging = -1; pointer = null; drawAlign(); } };
  canvas.addEventListener('pointerup', endDrag);
  canvas.addEventListener('pointercancel', endDrag);
  addEventListener('resize', () => { if (step === 2) drawAlign(); });

  const busyBtn = async (btn, text, fn) => {
    const old = btn.textContent;
    btn.disabled = true; btn.textContent = text;
    try { await fn(); } finally { btn.disabled = false; btn.textContent = old; }
  };
  $('#sc-detectar').addEventListener('click', ev => busyBtn(ev.currentTarget, 'Detectando…', async () => {
    const res = await call('detect');
    corners = res.corners;
    alignHint.textContent = res.found ? 'Bordas encontradas. Arraste os cantos se precisar.' : 'Não achei as bordas. Arraste os cantos até as pontas da foto.';
    drawAlign();
  }));
  $('#sc-girar').addEventListener('click', ev => busyBtn(ev.currentTarget, 'Girando…', async () => applySmall(await call('rotate'))));
  $('#sc-inteira').addEventListener('click', () => {
    corners = [{ x: 0, y: 0 }, { x: 1, y: 0 }, { x: 1, y: 1 }, { x: 0, y: 1 }];
    drawAlign();
  });

  /* ---------- Etapa 3: tratamento ---------- */
  const before = $('#sc-antes'), after = $('#sc-depois');
  const auto = $('#sc-auto'), manual = $('#sc-manuais'), busy = $('#sc-ocupado');
  const KEYS = ['sombra', 'cor', 'contraste', 'nitidez'];
  const toggle = k => $(`[data-liga="${k}"]`), force = k => $(`[data-forca="${k}"]`);
  let suggestion = { sombra: 50, cor: 70, contraste: 50, nitidez: 50 };
  let renderSeq = 0, timer = 0;

  const options = () => Object.fromEntries(KEYS.map(k => [k, toggle(k).checked ? Number(force(k).value) / 100 : 0]));
  function applySuggestion() {
    KEYS.forEach(k => { toggle(k).checked = true; force(k).value = suggestion[k]; });
  }
  function syncAuto() {
    manual.inert = auto.checked;
    if (auto.checked) applySuggestion();
  }

  async function renderPreview() {
    const mine = ++renderSeq;
    busy.hidden = false;
    try {
      const res = await call('enhance', { target: 'preview', options: options() });
      if (mine === renderSeq) after.getContext('2d').putImageData(res.image, 0, 0);
    } catch {
      /* mantém a última pré-visualização */
    } finally {
      if (mine === renderSeq) busy.hidden = true;
    }
  }
  const schedule = () => { clearTimeout(timer); timer = setTimeout(renderPreview, 140); };

  $('#sc-alinhar-ok').addEventListener('click', ev => busyBtn(ev.currentTarget, 'Recortando…', async () => {
    const res = await call('warp', { corners });
    suggestion = res.suggestion;
    for (const c of [before, after]) { c.width = res.image.width; c.height = res.image.height; c.getContext('2d').putImageData(res.image, 0, 0); }
    auto.checked = true;
    syncAuto();
    setDivider(50);
    go(3);
    renderPreview();
  }));

  auto.addEventListener('change', () => { syncAuto(); schedule(); });
  KEYS.forEach(k => { toggle(k).addEventListener('change', schedule); force(k).addEventListener('input', schedule); });
  $('#sc-original').addEventListener('click', () => {
    auto.checked = false;
    manual.inert = false;
    KEYS.forEach(k => { toggle(k).checked = false; });
    schedule();
  });

  // Comparador de antes e depois
  const compare = $('#sc-compara'), divider = $('#sc-divisor'), beforeBox = $('#sc-antes-caixa');
  let divPos = 50;
  function setDivider(p) {
    divPos = Math.min(100, Math.max(0, p));
    divider.style.left = divPos + '%';
    beforeBox.style.clipPath = `inset(0 ${100 - divPos}% 0 0)`;
    divider.setAttribute('aria-valuenow', String(Math.round(divPos)));
  }
  const dividerFrom = ev => { const r = compare.getBoundingClientRect(); setDivider((ev.clientX - r.left) / r.width * 100); };
  compare.addEventListener('pointerdown', ev => { compare.setPointerCapture(ev.pointerId); dividerFrom(ev); });
  compare.addEventListener('pointermove', ev => { if (compare.hasPointerCapture(ev.pointerId)) dividerFrom(ev); });
  divider.addEventListener('keydown', ev => {
    if (ev.key === 'ArrowLeft') setDivider(divPos - 5);
    if (ev.key === 'ArrowRight') setDivider(divPos + 5);
  });

  /* ---------- Etapa 4: legenda e envio ---------- */
  const final = $('#sc-final'), form = $('#sc-form'), errorBox = $('#sc-erro');
  let finalBlob = null;

  $('#sc-tratar-ok').addEventListener('click', ev => busyBtn(ev.currentTarget, 'Finalizando…', async () => {
    const res = await call('enhance', { target: 'full', options: options() });
    final.width = res.image.width; final.height = res.image.height;
    final.getContext('2d').putImageData(res.image, 0, 0);
    // JPEG gerado pelo canvas não carrega metadados (EXIF) da câmera
    finalBlob = await new Promise(resolve => final.toBlob(resolve, 'image/jpeg', 0.9));
    errorBox.hidden = true;
    go(4);
  }));

  async function send(status, btn) {
    if (!finalBlob) return;
    const fd = new FormData(form);
    fd.set('action', 'scanner_salvar');
    fd.set('status', status);
    fd.set('csrf', root.dataset.csrf);
    fd.set('imagem', finalBlob, 'scanner.jpg');
    // O original sem tratamento só vai junto quando cabe nos limites do servidor
    if (originalFile && originalFile.size <= maxFile && finalBlob.size + originalFile.size < maxPost * 0.9) {
      fd.set('original', originalFile, 'original.jpg');
    }
    const buttons = [$('#sc-rascunho'), $('#sc-publicar')];
    const old = btn.textContent;
    buttons.forEach(b => { b.disabled = true; });
    btn.textContent = 'Enviando…';
    errorBox.hidden = true;
    try {
      const res = await fetch(location.pathname + '?p=scanner', { method: 'POST', body: fd, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await res.json();
      if (!data.ok) throw new Error(data.message || 'O servidor recusou a foto.');
      $('#sc-feito-titulo').textContent = data.status === 'draft' ? 'Rascunho salvo' : 'Foto publicada na galeria';
      $('#sc-feito-editar').href = data.edit;
      finalBlob = null;
      go(5);
    } catch (err) {
      const offline = err instanceof TypeError || err instanceof SyntaxError;
      errorBox.textContent = (offline ? 'O envio não chegou ao servidor. Verifique a internet. ' : err.message + ' ')
        + 'A foto continua aqui: toque em "' + old + '" para tentar de novo.';
      errorBox.hidden = false;
      errorBox.scrollIntoView({ block: 'nearest' });
    } finally {
      buttons.forEach(b => { b.disabled = false; });
      btn.textContent = old;
    }
  }
  $('#sc-publicar').addEventListener('click', ev => send('published', ev.currentTarget));
  $('#sc-rascunho').addEventListener('click', ev => send('draft', ev.currentTarget));
  form.addEventListener('submit', ev => ev.preventDefault());
})();
