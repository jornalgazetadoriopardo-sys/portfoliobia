<?php
/** Painel administrativo: entrada, autenticação e moldura das telas. */
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/site.php';

define('BASE', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php', 2)), '/') . '/');

@ini_set('memory_limit', '256M'); // fotos de celular pedem memória para redimensionar
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
start_session();

$pdo = db();
$hasUser = (bool) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$logged = $hasUser && !empty($_SESSION['admin']);
$page = preg_replace('/[^a-z]/', '', (string) ($_GET['p'] ?? 'inicio'));
$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function flash(string $text, bool $ok = true): void { $_SESSION['flash'] = [$text, $ok]; }
function back(string $query): never { redirect('?p=' . $query); }
function post(string $key, int $max = 300): string { return clean((string) ($_POST[$key] ?? ''), $max); }
function json_out(array $data, int $code = 200): never
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../inc/admin/acoes.php';
    exit;
}

// Download do original sem tratamento (fica fora da pasta pública).
if ($logged && $page === 'original') {
    $st = $pdo->prepare('SELECT original, title FROM photos WHERE id = ?');
    $st->execute([(int) ($_GET['id'] ?? 0)]);
    $p = $st->fetch();
    $file = $p && preg_match('/^[A-Za-z0-9.-]+$/', $p['original']) ? ORIGINAL_DIR . '/' . $p['original'] : '';
    if ($file === '' || !is_file($file)) { http_response_code(404); exit('Original não encontrado.'); }
    header('Content-Type: image/jpeg');
    header('Content-Disposition: attachment; filename="original-' . (slugify($p['title']) ?: 'foto') . '.jpg"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$s = settings();
$pending = $logged ? (int) $pdo->query("SELECT COUNT(*) FROM comments WHERE status = 'pending'")->fetchColumn() : 0;

$menu = ['inicio' => 'Início', 'scanner' => 'Scanner', 'fotos' => 'Fotos', 'historia' => 'História', 'trabalhos' => 'Trabalhos',
         'audios' => 'Áudios', 'videos' => 'Vídeos', 'recados' => 'Recados', 'config' => 'Configurações', 'senha' => 'Senha'];
if (!isset($menu[$page])) $page = 'inicio';
$adminAsset = fn(string $f) => e(BASE . 'assets/' . $f . '?v=' . ASSET_VERSION);
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($menu[$page]) ?> | Painel <?= e($s['site_name']) ?></title>
<link rel="stylesheet" href="<?= $adminAsset('css/admin.css') ?>">
</head>
<body class="p-<?= $page ?>">
<?php if (!$logged): ?>
<main class="portao">
  <form method="post" class="cartao">
    <?= csrf_field() ?>
    <h1><?= $hasUser ? 'Painel' : 'Primeiro acesso' ?></h1>
    <p class="dica"><?= $hasUser ? 'Entre para atualizar o site.' : 'Crie o usuário e a senha que vão dar acesso ao painel.' ?></p>
    <?php if ($flash): ?><p class="aviso <?= $flash[1] ? 'ok' : 'erro' ?>" role="status"><?= e($flash[0]) ?></p><?php endif ?>
    <label>Usuário <input name="usuario" required autocomplete="username" autocapitalize="none" autofocus></label>
    <label>Senha <input name="senha" type="password" required autocomplete="<?= $hasUser ? 'current-password' : 'new-password' ?>"<?= $hasUser ? '' : ' minlength="8"' ?>></label>
    <?php if (!$hasUser): ?>
      <label>Repita a senha <input name="senha2" type="password" required autocomplete="new-password" minlength="8"></label>
    <?php endif ?>
    <button name="action" value="<?= $hasUser ? 'login' : 'setup' ?>" class="primario largo"><?= $hasUser ? 'Entrar' : 'Criar conta' ?></button>
    <p class="dica centro"><a href="<?= e(BASE) ?>">Voltar ao site</a></p>
  </form>
</main>
<?php else: ?>
<header class="barra">
  <div class="barra-in">
    <strong><?= e($s['site_name']) ?> <span>painel</span></strong>
    <div class="barra-fim">
      <a href="<?= e(BASE) ?>" target="_blank" rel="noopener">Ver o site</a>
      <form method="post"><?= csrf_field() ?><button name="action" value="logout" class="link">Sair</button></form>
    </div>
  </div>
  <nav aria-label="Seções do painel">
    <?php foreach ($menu as $k => $label): ?>
      <a href="?p=<?= $k ?>"<?= $k === $page ? ' aria-current="page"' : '' ?>><?= $label ?><?= $k === 'recados' && $pending ? " <b>$pending</b>" : '' ?></a>
    <?php endforeach ?>
  </nav>
</header>
<main class="pagina">
<?php if ($flash): ?><p class="aviso <?= $flash[1] ? 'ok' : 'erro' ?>" role="status"><?= e($flash[0]) ?></p><?php endif ?>
<?php require __DIR__ . '/../inc/admin/telas.php'; ?>
</main>
<script src="<?= $adminAsset('js/admin.js') ?>" defer></script>
<?php endif ?>
</body>
</html>
