<?php
/** Contato: WhatsApp e redes sociais. Sem formulário, por decisão do projeto. */
$canais = contact_channels();
$whats = whatsapp_link();

page_header([
    'title' => 'Contato', 'path' => 'contato', 'current' => 'contato',
    'description' => 'Fale com ' . $s['site_name'] . ' pelo WhatsApp ou pelas redes sociais.',
]);
?>
<section class="faixa faixa-escura cabeca contato-faixa">
  <div class="wrap contato">
    <div>
      <p class="kicker">Contato</p>
      <h1>Vamos conversar</h1>
      <p class="intro">Para locuções, apresentações, eventos, entrevistas e parcerias.</p>
      <?php if ($whats): ?>
        <p class="acoes"><a class="btn btn-vermelho btn-grande" href="<?= e($whats) ?>" target="_blank" rel="noopener"><?= icon_whatsapp() ?>Chamar no WhatsApp</a></p>
      <?php endif ?>
    </div>
    <?php if ($canais): ?>
      <ul class="canais">
        <?php foreach ($canais as [$label, $value, $link]): ?>
          <li><a href="<?= e($link) ?>"<?= str_starts_with($link, 'http') ? ' target="_blank" rel="noopener"' : '' ?>><span><?= $label ?></span><strong><?= e($value) ?></strong></a></li>
        <?php endforeach ?>
      </ul>
    <?php else: ?>
      <p class="vazio">Os canais de contato serão publicados em breve.</p>
    <?php endif ?>
  </div>
</section>
<?php page_footer();
