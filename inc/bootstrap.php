<?php
/**
 * Núcleo do site: banco de dados, sessão, segurança, imagens e utilitários.
 * Incluído por todas as páginas (públicas e do painel).
 */
declare(strict_types=1);

const ROOT_DIR     = __DIR__ . '/..';
const DATA_DIR     = ROOT_DIR . '/data';
const UPLOAD_DIR   = ROOT_DIR . '/uploads';
const ORIGINAL_DIR = DATA_DIR . '/originais'; // originais sem tratamento: fora do alcance da web
const DB_FILE      = DATA_DIR . '/site.sqlite';
const ASSET_VERSION = '2';

mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Sao_Paulo');

/* ---------- Sessão ---------- */
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('bia_sess');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => is_https(),
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------- Banco de dados ---------- */
function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
    migrate($pdo);
    return $pdo;
}

/** Cria e atualiza as tabelas. Cada bloco roda uma única vez por instalação. */
function migrate(PDO $pdo): void
{
    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($version >= 2) return;

    if ($version < 1) {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS photos (
                id INTEGER PRIMARY KEY, file TEXT NOT NULL, thumb TEXT NOT NULL DEFAULT \'\',
                caption TEXT NOT NULL DEFAULT \'\', w INTEGER NOT NULL, h INTEGER NOT NULL,
                section TEXT NOT NULL DEFAULT \'galeria\', position INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS media (
                id INTEGER PRIMARY KEY, type TEXT NOT NULL, youtube_id TEXT NOT NULL DEFAULT \'\',
                title TEXT NOT NULL, description TEXT NOT NULL DEFAULT \'\',
                position INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS comments (
                id INTEGER PRIMARY KEY, name TEXT NOT NULL, city TEXT NOT NULL DEFAULT \'\',
                message TEXT NOT NULL, status TEXT NOT NULL DEFAULT \'pending\',
                ip_hash TEXT NOT NULL DEFAULT \'\', created_at TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS attempts (ip_hash TEXT NOT NULL, kind TEXT NOT NULL, ts INTEGER NOT NULL);
            CREATE INDEX IF NOT EXISTS idx_attempts ON attempts(ip_hash, kind, ts);
        ');
    }

    // Versão 2: legenda completa das fotos, marcos, trabalhos, áudio em MP3 e recados cadastrados.
    $pdo->exec('
        ALTER TABLE photos ADD COLUMN title TEXT NOT NULL DEFAULT \'\';
        ALTER TABLE photos ADD COLUMN year INTEGER;
        ALTER TABLE photos ADD COLUMN decade INTEGER;
        ALTER TABLE photos ADD COLUMN place TEXT NOT NULL DEFAULT \'\';
        ALTER TABLE photos ADD COLUMN people TEXT NOT NULL DEFAULT \'\';
        ALTER TABLE photos ADD COLUMN story TEXT NOT NULL DEFAULT \'\';
        ALTER TABLE photos ADD COLUMN focal_x REAL NOT NULL DEFAULT 50;
        ALTER TABLE photos ADD COLUMN focal_y REAL NOT NULL DEFAULT 35;
        ALTER TABLE photos ADD COLUMN featured INTEGER NOT NULL DEFAULT 0;
        ALTER TABLE photos ADD COLUMN original TEXT NOT NULL DEFAULT \'\';
        ALTER TABLE photos ADD COLUMN status TEXT NOT NULL DEFAULT \'published\';
        UPDATE photos SET title = caption, section = \'galeria\';
        ALTER TABLE media ADD COLUMN year INTEGER;
        ALTER TABLE media ADD COLUMN file TEXT NOT NULL DEFAULT \'\';
        ALTER TABLE comments ADD COLUMN relation TEXT NOT NULL DEFAULT \'ouvinte\';
        ALTER TABLE comments ADD COLUMN photo TEXT NOT NULL DEFAULT \'\';
        CREATE TABLE milestones (
            id INTEGER PRIMARY KEY, period TEXT NOT NULL, title TEXT NOT NULL, body TEXT NOT NULL DEFAULT \'\',
            photo_id INTEGER, position INTEGER NOT NULL DEFAULT 0);
        CREATE TABLE works (
            id INTEGER PRIMARY KEY, name TEXT NOT NULL, outlet TEXT NOT NULL DEFAULT \'\', schedule TEXT NOT NULL DEFAULT \'\',
            description TEXT NOT NULL DEFAULT \'\', image TEXT NOT NULL DEFAULT \'\', image_w INTEGER NOT NULL DEFAULT 0,
            image_h INTEGER NOT NULL DEFAULT 0, focal_x REAL NOT NULL DEFAULT 50, focal_y REAL NOT NULL DEFAULT 35,
            link TEXT NOT NULL DEFAULT \'\', link_label TEXT NOT NULL DEFAULT \'\', active INTEGER NOT NULL DEFAULT 1,
            position INTEGER NOT NULL DEFAULT 0);
        CREATE INDEX idx_photos2 ON photos(status, decade, year);
        CREATE INDEX idx_media2 ON media(type, position);
        CREATE INDEX idx_comments2 ON comments(status, id);
    ');

    // Fotos enviadas na primeira versão ganham os novos tamanhos.
    foreach ($pdo->query('SELECT id, file, thumb FROM photos')->fetchAll() as $p) {
        $old = UPLOAD_DIR . '/' . $p['file'];
        $src = is_file($old) ? @imagecreatefromjpeg($old) : false;
        if (!$src) continue;
        $base = preg_replace('/\.jpg$/', '', $p['file']);
        make_variants($src, $base);
        imagedestroy($src);
        @unlink($old);
        @unlink(UPLOAD_DIR . '/' . $p['thumb']);
        $pdo->prepare('UPDATE photos SET file = ?, thumb = \'\' WHERE id = ?')->execute([$base, $p['id']]);
    }
    $pdo->exec("DELETE FROM settings WHERE key = 'hero_photo'");
    $pdo->exec('PRAGMA user_version = 2');
}

/* ---------- Configurações do site ---------- */
const SETTING_DEFAULTS = [
    'site_name'  => 'Bia Pizani',
    'tagline'    => 'Locutora',
    'slogan'     => '34 anos de rádio e o que a sintonia não mostra.',
    'about'      => 'São 34 anos de microfone aberto. Aqui estão as fotos, as histórias de bastidores e os trabalhos de hoje.',
    'history_intro' => '',
    'city'       => 'São José do Rio Pardo, SP',
    'hero_photo' => '', 'hero_w' => '0', 'hero_h' => '0', 'hero_focal_x' => '50', 'hero_focal_y' => '35',
    'live_url'   => '',
    'whatsapp'   => '',
    'whatsapp_msg' => 'Olá, Bia! Vi o seu site e quero falar com você.',
    'email'      => '',
    'instagram'  => '', 'facebook' => '', 'youtube' => '', 'tiktok' => '',
    'visitor_notes' => '1',          // visitantes podem enviar recados
    'comments_moderation' => '1',    // recado de visitante só aparece depois de aprovado
    'secret'     => '',
];

const RELATIONS = ['ouvinte' => 'Ouvinte', 'colega' => 'Colega', 'amigo' => 'Amigo ou amiga', 'familia' => 'Família'];

function settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = SETTING_DEFAULTS;
        foreach (db()->query('SELECT key, value FROM settings') as $r) $cache[$r['key']] = $r['value'];
    }
    return $cache;
}

function setting(string $key): string
{
    return settings()[$key] ?? '';
}

function set_setting(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings(key, value) VALUES(?, ?)
                   ON CONFLICT(key) DO UPDATE SET value = excluded.value')->execute([$key, $value]);
}

/** Segredo próprio da instalação, usado para anonimizar IPs. */
function site_secret(): string
{
    $s = setting('secret');
    if ($s === '') {
        $s = bin2hex(random_bytes(32));
        set_setting('secret', $s);
    }
    return $s;
}

/* ---------- Utilitários ---------- */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function clean(string $s, int $max): string
{
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    return mb_substr(trim($s), 0, $max);
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function ip_hash(): string
{
    return hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? '0', site_secret());
}

function attempts(string $kind, int $windowSeconds): int
{
    $pdo = db();
    $pdo->prepare('DELETE FROM attempts WHERE ts < ?')->execute([time() - 86400]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM attempts WHERE ip_hash = ? AND kind = ? AND ts > ?');
    $st->execute([ip_hash(), $kind, time() - $windowSeconds]);
    return (int) $st->fetchColumn();
}

function add_attempt(string $kind): void
{
    db()->prepare('INSERT INTO attempts(ip_hash, kind, ts) VALUES(?, ?, ?)')->execute([ip_hash(), $kind, time()]);
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    start_session();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_ok(): bool
{
    start_session();
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    return is_string($sent) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/* ---------- Texto e links ---------- */
function paragraphs(string $text): string
{
    $out = '';
    foreach (preg_split('/\R{2,}/u', trim($text)) ?: [] as $p) {
        if (trim($p) !== '') $out .= '<p>' . nl2br(e(trim($p))) . "</p>\n";
    }
    return $out;
}

function excerpt(string $text, int $max): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if (mb_strlen($text) <= $max) return $text;
    return rtrim(mb_substr($text, 0, (int) mb_strrpos(mb_substr($text, 0, $max), ' ')), ' ,.;:') . '…';
}

function slugify(string $s): string
{
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)) ?? '', '-');
    return mb_substr($s, 0, 60);
}

function digits(string $s): string
{
    return preg_replace('/\D+/', '', $s) ?? '';
}

/** Link do WhatsApp; números sem DDI recebem o 55 do Brasil. */
function whatsapp_url(string $number, string $text = ''): string
{
    $d = ltrim(digits($number), '0');
    if ($d === '') return '';
    if (strlen($d) <= 11) $d = '55' . $d;
    return 'https://wa.me/' . $d . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/** Aceita "@usuario", "usuario" ou o endereço completo do perfil. */
function social_url(string $value, string $base): string
{
    $value = trim($value);
    if ($value === '') return '';
    if (preg_match('~^https?://~i', $value)) return $value;
    return $base . ltrim($value, '@/');
}

function social_handle(string $value): string
{
    $value = trim($value);
    if (preg_match('~^https?://~i', $value)) {
        $path = trim((string) parse_url($value, PHP_URL_PATH), '/');
        $last = $path === '' ? '' : basename($path);
        return $last === '' ? (string) parse_url($value, PHP_URL_HOST) : '@' . ltrim($last, '@');
    }
    return '@' . ltrim($value, '@/');
}

/** Só aceita endereços http(s); qualquer outra coisa vira vazio. */
function safe_url(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';
    if (!preg_match('~^https?://~i', $url)) $url = 'https://' . $url;
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

/** Extrai o código do vídeo de qualquer formato de link do YouTube. */
function youtube_id(string $input): string
{
    $input = trim($input);
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) return $input;
    if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~', $input, $m)) {
        return $m[1];
    }
    return '';
}

/** Converte valores do php.ini como "8M" em bytes. */
function ini_bytes(string $key): int
{
    $v = trim((string) ini_get($key));
    $n = (float) $v;
    return (int) match (strtolower(substr($v, -1))) {
        'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n,
    };
}

function date_br(string $iso): string
{
    $meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    $t = strtotime($iso) ?: time();
    return (int) date('j', $t) . ' de ' . $meses[(int) date('n', $t) - 1] . ' de ' . date('Y', $t);
}

/** Ano válido (1900 até o ano que vem) ou null. */
function year_or_null(string $v): ?int
{
    $y = (int) digits($v);
    return ($y >= 1900 && $y <= (int) date('Y') + 1) ? $y : null;
}

function decade_of(?int $year): ?int
{
    return $year ? intdiv($year, 10) * 10 : null;
}

/** Década de um período escrito livremente, como "1990–1995" ou "Verão de 2004". */
function decade_of_period(string $period): ?int
{
    return preg_match('/(19|20)\d{2}/', $period, $m) ? decade_of((int) $m[0]) : null;
}

/* ---------- Imagens ---------- */

/** Tamanhos gerados para cada foto: sufixo => lado maior em pixels. */
const VARIANTS = ['l' => 2400, 'm' => 1200, 's' => 600];

/**
 * Valida a imagem enviada, regrava como JPEG (o que descarta metadados e qualquer conteúdo
 * que não seja imagem) e gera os tamanhos usados no site. Retorna os dados ou uma mensagem de erro.
 * @return array{file:string,w:int,h:int}|string
 */
function save_image(array $upload): array|string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return match ($upload['error'] ?? 0) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A foto é maior do que o servidor aceita. Tente uma versão menor.',
            UPLOAD_ERR_NO_FILE => 'Nenhuma foto foi escolhida.',
            default => 'O envio da foto falhou. Tente de novo.',
        };
    }
    if (!is_uploaded_file($upload['tmp_name'])) return 'O envio da foto falhou. Tente de novo.';

    $info = @getimagesize($upload['tmp_name']);
    $src = match ($info[2] ?? 0) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($upload['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($upload['tmp_name']),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($upload['tmp_name']) : false,
        default => null,
    };
    if ($src === null) return 'Formato não aceito. Envie fotos em JPG, PNG ou WebP.';
    if (!$src) return 'Não foi possível ler esta foto. Ela pode estar corrompida ou ser grande demais.';

    // Fotos de celular guardam a rotação nos metadados.
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int) (@exif_read_data($upload['tmp_name'])['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($angle) $src = imagerotate($src, $angle, 0);
    }

    $base = date('Ymd') . '-' . bin2hex(random_bytes(8));
    [$w, $h] = make_variants($src, $base);
    imagedestroy($src);
    return ['file' => $base, 'w' => $w, 'h' => $h];
}

/** Grava os tamanhos de uma imagem (JPEG e, quando o servidor permite, WebP). @return array{0:int,1:int} */
function make_variants(GdImage $src, string $base): array
{
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    $sw = imagesx($src);
    $sh = imagesy($src);
    $size = [$sw, $sh];
    foreach (VARIANTS as $suffix => $max) {
        $scale = min(1, $max / max($sw, $sh));
        $w = max(1, (int) round($sw * $scale));
        $h = max(1, (int) round($sh * $scale));
        $dst = imagecreatetruecolor($w, $h);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // fundo para PNG transparente
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);
        imageinterlace($dst, true);
        imagejpeg($dst, UPLOAD_DIR . "/$base-$suffix.jpg", $suffix === 'l' ? 88 : 82);
        if ($suffix !== 'l' && function_exists('imagewebp')) {
            imageinterlace($dst, false);
            @imagewebp($dst, UPLOAD_DIR . "/$base-$suffix.webp", 80);
        }
        imagedestroy($dst);
        if ($suffix === 'l') $size = [$w, $h];
    }
    return $size;
}

function delete_image_files(string $base): void
{
    if ($base === '' || !preg_match('/^[A-Za-z0-9-]+$/', $base)) return;
    foreach (glob(UPLOAD_DIR . "/$base-*") ?: [] as $f) @unlink($f);
}

/** Largura real de um tamanho, dado o tamanho original guardado no banco. */
function variant_width(int $w, int $h, string $suffix): int
{
    if ($w < 1 || $h < 1) return VARIANTS[$suffix];
    // $w e $h são do tamanho "l"; os menores são reduções proporcionais dele.
    return max(1, (int) round($w * min(1, VARIANTS[$suffix] / max($w, $h))));
}

/**
 * Monta a tag <picture> com WebP e srcset. A imagem nunca é cortada pela tag;
 * quando o CSS precisa cortar (capa, cards), o ponto focal entra em object-position.
 */
function picture(string $base, int $w, int $h, string $alt, string $sizes, string $prefix, array $opt = []): string
{
    if ($base === '') return '';
    $src = fn(string $suffix, string $ext) => e($prefix . 'uploads/' . $base . "-$suffix.$ext");
    $set = function (array $suffixes, string $ext) use ($src, $w, $h): string {
        $parts = [];
        $seen = [];
        foreach ($suffixes as $sfx) {
            $vw = variant_width($w, $h, $sfx);
            if (isset($seen[$vw])) continue; // foto pequena: tamanhos iguais não se repetem
            $seen[$vw] = true;
            $parts[] = $src($sfx, $ext) . ' ' . $vw . 'w';
        }
        return implode(', ', $parts);
    };
    $style = isset($opt['focal']) ? ' style="object-position: ' . (float) $opt['focal'][0] . '% ' . (float) $opt['focal'][1] . '%"' : '';
    $class = isset($opt['class']) ? ' class="' . e($opt['class']) . '"' : '';
    $loading = empty($opt['eager']) ? ' loading="lazy" decoding="async"' : ' fetchpriority="high"';
    $html = '<picture>';
    if (is_file(UPLOAD_DIR . "/$base-m.webp")) {
        $html .= '<source type="image/webp" srcset="' . $set(['s', 'm'], 'webp') . '" sizes="' . e($sizes) . '">';
    }
    return $html . '<img src="' . $src('m', 'jpg') . '" srcset="' . $set(['s', 'm', 'l'], 'jpg') . '" sizes="' . e($sizes) . '"'
        . ' width="' . $w . '" height="' . $h . '" alt="' . e($alt) . '"' . $class . $style . $loading . '></picture>';
}

/** Texto alternativo de uma foto da galeria: título e quem aparece. */
function photo_alt(array $p): string
{
    $alt = $p['title'] !== '' ? $p['title'] : 'Foto do acervo';
    if ($p['people'] !== '') $alt .= '. Na foto: ' . $p['people'];
    if ($p['year']) $alt .= ' (' . $p['year'] . ')';
    return $alt;
}

/** Linha curta de legenda: ano e local. */
function photo_meta(array $p): string
{
    return implode(', ', array_filter([$p['year'] ? (string) $p['year'] : '', $p['place']]));
}

/** Normaliza $_FILES de um campo "multiple" em uma lista de envios. */
function uploaded_list(string $field): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f) return [];
    if (!is_array($f['name'])) return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
    $list = [];
    foreach ($f['name'] as $i => $_) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $list[] = ['name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $list;
}

/**
 * Guarda um MP3 enviado. Confere extensão e conteúdo antes de aceitar.
 * @return string nome do arquivo salvo, ou mensagem de erro iniciada por "!"
 */
function save_mp3(array $upload): string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        return in_array($upload['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? '!O áudio é maior do que o servidor aceita. Reduza o arquivo ou publique pelo YouTube.'
            : '!O envio do áudio falhou. Tente de novo.';
    }
    $ext = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']) ?: '';
    $head = (string) file_get_contents($upload['tmp_name'], false, null, 0, 3);
    $looksMp3 = $head === 'ID3' || (strlen($head) >= 2 && ord($head[0]) === 0xFF && (ord($head[1]) & 0xE0) === 0xE0);
    if ($ext !== 'mp3' || !$looksMp3 || !in_array($mime, ['audio/mpeg', 'audio/mp3', 'application/octet-stream'], true)) {
        return '!Envie o áudio em MP3.';
    }
    if (!is_dir(UPLOAD_DIR . '/audio')) mkdir(UPLOAD_DIR . '/audio', 0755, true);
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.mp3';
    return move_uploaded_file($upload['tmp_name'], UPLOAD_DIR . '/audio/' . $name) ? $name : '!O envio do áudio falhou. Tente de novo.';
}
