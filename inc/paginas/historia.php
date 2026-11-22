<?php
/** Minha História: linha do tempo agrupada por década. */
$marcos = $pdo->query('SELECT m.*, p.file, p.w, p.h, p.title AS ptitle, p.people, p.year, p.place, p.story
                       FROM milestones m LEFT JOIN photos p ON p.id = m.photo_id AND p.status = \'published\'
                       ORDER BY m.position ASC, m.id ASC')->fetchAll();
$porDecada = [];
foreach ($marcos as $m) $porDecada[decade_of_period($m['period']) ?? 0][] = $m;

page_header([
    'title' => 'Minha história', 'path' => 'minha-historia', 'current' => 'minha-historia',
    'description' => $s['history_intro'] ?: 'A trajetória de ' . $s['site_name'] . ' no rádio, década por década.',
]);
?>
<section class="faixa cabeca">
  <div class="wrap">
    <p class="kicker">Minha história</p>
    <h1><?= e($s['slogan']) ?></h1>
    <?php if ($s['history_intro']): ?><div class="intro"><?= paragraphs($s['history_intro']) ?></div><?php endif ?>
  </div>
</section>

<section class="faixa faixa-sem-topo">
  <div class="wrap">
    <?php if (!$marcos): ?>
      <p class="vazio">A linha do tempo está sendo montada. Volte em breve.</p>
    <?php endif ?>
    <?php foreach ($porDecada as $decada => $lista): ?>
      <div class="decada">
        <?php if ($decada): ?><h2 class="decada-titulo"><?= decade_label($decada) ?></h2><?php endif ?>
        <ol class="linha">
          <?php foreach ($lista as $i => $m): ?>
            <li class="marco<?= $m['file'] ? ' marco-com-foto' : '' ?>">
              <div class="marco-texto">
                <p class="marco-ano"><?= e($m['period']) ?></p>
                <h3><?= e($m['title']) ?></h3>
                <?= paragraphs($m['body']) ?>
              </div>
              <?php if ($m['file']): ?>
                <?= polaroid(['id' => $m['photo_id'], 'file' => $m['file'], 'w' => $m['w'], 'h' => $m['h'], 'title' => $m['ptitle'],
                              'people' => $m['people'], 'year' => $m['year'], 'place' => $m['place'], 'story' => $m['story']],
                             '(min-width: 900px) 30vw, 80vw', $i) ?>
              <?php endif ?>
            </li>
          <?php endforeach ?>
        </ol>
      </div>
    <?php endforeach ?>
  </div>
</section>
<?php page_footer();
