/* Portfólio — comportamento da página pública */
(() => {
  'use strict';
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
  const calm = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Ponteiro do dial acompanha a rolagem ---------- */
  const needle = $('#needle');
  const links = $$('.dial-band a');
  if (needle && links.length) {
    const sections = links.map(a => document.getElementById(a.dataset.station));
    let ticking = false;

    const tune = () => {
      ticking = false;
      const band = needle.parentElement.getBoundingClientRect();
      const centers = links.map(a => { const r = a.getBoundingClientRect(); return r.left + r.width / 2 - band.left; });
      const tops = sections.map(s => s.getBoundingClientRect().top + scrollY);
      const y = scrollY + innerHeight * 0.35;
      const atEnd = innerHeight + scrollY >= document.documentElement.scrollHeight - 4;

      let x, active = -1;
      if (atEnd) { x = centers.at(-1); active = links.length - 1; }
      else if (y <= tops[0]) x = centers[0] * Math.min(1, scrollY / Math.max(1, tops[0] - innerHeight * 0.35));
      else {
        active = tops.findLastIndex(t => y >= t);
        const next = tops[active + 1];
        // dentro da seção o ponteiro repousa na estação; só desliza no trecho final
        const t = next === undefined ? 0 : Math.max(0, (y - (next - innerHeight * 0.5)) / (innerHeight * 0.5));
        x = centers[active] + ((centers[active + 1] ?? centers[active]) - centers[active]) * Math.min(1, t);
      }
      needle.style.setProperty('--x', x.toFixed(1) + 'px');
      links.forEach((a, i) => a.classList.toggle('on', i === active));
    };
    const request = () => { if (!ticking) { ticking = true; requestAnimationFrame(tune); } };
    addEventListener('scroll', request, { passive: true });
    addEventListener('resize', request);
    addEventListener('load', request);
    document.fonts?.ready.then(request);
    tune();
  }

  /* ---------- "Ver mais" (fotos e recados) ---------- */
  $$('[data-more]').forEach(btn => btn.addEventListener('click', () => {
    const hidden = $$(btn.dataset.more).filter(el => el.hidden);
    const step = Number(btn.dataset.step) || 12;
    hidden.slice(0, step).forEach(el => { el.hidden = false; });
    hidden[0]?.querySelector('a, p')?.focus?.();
    if (hidden.length <= step) btn.parentElement.remove();
  }));

  /* ---------- Ampliação de fotos e vídeos ---------- */
  const box = $('#lightbox');
  const stage = $('#lb-stage');
  const caption = $('#lb-caption');
  const photoLinks = $$('[data-photo]');
  let current = 0;

  const showPhoto = i => {
    current = (i + photoLinks.length) % photoLinks.length;
    const a = photoLinks[current];
    const img = new Image();
    img.src = a.href;
    img.alt = a.querySelector('img').alt;
    stage.replaceChildren(img);
    caption.textContent = a.dataset.caption || '';
    box.classList.remove('is-video');
    box.classList.toggle('single', photoLinks.length < 2);
    // quem abre a foto 12 quer ver a 13: revela as ocultas conforme navega
    const fig = a.closest('figure');
    if (fig?.hidden) fig.hidden = false;
  };

  photoLinks.forEach((a, i) => a.addEventListener('click', ev => {
    ev.preventDefault();
    showPhoto(i);
    box.showModal();
  }));

  $$('[data-video]').forEach(btn => btn.addEventListener('click', () => {
    pauseAudio();
    const frame = document.createElement('iframe');
    frame.src = `https://www.youtube-nocookie.com/embed/${btn.dataset.video}?autoplay=1&rel=0`;
    frame.title = btn.dataset.title;
    frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
    frame.allowFullscreen = true;
    stage.replaceChildren(frame);
    caption.textContent = btn.dataset.title;
    box.classList.add('is-video');
    box.showModal();
  }));

  if (box) {
    $('.lb-prev', box).addEventListener('click', () => showPhoto(current - 1));
    $('.lb-next', box).addEventListener('click', () => showPhoto(current + 1));
    $('[data-close]', box).addEventListener('click', () => box.close());
    box.addEventListener('click', ev => { if (ev.target === box || ev.target === stage) box.close(); });
    box.addEventListener('close', () => stage.replaceChildren()); // interrompe o vídeo
    box.addEventListener('keydown', ev => {
      if (box.classList.contains('is-video')) return;
      if (ev.key === 'ArrowLeft') showPhoto(current - 1);
      if (ev.key === 'ArrowRight') showPhoto(current + 1);
    });
    let startX = null;
    stage.addEventListener('touchstart', ev => { startX = ev.touches[0].clientX; }, { passive: true });
    stage.addEventListener('touchend', ev => {
      if (startX === null || box.classList.contains('is-video')) return;
      const dx = ev.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 50) showPhoto(current + (dx < 0 ? 1 : -1));
      startX = null;
    }, { passive: true });
  }

  /* ---------- Estúdio: áudios do YouTube com tela animada ---------- */
  const studio = $('#studio');
  let player = null, playerReady = false, pendingId = null, playing = false;

  function pauseAudio() { if (playerReady && playing) player.pauseVideo(); }

  if (studio) {
    const tracks = $$('[data-audio]', studio);
    const title = $('#deck-title'), status = $('#deck-status');
    const playBtn = $('#deck-play'), seek = $('#deck-seek');
    const now = $('#deck-now'), total = $('#deck-total');
    let currentId = tracks[0].dataset.audio, loadedId = null, seeking = false;

    const fmt = s => { s = Math.max(0, Math.floor(s || 0)); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); };
    const setPlaying = on => {
      playing = on;
      studio.classList.toggle('playing', on);
      playBtn.setAttribute('aria-label', on ? 'Pausar' : 'Tocar');
    };

    // A API do YouTube só é carregada quando o estúdio se aproxima da tela.
    const loadApi = () => {
      if (window.YT || $('script[data-yt]')) return;
      const tag = document.createElement('script');
      tag.src = 'https://www.youtube.com/iframe_api';
      tag.dataset.yt = '1';
      tag.onerror = () => { status.textContent = 'Não foi possível conectar ao YouTube'; };
      document.head.append(tag);
    };
    window.onYouTubeIframeAPIReady = () => {
      player = new YT.Player('yt-audio', {
        width: 200, height: 200, videoId: currentId,
        host: 'https://www.youtube-nocookie.com',
        playerVars: { controls: 0, disablekb: 1, playsinline: 1, rel: 0, modestbranding: 1 },
        events: {
          onReady: () => {
            playerReady = true;
            loadedId = currentId;
            if (pendingId) { const id = pendingId; pendingId = null; play(id); }
          },
          onStateChange: ev => {
            const S = YT.PlayerState;
            if (ev.data === S.PLAYING) { setPlaying(true); status.textContent = 'Tocando agora'; }
            else if (ev.data === S.BUFFERING) status.textContent = 'Carregando…';
            else if (ev.data === S.PAUSED) { setPlaying(false); status.textContent = 'Em pausa'; }
            else if (ev.data === S.ENDED) {
              setPlaying(false);
              const i = tracks.findIndex(t => t.dataset.audio === currentId);
              if (tracks[i + 1]) select(tracks[i + 1], true);
              else status.textContent = 'Fim da lista';
            }
          },
          onError: () => { setPlaying(false); status.textContent = 'Este áudio não está disponível'; },
        },
      });
    };
    new IntersectionObserver((entries, obs) => {
      if (entries.some(en => en.isIntersecting)) { loadApi(); obs.disconnect(); }
    }, { rootMargin: '600px' }).observe(studio);

    function play(id) {
      if (!playerReady) { pendingId = id; status.textContent = 'Carregando…'; loadApi(); return; }
      if (loadedId !== id) { loadedId = id; player.loadVideoById(id); }
      else player.playVideo();
    }

    function select(btn, autoplay) {
      tracks.forEach(t => t.removeAttribute('aria-current'));
      btn.setAttribute('aria-current', 'true');
      currentId = btn.dataset.audio;
      title.textContent = btn.dataset.title;
      seek.value = 0; seek.style.setProperty('--p', '0%');
      now.textContent = total.textContent = '0:00';
      if (autoplay) play(currentId);
    }

    tracks.forEach(btn => btn.addEventListener('click', () => {
      if (btn.dataset.audio === currentId && playing) player.pauseVideo();
      else select(btn, true);
    }));
    playBtn.addEventListener('click', () => { playing ? player.pauseVideo() : play(currentId); });

    seek.addEventListener('input', () => { seeking = true; seek.style.setProperty('--p', seek.value / 10 + '%'); });
    seek.addEventListener('change', () => {
      if (playerReady && loadedId === currentId) player.seekTo(player.getDuration() * seek.value / 1000, true);
      seeking = false;
    });
    setInterval(() => {
      if (!playerReady || !playing || seeking) return;
      const d = player.getDuration() || 0, t = player.getCurrentTime() || 0;
      now.textContent = fmt(t); total.textContent = fmt(d);
      if (d) { seek.value = t / d * 1000; seek.style.setProperty('--p', (t / d * 100) + '%'); }
    }, 500);

    /* Tela animada: barras que dançam enquanto toca e repousam em pausa.
       O YouTube não expõe o som, então o movimento é sintetizado. */
    const canvas = $('#viz'), ctx = canvas.getContext('2d');
    const BARS = 56;
    const level = new Float32Array(BARS);
    let energy = 0, visible = false, raf = 0;

    const draw = ms => {
      raf = 0;
      const t = ms / 1000, W = canvas.width, H = canvas.height;
      energy += ((playing ? 1 : 0) - energy) * 0.06;
      ctx.clearRect(0, 0, W, H);
      const gap = W / BARS, bw = gap * 0.56, mid = H * 0.56;
      const grad = ctx.createLinearGradient(0, mid - H * 0.42, 0, mid);
      grad.addColorStop(0, '#FFB224'); grad.addColorStop(0.55, '#FF4FA3'); grad.addColorStop(1, '#8E6BFF');
      for (let i = 0; i < BARS; i++) {
        const c = 1 - Math.abs(i - (BARS - 1) / 2) / (BARS / 2);      // mais alto no centro
        const wave = Math.sin(t * 2.1 + i * 0.42) * 0.5 + Math.sin(t * 3.7 - i * 0.9) * 0.3 + Math.sin(t * 7.3 + i * 2.1) * 0.2;
        const target = (0.18 + 0.82 * Math.abs(wave)) * (0.35 + 0.65 * c) * energy;
        level[i] += (target - level[i]) * 0.22;
        const h = Math.max(3, level[i] * H * 0.42);
        const x = i * gap + (gap - bw) / 2;
        ctx.fillStyle = grad;
        ctx.beginPath(); ctx.roundRect(x, mid - h, bw, h, bw / 2); ctx.fill();
        ctx.globalAlpha = 0.22;                                          // reflexo
        ctx.beginPath(); ctx.roundRect(x, mid + 4, bw, h * 0.45, bw / 2); ctx.fill();
        ctx.globalAlpha = 1;
      }
      if (visible && !calm && (playing || energy > 0.01)) raf = requestAnimationFrame(draw);
    };
    const kick = () => { if (!raf) raf = requestAnimationFrame(draw); };
    new IntersectionObserver(en => { visible = en[0].isIntersecting; if (visible) kick(); }).observe(canvas);
    new MutationObserver(kick).observe(studio, { attributes: true, attributeFilter: ['class'] });
    if (calm) { energy = 0.5; level.fill(0); }
    kick();
  }

  /* ---------- Formulário de recados ---------- */
  const form = $('#note-form');
  if (form) {
    const msg = $('#note-msg'), submit = $('button[type=submit]', form);
    form.addEventListener('submit', async ev => {
      ev.preventDefault();
      const say = (text, ok) => { msg.textContent = text; msg.className = 'form-msg ' + (ok ? 'ok' : 'err'); };
      if (form.nome.value.trim().length < 2) { say('Escreva o seu nome.', false); form.nome.focus(); return; }
      if (form.mensagem.value.trim().length < 5) { say('Escreva o recado antes de enviar.', false); form.mensagem.focus(); return; }
      submit.disabled = true; submit.textContent = 'Enviando…';
      try {
        const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
        const data = await res.json();
        say(data.message, data.ok);
        if (data.ok) { form.nome.value = form.cidade.value = form.mensagem.value = ''; }
      } catch {
        say('Sem conexão. Verifique a internet e envie de novo.', false);
      }
      submit.disabled = false; submit.textContent = 'Enviar recado';
    });
  }
})();
