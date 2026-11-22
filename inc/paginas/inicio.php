<?php
/** Início: capa, fotos em destaque, trabalho atual, trecho da história e um recado. */
$name = $s['site_name'];

$fotos = $pdo->query("SELECT * FROM photos WHERE status = 'published' ORDER BY featured DESC, id DESC LIMIT 4")->fetchAll();
$trabalho = $pdo->query('SELECT * FROM works WHERE active = 1 ORDER BY position DESC, id DESC LIMIT 1')->fetch();
$marcos = $pdo->query('SELECT * FROM milestones ORDER BY position ASC, id ASC LIMIT 3')->fetchAll();
$recado = $pdo->query("SELECT * FROM comments WHERE status = 'approved' ORDER BY id DESC LIMIT 1")->fetch();

$sameAs = array_values(array_filter(array_map(fn($c) => str_starts_with($c[2], 'http') && $c[0] !== 'WhatsApp' ? $c[2] : null, contact_channels())));
$jsonld = array_filter([
    '@context' => 'https://schema.org', '@type' => 'Person',
    'name' => $name, 'jobTitle' => $s['tagline'], 'description' => $s['about'],
    'url' => abs_url(), 'image' => $s['hero_photo'] ? abs_url('uploads/' . $s['hero_photo'] . '-m.jpg') : null,
    'homeLocation' => $s['city'] ?: null, 'sameAs' => $sameAs ?: null,
]);

page_header(['description' => $s['about'], 'jsonld' => $jsonld, 'body_class' => 'pg-inicio']);
?>
<section class="capa<?= $s['hero_photo'] ? '' : ' capa-sem-foto' ?>">
  <?php if ($s['hero_photo']): ?>
    <div class="capa-foto">
      <?= picture($s['hero_photo'], (int) $s['hero_w'], (int) $s['hero_h'], 'Retrato de ' . $name, '(min-width: 900px) 55vw, 100vw', BASE,
          ['focal' => [$s['hero_focal_x'], $s['hero_focal_y']], 'eager' => true]) ?>
    </div>
  <?php endif ?>
  <div class="wrap capa-texto">
    <p class="selo"><span class="ponto" aria-hidden="true"></span>No ar</p>
    <h1><?= e($name) ?></h1>
    <p class="capa-slogan"><?= e($s['slogan']) ?></p>
    <div class="acoes">
      <?php if ($s['live_url']): ?>
        <a class="btn btn-vermelho" href="<?= e($s['live_url']) ?>" target="_blank" rel="noopener">Ouvir ao vivo</a>
      <?php endif ?>
      <a class="btn <?= $s['live_url'] ? 'btn-claro' : 'btn-vermelho' ?>" href="<?= url('minha-historia') ?>">Conhecer a história</a>
    </div>
  </div>
</section>

<section class="faixa">
  <div class="wrap sobre-curto">
    <p class="kicker">Sobre</p>
    <p class="frase"><?= e($s['about']) ?></p>
  </div>
</section>

<?php if ($fotos): ?>
<section class="faixa faixa-creme-escuro">
  <div class="wrap">
    <p class="kicker">Galeria</p>
    <h2>Dos bastidores</h2>
    <div class="varal">
      <?php foreach ($fotos as $i => $p) echo polaroid($p, '(min-width: 900px) 22vw, 45vw', $i) ?>
    </div>
    <p class="mais"><a class="btn btn-linha" href="<?= url('galeria') ?>">Ver a galeria completa</a></p>
  </div>
</section>
<?php endif ?>

<?php if ($trabalho): ?>
<section class="faixa faixa-escura">
  <div class="wrap destaque-trabalho">
    <?php if ($trabalho['image']): ?>
      <div class="corte corte-largo">
        <?= picture($trabalho['image'], (int) $trabalho['image_w'], (int) $trabalho['image_h'], $trabalho['name'], '(min-width: 900px) 45vw, 100vw', BASE,
            ['focal' => [$trabalho['focal_x'], $trabalho['focal_y']]]) ?>
      </div>
    <?php endif ?>
    <div>
      <p class="kicker">No ar hoje</p>
      <h2><?= e($trabalho['name']) ?></h2>
      <?php if ($trabalho['outlet'] || $trabalho['schedule']): ?>
        <p class="trabalho-quando"><?= e(implode(', ', array_filter([$trabalho['outlet'], $trabalho['schedule']]))) ?></p>
      <?php endif ?>
      <?php if ($trabalho['description']): ?><p><?= e(excerpt($trabalho['description'], 220)) ?></p><?php endif ?>
      <div class="acoes">
        <?php if ($trabalho['link']): ?>
          <a class="btn btn-vermelho" href="<?= e($trabalho['link']) ?>" target="_blank" rel="noopener"><?= e($trabalho['link_label'] ?: 'Ouvir ao vivo') ?></a>
        <?php endif ?>
        <a class="btn btn-claro" href="<?= url('trabalhos') ?>">Todos os trabalhos</a>
      </div>
    </div>
  </div>
</section>
<?php endif ?>

<?php if ($marcos): ?>
<section class="faixa">
  <div class="wrap">
    <p class="kicker">Minha história</p>
    <h2>Como tudo começou</h2>
    <ol class="trecho">
      <?php foreach ($marcos as $m): ?>
        <li>
          <p class="trecho-ano"><?= e($m['period']) ?></p>
          <h3><?= e($m['title']) ?></h3>
          <p><?= e(excerpt($m['body'], 160)) ?></p>
        </li>
      <?php endforeach ?>
    </ol>
    <p class="mais"><a class="btn btn-linha" href="<?= url('minha-historia') ?>">Ler a história inteira</a></p>
  </div>
</section>
<?php endif ?>

<?php if ($recado): ?>
<section class="faixa faixa-creme-escuro">
  <div class="wrap recado-destaque">
    <p class="kicker">Recados</p>
    <blockquote>
      <p><?= e(excerpt($recado['message'], 280)) ?></p>
      <footer><?= e($recado['name']) ?><?= isset(RELATIONS[$recado['relation']]) ? ', ' . mb_strtolower(RELATIONS[$recado['relation']]) : '' ?></footer>
    </blockquote>
    <p class="mais"><a class="btn btn-linha" href="<?= url('recados') ?>">Ler todos os recados</a></p>
  </div>
</section>
<?php endif ?>
<?php page_footer();
