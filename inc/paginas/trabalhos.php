<?php
/** Trabalhos Atuais: programas, horários e outros projetos. */
$trabalhos = $pdo->query('SELECT * FROM works WHERE active = 1 ORDER BY position DESC, id DESC')->fetchAll();

page_header([
    'title' => 'Trabalhos atuais', 'path' => 'trabalhos', 'current' => 'trabalhos',
    'description' => 'Programas, horários, locuções comerciais e projetos atuais de ' . $s['site_name'] . '.',
]);
?>
<section class="faixa cabeca">
  <div class="wrap">
    <p class="kicker">Trabalhos atuais</p>
    <h1>Onde ouvir a <?= e(explode(' ', $s['site_name'])[0]) ?> hoje</h1>
    <?php if ($s['live_url']): ?>
      <p class="acoes"><a class="btn btn-cheio" href="<?= e($s['live_url']) ?>" target="_blank" rel="noopener">Ouvir ao vivo</a></p>
    <?php endif ?>
  </div>
</section>

<section class="faixa faixa-sem-topo">
  <div class="wrap">
    <?php if (!$trabalhos): ?>
      <p class="vazio">Os programas e projetos atuais serão publicados aqui.</p>
    <?php endif ?>
    <div class="trabalhos">
      <?php foreach ($trabalhos as $t): ?>
        <article class="trabalho">
          <?php if ($t['image']): ?>
            <div class="corte">
              <?= picture($t['image'], (int) $t['image_w'], (int) $t['image_h'], $t['name'], '(min-width: 800px) 40vw, 92vw', BASE,
                  ['focal' => [$t['focal_x'], $t['focal_y']]]) ?>
            </div>
          <?php endif ?>
          <div class="trabalho-corpo">
            <h2><?= e($t['name']) ?></h2>
            <?php if ($t['outlet']): ?><p class="trabalho-onde"><?= e($t['outlet']) ?></p><?php endif ?>
            <?php if ($t['schedule']): ?><p class="trabalho-quando"><?= e($t['schedule']) ?></p><?php endif ?>
            <?php if ($t['description']): ?><div class="trabalho-desc"><?= paragraphs($t['description']) ?></div><?php endif ?>
            <?php if ($t['link']): ?>
              <p><a class="btn btn-linha" href="<?= e($t['link']) ?>" target="_blank" rel="noopener"><?= e($t['link_label'] ?: 'Saiba mais') ?></a></p>
            <?php endif ?>
          </div>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>
<?php page_footer();
