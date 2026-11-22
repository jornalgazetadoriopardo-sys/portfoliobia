<?php
/** Entrada do site público: decide qual página mostrar a partir do endereço. */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/site.php';

define('BASE', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/');

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (str_starts_with($path, BASE)) $path = substr($path, strlen(BASE));
$path = trim(rawurldecode($path), '/');
if ($path === 'index.php') $path = '';

$pdo = db();
$s = settings();
$route = ['pagina' => 'erro404'];

if ($path === '')                         $route = ['pagina' => 'inicio'];
elseif ($path === 'minha-historia')       $route = ['pagina' => 'historia'];
elseif ($path === 'galeria')              $route = ['pagina' => 'galeria', 'decada' => null];
elseif (preg_match('~^galeria/decada/(\d{4}|sem-data)$~', $path, $m)) $route = ['pagina' => 'galeria', 'decada' => $m[1]];
elseif (preg_match('~^foto/(\d+)(?:-[a-z0-9-]*)?$~', $path, $m))      $route = ['pagina' => 'foto', 'id' => (int) $m[1]];
elseif ($path === 'trabalhos')            $route = ['pagina' => 'trabalhos'];
elseif ($path === 'audios')               $route = ['pagina' => 'audios'];
elseif ($path === 'videos')               $route = ['pagina' => 'videos'];
elseif ($path === 'recados')              $route = ['pagina' => 'recados'];
elseif ($path === 'contato')              $route = ['pagina' => 'contato'];
elseif ($path === 'sitemap.xml')          { sitemap(); exit; }
elseif ($path === 'robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nDisallow: " . BASE . "admin/\nSitemap: " . abs_url('sitemap.xml') . "\n";
    exit;
}

require __DIR__ . '/inc/paginas/' . $route['pagina'] . '.php';
