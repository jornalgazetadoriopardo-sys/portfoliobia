/* Bia Pizani — comportamento das páginas públicas */
(() => {
  'use strict';
  document.documentElement.classList.add('js');
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
  const calmo = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Menu no celular ---------- */
  const menuBtn = $('.menu-btn'), menu = $('#menu');
  if (menuBtn && menu) {
    const abrir = on => { menu.classList.toggle('aberto', on); menuBtn.setAttribute('aria-expanded', String(on)); };
    menuBtn.addEventListener('click', () => abrir(!menu.classList.contains('aberto')));
    document.addEventListener('keydown', ev => { if (ev.key === 'Escape' && menu.classList.contains('aberto')) { abrir(false); menuBtn.focus(); } });
  }

  /* ---------- Ampliação de fotos (galeria) e vídeos ---------- */
  const luz = $('#luz'), palco = $('#luz-palco'), legenda = $('#luz-legenda');
  const fotos = $$('[data-foto]');
  let atual = 0;

  const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text) n.textContent = text; return n; };

  function mostrar(i) {
    atual = (i + fotos.length) % fotos.length;
    const d = JSON.parse(fotos[atual].dataset.foto);
    const img = new Image();
    img.alt = d.alt; img.width = d.w; img.height = d.h; img.src = d.src;
    palco.replaceChildren(img);
    const partes = [];
    if (d.titulo) partes.push(el('h2', '', d.titulo));
    if (d.meta) partes.push(el('p', 'l-meta', d.meta));
    if (d.quem) partes.push(el('p', 'l-quem', 'Na foto: ' + d.quem));
    if (d.historia) partes.push(el('p', 'l-hist', d.historia));
    const link = el('a', '', 'Abrir a página desta foto'); link.href = d.url; partes.push(link);
    legenda.replaceChildren(...partes);
    legenda.scrollTop = 0;
    // pré-carrega a vizinha para a troca ser imediata
    const prox = fotos[(atual + 1) % fotos.length];
    if (prox && fotos.length > 1) new Image().src = JSON.parse(prox.dataset.foto).src;
  }

  if (luz && fotos.length) {
    fotos.forEach((a, i) => a.addEventListener('click', ev => {
      if (ev.metaKey || ev.ctrlKey || ev.shiftKey) return; // abrir em nova aba continua funcionando
      ev.preventDefault();
      mostrar(i);
      luz.showModal();
    }));
    const ant = $('.luz-ant', luz), prox = $('.luz-prox', luz);
    if (fotos.length < 2) { ant.hidden = prox.hidden = true; }
    ant.addEventListener('click', () => mostrar(atual - 1));
    prox.addEventListener('click', () => mostrar(atual + 1));
    luz.addEventListener('keydown', ev => {
      if (ev.key === 'ArrowLeft') mostrar(atual - 1);
      if (ev.key === 'ArrowRight') mostrar(atual + 1);
    });
    let x0 = null;
    palco.addEventListener('touchstart', ev => { x0 = ev.touches[0].clientX; }, { passive: true });
    palco.addEventListener('touchend', ev => {
      if (x0 === null) return;
      const dx = ev.changedTouches[0].clientX - x0;
      if (Math.abs(dx) > 50) mostrar(atual + (dx < 0 ? 1 : -1));
      x0 = null;
    }, { passive: true });
  }

  $$('[data-video]').forEach(btn => btn.addEventListener('click', () => {
    const frame = document.createElement('iframe');
    frame.src = `https://www.youtube-nocookie.com/embed/${btn.dataset.video}?autoplay=1&rel=0`;
    frame.title = btn.dataset.titulo;
    frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
    frame.allowFullscreen = true;
    palco.replaceChildren(frame);
    legenda.replaceChildren(el('h2', '', btn.dataset.titulo));
    luz.showModal();
  }));

  if (luz) {
    $('[data-fechar]', luz).addEventListener('click', () => luz.close());
    luz.addEventListener('click', ev => { if (ev.target === luz || ev.target === palco) luz.close(); });
    luz.addEventListener('close', () => palco.replaceChildren()); // interrompe o vídeo
  }

  /* ---------- Estúdio: MP3 ou YouTube no mesmo tocador, com tela animada ---------- */
  const estudio = $('#estudio');
  if (estudio) {
    const faixas = $$('.faixas button', estudio);
    const titulo = $('#deck-titulo'), estado = $('#deck-estado');
    const playBtn = $('#deck-play'), barra = $('#deck-barra');
    const agora = $('#deck-agora'), total = $('#deck-total');
    let tocando = false, arrastando = false, sel = faixas[0];

    const fmt = s => { s = Math.max(0, Math.floor(s || 0)); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); };
    const setTocando = on => {
      tocando = on;
      estudio.classList.toggle('tocando', on);
      playBtn.setAttribute('aria-label', on ? 'Pausar' : 'Tocar');
    };
    const proxima = () => {
      const i = faixas.indexOf(sel);
      if (faixas[i + 1]) escolher(faixas[i + 1], true); else estado.textContent = 'Fim da lista';
    };

    /* Fonte 1: arquivo MP3 */
    const audio = new Audio();
    audio.preload = 'none';
    audio.addEventListener('playing', () => { setTocando(true); estado.textContent = 'Tocando agora'; });
    audio.addEventListener('waiting', () => { estado.textContent = 'Carregando…'; });
    audio.addEventListener('pause', () => { if (!audio.ended) { setTocando(false); estado.textContent = 'Em pausa'; } });
    audio.addEventListener('ended', () => { setTocando(false); proxima(); });
    audio.addEventListener('error', () => { if (audio.src) { setTocando(false); estado.textContent = 'Este áudio não está disponível'; } });
    const mp3 = {
      tocar(src) { if (audio.dataset.src !== src) { audio.dataset.src = src; audio.src = src; } audio.play().catch(() => {}); },
      pausar() { audio.pause(); },
      tempo: () => audio.currentTime, duracao: () => audio.duration || 0,
      ir(t) { audio.currentTime = t; },
    };

    /* Fonte 2: YouTube (o vídeo fica oculto; só o som interessa) */
    let player = null, pronto = false, pendente = null, carregado = null;
    const carregarApi = () => {
      if (window.YT || $('script[data-yt]')) return;
      const tag = document.createElement('script');
      tag.src = 'https://www.youtube.com/iframe_api';
      tag.dataset.yt = '1';
      tag.onerror = () => { if (sel.dataset.youtube && pendente) estado.textContent = 'Não foi possível conectar ao YouTube'; };
      document.head.append(tag);
    };
    window.onYouTubeIframeAPIReady = () => {
      const primeiro = faixas.find(f => f.dataset.youtube);
      player = new YT.Player('yt-audio', {
        width: 200, height: 200, videoId: primeiro.dataset.youtube,
        host: 'https://www.youtube-nocookie.com',
        playerVars: { controls: 0, disablekb: 1, playsinline: 1, rel: 0 },
        events: {
          onReady: () => { pronto = true; carregado = primeiro.dataset.youtube; if (pendente) { const id = pendente; pendente = null; yt.tocar(id); } },
          onStateChange: ev => {
            const S = YT.PlayerState;
            if (ev.data === S.PLAYING) { setTocando(true); estado.textContent = 'Tocando agora'; }
            else if (ev.data === S.BUFFERING) estado.textContent = 'Carregando…';
            else if (ev.data === S.PAUSED) { setTocando(false); estado.textContent = 'Em pausa'; }
            else if (ev.data === S.ENDED) { setTocando(false); proxima(); }
          },
          onError: () => { setTocando(false); estado.textContent = 'Este áudio não está disponível'; },
        },
      });
    };
    const yt = {
      tocar(id) {
        if (!pronto) { pendente = id; estado.textContent = 'Carregando…'; carregarApi(); return; }
        if (carregado !== id) { carregado = id; player.loadVideoById(id); } else player.playVideo();
      },
      pausar() { pendente = null; if (pronto) player.pauseVideo(); },
      tempo: () => (pronto ? player.getCurrentTime() : 0), duracao: () => (pronto ? player.getDuration() : 0),
      ir(t) { if (pronto) player.seekTo(t, true); },
    };
    if (faixas.some(f => f.dataset.youtube)) {
      new IntersectionObserver((en, obs) => { if (en.some(x => x.isIntersecting)) { carregarApi(); obs.disconnect(); } }, { rootMargin: '600px' }).observe(estudio);
    }

    const fonte = btn => (btn.dataset.mp3 ? mp3 : yt);
    const tocar = () => fonte(sel).tocar(sel.dataset.mp3 || sel.dataset.youtube);

    function escolher(btn, autoplay) {
      if (btn !== sel) { fonte(sel).pausar(); setTocando(false); }
      faixas.forEach(f => f.removeAttribute('aria-current'));
      btn.setAttribute('aria-current', 'true');
      sel = btn;
      titulo.textContent = btn.dataset.titulo;
      barra.value = 0; barra.style.setProperty('--p', '0%');
      agora.textContent = total.textContent = '0:00';
      if (autoplay) tocar();
    }

    faixas.forEach(btn => btn.addEventListener('click', () => {
      if (btn === sel && tocando) fonte(sel).pausar(); else escolher(btn, true);
    }));
    playBtn.addEventListener('click', () => { tocando ? fonte(sel).pausar() : tocar(); });
    barra.addEventListener('input', () => { arrastando = true; barra.style.setProperty('--p', barra.value / 10 + '%'); });
    barra.addEventListener('change', () => { const d = fonte(sel).duracao(); if (d) fonte(sel).ir(d * barra.value / 1000); arrastando = false; });
    setInterval(() => {
      if (!tocando || arrastando) return;
      const d = fonte(sel).duracao() || 0, t = fonte(sel).tempo() || 0;
      agora.textContent = fmt(t); total.textContent = fmt(d);
      if (d) { barra.value = t / d * 1000; barra.style.setProperty('--p', (t / d * 100) + '%'); }
    }, 500);

    /* Tela animada: barras que dançam enquanto toca e repousam em pausa. */
    const canvas = $('#viz'), ctx = canvas.getContext('2d');
    const BARRAS = 56, nivel = new Float32Array(BARRAS);
    let energia = 0, visivel = false, raf = 0;
    const desenhar = ms => {
      raf = 0;
      const t = ms / 1000, W = canvas.width, H = canvas.height;
      energia += ((tocando ? 1 : 0) - energia) * 0.06;
      ctx.clearRect(0, 0, W, H);
      const passo = W / BARRAS, bw = passo * 0.56, meio = H * 0.58;
      const grad = ctx.createLinearGradient(0, meio - H * 0.44, 0, meio);
      grad.addColorStop(0, '#ffffff'); grad.addColorStop(0.35, '#ff8a85'); grad.addColorStop(1, '#c9363a');
      for (let i = 0; i < BARRAS; i++) {
        const c = 1 - Math.abs(i - (BARRAS - 1) / 2) / (BARRAS / 2);
        const onda = Math.sin(t * 2.1 + i * 0.42) * 0.5 + Math.sin(t * 3.7 - i * 0.9) * 0.3 + Math.sin(t * 7.3 + i * 2.1) * 0.2;
        const alvo = (0.18 + 0.82 * Math.abs(onda)) * (0.35 + 0.65 * c) * energia;
        nivel[i] += (alvo - nivel[i]) * 0.22;
        const h = Math.max(3, nivel[i] * H * 0.44);
        const x = i * passo + (passo - bw) / 2;
        ctx.fillStyle = grad;
        ctx.beginPath(); ctx.roundRect(x, meio - h, bw, h, bw / 2); ctx.fill();
        ctx.globalAlpha = 0.2;
        ctx.beginPath(); ctx.roundRect(x, meio + 4, bw, h * 0.45, bw / 2); ctx.fill();
        ctx.globalAlpha = 1;
      }
      if (visivel && !calmo && (tocando || energia > 0.01)) raf = requestAnimationFrame(desenhar);
    };
    const acordar = () => { if (!raf) raf = requestAnimationFrame(desenhar); };
    new IntersectionObserver(en => { visivel = en[0].isIntersecting; if (visivel) acordar(); }).observe(canvas);
    new MutationObserver(acordar).observe(estudio, { attributes: true, attributeFilter: ['class'] });
    acordar();
  }

  /* ---------- Formulário de recados ---------- */
  const form = $('#form-recado');
  if (form) {
    const msg = $('#recado-msg'), enviar = $('button[type=submit]', form);
    form.addEventListener('submit', async ev => {
      ev.preventDefault();
      const dizer = (texto, ok) => { msg.textContent = texto; msg.className = 'form-msg ' + (ok ? 'ok' : 'erro'); };
      if (form.nome.value.trim().length < 2) { dizer('Escreva o seu nome.', false); form.nome.focus(); return; }
      if (form.mensagem.value.trim().length < 5) { dizer('Escreva o recado antes de enviar.', false); form.mensagem.focus(); return; }
      enviar.disabled = true; enviar.textContent = 'Enviando…';
      try {
        const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
        const data = await res.json();
        dizer(data.message, data.ok);
        if (data.ok) { form.nome.value = form.cidade.value = form.mensagem.value = ''; }
      } catch {
        dizer('Sem conexão. Verifique a internet e envie de novo.', false);
      }
      enviar.disabled = false; enviar.textContent = 'Enviar recado';
    });
  }
})();
