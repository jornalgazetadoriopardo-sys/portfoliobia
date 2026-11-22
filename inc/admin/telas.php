<?php
/** Telas do painel. Incluído por admin/index.php depois da autenticação. */
declare(strict_types=1);

$editId = (int) ($_GET['id'] ?? 0);
$isNew = isset($_GET['novo']) || isset($_GET['nova']);
$thumb = fn(string $base) => e(BASE . 'uploads/' . $base . '-s.jpg');

/** Botões de ordem e exclusão usados nas listas. */
function row_tools(string $prefix, int $id, string $confirm, string $voltar = '', bool $mover = true): string
{
    $h = '<form method="post" class="ferramentas">' . csrf_field()
       . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="voltar" value="' . e($voltar) . '">';
    if ($mover) {
        $h .= '<input type="hidden" name="dir" value="up">'
            . '<button name="action" value="' . $prefix . '_mover" class="icone" aria-label="Mostrar antes" title="Mostrar antes" onclick="this.form.dir.value=\'up\'">↑</button>'
            . '<button name="action" value="' . $prefix . '_mover" class="icone" aria-label="Mostrar depois" title="Mostrar depois" onclick="this.form.dir.value=\'down\'">↓</button>';
    }
    return $h . '<button name="action" value="' . $prefix . '_excluir" class="perigo" onclick="return confirm(\'' . e($confirm) . '\')">Excluir</button></form>';
}

/** Imagem com marcador arrastável para escolher o ponto focal. */
function focal_picker(string $src, float $x, float $y): string
{
    return '<div class="focal"><div class="focal-img" data-focal><img src="' . $src . '" alt="">'
         . '<span class="focal-ponto" style="left:' . $x . '%;top:' . $y . '%"></span></div>'
         . '<input type="hidden" name="focal_x" value="' . $x . '"><input type="hidden" name="focal_y" value="' . $y . '">'
         . '<p class="dica">Ponto focal: toque no rosto ou na parte mais importante. Quando a foto precisar ser cortada (capa, cartões), o corte preserva esse ponto.</p></div>';
}

/** Campos de legenda de uma foto (usados no envio, na edição e no scanner). */
function photo_form_fields(array $p = []): string
{
    $v = fn(string $k) => e((string) ($p[$k] ?? ''));
    return '<label>Título <small>uma frase curta, como numa legenda de álbum</small><input name="title" maxlength="140" value="' . $v('title') . '"></label>'
         . '<div class="grade2"><label>Ano <input name="year" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="1994" value="' . $v('year') . '"></label>'
         . '<label>Local <input name="place" maxlength="140" placeholder="Estúdio da rádio, cidade" value="' . $v('place') . '"></label></div>'
         . '<label>Quem aparece <small>da esquerda para a direita</small><input name="people" maxlength="300" value="' . $v('people') . '"></label>'
         . '<label>A história desse momento <textarea name="story" rows="4" maxlength="4000">' . $v('story') . '</textarea></label>';
}

/* ================= Início ================= */
if ($page === 'inicio'):
    $count = fn(string $sql) => (int) $pdo->query($sql)->fetchColumn();
    $drafts = $count("SELECT COUNT(*) FROM photos WHERE status = 'draft'"); ?>
  <h1>Olá, <?= e($_SESSION['admin']) ?></h1>
  <p class="dica">O que você quer fazer agora?</p>
  <div class="atalhos">
    <a class="atalho destaque" href="?p=scanner"><b>Escanear foto</b><span>Fotografe uma foto impressa e publique</span></a>
    <a class="atalho" href="?p=fotos&nova=1"><b>Nova foto</b><span>Enviar fotos do celular ou do computador</span></a>
    <a class="atalho" href="?p=historia&novo=1"><b>Novo marco</b><span>Um momento da sua história</span></a>
    <a class="atalho" href="?p=trabalhos&novo=1"><b>Novo trabalho</b><span>Programa, locução ou projeto</span></a>
    <a class="atalho" href="?p=recados&novo=1"><b>Novo recado</b><span>Depoimento de ouvinte, colega ou amigo</span></a>
  </div>
  <ul class="resumo">
    <li><a href="?p=fotos"><b><?= $count("SELECT COUNT(*) FROM photos WHERE status = 'published'") ?></b> fotos na galeria<?= $drafts ? " e $drafts em rascunho" : '' ?></a></li>
    <li><a href="?p=historia"><b><?= $count('SELECT COUNT(*) FROM milestones') ?></b> marcos na história</a></li>
    <li><a href="?p=trabalhos"><b><?= $count('SELECT COUNT(*) FROM works WHERE active = 1') ?></b> trabalhos ativos</a></li>
    <li><a href="?p=recados"><b><?= $pending ?></b> recados aguardando aprovação</a></li>
  </ul>
  <?php $todo = [];
    if (!$s['hero_photo']) $todo[] = ['config', 'Escolha a foto de capa do site'];
    if (!$s['whatsapp']) $todo[] = ['config', 'Informe o WhatsApp e as redes sociais'];
    if (!$s['live_url']) $todo[] = ['config', 'Informe o link para ouvir ao vivo'];
    if ($todo): ?>
    <section class="cartao">
      <h2>Para deixar o site completo</h2>
      <ul class="afazer"><?php foreach ($todo as [$p, $t]): ?><li><a href="?p=<?= $p ?>"><?= $t ?></a></li><?php endforeach ?></ul>
    </section>
  <?php endif ?>

<?php /* ================= Scanner ================= */
elseif ($page === 'scanner'): ?>
  <div id="scanner" class="scanner" data-csrf="<?= e(csrf_token()) ?>" data-max-post="<?= ini_bytes('post_max_size') ?: 8388608 ?>" data-max-file="<?= ini_bytes('upload_max_filesize') ?: 2097152 ?>"
       data-worker="<?= $adminAsset('js/scanner-worker.js') ?>" data-opencv="<?= e(BASE . 'assets/vendor/opencv/opencv.js') ?>">
    <ol class="passos" aria-label="Etapas">
      <li data-passo="1" aria-current="step">Fotografa</li><li data-passo="2">Alinha</li><li data-passo="3">Trata</li><li data-passo="4">Publica</li>
    </ol>

    <section class="etapa" data-etapa="1">
      <h1>Scanner de fotos</h1>
      <p class="dica">Fotografe a foto impressa. O scanner recorta o fundo, endireita e melhora a imagem.</p>
      <p class="estado-motor" id="sc-motor" role="status">Preparando o scanner…</p>
      <div class="captura">
        <label class="botao primario gigante">Fotografar agora<input type="file" accept="image/*" capture="environment" id="sc-camera" hidden></label>
        <label class="botao gigante">Escolher uma imagem do aparelho<input type="file" accept="image/*" id="sc-arquivo" hidden></label>
      </div>
      <div class="cartao orienta">
        <h2>Para a foto sair boa</h2>
        <ul>
          <li>Fique perto de uma janela e desligue o flash.</li>
          <li>Apoie a foto sobre uma superfície escura e lisa.</li>
          <li>Enquadre a foto inteira, com um pouco de fundo sobrando em volta.</li>
        </ul>
        <p class="dica">Reflexo forte em foto brilhosa e fotos muito danificadas (rasgos, manchas) melhoram, mas não ficam perfeitas.</p>
      </div>
    </section>

    <section class="etapa" data-etapa="2" hidden>
      <h1>Alinhe as bordas</h1>
      <p class="dica" id="sc-alinha-dica">Arraste os quatro cantos até as pontas da foto.</p>
      <div class="palco" id="sc-palco"><canvas id="sc-canvas"></canvas></div>
      <div class="linha-botoes">
        <button type="button" id="sc-detectar">Detectar de novo</button>
        <button type="button" id="sc-girar">Girar 90°</button>
        <button type="button" id="sc-inteira">Usar imagem inteira</button>
      </div>
      <div class="rodape-etapa">
        <button type="button" data-ir="1">Trocar a foto</button>
        <button type="button" class="primario" id="sc-alinhar-ok">Continuar</button>
      </div>
    </section>

    <section class="etapa" data-etapa="3" hidden>
      <h1>Antes e depois</h1>
      <p class="dica">Arraste a linha para comparar. Ajuste se quiser.</p>
      <div class="compara" id="sc-compara">
        <canvas id="sc-depois"></canvas>
        <div class="compara-antes" id="sc-antes-caixa"><canvas id="sc-antes"></canvas></div>
        <span class="rotulo rotulo-antes">Antes</span><span class="rotulo rotulo-depois">Depois</span>
        <div class="divisor" id="sc-divisor" role="slider" tabindex="0" aria-label="Comparar antes e depois" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50"><span></span></div>
        <p class="ocupado" id="sc-ocupado" hidden>Tratando…</p>
      </div>
      <div class="cartao ajustes">
        <label class="chave"><input type="checkbox" id="sc-auto" checked> <b>Automático</b> <small>o scanner escolhe os ajustes</small></label>
        <div id="sc-manuais">
          <?php foreach (['sombra' => ['Sombra', 50], 'cor' => ['Cor', 70], 'contraste' => ['Contraste', 50], 'nitidez' => ['Nitidez', 50]] as $k => [$rot, $val]): ?>
            <div class="ajuste">
              <label class="chave"><input type="checkbox" data-liga="<?= $k ?>" checked> <?= $rot ?></label>
              <input type="range" min="0" max="100" value="<?= $val ?>" data-forca="<?= $k ?>" aria-label="Intensidade: <?= $rot ?>">
            </div>
          <?php endforeach ?>
        </div>
        <div class="linha-botoes">
          <button type="button" id="sc-original">Voltar ao original</button>
          <button type="button" data-ir="2">Refazer alinhamento</button>
        </div>
      </div>
      <div class="rodape-etapa">
        <span></span>
        <button type="button" class="primario" id="sc-tratar-ok">Ficou boa, continuar</button>
      </div>
    </section>

    <section class="etapa" data-etapa="4" hidden>
      <h1>Legenda</h1>
      <div class="publica">
        <canvas id="sc-final" class="previa"></canvas>
        <form id="sc-form" class="cartao">
          <?= photo_form_fields() ?>
          <p class="aviso erro" id="sc-erro" role="alert" hidden></p>
          <div class="rodape-etapa">
            <button type="button" id="sc-rascunho">Salvar rascunho</button>
            <button type="button" class="primario" id="sc-publicar">Publicar na galeria</button>
          </div>
          <p class="centro"><button type="button" class="link" data-ir="3">Voltar ao tratamento</button></p>
        </form>
      </div>
    </section>

    <section class="etapa" data-etapa="5" hidden>
      <div class="cartao centro feito">
        <h1 id="sc-feito-titulo">Foto publicada na galeria</h1>
        <p class="dica">Você pode ajustar a legenda e o ponto focal quando quiser.</p>
        <p class="linha-botoes centro">
          <a class="botao primario" href="?p=scanner">Escanear outra foto</a>
          <a class="botao" id="sc-feito-editar" href="?p=fotos">Ver a foto no painel</a>
        </p>
      </div>
    </section>
  </div>
  <script src="<?= $adminAsset('js/scanner.js') ?>" defer></script>

<?php /* ================= Fotos ================= */
elseif ($page === 'fotos' && $editId):
    $st = $pdo->prepare('SELECT * FROM photos WHERE id = ?');
    $st->execute([$editId]);
    $p = $st->fetch();
    if (!$p): ?><p class="aviso erro">Foto não encontrada.</p><p><a href="?p=fotos">Voltar para as fotos</a></p><?php else: ?>
  <p class="trilha"><a href="?p=fotos">Fotos</a></p>
  <h1>Editar foto</h1>
  <form method="post" class="cartao editor-foto">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
    <?= focal_picker(e(BASE . 'uploads/' . $p['file'] . '-m.jpg'), (float) $p['focal_x'], (float) $p['focal_y']) ?>
    <div>
      <?= photo_form_fields($p) ?>
      <label>Década <small>calculada pelo ano; mude só se precisar</small>
        <input name="decade" inputmode="numeric" maxlength="4" placeholder="1990" value="<?= e((string) ($p['decade'] ?? '')) ?>"></label>
      <label class="chave"><input type="checkbox" name="featured" <?= $p['featured'] ? 'checked' : '' ?>> Destacar na página inicial</label>
      <label class="chave"><input type="checkbox" name="status" value="draft" <?= $p['status'] === 'draft' ? 'checked' : '' ?>> Rascunho <small>não aparece no site</small></label>
      <div class="linha-botoes">
        <button name="action" value="foto_salvar" class="primario">Salvar foto</button>
        <button name="action" value="foto_capa" onclick="return confirm('Usar esta foto como capa do site?')">Usar como capa do site</button>
        <?php if ($p['status'] === 'published'): ?><a class="botao" href="<?= e(photo_url($p)) ?>" target="_blank" rel="noopener">Ver no site</a><?php endif ?>
        <?php if ($p['original']): ?><a class="botao" href="?p=original&id=<?= $p['id'] ?>">Baixar o original sem tratamento</a><?php endif ?>
      </div>
    </div>
  </form>
  <?= row_tools('foto', (int) $p['id'], 'Excluir esta foto? Não dá para desfazer.', '', false) ?>
  <?php endif ?>

<?php elseif ($page === 'fotos' && $isNew): ?>
  <p class="trilha"><a href="?p=fotos">Fotos</a></p>
  <h1>Nova foto</h1>
  <form method="post" enctype="multipart/form-data" class="cartao">
    <?= csrf_field() ?>
    <p class="dica">Para fotos impressas, prefira o <a href="?p=scanner">scanner</a>. Aqui entram fotos que já estão no aparelho (JPG, PNG ou WebP). Dá para escolher várias de uma vez.</p>
    <label>Fotos <input type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple required></label>
    <?= photo_form_fields() ?>
    <p class="dica">Se enviar várias, a legenda acima vale para todas e pode ser ajustada depois, foto por foto.</p>
    <div class="linha-botoes"><button name="action" value="foto_enviar" class="primario">Publicar</button></div>
  </form>

<?php elseif ($page === 'fotos'):
    $list = $pdo->query("SELECT * FROM photos ORDER BY status = 'draft' DESC, id DESC")->fetchAll(); ?>
  <div class="titulo-acao"><h1>Fotos</h1>
    <p class="linha-botoes"><a class="botao primario" href="?p=scanner">Escanear foto</a><a class="botao" href="?p=fotos&nova=1">Nova foto</a></p></div>
  <?php if (!$list): ?>
    <p class="cartao dica">Nenhuma foto ainda. Comece escaneando uma foto impressa ou enviando uma do aparelho.</p>
  <?php else: ?>
    <ul class="grade-fotos">
      <?php foreach ($list as $p): ?>
        <li><a href="?p=fotos&id=<?= $p['id'] ?>">
          <img src="<?= $thumb($p['file']) ?>" alt="" loading="lazy" width="<?= (int) $p['w'] ?>" height="<?= (int) $p['h'] ?>">
          <span class="gf-titulo"><?= e($p['title'] ?: 'Sem título') ?></span>
          <span class="gf-meta"><?= e(photo_meta($p) ?: 'Sem ano e local') ?>
            <?php if ($p['status'] === 'draft'): ?><em class="etiqueta espera">Rascunho</em><?php endif ?>
            <?php if ($p['featured']): ?><em class="etiqueta">Destaque</em><?php endif ?></span>
        </a></li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>

<?php /* ================= Minha história ================= */
elseif ($page === 'historia' && ($editId || $isNew)):
    $m = ['id' => 0, 'period' => '', 'title' => '', 'body' => '', 'photo_id' => null];
    if ($editId) { $st = $pdo->prepare('SELECT * FROM milestones WHERE id = ?'); $st->execute([$editId]); $m = $st->fetch() ?: $m; }
    $fotos = $pdo->query("SELECT id, title, year FROM photos WHERE status = 'published' ORDER BY year IS NULL, year, id")->fetchAll(); ?>
  <p class="trilha"><a href="?p=historia">História</a></p>
  <h1><?= $m['id'] ? 'Editar marco' : 'Novo marco' ?></h1>
  <form method="post" class="cartao estreito">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
    <label>Ano ou período <small>ex.: 1992 ou 1990–1995</small><input name="period" maxlength="40" required value="<?= e($m['period']) ?>"></label>
    <label>Título <input name="title" maxlength="140" required value="<?= e($m['title']) ?>"></label>
    <label>Texto <small>deixe uma linha em branco entre os parágrafos</small><textarea name="body" rows="8" maxlength="6000"><?= e($m['body']) ?></textarea></label>
    <label>Foto <small>opcional; escolha entre as fotos da galeria</small>
      <select name="photo_id"><option value="0">Sem foto</option>
        <?php foreach ($fotos as $f): ?>
          <option value="<?= $f['id'] ?>"<?= (int) $m['photo_id'] === (int) $f['id'] ? ' selected' : '' ?>><?= e(($f['year'] ? $f['year'] . ' · ' : '') . ($f['title'] ?: 'Foto sem título nº ' . $f['id'])) ?></option>
        <?php endforeach ?>
      </select></label>
    <div class="linha-botoes"><button name="action" value="marco_salvar" class="primario">Salvar marco</button></div>
  </form>

<?php elseif ($page === 'historia'):
    $list = $pdo->query('SELECT * FROM milestones ORDER BY position ASC, id ASC')->fetchAll(); ?>
  <div class="titulo-acao"><h1>Minha história</h1><p class="linha-botoes"><a class="botao primario" href="?p=historia&novo=1">Novo marco</a></p></div>
  <section class="cartao">
    <?php if (!$list): ?><p class="dica">Nenhum marco ainda. Cada marco é um momento da linha do tempo: o começo no rádio, uma mudança de emissora, um programa, um prêmio.</p><?php else: ?>
    <p class="dica">Os marcos aparecem no site nesta ordem. Use as setas para arrumar.</p>
    <ul class="lista"><?php foreach ($list as $m): ?>
      <li><a class="lista-corpo" href="?p=historia&id=<?= $m['id'] ?>"><b><?= e($m['period']) ?></b> <?= e($m['title']) ?></a>
        <?= row_tools('marco', (int) $m['id'], 'Excluir este marco?') ?></li>
    <?php endforeach ?></ul><?php endif ?>
  </section>
  <form method="post" class="cartao">
    <?= csrf_field() ?>
    <h2>Texto de abertura</h2>
    <label>Aparece no topo da página, antes da linha do tempo <small>opcional</small>
      <textarea name="history_intro" rows="4" maxlength="2000"><?= e($s['history_intro']) ?></textarea></label>
    <div class="linha-botoes"><button name="action" value="intro_salvar">Salvar texto</button></div>
  </form>

<?php /* ================= Trabalhos ================= */
elseif ($page === 'trabalhos' && ($editId || $isNew)):
    $t = ['id' => 0, 'name' => '', 'outlet' => '', 'schedule' => '', 'description' => '', 'image' => '', 'link' => '', 'link_label' => '', 'active' => 1, 'focal_x' => 50, 'focal_y' => 35];
    if ($editId) { $st = $pdo->prepare('SELECT * FROM works WHERE id = ?'); $st->execute([$editId]); $t = $st->fetch() ?: $t; } ?>
  <p class="trilha"><a href="?p=trabalhos">Trabalhos</a></p>
  <h1><?= $t['id'] ? 'Editar trabalho' : 'Novo trabalho' ?></h1>
  <form method="post" enctype="multipart/form-data" class="cartao estreito">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
    <label>Nome <small>do programa, da locução ou do projeto</small><input name="name" maxlength="140" required value="<?= e($t['name']) ?>"></label>
    <label>Rádio ou veículo <input name="outlet" maxlength="140" value="<?= e($t['outlet']) ?>"></label>
    <label>Dias e horários <input name="schedule" maxlength="200" placeholder="Segunda a sexta, das 8h às 11h" value="<?= e($t['schedule']) ?>"></label>
    <label>Descrição <textarea name="description" rows="5" maxlength="3000"><?= e($t['description']) ?></textarea></label>
    <div class="grade2">
      <label>Link <small>ouvir ao vivo ou saiba mais</small><input name="link" inputmode="url" maxlength="300" placeholder="https://" value="<?= e($t['link']) ?>"></label>
      <label>Texto do botão <input name="link_label" maxlength="40" placeholder="Ouvir ao vivo" value="<?= e($t['link_label']) ?>"></label>
    </div>
    <?php if ($t['image']) echo focal_picker($thumb($t['image']), (float) $t['focal_x'], (float) $t['focal_y']) ?>
    <label><?= $t['image'] ? 'Trocar a imagem' : 'Imagem' ?> <small>opcional</small><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
    <label class="chave"><input type="checkbox" name="active" <?= $t['active'] ? 'checked' : '' ?>> Ativo <small>desmarque para tirar do site sem apagar</small></label>
    <div class="linha-botoes"><button name="action" value="trabalho_salvar" class="primario">Salvar trabalho</button></div>
  </form>

<?php elseif ($page === 'trabalhos'):
    $list = $pdo->query('SELECT * FROM works ORDER BY position DESC, id DESC')->fetchAll(); ?>
  <div class="titulo-acao"><h1>Trabalhos atuais</h1><p class="linha-botoes"><a class="botao primario" href="?p=trabalhos&novo=1">Novo trabalho</a></p></div>
  <section class="cartao">
    <?php if (!$list): ?><p class="dica">Nenhum trabalho cadastrado. Inclua os programas, horários, locuções comerciais e outros projetos.</p><?php else: ?>
    <p class="dica">O primeiro da lista aparece em destaque na página inicial.</p>
    <ul class="lista"><?php foreach ($list as $t): ?>
      <li><a class="lista-corpo" href="?p=trabalhos&id=<?= $t['id'] ?>"><b><?= e($t['name']) ?></b> <?= e($t['outlet']) ?>
          <?php if (!$t['active']): ?><em class="etiqueta espera">Fora do site</em><?php endif ?></a>
        <?= row_tools('trabalho', (int) $t['id'], 'Excluir este trabalho?') ?></li>
    <?php endforeach ?></ul><?php endif ?>
  </section>

<?php /* ================= Áudios e vídeos ================= */
elseif ($page === 'videos' || $page === 'audios'):
    $type = $page === 'audios' ? 'audio' : 'video';
    $um = $type === 'audio' ? 'áudio' : 'vídeo';
    $st = $pdo->prepare('SELECT * FROM media WHERE type = ? ORDER BY position DESC, id DESC');
    $st->execute([$type]);
    $list = $st->fetchAll(); ?>
  <h1><?= $menu[$page] ?></h1>
  <form method="post" enctype="multipart/form-data" class="cartao">
    <?= csrf_field() ?><input type="hidden" name="type" value="<?= $type ?>">
    <h2>Publicar <?= $um ?></h2>
    <label>Título <input name="title" required maxlength="140"></label>
    <?php if ($type === 'audio'): ?>
      <div class="grade2">
        <label>Arquivo MP3 <input type="file" name="mp3" accept=".mp3,audio/mpeg"></label>
        <label>ou link do YouTube <small>o site toca só o som</small><input name="youtube" placeholder="https://www.youtube.com/watch?v=..." inputmode="url"></label>
      </div>
      <p class="dica">Limite de envio deste servidor: <?= e(ini_get('upload_max_filesize')) ?> por arquivo. Áudios longos ficam melhor no YouTube.</p>
    <?php else: ?>
      <label>Link do YouTube <small>o vídeo precisa estar como público ou não listado</small><input name="youtube" required placeholder="https://www.youtube.com/watch?v=..." inputmode="url"></label>
    <?php endif ?>
    <div class="grade2">
      <label>Ano <small>opcional</small><input name="year" inputmode="numeric" maxlength="4"></label>
      <label>Descrição <small>opcional</small><input name="description" maxlength="300"></label>
    </div>
    <div class="linha-botoes"><button name="action" value="midia_salvar" class="primario">Publicar <?= $um ?></button></div>
  </form>
  <section class="cartao">
    <h2><?= count($list) ?> <?= $type === 'audio' ? (count($list) === 1 ? 'áudio publicado' : 'áudios publicados') : (count($list) === 1 ? 'vídeo publicado' : 'vídeos publicados') ?></h2>
    <?php if ($list): ?><ul class="lista midias"><?php foreach ($list as $m): ?>
      <li>
        <form method="post" enctype="multipart/form-data" class="lista-corpo editar">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="type" value="<?= $type ?>">
          <input name="title" value="<?= e($m['title']) ?>" maxlength="140" required aria-label="Título">
          <input name="description" value="<?= e($m['description']) ?>" maxlength="300" placeholder="Descrição" aria-label="Descrição">
          <div class="grade2">
            <input name="year" value="<?= e((string) ($m['year'] ?? '')) ?>" inputmode="numeric" maxlength="4" placeholder="Ano" aria-label="Ano">
            <input name="youtube" value="<?= $m['youtube_id'] ? 'https://youtu.be/' . e($m['youtube_id']) : '' ?>" placeholder="<?= $m['file'] ? 'Arquivo MP3 enviado' : 'Link do YouTube' ?>" aria-label="Link do YouTube">
          </div>
          <?php if ($m['file']): ?><audio controls preload="none" src="<?= e(BASE . 'uploads/audio/' . $m['file']) ?>"></audio><?php endif ?>
          <button name="action" value="midia_salvar">Salvar alterações</button>
        </form>
        <?= row_tools('midia', (int) $m['id'], "Excluir este $um?", $page) ?>
      </li><?php endforeach ?></ul><?php endif ?>
  </section>

<?php /* ================= Recados ================= */
elseif ($page === 'recados' && ($editId || $isNew)):
    $c = ['id' => 0, 'name' => '', 'city' => '', 'relation' => 'ouvinte', 'message' => '', 'photo' => ''];
    if ($editId) { $st = $pdo->prepare('SELECT * FROM comments WHERE id = ?'); $st->execute([$editId]); $c = $st->fetch() ?: $c; } ?>
  <p class="trilha"><a href="?p=recados">Recados</a></p>
  <h1><?= $c['id'] ? 'Editar recado' : 'Novo recado' ?></h1>
  <form method="post" enctype="multipart/form-data" class="cartao estreito">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
    <label>Nome <input name="name" maxlength="60" required value="<?= e($c['name']) ?>"></label>
    <div class="grade2">
      <label>Relação <select name="relation"><?php foreach (RELATIONS as $k => $label): ?><option value="<?= $k ?>"<?= $c['relation'] === $k ? ' selected' : '' ?>><?= $label ?></option><?php endforeach ?></select></label>
      <label>Cidade <small>opcional</small><input name="city" maxlength="60" value="<?= e($c['city']) ?>"></label>
    </div>
    <label>Recado <textarea name="message" rows="6" maxlength="1500" required><?= e($c['message']) ?></textarea></label>
    <div class="campo-foto">
      <?php if ($c['photo']): ?><img src="<?= $thumb($c['photo']) ?>" alt="" class="avatar"><?php endif ?>
      <label>Foto da pessoa <small>opcional</small><input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label>
    </div>
    <div class="linha-botoes"><button name="action" value="recado_salvar" class="primario">Salvar recado</button></div>
  </form>

<?php elseif ($page === 'recados'):
    $list = $pdo->query("SELECT * FROM comments ORDER BY status = 'pending' DESC, id DESC LIMIT 500")->fetchAll(); ?>
  <div class="titulo-acao"><h1>Recados</h1><p class="linha-botoes"><a class="botao primario" href="?p=recados&novo=1">Novo recado</a></p></div>
  <form method="post" class="cartao">
    <?= csrf_field() ?>
    <label class="chave"><input type="checkbox" name="visitor_notes" <?= $s['visitor_notes'] === '1' ? 'checked' : '' ?>> Visitantes podem enviar recados pelo site</label>
    <label class="chave"><input type="checkbox" name="moderar" <?= $s['comments_moderation'] === '1' ? 'checked' : '' ?>> Recado de visitante só aparece depois que eu aprovar</label>
    <div class="linha-botoes"><button name="action" value="recado_opcoes">Salvar preferências</button></div>
  </form>
  <section class="cartao">
    <?php if (!$list): ?><p class="dica">Nenhum recado ainda.</p><?php else: ?>
    <ul class="recados"><?php foreach ($list as $c): ?>
      <li class="<?= $c['status'] ?>">
        <p class="meta"><strong><?= e($c['name']) ?></strong> <?= e(implode(', ', array_filter([RELATIONS[$c['relation']] ?? '', $c['city']]))) ?>
          <span class="etiqueta<?= $c['status'] === 'pending' ? ' espera' : '' ?>"><?= $c['status'] === 'pending' ? 'Aguardando' : 'No site' ?></span>
          <small><?= date_br($c['created_at']) ?></small></p>
        <p><?= nl2br(e($c['message'])) ?></p>
        <form method="post" class="ferramentas"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>">
          <?php if ($c['status'] === 'pending'): ?><button name="action" value="recado_aprovar" class="primario">Publicar</button>
          <?php else: ?><button name="action" value="recado_ocultar">Retirar do site</button><?php endif ?>
          <a class="botao" href="?p=recados&id=<?= $c['id'] ?>">Editar</a>
          <button name="action" value="recado_excluir" class="perigo" onclick="return confirm('Excluir este recado?')">Excluir</button></form>
      </li><?php endforeach ?></ul><?php endif ?>
  </section>

<?php /* ================= Configurações ================= */
elseif ($page === 'config'): ?>
  <h1>Configurações do site</h1>
  <form method="post" enctype="multipart/form-data" class="cartao">
    <?= csrf_field() ?>
    <h2>Capa</h2>
    <?php if ($s['hero_photo']) echo focal_picker(e(BASE . 'uploads/' . $s['hero_photo'] . '-m.jpg'), (float) $s['hero_focal_x'], (float) $s['hero_focal_y']) ?>
    <label><?= $s['hero_photo'] ? 'Trocar a foto de capa' : 'Foto de capa' ?> <small>também dá para escolher uma foto da galeria: abra a foto e toque em "Usar como capa do site"</small>
      <input type="file" name="hero_photo" accept="image/jpeg,image/png,image/webp"></label>
    <div class="grade2">
      <label>Nome que aparece no site <input name="site_name" value="<?= e($s['site_name']) ?>" maxlength="60" required></label>
      <label>Profissão <input name="tagline" value="<?= e($s['tagline']) ?>" maxlength="90"></label>
    </div>
    <label>Slogan <input name="slogan" value="<?= e($s['slogan']) ?>" maxlength="140"></label>
    <label>Convite <small>frase curta que acompanha o slogan na capa e no rodapé</small><input name="invite" value="<?= e($s['invite']) ?>" maxlength="200"></label>
    <label>Texto curto "sobre" <small>uma ou duas frases para a página inicial</small><textarea name="about" rows="3" maxlength="400"><?= e($s['about']) ?></textarea></label>
    <label>Cidade <input name="city" value="<?= e($s['city']) ?>" maxlength="80"></label>

    <h2>Ouvir ao vivo</h2>
    <label>Link da rádio ou do programa <small>deixe em branco para esconder o botão</small><input name="live_url" value="<?= e($s['live_url']) ?>" inputmode="url" placeholder="https://" maxlength="300"></label>

    <h2>WhatsApp</h2>
    <div class="grade2">
      <label>Número <small>com DDD</small><input name="whatsapp" value="<?= e($s['whatsapp']) ?>" placeholder="(19) 99999-9999" inputmode="tel"></label>
      <label>Mensagem que já vem escrita <input name="whatsapp_msg" value="<?= e($s['whatsapp_msg']) ?>" maxlength="200"></label>
    </div>

    <h2>Redes sociais e e-mail</h2>
    <p class="dica">Só aparecem no site os campos preenchidos.</p>
    <div class="grade2">
      <label>Instagram <small>@usuario ou link</small><input name="instagram" value="<?= e($s['instagram']) ?>" autocapitalize="none"></label>
      <label>Facebook <small>usuário ou link</small><input name="facebook" value="<?= e($s['facebook']) ?>" autocapitalize="none"></label>
      <label>YouTube <small>@canal ou link</small><input name="youtube" value="<?= e($s['youtube']) ?>" autocapitalize="none"></label>
      <label>TikTok <small>@usuario ou link</small><input name="tiktok" value="<?= e($s['tiktok']) ?>" autocapitalize="none"></label>
      <label>E-mail <small>opcional</small><input name="email" type="email" value="<?= e($s['email']) ?>"></label>
    </div>
    <div class="linha-botoes">
      <button name="action" value="config" class="primario">Salvar configurações</button>
      <?php if ($s['hero_photo']): ?><button name="action" value="hero_remover" class="perigo" formnovalidate onclick="return confirm('Remover a foto de capa?')">Remover foto de capa</button><?php endif ?>
    </div>
  </form>

<?php /* ================= Senha ================= */
elseif ($page === 'senha'): ?>
  <h1>Senha</h1>
  <form method="post" class="cartao estreito">
    <?= csrf_field() ?>
    <label>Senha atual <input name="atual" type="password" required autocomplete="current-password"></label>
    <label>Nova senha <small>pelo menos 8 caracteres</small><input name="nova" type="password" required minlength="8" autocomplete="new-password"></label>
    <label>Repita a nova senha <input name="nova2" type="password" required minlength="8" autocomplete="new-password"></label>
    <div class="linha-botoes"><button name="action" value="senha" class="primario">Alterar senha</button></div>
  </form>
<?php endif;
