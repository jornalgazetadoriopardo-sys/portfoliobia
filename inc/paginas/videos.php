<?php
/** Vídeos hospedados no YouTube. */
$videos = $pdo->query("SELECT * FROM media WHERE type = 'video' ORDER BY position DESC, id DESC")->fetchAll();

page_header([
    'title' => 'Vídeos', 'path' => 'videos', 'current' => 'videos',
    'description' => 'Entrevistas, apresentações e reportagens com ' . $s['site_name'] . '.',
]);
?>
<section class="faixa cabeca">
  <div class="wrap">
    <p class="kicker">Vídeos</p>
    <h1>Entrevistas e apresentações</h1>
  </div>
</section>
<section class="faixa faixa-sem-topo">
  <div class="wrap">
    <?php if (!$videos): ?><p class="vazio">Os primeiros vídeos serão publicados em breve.</p><?php endif ?>
    <div class="videos">
      <?php foreach ($videos as $v): ?>
        <article class="video">
          <button type="button" data-video="<?= e($v['youtube_id']) ?>" data-titulo="<?= e($v['title']) ?>" aria-label="Assistir: <?= e($v['title']) ?>">
            <img src="https://i.ytimg.com/vi/<?= e($v['youtube_id']) ?>/hqdefault.jpg" alt="" width="480" height="360" loading="lazy">
            <span class="play" aria-hidden="true"></span>
          </button>
          <h2><?= e($v['title']) ?></h2>
          <?php if ($v['description']): ?><p><?= e($v['description']) ?></p><?php endif ?>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>
<dialog class="luz luz-video" id="luz" aria-label="Vídeo">
  <button class="luz-fechar" type="button" data-fechar aria-label="Fechar">×</button>
  <div class="luz-palco" id="luz-palco"></div>
  <div class="luz-legenda" id="luz-legenda"></div>
</dialog>
<?php page_footer();
