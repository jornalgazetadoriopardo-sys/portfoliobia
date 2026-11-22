<?php
/** Recebe um recado do formulário público. Responde em JSON (fetch) ou redireciona (sem JavaScript). */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function finish(bool $ok, string $message, bool $json): never
{
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($ok ? 200 : 422);
        echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
    start_session();
    $_SESSION['recado_flash'] = ['ok' => $ok, 'message' => $message];
    redirect('index.php#recados');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php#recados');

start_session();
if (!csrf_ok()) finish(false, 'A página ficou aberta por muito tempo. Atualize e envie de novo.', $wantsJson);

// Armadilhas contra robôs: campo invisível preenchido ou envio rápido demais.
$started = (int) ($_SESSION['form_started'] ?? 0);
if (($_POST['site'] ?? '') !== '' || $started === 0 || time() - $started < 3) {
    finish(true, 'Recado recebido.', $wantsJson); // resposta neutra, nada é gravado
}

$name    = clean((string) ($_POST['nome'] ?? ''), 60);
$city    = clean((string) ($_POST['cidade'] ?? ''), 60);
$message = clean((string) ($_POST['mensagem'] ?? ''), 800);

if (mb_strlen($name) < 2) finish(false, 'Escreva o seu nome.', $wantsJson);
if (mb_strlen($message) < 5) finish(false, 'Escreva o recado antes de enviar.', $wantsJson);
if (preg_match_all('~https?://|www\.~i', $message) > 1) finish(false, 'O recado não pode ter mais de um link.', $wantsJson);

if (attempts('recado', 3600) >= 4) {
    finish(false, 'Você já enviou alguns recados há pouco. Tente de novo mais tarde.', $wantsJson);
}
add_attempt('recado');

$moderated = setting('comments_moderation') === '1';
db()->prepare('INSERT INTO comments(name, city, message, status, ip_hash, created_at) VALUES(?,?,?,?,?,?)')
    ->execute([$name, $city, $message, $moderated ? 'pending' : 'approved', ip_hash(), date('c')]);

finish(true, $moderated
    ? 'Recado enviado. Ele aparece aqui depois de aprovado.'
    : 'Recado publicado. Obrigada pelo carinho!', $wantsJson);
