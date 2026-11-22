<?php
http_response_code(404);
page_header(['title' => 'Página não encontrada', 'path' => '']);
?>
<section class="faixa cabeca">
  <div class="wrap">
    <p class="kicker">Fora do ar</p>
    <h1>Esta página não existe</h1>
    <p class="intro">O endereço pode ter mudado ou a foto foi retirada da galeria.</p>
    <p class="acoes"><a class="btn btn-cheio" href="<?= url() ?>">Ir para o início</a> <a class="btn btn-linha" href="<?= url('galeria') ?>">Ver a galeria</a></p>
  </div>
</section>
<?php page_footer();
