<?php
/** Áudios: MP3 enviados pelo painel ou áudios hospedados no YouTube, no mesmo tocador. */
$audios = $pdo->query("SELECT * FROM media WHERE type = 'audio' ORDER BY position DESC, id DESC")->fetchAll();

page_header([
    'title' => 'Áudios', 'path' => 'audios', 'current' => 'audios',
    'description' => 'Trechos marcantes, vinhetas e gravações antigas de ' . $s['site_name'] . '.',
]);
?>
<section class="faixa cabeca estudio-faixa">
  <div class="wrap">
    <p class="kicker">Áudios</p>
    <h1>O que a sintonia não mostra, em som</h1>
    <?php if (!$audios): ?>
      <p class="vazio">Os primeiros áudios serão publicados em breve.</p>
    <?php else: ?>
    <div class="estudio" id="estudio">
      <div class="deck">
        <div class="tela">
          <div class="yt-vaga" aria-hidden="true"><div id="yt-audio"></div></div>
          <canvas id="viz" width="900" height="360" aria-hidden="true"></canvas>
          <p class="tela-estado" id="deck-estado">Pronto para tocar</p>
        </div>
        <p class="deck-titulo" id="deck-titulo" aria-live="polite"><?= e($audios[0]['title']) ?></p>
        <div class="deck-controles">
          <button class="deck-play" id="deck-play" type="button" aria-label="Tocar"><span aria-hidden="true"></span></button>
          <span class="deck-tempo" id="deck-agora">0:00</span>
          <input class="deck-barra" id="deck-barra" type="range" min="0" max="1000" value="0" aria-label="Posição do áudio">
          <span class="deck-tempo" id="deck-total">0:00</span>
        </div>
      </div>
      <ol class="faixas">
        <?php foreach ($audios as $i => $a): ?>
          <li>
            <button type="button" data-titulo="<?= e($a['title']) ?>"<?= $a['file'] ? ' data-mp3="' . e(url('uploads/audio/' . $a['file'])) . '"' : ' data-youtube="' . e($a['youtube_id']) . '"' ?><?= $i === 0 ? ' aria-current="true"' : '' ?>>
              <span class="faixa-eq" aria-hidden="true"><i></i><i></i><i></i></span>
              <span class="faixa-nome"><?= e($a['title']) ?><?= $a['year'] ? ' <small>' . (int) $a['year'] . '</small>' : '' ?></span>
              <?php if ($a['description']): ?><span class="faixa-desc"><?= e($a['description']) ?></span><?php endif ?>
            </button>
          </li>
        <?php endforeach ?>
      </ol>
    </div>
    <?php endif ?>
  </div>
</section>
<?php page_footer();
