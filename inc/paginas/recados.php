<?php
/** Recados de ouvintes, colegas e amigos. O envio por visitantes é opcional (Configurações). */
start_session();
$_SESSION['form_started'] = time();
$flash = $_SESSION['recado_flash'] ?? null;
unset($_SESSION['recado_flash']);

$recados = $pdo->query("SELECT * FROM comments WHERE status = 'approved' ORDER BY id DESC LIMIT 300")->fetchAll();
$aberto = $s['visitor_notes'] === '1';
$primeiro = explode(' ', $s['site_name'])[0];

page_header([
    'title' => 'Recados', 'path' => 'recados', 'current' => 'recados',
    'description' => 'Depoimentos de ouvintes, colegas e amigos sobre ' . $s['site_name'] . '.',
]);
?>
<section class="faixa cabeca">
  <div class="wrap">
    <p class="kicker">Recados</p>
    <h1>De quem ouve, trabalha e convive</h1>
  </div>
</section>

<section class="faixa faixa-sem-topo">
  <div class="wrap">
    <?php if (!$recados): ?>
      <p class="vazio">Ainda não há recados publicados.<?= $aberto ? ' O primeiro pode ser o seu.' : '' ?></p>
    <?php endif ?>
    <div class="recados">
      <?php foreach ($recados as $c): ?>
        <blockquote class="recado">
          <p><?= nl2br(e($c['message'])) ?></p>
          <footer>
            <?php if ($c['photo']): ?><img src="<?= url('uploads/' . e($c['photo']) . '-s.jpg') ?>" alt="" width="56" height="56" loading="lazy"><?php endif ?>
            <span><strong><?= e($c['name']) ?></strong>
              <?= e(implode(', ', array_filter([RELATIONS[$c['relation']] ?? '', $c['city']]))) ?></span>
          </footer>
        </blockquote>
      <?php endforeach ?>
    </div>
  </div>
</section>

<?php if ($aberto): ?>
<section class="faixa faixa-creme-escuro" id="enviar">
  <div class="wrap recado-form">
    <div>
      <p class="kicker">Deixe o seu</p>
      <h2>Já ouviu ou conviveu com a <?= e($primeiro) ?>?</h2>
      <p>Escreva o seu recado.<?= $s['comments_moderation'] === '1' ? ' Ele aparece aqui depois de aprovado.' : '' ?></p>
    </div>
    <form action="<?= url('comentar.php') ?>" method="post" id="form-recado" novalidate>
      <?= csrf_field() ?>
      <p class="armadilha" aria-hidden="true"><label>Não preencha este campo <input type="text" name="site" tabindex="-1" autocomplete="off"></label></p>
      <div class="campos-2">
        <p class="campo"><label for="f-nome">Seu nome</label><input id="f-nome" name="nome" type="text" maxlength="60" required autocomplete="name"></p>
        <p class="campo"><label for="f-cidade">Cidade <span>(opcional)</span></label><input id="f-cidade" name="cidade" type="text" maxlength="60" autocomplete="address-level2"></p>
      </div>
      <p class="campo"><label for="f-relacao">Você é</label>
        <select id="f-relacao" name="relacao">
          <?php foreach (RELATIONS as $k => $label): ?><option value="<?= $k ?>"><?= $label ?></option><?php endforeach ?>
        </select></p>
      <p class="campo"><label for="f-msg">Recado</label><textarea id="f-msg" name="mensagem" rows="5" maxlength="800" required></textarea></p>
      <p class="form-fim">
        <button class="btn btn-vermelho" type="submit">Enviar recado</button>
        <span class="form-msg<?= $flash ? ($flash['ok'] ? ' ok' : ' erro') : '' ?>" id="recado-msg" role="status"><?= $flash ? e($flash['message']) : '' ?></span>
      </p>
    </form>
  </div>
</section>
<?php endif ?>
<?php page_footer();
