<?php
/** Ações do painel (todas por POST, com verificação de sessão e de token). */
declare(strict_types=1);

// Quando o envio passa do limite do servidor, o PHP descarta o formulário inteiro.
if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $msg = 'O envio é maior do que este servidor aceita (' . ini_get('post_max_size') . '). Envie menos arquivos por vez ou arquivos menores.';
    if ($wantsJson) json_out(['ok' => false, 'message' => $msg], 413);
    flash($msg, false);
    back($page);
}

if (!csrf_ok()) {
    if ($wantsJson) json_out(['ok' => false, 'message' => 'A sessão expirou. Recarregue a página e entre de novo.'], 403);
    flash('A página ficou aberta por muito tempo. Tente de novo.', false);
    back($page);
}
$action = (string) ($_POST['action'] ?? '');
$id = (int) ($_POST['id'] ?? 0);

function next_position(string $table, string $where = '1=1', array $args = []): int
{
    $st = db()->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM $table WHERE $where");
    $st->execute($args);
    return (int) $st->fetchColumn();
}

/**
 * Troca um item de lugar com o vizinho. "up" = aparece antes no site.
 * $asc diz se a lista é exibida em ordem crescente de posição.
 */
function move_item(string $table, int $id, string $dir, bool $asc, string $where = '1=1', array $args = []): void
{
    $pdo = db();
    $st = $pdo->prepare("SELECT id FROM $table WHERE $where ORDER BY position " . ($asc ? 'ASC, id ASC' : 'DESC, id DESC'));
    $st->execute($args);
    $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    $i = array_search($id, $ids, true);
    $j = $i === false ? false : $i + ($dir === 'up' ? -1 : 1);
    if ($i === false || !isset($ids[$j])) return;
    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
    // Regrava a ordem inteira: listas são curtas e isso elimina posições repetidas.
    $up = $pdo->prepare("UPDATE $table SET position = ? WHERE id = ?");
    $n = count($ids);
    foreach ($ids as $k => $itemId) $up->execute([$asc ? $k + 1 : $n - $k, $itemId]);
}

/** Lê os campos de legenda de uma foto enviados pelo formulário. */
function photo_fields(): array
{
    $year = year_or_null(post('year', 4));
    $decade = year_or_null(post('decade', 4));
    return [
        'title' => post('title', 140), 'year' => $year,
        'decade' => $decade ? decade_of($decade) : decade_of($year),
        'place' => post('place', 140), 'people' => post('people', 300), 'story' => post('story', 4000),
    ];
}

function focal(string $key): float
{
    return max(0.0, min(100.0, (float) ($_POST[$key] ?? 50)));
}

/* ---------- Entrada ---------- */
if (!$hasUser && $action === 'setup') {
    $user = post('usuario', 40);
    $pass = (string) ($_POST['senha'] ?? '');
    if (mb_strlen($user) < 3) flash('O nome de usuário precisa de pelo menos 3 letras.', false);
    elseif (strlen($pass) < 8) flash('A senha precisa de pelo menos 8 caracteres.', false);
    elseif ($pass !== ($_POST['senha2'] ?? '')) flash('As duas senhas não são iguais.', false);
    else {
        $pdo->prepare('INSERT INTO users(username, password_hash) VALUES(?, ?)')->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['admin'] = $user;
        flash('Conta criada. Comece pelas configurações do site.');
        back('config');
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

if (!$logged) {
    if ($wantsJson) json_out(['ok' => false, 'message' => 'A sessão expirou. Entre de novo no painel.'], 401);
    back('inicio');
}

switch ($action) {
    case 'logout':
        $_SESSION = [];
        session_destroy();
        redirect('./');

    /* ---------- Configurações do site ---------- */
    case 'config':
        foreach (['site_name' => 60, 'tagline' => 90, 'slogan' => 140, 'invite' => 200, 'about' => 400, 'city' => 80,
                  'whatsapp' => 30, 'whatsapp_msg' => 200, 'instagram' => 120, 'facebook' => 120, 'youtube' => 120, 'tiktok' => 120] as $k => $max) {
            set_setting($k, post($k, $max));
        }
        if (post('site_name', 60) === '') set_setting('site_name', SETTING_DEFAULTS['site_name']);
        set_setting('live_url', safe_url(post('live_url', 300)));
        set_setting('hero_focal_x', (string) focal('focal_x'));
        set_setting('hero_focal_y', (string) focal('focal_y'));
        $problem = '';
        $email = post('email', 120);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $problem = 'O e-mail parece incorreto e ficou de fora.';
        else set_setting('email', $email);
        if (post('live_url', 300) !== '' && setting('live_url') === '' && safe_url(post('live_url', 300)) === '') $problem = 'O link "ouvir ao vivo" não é um endereço válido.';
        if (!empty($_FILES['hero_photo']['name'])) {
            $img = save_image($_FILES['hero_photo']);
            if (is_string($img)) $problem = $img;
            else {
                delete_image_files(setting('hero_photo'));
                set_setting('hero_photo', $img['file']);
                set_setting('hero_w', (string) $img['w']);
                set_setting('hero_h', (string) $img['h']);
                set_setting('hero_focal_x', '50');
                set_setting('hero_focal_y', '35');
            }
        }
        flash($problem ? 'Configurações salvas, com uma ressalva: ' . $problem : 'Configurações salvas.', !$problem);
        back('config');

    case 'hero_remover':
        delete_image_files(setting('hero_photo'));
        set_setting('hero_photo', '');
        flash('Foto de capa removida.');
        back('config');

    /* ---------- Fotos ---------- */
    case 'foto_enviar':
        $f = photo_fields();
        $ok = 0; $errors = []; $last = 0;
        foreach (uploaded_list('fotos') as $up) {
            $img = save_image($up);
            if (is_string($img)) { $errors[] = $up['name'] . ': ' . $img; continue; }
            $pdo->prepare('INSERT INTO photos(thumb, file, w, h, title, year, decade, place, people, story, status, position, created_at)
                           VALUES(\'\',?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$img['file'], $img['w'], $img['h'], $f['title'], $f['year'], $f['decade'], $f['place'], $f['people'], $f['story'],
                           'published', next_position('photos'), date('c')]);
            $last = (int) $pdo->lastInsertId();
            $ok++;
        }
        if (!$ok) { flash($errors ? implode(' ', $errors) : 'Nenhuma foto foi escolhida.', false); back('fotos&nova=1'); }
        flash(($ok === 1 ? 'Foto publicada. Confira a legenda e o ponto focal.' : "$ok fotos publicadas. Toque em cada uma para completar a legenda.")
              . ($errors ? ' Não entraram: ' . implode(' ', $errors) : ''), !$errors);
        back($ok === 1 ? 'fotos&id=' . $last : 'fotos');

    case 'foto_salvar':
        $f = photo_fields();
        $status = ($_POST['status'] ?? '') === 'draft' ? 'draft' : 'published';
        $pdo->prepare('UPDATE photos SET title=?, year=?, decade=?, place=?, people=?, story=?, focal_x=?, focal_y=?, featured=?, status=? WHERE id=?')
            ->execute([$f['title'], $f['year'], $f['decade'], $f['place'], $f['people'], $f['story'], focal('focal_x'), focal('focal_y'),
                       isset($_POST['featured']) ? 1 : 0, $status, $id]);
        flash($status === 'draft' ? 'Foto salva como rascunho. Ela não aparece no site.' : 'Foto salva.');
        back('fotos&id=' . $id);

    case 'foto_capa': // copia a foto da galeria para a capa do site
        $st = $pdo->prepare('SELECT * FROM photos WHERE id = ?');
        $st->execute([$id]);
        if ($p = $st->fetch()) {
            $src = @imagecreatefromjpeg(UPLOAD_DIR . '/' . $p['file'] . '-l.jpg');
            if ($src) {
                $base = date('Ymd') . '-' . bin2hex(random_bytes(8));
                [$w, $h] = make_variants($src, $base);
                imagedestroy($src);
                delete_image_files(setting('hero_photo'));
                foreach (['hero_photo' => $base, 'hero_w' => $w, 'hero_h' => $h, 'hero_focal_x' => $p['focal_x'], 'hero_focal_y' => $p['focal_y']] as $k => $v) {
                    set_setting($k, (string) $v);
                }
                flash('Esta foto agora é a capa do site.');
            } else flash('Não foi possível usar esta foto como capa.', false);
        }
        back('fotos&id=' . $id);

    case 'foto_excluir':
        $st = $pdo->prepare('SELECT * FROM photos WHERE id = ?');
        $st->execute([$id]);
        if ($p = $st->fetch()) {
            delete_image_files($p['file']);
            if (preg_match('/^[A-Za-z0-9.-]+$/', $p['original'])) @unlink(ORIGINAL_DIR . '/' . $p['original']);
            $pdo->prepare('UPDATE milestones SET photo_id = NULL WHERE photo_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM photos WHERE id = ?')->execute([$id]);
            flash('Foto excluída.');
        }
        back('fotos');

    /* ---------- Scanner: recebe a foto já tratada no celular ---------- */
    case 'scanner_salvar':
        $img = save_image($_FILES['imagem'] ?? []);
        if (is_string($img)) json_out(['ok' => false, 'message' => $img], 422);
        $original = '';
        $orig = $_FILES['original'] ?? null;
        if ($orig && $orig['error'] === UPLOAD_ERR_OK && is_uploaded_file($orig['tmp_name']) && @getimagesize($orig['tmp_name'])) {
            if (!is_dir(ORIGINAL_DIR)) mkdir(ORIGINAL_DIR, 0755, true);
            $original = $img['file'] . '-original.jpg';
            if (!move_uploaded_file($orig['tmp_name'], ORIGINAL_DIR . '/' . $original)) $original = '';
        }
        $f = photo_fields();
        $status = ($_POST['status'] ?? '') === 'draft' ? 'draft' : 'published';
        $pdo->prepare('INSERT INTO photos(thumb, file, w, h, title, year, decade, place, people, story, original, status, position, created_at)
                       VALUES(\'\',?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$img['file'], $img['w'], $img['h'], $f['title'], $f['year'], $f['decade'], $f['place'], $f['people'], $f['story'],
                       $original, $status, next_position('photos'), date('c')]);
        $newId = (int) $pdo->lastInsertId();
        json_out(['ok' => true, 'id' => $newId, 'status' => $status, 'edit' => '?p=fotos&id=' . $newId,
                  'message' => $status === 'draft' ? 'Rascunho salvo.' : 'Foto publicada na galeria.']);

    /* ---------- Minha história ---------- */
    case 'intro_salvar':
        set_setting('history_intro', post('history_intro', 2000));
        flash('Texto de abertura salvo.');
        back('historia');

    case 'marco_salvar':
        $period = post('period', 40);
        $title = post('title', 140);
        if ($period === '' || $title === '') { flash('Preencha o ano (ou período) e o título.', false); back('historia' . ($id ? '&id=' . $id : '&novo=1')); }
        $photoId = (int) ($_POST['photo_id'] ?? 0) ?: null;
        if ($id) {
            $pdo->prepare('UPDATE milestones SET period=?, title=?, body=?, photo_id=? WHERE id=?')->execute([$period, $title, post('body', 6000), $photoId, $id]);
        } else {
            $pdo->prepare('INSERT INTO milestones(period, title, body, photo_id, position) VALUES(?,?,?,?,?)')
                ->execute([$period, $title, post('body', 6000), $photoId, next_position('milestones')]);
        }
        flash('Marco salvo.');
        back('historia');

    case 'marco_mover':
        move_item('milestones', $id, post('dir', 4), true);
        back('historia');

    case 'marco_excluir':
        $pdo->prepare('DELETE FROM milestones WHERE id = ?')->execute([$id]);
        flash('Marco excluído.');
        back('historia');

    /* ---------- Trabalhos ---------- */
    case 'trabalho_salvar':
        $name = post('name', 140);
        if ($name === '') { flash('Dê um nome ao trabalho.', false); back('trabalhos' . ($id ? '&id=' . $id : '&novo=1')); }
        $data = [$name, post('outlet', 140), post('schedule', 200), post('description', 3000), safe_url(post('link', 300)),
                 post('link_label', 40), isset($_POST['active']) ? 1 : 0, focal('focal_x'), focal('focal_y')];
        if ($id) {
            $pdo->prepare('UPDATE works SET name=?, outlet=?, schedule=?, description=?, link=?, link_label=?, active=?, focal_x=?, focal_y=? WHERE id=?')
                ->execute([...$data, $id]);
        } else {
            $pdo->prepare('INSERT INTO works(name, outlet, schedule, description, link, link_label, active, focal_x, focal_y, position) VALUES(?,?,?,?,?,?,?,?,?,?)')
                ->execute([...$data, next_position('works')]);
            $id = (int) $pdo->lastInsertId();
        }
        $problem = '';
        if (!empty($_FILES['image']['name'])) {
            $img = save_image($_FILES['image']);
            if (is_string($img)) $problem = $img;
            else {
                $st = $pdo->prepare('SELECT image FROM works WHERE id = ?');
                $st->execute([$id]);
                delete_image_files((string) $st->fetchColumn());
                $pdo->prepare('UPDATE works SET image=?, image_w=?, image_h=?, focal_x=50, focal_y=35 WHERE id=?')->execute([$img['file'], $img['w'], $img['h'], $id]);
            }
        }
        flash($problem ? 'Trabalho salvo, mas a imagem não entrou: ' . $problem : 'Trabalho salvo.', !$problem);
        back($problem || !empty($_FILES['image']['name']) ? 'trabalhos&id=' . $id : 'trabalhos');

    case 'trabalho_mover':
        move_item('works', $id, post('dir', 4), false);
        back('trabalhos');

    case 'trabalho_excluir':
        $st = $pdo->prepare('SELECT image FROM works WHERE id = ?');
        $st->execute([$id]);
        delete_image_files((string) $st->fetchColumn());
        $pdo->prepare('DELETE FROM works WHERE id = ?')->execute([$id]);
        flash('Trabalho excluído.');
        back('trabalhos');

    /* ---------- Áudios e vídeos ---------- */
    case 'midia_salvar':
        $type = ($_POST['type'] ?? '') === 'audio' ? 'audio' : 'video';
        $dest = $type === 'audio' ? 'audios' : 'videos';
        $title = post('title', 140);
        $ytRaw = post('youtube', 300);
        $yt = youtube_id($ytRaw);
        if ($title === '') { flash('Dê um título.', false); back($dest); }
        if ($ytRaw !== '' && $yt === '') { flash('Não reconheci o link do YouTube. Cole o endereço completo.', false); back($dest); }

        $current = ['file' => '', 'youtube_id' => ''];
        if ($id) {
            $st = $pdo->prepare('SELECT file, youtube_id FROM media WHERE id = ?');
            $st->execute([$id]);
            $current = $st->fetch() ?: $current;
        }
        $file = $current['file'];
        if ($type === 'audio' && !empty($_FILES['mp3']['name'])) {
            $saved = save_mp3($_FILES['mp3']);
            if ($saved[0] === '!') { flash(substr($saved, 1), false); back($dest); }
            if ($file !== '') @unlink(UPLOAD_DIR . '/audio/' . basename($file));
            $file = $saved;
            $yt = '';
        } elseif ($yt !== '' && $file !== '') { // trocou o MP3 por um link
            @unlink(UPLOAD_DIR . '/audio/' . basename($file));
            $file = '';
        }
        if ($yt === '' && $file === '') $yt = $current['youtube_id'];
        if ($yt === '' && $file === '') {
            flash($type === 'audio' ? 'Envie um arquivo MP3 ou cole um link do YouTube.' : 'Cole o link do vídeo no YouTube.', false);
            back($dest);
        }
        $year = year_or_null(post('year', 4));
        if ($id) {
            $pdo->prepare('UPDATE media SET youtube_id=?, file=?, title=?, description=?, year=? WHERE id=?')
                ->execute([$yt, $file, $title, post('description', 300), $year, $id]);
            flash('Alterações salvas.');
        } else {
            $pdo->prepare('INSERT INTO media(type, youtube_id, file, title, description, year, position, created_at) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([$type, $yt, $file, $title, post('description', 300), $year, next_position('media', 'type = ?', [$type]), date('c')]);
            flash($type === 'audio' ? 'Áudio publicado.' : 'Vídeo publicado.');
        }
        back($dest);

    case 'midia_mover':
        $type = post('voltar', 10) === 'audios' ? 'audio' : 'video';
        move_item('media', $id, post('dir', 4), false, 'type = ?', [$type]);
        back($type === 'audio' ? 'audios' : 'videos');

    case 'midia_excluir':
        $st = $pdo->prepare('SELECT file FROM media WHERE id = ?');
        $st->execute([$id]);
        $file = (string) $st->fetchColumn();
        if ($file !== '') @unlink(UPLOAD_DIR . '/audio/' . basename($file));
        $pdo->prepare('DELETE FROM media WHERE id = ?')->execute([$id]);
        flash('Item excluído.');
        back(post('voltar', 10) === 'audios' ? 'audios' : 'videos');

    /* ---------- Recados ---------- */
    case 'recado_salvar':
        $name = post('name', 60);
        $message = post('message', 1500);
        if ($name === '' || $message === '') { flash('Preencha o nome e o texto do recado.', false); back('recados' . ($id ? '&id=' . $id : '&novo=1')); }
        $relation = isset(RELATIONS[$_POST['relation'] ?? '']) ? $_POST['relation'] : 'ouvinte';
        if ($id) {
            $pdo->prepare('UPDATE comments SET name=?, city=?, relation=?, message=? WHERE id=?')->execute([$name, post('city', 60), $relation, $message, $id]);
        } else {
            $pdo->prepare("INSERT INTO comments(name, city, relation, message, status, ip_hash, created_at) VALUES(?,?,?,?,'approved','',?)")
                ->execute([$name, post('city', 60), $relation, $message, date('c')]);
            $id = (int) $pdo->lastInsertId();
        }
        $problem = '';
        if (!empty($_FILES['photo']['name'])) {
            $img = save_image($_FILES['photo']);
            if (is_string($img)) $problem = $img;
            else {
                $st = $pdo->prepare('SELECT photo FROM comments WHERE id = ?');
                $st->execute([$id]);
                delete_image_files((string) $st->fetchColumn());
                $pdo->prepare('UPDATE comments SET photo = ? WHERE id = ?')->execute([$img['file'], $id]);
            }
        }
        flash($problem ? 'Recado salvo, mas a foto não entrou: ' . $problem : 'Recado salvo.', !$problem);
        back('recados');

    case 'recado_aprovar':
        $pdo->prepare("UPDATE comments SET status = 'approved' WHERE id = ?")->execute([$id]);
        flash('Recado publicado.');
        back('recados');

    case 'recado_ocultar':
        $pdo->prepare("UPDATE comments SET status = 'pending' WHERE id = ?")->execute([$id]);
        flash('Recado retirado do site.');
        back('recados');

    case 'recado_excluir':
        $st = $pdo->prepare('SELECT photo FROM comments WHERE id = ?');
        $st->execute([$id]);
        delete_image_files((string) $st->fetchColumn());
        $pdo->prepare('DELETE FROM comments WHERE id = ?')->execute([$id]);
        flash('Recado excluído.');
        back('recados');

    case 'recado_opcoes':
        set_setting('visitor_notes', isset($_POST['visitor_notes']) ? '1' : '0');
        set_setting('comments_moderation', isset($_POST['moderar']) ? '1' : '0');
        flash('Preferências salvas.');
        back('recados');

    /* ---------- Senha ---------- */
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
