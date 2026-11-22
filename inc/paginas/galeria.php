<?php
/** Galeria / Bastidores: fotos por época, com filtro por década. */
$decadas = $pdo->query("SELECT decade, COUNT(*) AS n FROM photos WHERE status = 'published' GROUP BY decade ORDER BY decade IS NULL, decade")->fetchAll();
$filtro = $route['decada'];

if ($filtro === null) {
    $fotos = $pdo->query("SELECT * FROM photos WHERE status = 'published' ORDER BY decade IS NULL, decade, year, id")->fetchAll();
} elseif ($filtro === 'sem-data') {
    $fotos = $pdo->query("SELECT * FROM photos WHERE status = 'published' AND decade IS NULL ORDER BY id")->fetchAll();
} else {
    $st = $pdo->prepare("SELECT * FROM photos WHERE status = 'published' AND decade = ? ORDER BY year, id");
    $st->execute([(int) $filtro]);
    $fotos = $st->fetchAll();
}
if ($filtro !== null && !$fotos) {
    require __DIR__ . '/erro404.php';
    return;
}

$grupos = [];
foreach ($fotos as $p) $grupos[$p['decade'] ?? 0][] = $p;
$rotulo = $filtro === null ? '' : ($filtro === 'sem-data' ? 'Sem data' : decade_label((int) $filtro));

page_header([
    'title' => 'Galeria' . ($rotulo ? ', ' . mb_strtolower($rotulo) : ''),
    'path' => 'galeria' . ($filtro !== null ? '/decada/' . $filtro : ''), 'current' => 'galeria',
    'description' => 'Fotos de bastidores, estúdio e encontros de ' . $s['site_name'] . ', organizadas por época.',
]);
?>
<section class="faixa cabeca">
  <div class="wrap">
    <p class="kicker">Galeria</p>
    <h1>Bastidores</h1>
    <p class="intro"><?= e($s['invite']) ?> Cada foto traz o ano, o lugar, quem aparece e a história daquele momento.</p>
    <?php if (count($decadas) > 1): ?>
      <nav class="filtro" aria-label="Filtrar por década">
        <a href="<?= url('galeria') ?>"<?= $filtro === null ? ' aria-current="true"' : '' ?>>Todas</a>
        <?php foreach ($decadas as $d): $slug = $d['decade'] ? (string) $d['decade'] : 'sem-data'; ?>
          <a href="<?= url('galeria/decada/' . $slug) ?>"<?= $filtro === $slug ? ' aria-current="true"' : '' ?>><?= decade_label($d['decade'] ? (int) $d['decade'] : null) ?></a>
        <?php endforeach ?>
      </nav>
    <?php endif ?>
  </div>
</section>

<section class="faixa faixa-sem-topo">
  <div class="wrap">
    <?php if (!$fotos): ?>
      <p class="vazio">As primeiras fotos estão a caminho.</p>
    <?php endif ?>
    <?php $n = 0; foreach ($grupos as $decada => $lista): ?>
      <?php if ($filtro === null && count($grupos) > 1): ?>
        <h2 class="decada-titulo"><?= decade_label($decada ?: null) ?></h2>
      <?php endif ?>
      <div class="mural">
        <?php foreach ($lista as $p) echo polaroid($p, '(min-width: 1000px) 30vw, (min-width: 600px) 45vw, 92vw', $n++, true) ?>
      </div>
    <?php endforeach ?>
  </div>
</section>

<dialog class="luz" id="luz" aria-label="Foto ampliada">
  <button class="luz-fechar" type="button" data-fechar aria-label="Fechar">×</button>
  <button class="luz-nav luz-ant" type="button" aria-label="Foto anterior">‹</button>
  <div class="luz-palco" id="luz-palco"></div>
  <button class="luz-nav luz-prox" type="button" aria-label="Próxima foto">›</button>
  <div class="luz-legenda" id="luz-legenda"></div>
</dialog>
<?php page_footer();
