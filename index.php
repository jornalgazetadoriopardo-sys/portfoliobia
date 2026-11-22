<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

start_session();
$_SESSION['form_started'] = time();
$flash = $_SESSION['recado_flash'] ?? null;
unset($_SESSION['recado_flash']);

$pdo = db();
$s = settings();
$name = $s['site_name'];

$bioPhotos = $pdo->query("SELECT * FROM photos WHERE section = 'bio' ORDER BY position DESC, id DESC LIMIT 3")->fetchAll();
$photos    = $pdo->query("SELECT * FROM photos WHERE section = 'galeria' ORDER BY position DESC, id DESC")->fetchAll();
$videos    = $pdo->query("SELECT * FROM media WHERE type = 'video' ORDER BY position DESC, id DESC")->fetchAll();
$audios    = $pdo->query("SELECT * FROM media WHERE type = 'audio' ORDER BY position DESC, id DESC")->fetchAll();
$comments  = $pdo->query("SELECT * FROM comments WHERE status = 'approved' ORDER BY id DESC LIMIT 200")->fetchAll();

$whats = whatsapp_url($s['whatsapp'], "Olá, $name! Vi o seu site e quero falar com você.");

// Canais de contato preenchidos no painel.
$contacts = [];
if ($whats)            $contacts[] = ['WhatsApp', $s['whatsapp'], $whats];
if ($s['phone'])       $contacts[] = ['Telefone', $s['phone'], 'tel:+' . (strlen(digits($s['phone'])) <= 11 ? '55' : '') . digits($s['phone'])];
if ($s['email'])       $contacts[] = ['E-mail', $s['email'], 'mailto:' . $s['email']];
if ($s['instagram'])   $contacts[] = ['Instagram', social_handle($s['instagram']), social_url($s['instagram'], 'https://www.instagram.com/')];
if ($s['facebook'])    $contacts[] = ['Facebook', ltrim(social_handle($s['facebook']), '@'), social_url($s['facebook'], 'https://www.facebook.com/')];
if ($s['youtube'])     $contacts[] = ['YouTube', social_handle($s['youtube']), social_url($s['youtube'], 'https://www.youtube.com/@')];
if ($s['tiktok'])      $contacts[] = ['TikTok', social_handle($s['tiktok']), social_url($s['tiktok'], 'https://www.tiktok.com/@')];

// Estações do dial: só entram as seções que têm conteúdo.
$stations = ['sobre' => 'Sobre'];
if ($photos) $stations['fotos']  = 'Fotos';
if ($videos) $stations['videos'] = 'Vídeos';
if ($audios) $stations['audios'] = 'Áudios';
$stations['recados'] = 'Recados';
if ($contacts) $stations['contato'] = 'Contato';

$freqs = [];
$n = count($stations);
$i = 0;
foreach ($stations as $id => $_) {
    $freqs[$id] = number_format(89.1 + ($n > 1 ? $i * (17.8 / ($n - 1)) : 0), 1, ',', '');
    $i++;
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$base = ($https ? 'https' : 'http') . '://' . preg_replace('/[^A-Za-z0-9.:\\-\\[\\]]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost')
      . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';

$PAGE = 12; // fotos e recados visíveis antes do "ver mais"
$description = $name . ', ' . mb_strtolower($s['tagline']) . '. ' . $s['hero_text'];
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($name) ?> | <?= e($s['tagline']) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($name . ' | ' . $s['tagline']) ?>">
<meta property="og:description" content="<?= e($s['hero_text']) ?>">
<?php if ($s['hero_photo']): ?><meta property="og:image" content="<?= e($base . 'uploads/' . $s['hero_photo']) ?>"><?php endif ?>
<meta name="theme-color" content="#17103A">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Figtree:ital,wght@0,400..700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/site.css?v=1">
</head>
<body>
<a class="skip" href="#sobre">Pular para o conteúdo</a>

<header class="dial" id="dial">
  <a class="dial-brand" href="#topo"><?= e($name) ?></a>
  <nav class="dial-band" aria-label="Seções do site">
    <span class="dial-needle" id="needle" aria-hidden="true"></span>
    <?php foreach ($stations as $id => $label): ?>
      <a href="#<?= $id ?>" data-station="<?= $id ?>"><small aria-hidden="true"><?= $freqs[$id] ?></small><?= $label ?></a>
    <?php endforeach ?>
  </nav>
</header>

<main>
<section class="hero" id="topo">
  <div class="wrap hero-grid">
    <div class="hero-copy">
      <p class="onair"><span class="lamp" aria-hidden="true"></span>No ar<?= $s['city'] ? ', de ' . e($s['city']) : '' ?></p>
      <h1><?= e($name) ?></h1>
      <p class="hero-tagline"><?= e($s['tagline']) ?></p>
      <p class="hero-text"><?= e($s['hero_text']) ?></p>
      <div class="actions">
        <?php if ($audios): ?>
          <a class="btn btn-signal" href="#audios">Ouvir a <?= e($name) ?></a>
        <?php elseif ($videos): ?>
          <a class="btn btn-signal" href="#videos">Assistir aos vídeos</a>
        <?php else: ?>
          <a class="btn btn-signal" href="#sobre">Conhecer a <?= e($name) ?></a>
        <?php endif ?>
        <?php if ($whats): ?>
          <a class="btn btn-ghost" href="<?= e($whats) ?>" target="_blank" rel="noopener">Chamar no WhatsApp</a>
        <?php endif ?>
      </div>
    </div>
    <div class="signal" aria-hidden="<?= $s['hero_photo'] ? 'false' : 'true' ?>">
      <span class="ring"></span><span class="ring"></span><span class="ring"></span>
      <div class="signal-core">
        <?php if ($s['hero_photo']): ?>
          <img src="uploads/<?= e($s['hero_photo']) ?>" alt="Retrato de <?= e($name) ?>" width="640" height="640" fetchpriority="high">
        <?php else: ?>
          <span class="signal-initial"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
        <?php endif ?>
      </div>
    </div>
  </div>
</section>

<section class="band" id="sobre">
  <div class="wrap about<?= $bioPhotos ? '' : ' about-solo' ?>">
    <div class="about-text">
      <h2>Quem é a <?= e($name) ?></h2>
      <div class="prose"><?= paragraphs($s['bio']) ?></div>
    </div>
    <?php if ($bioPhotos): ?>
      <div class="about-photos n<?= count($bioPhotos) ?>">
        <?php foreach ($bioPhotos as $p): ?>
          <figure>
            <img src="uploads/<?= e($p['thumb']) ?>" alt="<?= e($p['caption'] ?: 'Foto de ' . $name) ?>" width="<?= (int) $p['w'] ?>" height="<?= (int) $p['h'] ?>" loading="lazy">
            <?php if ($p['caption']): ?><figcaption><?= e($p['caption']) ?></figcaption><?php endif ?>
          </figure>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </div>
</section>

<?php if ($photos): ?>
<section class="band band-tint" id="fotos">
  <div class="wrap">
    <h2>Fotos</h2>
    <p class="lede">Bastidores, estúdio, eventos e encontros com quem ouve.</p>
    <div class="gallery" id="gallery">
      <?php foreach ($photos as $i => $p): ?>
        <figure<?= $i >= $PAGE ? ' hidden' : '' ?>>
          <a href="uploads/<?= e($p['file']) ?>" data-photo="<?= $i ?>" data-caption="<?= e($p['caption']) ?>">
            <img src="uploads/<?= e($p['thumb']) ?>" alt="<?= e($p['caption'] ?: 'Foto ' . ($i + 1) . ' da galeria') ?>" width="<?= (int) $p['w'] ?>" height="<?= (int) $p['h'] ?>" loading="lazy">
          </a>
          <?php if ($p['caption']): ?><figcaption><?= e($p['caption']) ?></figcaption><?php endif ?>
        </figure>
      <?php endforeach ?>
    </div>
    <?php if (count($photos) > $PAGE): ?>
      <p class="more"><button class="btn btn-line" type="button" data-more="#gallery > figure" data-step="<?= $PAGE ?>">Ver mais fotos</button></p>
    <?php endif ?>
  </div>
</section>
<?php endif ?>

<?php if ($videos): ?>
<section class="band" id="videos">
  <div class="wrap">
    <h2>Vídeos</h2>
    <p class="lede">Entrevistas, apresentações e reportagens.</p>
    <div class="videos">
      <?php foreach ($videos as $v): ?>
        <article class="video">
          <button type="button" data-video="<?= e($v['youtube_id']) ?>" data-title="<?= e($v['title']) ?>" aria-label="Assistir: <?= e($v['title']) ?>">
            <img src="https://i.ytimg.com/vi/<?= e($v['youtube_id']) ?>/hqdefault.jpg" alt="" width="480" height="360" loading="lazy">
            <span class="play" aria-hidden="true"></span>
          </button>
          <h3><?= e($v['title']) ?></h3>
          <?php if ($v['description']): ?><p><?= e($v['description']) ?></p><?php endif ?>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>
<?php endif ?>

<?php if ($audios): ?>
<section class="band band-night" id="audios">
  <div class="wrap">
    <h2>Áudios</h2>
    <p class="lede">Trechos de programas, vinhetas e locuções. Escolha uma faixa e aperte o play.</p>
    <div class="studio" id="studio">
      <div class="deck">
        <div class="screen">
          <div class="yt-slot" aria-hidden="true"><div id="yt-audio"></div></div>
          <canvas id="viz" width="900" height="360" aria-hidden="true"></canvas>
          <p class="screen-status" id="deck-status">Pronto para tocar</p>
        </div>
        <p class="deck-title" id="deck-title" aria-live="polite"><?= e($audios[0]['title']) ?></p>
        <div class="deck-controls">
          <button class="deck-play" id="deck-play" type="button" aria-label="Tocar"><span aria-hidden="true"></span></button>
          <span class="deck-time" id="deck-now">0:00</span>
          <input class="deck-seek" id="deck-seek" type="range" min="0" max="1000" value="0" aria-label="Posição do áudio">
          <span class="deck-time" id="deck-total">0:00</span>
        </div>
      </div>
      <ol class="tracks" id="tracks">
        <?php foreach ($audios as $i => $a): ?>
          <li>
            <button type="button" data-audio="<?= e($a['youtube_id']) ?>" data-title="<?= e($a['title']) ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>>
              <span class="track-eq" aria-hidden="true"><i></i><i></i><i></i></span>
              <span class="track-name"><?= e($a['title']) ?></span>
              <?php if ($a['description']): ?><span class="track-desc"><?= e($a['description']) ?></span><?php endif ?>
            </button>
          </li>
        <?php endforeach ?>
      </ol>
    </div>
  </div>
</section>
<?php endif ?>

<section class="band band-tint" id="recados">
  <div class="wrap notes">
    <div class="notes-form">
      <h2>Recados</h2>
      <p class="lede">Já ouviu, trabalhou ou conviveu com a <?= e($name) ?>? Deixe o seu recado.</p>
      <form action="comentar.php" method="post" id="note-form" novalidate>
        <?= csrf_field() ?>
        <p class="trap" aria-hidden="true"><label>Não preencha este campo <input type="text" name="site" tabindex="-1" autocomplete="off"></label></p>
        <div class="field-row">
          <p class="field"><label for="f-nome">Seu nome</label><input id="f-nome" name="nome" type="text" maxlength="60" required autocomplete="name"></p>
          <p class="field"><label for="f-cidade">Cidade <span>(opcional)</span></label><input id="f-cidade" name="cidade" type="text" maxlength="60" autocomplete="address-level2"></p>
        </div>
        <p class="field"><label for="f-msg">Recado</label><textarea id="f-msg" name="mensagem" rows="5" maxlength="800" required></textarea></p>
        <p class="form-foot">
          <button class="btn btn-signal" type="submit">Enviar recado</button>
          <span class="form-msg<?= $flash ? ($flash['ok'] ? ' ok' : ' err') : '' ?>" id="note-msg" role="status"><?= $flash ? e($flash['message']) : '' ?></span>
        </p>
      </form>
    </div>
    <div class="notes-list" id="notes">
      <?php if (!$comments): ?>
        <p class="empty">Ainda não há recados publicados. O primeiro pode ser o seu.</p>
      <?php endif ?>
      <?php foreach ($comments as $i => $c): ?>
        <blockquote<?= $i >= $PAGE ? ' hidden' : '' ?>>
          <p><?= nl2br(e($c['message'])) ?></p>
          <footer><strong><?= e($c['name']) ?></strong><?= $c['city'] ? ', ' . e($c['city']) : '' ?> <time datetime="<?= e($c['created_at']) ?>"><?= date_br($c['created_at']) ?></time></footer>
        </blockquote>
      <?php endforeach ?>
      <?php if (count($comments) > $PAGE): ?>
        <p class="more"><button class="btn btn-line" type="button" data-more="#notes > blockquote" data-step="<?= $PAGE ?>">Ver mais recados</button></p>
      <?php endif ?>
    </div>
  </div>
</section>

<?php if ($contacts): ?>
<section class="band band-night" id="contato">
  <div class="wrap contact">
    <div>
      <h2>Contato</h2>
      <p class="lede">Para locuções, apresentações, eventos, entrevistas e parcerias.</p>
      <?php if ($whats): ?>
        <p><a class="btn btn-signal" href="<?= e($whats) ?>" target="_blank" rel="noopener">Chamar no WhatsApp</a></p>
      <?php endif ?>
    </div>
    <ul class="channels">
      <?php foreach ($contacts as [$label, $value, $url]): ?>
        <li><a href="<?= e($url) ?>"<?= str_starts_with($url, 'http') ? ' target="_blank" rel="noopener"' : '' ?>><span><?= $label ?></span><strong><?= e($value) ?></strong></a></li>
      <?php endforeach ?>
    </ul>
  </div>
</section>
<?php endif ?>
</main>

<footer class="foot">
  <div class="wrap">
    <p>© <?= date('Y') ?> <?= e($name) ?>. Todos os direitos reservados.</p>
    <p><a href="admin/">Painel</a></p>
  </div>
</footer>

<dialog class="lightbox" id="lightbox" aria-label="Visualização ampliada">
  <button class="lb-close" type="button" data-close aria-label="Fechar">×</button>
  <button class="lb-nav lb-prev" type="button" aria-label="Foto anterior">‹</button>
  <div class="lb-stage" id="lb-stage"></div>
  <button class="lb-nav lb-next" type="button" aria-label="Próxima foto">›</button>
  <p class="lb-caption" id="lb-caption"></p>
</dialog>

<script src="assets/js/site.js?v=1" defer></script>
</body>
</html>
