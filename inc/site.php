<?php
/** Peças comuns das páginas públicas: endereços, cabeçalho, rodapé, sitemap. */
declare(strict_types=1);

function url(string $path = ''): string
{
    return BASE . ltrim($path, '/');
}

function abs_url(string $path = ''): string
{
    $host = preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    return (is_https() ? 'https' : 'http') . '://' . $host . url($path);
}

function asset(string $path): string
{
    return url('assets/' . $path) . '?v=' . ASSET_VERSION;
}

function photo_url(array $p): string
{
    $slug = slugify($p['title']);
    return url('foto/' . $p['id'] . ($slug !== '' ? '-' . $slug : ''));
}

function decade_label(?int $d): string
{
    return $d ? 'Anos ' . $d : 'Sem data';
}

function whatsapp_link(): string
{
    return whatsapp_url(setting('whatsapp'), setting('whatsapp_msg'));
}

/** Canais de contato preenchidos no painel: [rótulo, texto, link]. */
function contact_channels(): array
{
    $s = settings();
    $list = [];
    if ($w = whatsapp_link()) $list[] = ['WhatsApp', $s['whatsapp'], $w];
    if ($s['email'])     $list[] = ['E-mail', $s['email'], 'mailto:' . $s['email']];
    if ($s['instagram']) $list[] = ['Instagram', social_handle($s['instagram']), social_url($s['instagram'], 'https://www.instagram.com/')];
    if ($s['facebook'])  $list[] = ['Facebook', ltrim(social_handle($s['facebook']), '@'), social_url($s['facebook'], 'https://www.facebook.com/')];
    if ($s['youtube'])   $list[] = ['YouTube', social_handle($s['youtube']), social_url($s['youtube'], 'https://www.youtube.com/@')];
    if ($s['tiktok'])    $list[] = ['TikTok', social_handle($s['tiktok']), social_url($s['tiktok'], 'https://www.tiktok.com/@')];
    return $list;
}

/** Itens do menu; áudios e vídeos só entram quando há conteúdo. */
function menu_items(): array
{
    $pdo = db();
    $has = fn(string $type) => (bool) $pdo->query("SELECT 1 FROM media WHERE type = '$type' LIMIT 1")->fetchColumn();
    $items = ['minha-historia' => 'Minha história', 'galeria' => 'Galeria', 'trabalhos' => 'Trabalhos'];
    if ($has('audio')) $items['audios'] = 'Áudios';
    if ($has('video')) $items['videos'] = 'Vídeos';
    return $items + ['recados' => 'Recados', 'contato' => 'Contato'];
}

/** Balão de fala do logotipo, usado como detalhe gráfico. */
function icon_balao(): string
{
    return '<svg class="balao" viewBox="0 0 100 100" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="5" stroke-linejoin="round" stroke-linecap="round">'
        . '<path d="M50 8a40 40 0 1 1-17 76.200L14 92l6.500-19.500A40 40 0 0 1 50 8z"/></svg>';
}

function icon_whatsapp(): string
{
    return '<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
        . '<path d="M3.5 20.5l1.3-4.6A8.5 8.5 0 1 1 8.2 19.3L3.500 20.500z"/>'
        . '<path d="M9 8.300c0 3.600 3.100 6.700 6.700 6.700l1-1.700-2-1-.8.9c-1.100-.4-2.700-2-3.100-3.100l.9-.8-1-2L9 8.300z" fill="currentColor" stroke="none"/></svg>';
}

/**
 * Abre a página: <head> com SEO e o cabeçalho com o menu.
 * $meta: title, description, path (endereço canônico), image (endereço absoluto), jsonld, current, body_class
 */
function page_header(array $meta): void
{
    $s = settings();
    $name = $s['site_name'];
    $title = isset($meta['title']) ? $meta['title'] . ' | ' . $name : $name . ' | ' . $s['slogan'];
    $desc = excerpt($meta['description'] ?? ($name . ': ' . $s['slogan'] . ' ' . $s['invite']), 160);
    $canonical = abs_url($meta['path'] ?? '');
    $image = $meta['image'] ?? ($s['hero_photo'] ? abs_url('uploads/' . $s['hero_photo'] . '-m.jpg') : '');
    $current = $meta['current'] ?? '';
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:type" content="<?= e($meta['og_type'] ?? 'website') ?>">
<meta property="og:site_name" content="<?= e($name) ?>">
<meta property="og:locale" content="pt_BR">
<meta property="og:title" content="<?= e($meta['title'] ?? $name) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($image): ?>
<meta property="og:image" content="<?= e($image) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php endif ?>
<meta name="theme-color" content="#f6f4f0">
<link rel="preload" href="<?= url('assets/fonts/lora-latin-600-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= url('assets/fonts/poppins-latin-400-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
<?php if (!empty($meta['jsonld'])): ?>
<script type="application/ld+json"><?= json_encode($meta['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif ?>
</head>
<body class="<?= e($meta['body_class'] ?? '') ?>">
<a class="skip" href="#conteudo">Pular para o conteúdo</a>
<header class="dial">
  <div class="wrap dial-in">
    <a class="marca" href="<?= url() ?>"><b><?= e($name) ?></b><small>Comunicação</small></a>
    <nav class="dial-faixa" id="dial" aria-label="Seções do site">
      <span class="ponteiro" id="ponteiro" aria-hidden="true"></span>
      <?php $estacoes = menu_items(); $n = count($estacoes); $i = 0;
      foreach ($estacoes as $path => $label): $freq = number_format(88.1 + ($n > 1 ? $i++ * (19.6 / ($n - 1)) : 0), 1, ',', ''); ?>
        <a href="<?= url($path) ?>"<?= $current === $path ? ' aria-current="page"' : '' ?>><small aria-hidden="true"><?= $freq ?></small><?= $label ?></a>
      <?php endforeach ?>
    </nav>
    <?php if ($s['live_url']): ?>
      <a class="ao-vivo" href="<?= e($s['live_url']) ?>" target="_blank" rel="noopener"><span class="ponto" aria-hidden="true"></span>Ouvir ao vivo</a>
    <?php endif ?>
  </div>
</header>
<main id="conteudo">
<?php
}

function page_footer(): void
{
    $s = settings();
    $whats = whatsapp_link();
    ?>
</main>
<footer class="rodape">
  <div class="wrap rodape-in">
    <div>
      <p class="rodape-nome"><?= e($s['site_name']) ?></p>
      <p class="rodape-slogan"><?= e($s['slogan']) ?> <?= e($s['invite']) ?></p>
    </div>
    <ul class="rodape-links">
      <?php foreach (contact_channels() as [$label, $value, $link]): ?>
        <li><a href="<?= e($link) ?>"<?= str_starts_with($link, 'http') ? ' target="_blank" rel="noopener"' : '' ?>><?= $label ?></a></li>
      <?php endforeach ?>
    </ul>
  </div>
  <div class="wrap rodape-fim">
    <p>© <?= date('Y') ?> <?= e($s['site_name']) ?></p>
    <p><a href="<?= url('admin/') ?>">Painel</a></p>
  </div>
</footer>
<?php if ($whats): ?>
<a class="zap" href="<?= e($whats) ?>" target="_blank" rel="noopener" aria-label="Chamar no WhatsApp"><?= icon_whatsapp() ?><span>WhatsApp</span></a>
<?php endif ?>
<script src="<?= asset('js/site.js') ?>" defer></script>
</body>
</html>
<?php
}

/** Polaroid com link para a página da foto. $tilt alterna a rotação. */
function polaroid(array $p, string $sizes, int $index = 0, bool $lightbox = false): string
{
    $meta = photo_meta($p);
    $data = $lightbox ? ' data-foto="' . e(json_encode([
        'src' => url('uploads/' . $p['file'] . '-l.jpg'), 'w' => (int) $p['w'], 'h' => (int) $p['h'],
        'alt' => photo_alt($p), 'titulo' => $p['title'], 'meta' => $meta, 'quem' => $p['people'],
        'historia' => $p['story'], 'url' => photo_url($p),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"' : '';
    return '<figure class="polaroid t' . ($index % 4) . '"><a href="' . e(photo_url($p)) . '"' . $data . '>'
        . picture($p['file'], (int) $p['w'], (int) $p['h'], photo_alt($p), $sizes, BASE)
        . '<figcaption>' . ($p['title'] !== '' ? '<span class="pol-titulo">' . e($p['title']) . '</span>' : '')
        . ($meta !== '' ? '<span class="pol-meta">' . e($meta) . '</span>' : '') . '</figcaption></a></figure>';
}

function sitemap(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    $urls = [''];
    foreach (array_keys(menu_items()) as $p) $urls[] = $p;
    foreach (db()->query("SELECT id, title FROM photos WHERE status = 'published' ORDER BY id") as $p) {
        $urls[] = ltrim(substr(photo_url($p), strlen(BASE)), '/');
    }
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $u) echo '  <url><loc>' . e(abs_url($u)) . "</loc></url>\n";
    echo "</urlset>\n";
}
