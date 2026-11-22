<?php
/**
 * Núcleo do site: banco de dados, sessão, segurança e utilitários.
 * Incluído por todas as páginas (públicas e do painel).
 */
declare(strict_types=1);

const ROOT_DIR    = __DIR__ . '/..';
const DATA_DIR    = ROOT_DIR . '/data';
const UPLOAD_DIR  = ROOT_DIR . '/uploads';
const DB_FILE     = DATA_DIR . '/site.sqlite';

mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Sao_Paulo');

/* ---------- Sessão ---------- */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('bia_sess');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $https,
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
    $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS photos (
            id INTEGER PRIMARY KEY, file TEXT NOT NULL, thumb TEXT NOT NULL,
            caption TEXT NOT NULL DEFAULT "", w INTEGER NOT NULL, h INTEGER NOT NULL,
            section TEXT NOT NULL DEFAULT "galeria", position INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS media (
            id INTEGER PRIMARY KEY, type TEXT NOT NULL, youtube_id TEXT NOT NULL,
            title TEXT NOT NULL, description TEXT NOT NULL DEFAULT "",
            position INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS comments (
            id INTEGER PRIMARY KEY, name TEXT NOT NULL, city TEXT NOT NULL DEFAULT "",
            message TEXT NOT NULL, status TEXT NOT NULL DEFAULT "pending",
            ip_hash TEXT NOT NULL, created_at TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS attempts (ip_hash TEXT NOT NULL, kind TEXT NOT NULL, ts INTEGER NOT NULL);
        CREATE INDEX IF NOT EXISTS idx_photos ON photos(section, position);
        CREATE INDEX IF NOT EXISTS idx_media ON media(type, position);
        CREATE INDEX IF NOT EXISTS idx_comments ON comments(status, id);
        CREATE INDEX IF NOT EXISTS idx_attempts ON attempts(ip_hash, kind, ts);
    ');
    return $pdo;
}

/* ---------- Configurações do site ---------- */
const SETTING_DEFAULTS = [
    'site_name'  => 'Bia',
    'tagline'    => 'Radialista e comunicadora',
    'hero_text'  => 'Voz, informação e boa companhia todos os dias, no rádio e onde mais a conversa estiver.',
    'bio'        => "Escreva aqui a sua história: como começou no rádio, por onde passou, os programas que apresenta e o que mais gosta de fazer no ar.\n\nEste texto é editado no painel, em Perfil.",
    'hero_photo' => '',
    'whatsapp'   => '',
    'phone'      => '',
    'email'      => '',
    'instagram'  => '',
    'facebook'   => '',
    'youtube'    => '',
    'tiktok'     => '',
    'city'       => '',
    'comments_moderation' => '1',
    'secret'     => '',
];

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

/** Conta tentativas recentes e, opcionalmente, registra uma nova. */
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
    $sent = $_POST['csrf'] ?? '';
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

function date_br(string $iso): string
{
    $meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    $t = strtotime($iso) ?: time();
    return (int) date('j', $t) . ' de ' . $meses[(int) date('n', $t) - 1] . ' de ' . date('Y', $t);
}

/* ---------- Imagens ---------- */

/**
 * Valida a imagem enviada, regrava como JPEG (descartando qualquer conteúdo
 * que não seja imagem) e gera uma miniatura. Retorna os dados ou uma mensagem de erro.
 * @return array{file:string,thumb:string,w:int,h:int}|string
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

    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8));
    [$w, $h] = write_resized($src, UPLOAD_DIR . "/$name.jpg", 1800, 86);
    write_resized($src, UPLOAD_DIR . "/$name-p.jpg", 720, 80);
    imagedestroy($src);

    return ['file' => "$name.jpg", 'thumb' => "$name-p.jpg", 'w' => $w, 'h' => $h];
}

/** @return array{0:int,1:int} largura e altura finais */
function write_resized(GdImage $src, string $dest, int $maxSide, int $quality): array
{
    $sw = imagesx($src);
    $sh = imagesy($src);
    $scale = min(1, $maxSide / max($sw, $sh));
    $w = max(1, (int) round($sw * $scale));
    $h = max(1, (int) round($sh * $scale));
    $dst = imagecreatetruecolor($w, $h);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // fundo para PNG transparente
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);
    imageinterlace($dst, true);
    imagejpeg($dst, $dest, $quality);
    imagedestroy($dst);
    return [$w, $h];
}

function delete_image_files(string ...$files): void
{
    foreach ($files as $f) {
        if ($f !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $f)) @unlink(UPLOAD_DIR . '/' . $f);
    }
}

/** Normaliza $_FILES de um campo "multiple" em uma lista de envios. */
function uploaded_list(string $field): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f) return [];
    if (!is_array($f['name'])) return [$f];
    $list = [];
    foreach ($f['name'] as $i => $_) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $list[] = ['name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $list;
}
