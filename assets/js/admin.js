/* Painel — escolha do ponto focal por toque ou arrasto */
(() => {
  'use strict';
  document.querySelectorAll('[data-focal]').forEach(box => {
    const dot = box.querySelector('.focal-ponto');
    const wrap = box.closest('.focal');
    const fx = wrap.querySelector('[name=focal_x]'), fy = wrap.querySelector('[name=focal_y]');
    const set = ev => {
      const r = box.getBoundingClientRect();
      const x = Math.min(100, Math.max(0, (ev.clientX - r.left) / r.width * 100));
      const y = Math.min(100, Math.max(0, (ev.clientY - r.top) / r.height * 100));
      dot.style.left = x + '%'; dot.style.top = y + '%';
      fx.value = x.toFixed(1); fy.value = y.toFixed(1);
    };
    box.addEventListener('pointerdown', ev => { box.setPointerCapture(ev.pointerId); set(ev); });
    box.addEventListener('pointermove', ev => { if (box.hasPointerCapture(ev.pointerId)) set(ev); });
  });
})();
