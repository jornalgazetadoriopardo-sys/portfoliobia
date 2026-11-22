<?php
/** Página de uma foto: endereço próprio, para compartilhar. */
$st = $pdo->prepare("SELECT * FROM photos WHERE id = ? AND status = 'published'");
$st->execute([$route['id']]);
$p = $st->fetch();
if (!$p) {
    require __DIR__ . '/erro404.php';
    return;
}
// Vizinhas na mesma ordem da galeria (década, ano, cadastro).
$ordem = $pdo->query("SELECT id, title FROM photos WHERE status = 'published' ORDER BY decade IS NULL, decade, year, id")->fetchAll();
$pos = array_search($p['id'], array_column($ordem, 'id'));
$ant = $ordem[$pos - 1] ?? null;
$prox = $ordem[$pos + 1] ?? null;

$titulo = $p['title'] !== '' ? $p['title'] : 'Foto do acervo';
$desc = $p['story'] !== '' ? $p['story'] : implode('. ', array_filter([photo_meta($p), $p['people']]));
$path = ltrim(substr(photo_url($p), strlen(BASE)), '/');

page_header([
    'title' => $titulo . ($p['year'] ? ' (' . $p['year'] . ')' : ''), 'path' => $path, 'current' => 'galeria',
    'description' => $desc ?: $titulo, 'og_type' => 'article',
    'image' => abs_url('uploads/' . $p['file'] . '-m.jpg'),
]);
?>
<section class="faixa foto-pagina">
  <div class="wrap">
    <p class="voltar"><a href="<?= url('galeria' . ($p['decade'] ? '/decada/' . $p['decade'] : '')) ?>">Voltar para a galeria<?= $p['decade'] ? ', ' . mb_strtolower(decade_label((int) $p['decade'])) : '' ?></a></p>
    <figure class="foto-inteira">
      <?= picture($p['file'], (int) $p['w'], (int) $p['h'], photo_alt($p), '(min-width: 1100px) 1000px, 94vw', BASE, ['eager' => true]) ?>
    </figure>
    <div class="foto-ficha">
      <h1><?= e($titulo) ?></h1>
      <dl>
        <?php if ($p['year']): ?><div><dt>Ano</dt><dd><?= (int) $p['year'] ?></dd></div><?php endif ?>
        <?php if ($p['place']): ?><div><dt>Local</dt><dd><?= e($p['place']) ?></dd></div><?php endif ?>
        <?php if ($p['people']): ?><div><dt>Quem aparece</dt><dd><?= e($p['people']) ?></dd></div><?php endif ?>
      </dl>
      <?php if ($p['story']): ?><div class="foto-historia"><?= paragraphs($p['story']) ?></div><?php endif ?>
    </div>
    <nav class="foto-vizinhas" aria-label="Outras fotos">
      <?php if ($ant): ?><a rel="prev" href="<?= e(photo_url($ant)) ?>">‹ Foto anterior</a><?php else: ?><span></span><?php endif ?>
      <?php if ($prox): ?><a rel="next" href="<?= e(photo_url($prox)) ?>">Próxima foto ›</a><?php endif ?>
    </nav>
  </div>
</section>
<?php page_footer();
