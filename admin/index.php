<?php
/** Painel administrativo: perfil, fotos, vídeos, áudios, recados e contatos. */
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';

@ini_set('memory_limit', '256M'); // fotos de celular pedem memória para redimensionar
header('X-Robots-Tag: noindex, nofollow');
start_session();

$pdo = db();
$hasUser = (bool) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$logged = $hasUser && !empty($_SESSION['admin']);
$page = preg_replace('/[^a-z]/', '', (string) ($_GET['p'] ?? 'inicio'));
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

function flash(string $text, bool $ok = true): void { $_SESSION['flash'] = [$text, $ok]; }
function back(string $page): never { redirect('?p=' . $page); }
function post(string $key, int $max = 300): string { return clean((string) ($_POST[$key] ?? ''), $max); }

function next_position(string $table, string $where, array $args): int
{
    $st = db()->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM $table WHERE $where");
    $st->execute($args);
    return (int) $st->fetchColumn();
}

/** Troca a posição de um item com o vizinho (para cima = aparece antes no site). */
function move_item(string $table, int $id, string $dir, string $groupCol): void
{
    $pdo = db();
    $st = $pdo->prepare("SELECT id, position, $groupCol AS g FROM $table WHERE id = ?");
    $st->execute([$id]);
    if (!$a = $st->fetch()) return;
    [$op, $order] = $dir === 'up' ? ['>', 'ASC'] : ['<', 'DESC'];
    $st = $pdo->prepare("SELECT id, position FROM $table WHERE $groupCol = ? AND (position $op ? OR (position = ? AND id $op ?))
                         ORDER BY position $order, id $order LIMIT 1");
    $st->execute([$a['g'], $a['position'], $a['position'], $a['id']]);
    if (!$b = $st->fetch()) return;
    $posA = $b['position'] === $a['position'] ? $a['position'] + ($dir === 'up' ? 1 : -1) : $b['position'];
    $up = $pdo->prepare("UPDATE $table SET position = ? WHERE id = ?");
    $up->execute([$posA, $a['id']]);
    $up->execute([$a['position'], $b['id']]);
}

/* ================= Ações (POST) ================= */
if ($isPost) {
    if (!csrf_ok()) {
        flash('A página ficou aberta por muito tempo. Tente de novo.', false);
        back($page);
    }
    $action = (string) ($_POST['action'] ?? '');

    // Primeiro acesso: cria a conta da dona do site.
    if (!$hasUser && $action === 'setup') {
        $user = post('usuario', 40);
        $pass = (string) ($_POST['senha'] ?? '');
        if (mb_strlen($user) < 3) flash('O nome de usuário precisa de pelo menos 3 letras.', false);
        elseif (strlen($pass) < 8) flash('A senha precisa de pelo menos 8 caracteres.', false);
        elseif ($pass !== ($_POST['senha2'] ?? '')) flash('As duas senhas não são iguais.', false);
        else {
            $pdo->prepare('INSERT INTO users(username, password_hash) VALUES(?, ?)')
                ->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['admin'] = $user;
            flash('Conta criada. Comece preenchendo o perfil.');
            back('perfil');
        }
        back('inicio');
    }

    if ($hasUser && $action === 'login') {
        if (attempts('login', 900) >= 6) {
            flash('Muitas tentativas. Aguarde 15 minutos e tente de novo.', false);
            back('inicio');
        }
        $st = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $st->execute([post('usuario', 40)]);
        $u = $st->fetch();
        if ($u && password_verify((string) ($_POST['senha'] ?? ''), $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin'] = $u['username'];
        } else {
            add_attempt('login');
            flash('Usuário ou senha incorretos.', false);
        }
        back('inicio');
    }

    if (!$logged) back('inicio');

    switch ($action) {
        case 'logout':
            $_SESSION = [];
            session_destroy();
            redirect('./');

        /* ----- Perfil ----- */
        case 'perfil':
            foreach (['site_name' => 60, 'tagline' => 90, 'city' => 60, 'hero_text' => 240, 'bio' => 6000] as $k => $max) {
                set_setting($k, post($k, $max));
            }
            if (post('site_name', 60) === '') set_setting('site_name', SETTING_DEFAULTS['site_name']);
            if (!empty($_FILES['hero_photo']['name'])) {
                $img = save_image($_FILES['hero_photo']);
                if (is_string($img)) { flash($img, false); back('perfil'); }
                $old = setting('hero_photo');
                delete_image_files($old, str_replace('.jpg', '-p.jpg', $old));
                set_setting('hero_photo', $img['file']);
            }
            flash('Perfil salvo.');
            back('perfil');

        case 'hero_remover':
            $old = setting('hero_photo');
            delete_image_files($old, str_replace('.jpg', '-p.jpg', $old));
            set_setting('hero_photo', '');
            flash('Foto de abertura removida.');
            back('perfil');

        /* ----- Fotos (galeria e biografia) ----- */
        case 'foto_enviar':
            $section = ($_POST['section'] ?? '') === 'bio' ? 'bio' : 'galeria';
            $caption = post('caption', 200);
            $okCount = 0; $errors = [];
            foreach (uploaded_list('fotos') as $up) {
                $img = save_image($up);
                if (is_string($img)) { $errors[] = e($up['name']) . ': ' . $img; continue; }
                $pdo->prepare('INSERT INTO photos(file, thumb, caption, w, h, section, position, created_at) VALUES(?,?,?,?,?,?,?,?)')
                    ->execute([$img['file'], $img['thumb'], $caption, $img['w'], $img['h'], $section,
                               next_position('photos', 'section = ?', [$section]), date('c')]);
                $okCount++;
            }
            if (!$okCount && !$errors) $errors[] = 'Nenhuma foto foi escolhida.';
            if ($okCount) flash($okCount === 1 ? 'Foto publicada.' : "$okCount fotos publicadas." . ($errors ? ' Não entraram: ' . implode(' ', $errors) : ''), !$errors);
            else flash(implode(' ', $errors), false);
            back($section === 'bio' ? 'perfil' : 'fotos');

        case 'foto_legenda':
            $pdo->prepare('UPDATE photos SET caption = ? WHERE id = ?')->execute([post('caption', 200), (int) $_POST['id']]);
            flash('Legenda salva.');
            back(post('voltar', 10) === 'perfil' ? 'perfil' : 'fotos');

        case 'foto_mover':
            move_item('photos', (int) $_POST['id'], post('dir', 4), 'section');
            back(post('voltar', 10) === 'perfil' ? 'perfil' : 'fotos');

        case 'foto_excluir':
            $st = $pdo->prepare('SELECT * FROM photos WHERE id = ?');
            $st->execute([(int) $_POST['id']]);
            if ($p = $st->fetch()) {
                delete_image_files($p['file'], $p['thumb']);
                $pdo->prepare('DELETE FROM photos WHERE id = ?')->execute([$p['id']]);
                flash('Foto excluída.');
            }
            back(post('voltar', 10) === 'perfil' ? 'perfil' : 'fotos');

        /* ----- Vídeos e áudios ----- */
        case 'midia_salvar':
            $type = ($_POST['type'] ?? '') === 'audio' ? 'audio' : 'video';
            $dest = $type === 'audio' ? 'audios' : 'videos';
            $yt = youtube_id(post('youtube', 300));
            $title = post('title', 140);
            $id = (int) ($_POST['id'] ?? 0);
            if ($yt === '') { flash('Não reconheci o link do YouTube. Cole o endereço completo do vídeo.', false); back($dest); }
            if ($title === '') { flash('Dê um título.', false); back($dest); }
            if ($id) {
                $pdo->prepare('UPDATE media SET youtube_id = ?, title = ?, description = ? WHERE id = ?')
                    ->execute([$yt, $title, post('description', 300), $id]);
                flash('Alterações salvas.');
            } else {
                $pdo->prepare('INSERT INTO media(type, youtube_id, title, description, position, created_at) VALUES(?,?,?,?,?,?)')
                    ->execute([$type, $yt, $title, post('description', 300), next_position('media', 'type = ?', [$type]), date('c')]);
                flash($type === 'audio' ? 'Áudio publicado.' : 'Vídeo publicado.');
            }
            back($dest);

        case 'midia_mover':
            move_item('media', (int) $_POST['id'], post('dir', 4), 'type');
            back(post('voltar', 10) === 'audios' ? 'audios' : 'videos');

        case 'midia_excluir':
            $pdo->prepare('DELETE FROM media WHERE id = ?')->execute([(int) $_POST['id']]);
            flash('Item excluído.');
            back(post('voltar', 10) === 'audios' ? 'audios' : 'videos');

        /* ----- Recados ----- */
        case 'recado_aprovar':
            $pdo->prepare("UPDATE comments SET status = 'approved' WHERE id = ?")->execute([(int) $_POST['id']]);
            flash('Recado publicado.');
            back('recados');
        case 'recado_ocultar':
            $pdo->prepare("UPDATE comments SET status = 'pending' WHERE id = ?")->execute([(int) $_POST['id']]);
            flash('Recado retirado do site.');
            back('recados');
        case 'recado_excluir':
            $pdo->prepare('DELETE FROM comments WHERE id = ?')->execute([(int) $_POST['id']]);
            flash('Recado excluído.');
            back('recados');
        case 'recado_modo':
            set_setting('comments_moderation', isset($_POST['moderar']) ? '1' : '0');
            flash('Preferência salva.');
            back('recados');

        /* ----- Contatos ----- */
        case 'contatos':
            foreach (['whatsapp' => 30, 'phone' => 30, 'instagram' => 120, 'facebook' => 120, 'youtube' => 120, 'tiktok' => 120] as $k => $max) {
                set_setting($k, post($k, $max));
            }
            $email = post('email', 120);
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('Os contatos foram salvos, mas o e-mail parece incorreto e ficou de fora.', false);
            } else {
                set_setting('email', $email);
                flash('Contatos salvos.');
            }
            back('contatos');

        /* ----- Senha ----- */
        case 'senha':
            $st = $pdo->prepare('SELECT * FROM users WHERE username = ?');
            $st->execute([$_SESSION['admin']]);
            $u = $st->fetch();
            $new = (string) ($_POST['nova'] ?? '');
            if (!$u || !password_verify((string) ($_POST['atual'] ?? ''), $u['password_hash'])) flash('A senha atual não confere.', false);
            elseif (strlen($new) < 8) flash('A nova senha precisa de pelo menos 8 caracteres.', false);
            elseif ($new !== ($_POST['nova2'] ?? '')) flash('As duas senhas novas não são iguais.', false);
            else {
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
                flash('Senha alterada.');
            }
            back('senha');
    }
    back($page);
}

/* ================= Telas ================= */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$s = settings();
$pending = $logged ? (int) $pdo->query("SELECT COUNT(*) FROM comments WHERE status = 'pending'")->fetchColumn() : 0;

$menu = ['inicio' => 'Início', 'perfil' => 'Perfil', 'fotos' => 'Fotos', 'videos' => 'Vídeos', 'audios' => 'Áudios',
         'recados' => 'Recados', 'contatos' => 'Contatos', 'senha' => 'Senha'];
if (!isset($menu[$page])) $page = 'inicio';

/** Botões de ordem e exclusão usados nas listas. */
function row_tools(string $prefix, int $id, string $voltar, string $confirm): string
{
    $h = '<form method="post" class="tools">' . csrf_field()
       . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="voltar" value="' . e($voltar) . '">'
       . '<button name="action" value="' . $prefix . '_mover" class="icon" title="Mostrar antes" aria-label="Mostrar antes" onclick="this.form.dir.value=\'up\'">↑</button>'
       . '<button name="action" value="' . $prefix . '_mover" class="icon" title="Mostrar depois" aria-label="Mostrar depois" onclick="this.form.dir.value=\'down\'">↓</button>'
       . '<input type="hidden" name="dir" value="up">'
       . '<button name="action" value="' . $prefix . '_excluir" class="danger" onclick="return confirm(\'' . e($confirm) . '\')">Excluir</button>'
       . '</form>';
    return $h;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Painel | <?= e($s['site_name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600..800&family=Figtree:wght@400..700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin.css?v=1">
</head>
<body>
<?php if (!$logged): ?>
<main class="gate">
  <form method="post" class="card">
    <?= csrf_field() ?>
    <h1><?= $hasUser ? 'Painel' : 'Primeiro acesso' ?></h1>
    <p class="hint"><?= $hasUser ? 'Entre para atualizar o site.' : 'Crie o usuário e a senha que vão dar acesso ao painel.' ?></p>
    <?php if ($flash): ?><p class="flash <?= $flash[1] ? 'ok' : 'err' ?>" role="status"><?= e($flash[0]) ?></p><?php endif ?>
    <label>Usuário <input name="usuario" required autocomplete="username" autofocus></label>
    <label>Senha <input name="senha" type="password" required autocomplete="<?= $hasUser ? 'current-password' : 'new-password' ?>"<?= $hasUser ? '' : ' minlength="8"' ?>></label>
    <?php if (!$hasUser): ?>
      <label>Repita a senha <input name="senha2" type="password" required autocomplete="new-password" minlength="8"></label>
    <?php endif ?>
    <button name="action" value="<?= $hasUser ? 'login' : 'setup' ?>" class="primary"><?= $hasUser ? 'Entrar' : 'Criar conta' ?></button>
    <p class="hint"><a href="../">Voltar ao site</a></p>
  </form>
</main>
<?php else: ?>
<header class="top">
  <strong><?= e($s['site_name']) ?> <span>painel</span></strong>
  <nav aria-label="Seções do painel">
    <?php foreach ($menu as $k => $label): ?>
      <a href="?p=<?= $k ?>"<?= $k === $page ? ' aria-current="page"' : '' ?>><?= $label ?><?= $k === 'recados' && $pending ? " <b>$pending</b>" : '' ?></a>
    <?php endforeach ?>
  </nav>
  <div class="top-end">
    <a href="../" target="_blank" rel="noopener">Ver o site</a>
    <form method="post"><?= csrf_field() ?><button name="action" value="logout" class="link">Sair</button></form>
  </div>
</header>

<main class="page">
<?php if ($flash): ?><p class="flash <?= $flash[1] ? 'ok' : 'err' ?>" role="status"><?= $flash[0] /* já escapado na origem quando contém nomes de arquivo */ ?></p><?php endif ?>

<?php if ($page === 'inicio'):
    $count = fn(string $sql) => (int) $pdo->query($sql)->fetchColumn(); ?>
  <h1>Olá, <?= e($_SESSION['admin']) ?></h1>
  <p class="hint">Tudo o que você alterar aqui aparece no site na mesma hora.</p>
  <ul class="summary">
    <li><a href="?p=fotos"><b><?= $count("SELECT COUNT(*) FROM photos WHERE section = 'galeria'") ?></b> fotos na galeria</a></li>
    <li><a href="?p=videos"><b><?= $count("SELECT COUNT(*) FROM media WHERE type = 'video'") ?></b> vídeos</a></li>
    <li><a href="?p=audios"><b><?= $count("SELECT COUNT(*) FROM media WHERE type = 'audio'") ?></b> áudios</a></li>
    <li><a href="?p=recados"><b><?= $pending ?></b> recados aguardando aprovação</a></li>
  </ul>
  <?php if (!$s['hero_photo'] || !$s['whatsapp']): ?>
    <section class="card">
      <h2>Para deixar o site completo</h2>
      <ul class="todo">
        <?php if (!$s['hero_photo']): ?><li><a href="?p=perfil">Envie a foto de abertura e escreva a biografia</a></li><?php endif ?>
        <?php if (!$s['whatsapp']): ?><li><a href="?p=contatos">Informe o WhatsApp e as redes sociais</a></li><?php endif ?>
      </ul>
    </section>
  <?php endif ?>

<?php elseif ($page === 'perfil'):
    $bio = $pdo->query("SELECT * FROM photos WHERE section = 'bio' ORDER BY position DESC, id DESC")->fetchAll(); ?>
  <h1>Perfil</h1>
  <form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?>
    <div class="grid2">
      <label>Nome que aparece no site <input name="site_name" value="<?= e($s['site_name']) ?>" maxlength="60" required></label>
      <label>Profissão ou frase curta <input name="tagline" value="<?= e($s['tagline']) ?>" maxlength="90"></label>
    </div>
    <label>Cidade <small>aparece ao lado de "No ar"</small> <input name="city" value="<?= e($s['city']) ?>" maxlength="60"></label>
    <label>Apresentação <small>uma ou duas frases na abertura do site</small>
      <textarea name="hero_text" rows="2" maxlength="240"><?= e($s['hero_text']) ?></textarea></label>
    <label>Biografia <small>deixe uma linha em branco entre os parágrafos</small>
      <textarea name="bio" rows="12" maxlength="6000"><?= e($s['bio']) ?></textarea></label>
    <div class="photo-field">
      <?php if ($s['hero_photo']): ?><img src="../uploads/<?= e($s['hero_photo']) ?>" alt="Foto de abertura atual" class="avatar"><?php endif ?>
      <label>Foto de abertura <small>um retrato, de preferência quadrado; aparece em círculo</small>
        <input type="file" name="hero_photo" accept="image/jpeg,image/png,image/webp"></label>
    </div>
    <p class="actions"><button name="action" value="perfil" class="primary">Salvar perfil</button>
      <?php if ($s['hero_photo']): ?><button name="action" value="hero_remover" class="danger" formnovalidate onclick="return confirm('Remover a foto de abertura?')">Remover foto de abertura</button><?php endif ?></p>
  </form>

  <section class="card">
    <h2>Fotos da biografia</h2>
    <p class="hint">O site mostra as três primeiras ao lado do texto.</p>
    <form method="post" enctype="multipart/form-data" class="inline">
      <?= csrf_field() ?><input type="hidden" name="section" value="bio">
      <label>Fotos <input type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple required></label>
      <label>Legenda <small>opcional</small> <input name="caption" maxlength="200"></label>
      <button name="action" value="foto_enviar" class="primary">Enviar fotos</button>
    </form>
    <?php if ($bio): ?><ul class="photos"><?php foreach ($bio as $p): ?>
      <li>
        <img src="../uploads/<?= e($p['thumb']) ?>" alt="" loading="lazy">
        <form method="post" class="caption"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="voltar" value="perfil">
          <input name="caption" value="<?= e($p['caption']) ?>" maxlength="200" placeholder="Legenda" aria-label="Legenda">
          <button name="action" value="foto_legenda">Salvar</button></form>
        <?= row_tools('foto', (int) $p['id'], 'perfil', 'Excluir esta foto?') ?>
      </li><?php endforeach ?></ul><?php endif ?>
  </section>

<?php elseif ($page === 'fotos'):
    $list = $pdo->query("SELECT * FROM photos WHERE section = 'galeria' ORDER BY position DESC, id DESC")->fetchAll(); ?>
  <h1>Fotos</h1>
  <form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?><input type="hidden" name="section" value="galeria">
    <h2>Publicar fotos</h2>
    <p class="hint">Escolha uma ou várias de uma vez (JPG, PNG ou WebP). O site reduz e otimiza cada uma automaticamente.</p>
    <label>Fotos <input type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple required></label>
    <label>Legenda <small>opcional; se enviar várias, vale para todas e pode ser ajustada depois</small> <input name="caption" maxlength="200"></label>
    <p class="actions"><button name="action" value="foto_enviar" class="primary">Publicar fotos</button></p>
  </form>
  <section class="card">
    <h2><?= count($list) ?> <?= count($list) === 1 ? 'foto publicada' : 'fotos publicadas' ?></h2>
    <?php if (!$list): ?><p class="hint">Nenhuma foto ainda. Publique a primeira no formulário acima.</p><?php else: ?>
    <ul class="photos"><?php foreach ($list as $p): ?>
      <li>
        <img src="../uploads/<?= e($p['thumb']) ?>" alt="" loading="lazy">
        <form method="post" class="caption"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
          <input name="caption" value="<?= e($p['caption']) ?>" maxlength="200" placeholder="Legenda" aria-label="Legenda">
          <button name="action" value="foto_legenda">Salvar</button></form>
        <?= row_tools('foto', (int) $p['id'], 'fotos', 'Excluir esta foto?') ?>
      </li><?php endforeach ?></ul><?php endif ?>
  </section>

<?php elseif ($page === 'videos' || $page === 'audios'):
    $type = $page === 'audios' ? 'audio' : 'video';
    $um = $type === 'audio' ? 'áudio' : 'vídeo';
    $st = $pdo->prepare('SELECT * FROM media WHERE type = ? ORDER BY position DESC, id DESC');
    $st->execute([$type]);
    $list = $st->fetchAll(); ?>
  <h1><?= $menu[$page] ?></h1>
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="type" value="<?= $type ?>">
    <h2>Publicar <?= $um ?></h2>
    <p class="hint"><?= $type === 'audio'
        ? 'O áudio fica no YouTube, mas o site toca só o som, com uma tela animada no lugar da imagem. Cole o link do vídeo que contém o áudio.'
        : 'Cole o link do vídeo no YouTube. Ele precisa estar como público ou não listado.' ?></p>
    <label>Link do YouTube <input name="youtube" required placeholder="https://www.youtube.com/watch?v=..." inputmode="url"></label>
    <label>Título <input name="title" required maxlength="140"></label>
    <label>Descrição <small>opcional</small> <input name="description" maxlength="300"></label>
    <p class="actions"><button name="action" value="midia_salvar" class="primary">Publicar <?= $um ?></button></p>
  </form>
  <section class="card">
    <h2><?= count($list) ?> <?= $type === 'audio' ? (count($list) === 1 ? 'áudio publicado' : 'áudios publicados') : (count($list) === 1 ? 'vídeo publicado' : 'vídeos publicados') ?></h2>
    <?php if (!$list): ?><p class="hint">Nada publicado ainda.</p><?php else: ?>
    <ul class="media"><?php foreach ($list as $m): ?>
      <li>
        <img src="https://i.ytimg.com/vi/<?= e($m['youtube_id']) ?>/mqdefault.jpg" alt="" loading="lazy">
        <form method="post" class="edit"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="type" value="<?= $type ?>">
          <input name="title" value="<?= e($m['title']) ?>" maxlength="140" required aria-label="Título">
          <input name="description" value="<?= e($m['description']) ?>" maxlength="300" placeholder="Descrição" aria-label="Descrição">
          <input name="youtube" value="https://youtu.be/<?= e($m['youtube_id']) ?>" required aria-label="Link do YouTube">
          <button name="action" value="midia_salvar">Salvar alterações</button></form>
        <?= row_tools('midia', (int) $m['id'], $page, "Excluir este $um?") ?>
      </li><?php endforeach ?></ul><?php endif ?>
  </section>

<?php elseif ($page === 'recados'):
    $list = $pdo->query("SELECT * FROM comments ORDER BY status = 'pending' DESC, id DESC LIMIT 500")->fetchAll(); ?>
  <h1>Recados</h1>
  <form method="post" class="card inline">
    <?= csrf_field() ?>
    <label class="check"><input type="checkbox" name="moderar" <?= $s['comments_moderation'] === '1' ? 'checked' : '' ?>> Só publicar um recado depois que eu aprovar</label>
    <button name="action" value="recado_modo">Salvar preferência</button>
  </form>
  <section class="card">
    <?php if (!$list): ?><p class="hint">Nenhum recado recebido ainda.</p><?php else: ?>
    <ul class="notes"><?php foreach ($list as $c): ?>
      <li class="<?= $c['status'] ?>">
        <p class="meta"><strong><?= e($c['name']) ?></strong><?= $c['city'] ? ', ' . e($c['city']) : '' ?> · <?= date_br($c['created_at']) ?>
          <span class="tag"><?= $c['status'] === 'pending' ? 'Aguardando' : 'No site' ?></span></p>
        <p><?= nl2br(e($c['message'])) ?></p>
        <form method="post" class="tools"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>">
          <?php if ($c['status'] === 'pending'): ?><button name="action" value="recado_aprovar" class="primary">Publicar</button>
          <?php else: ?><button name="action" value="recado_ocultar">Retirar do site</button><?php endif ?>
          <button name="action" value="recado_excluir" class="danger" onclick="return confirm('Excluir este recado?')">Excluir</button></form>
      </li><?php endforeach ?></ul><?php endif ?>
  </section>

<?php elseif ($page === 'contatos'): ?>
  <h1>Contatos</h1>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <p class="hint">Só aparecem no site os campos preenchidos.</p>
    <div class="grid2">
      <label>WhatsApp <small>com DDD</small> <input name="whatsapp" value="<?= e($s['whatsapp']) ?>" placeholder="(19) 99999-9999" inputmode="tel"></label>
      <label>Telefone <small>com DDD</small> <input name="phone" value="<?= e($s['phone']) ?>" inputmode="tel"></label>
      <label>E-mail <input name="email" type="email" value="<?= e($s['email']) ?>"></label>
      <label>Instagram <small>@usuario ou link</small> <input name="instagram" value="<?= e($s['instagram']) ?>"></label>
      <label>Facebook <small>usuário ou link</small> <input name="facebook" value="<?= e($s['facebook']) ?>"></label>
      <label>YouTube <small>@canal ou link</small> <input name="youtube" value="<?= e($s['youtube']) ?>"></label>
      <label>TikTok <small>@usuario ou link</small> <input name="tiktok" value="<?= e($s['tiktok']) ?>"></label>
    </div>
    <p class="actions"><button name="action" value="contatos" class="primary">Salvar contatos</button></p>
  </form>

<?php elseif ($page === 'senha'): ?>
  <h1>Senha</h1>
  <form method="post" class="card narrow">
    <?= csrf_field() ?>
    <label>Senha atual <input name="atual" type="password" required autocomplete="current-password"></label>
    <label>Nova senha <small>pelo menos 8 caracteres</small> <input name="nova" type="password" required minlength="8" autocomplete="new-password"></label>
    <label>Repita a nova senha <input name="nova2" type="password" required minlength="8" autocomplete="new-password"></label>
    <p class="actions"><button name="action" value="senha" class="primary">Alterar senha</button></p>
  </form>
<?php endif ?>
</main>
<?php endif ?>
</body>
</html>
