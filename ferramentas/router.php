<?php
/**
 * Roteador para o servidor embutido do PHP, que não lê .htaccess. Só para testes:
 *   php -S localhost:8000 ferramentas/router.php
 */
if (PHP_SAPI !== 'cli-server') exit;
$root = dirname(__DIR__);
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('~^/(inc|data|ferramentas|docs)(/|$)~', $path)) { http_response_code(403); exit('Acesso negado'); }
$file = $root . $path;
if ($path !== '/' && is_file($file)) return false;
if (is_dir($file) && is_file(rtrim($file, '/') . '/index.php')) {
    $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php';
    require rtrim($file, '/') . '/index.php';
    return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
