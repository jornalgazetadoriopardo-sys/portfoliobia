<?php
/**
 * Preenche o site com conteúdo de exemplo para aprovar o layout antes de ter o material real.
 * Uso, na pasta do projeto:   php ferramentas/exemplo.php
 * As imagens são retângulos coloridos com o ano; troque pelas fotos reais no painel.
 */
if (PHP_SAPI !== 'cli') exit('Somente pela linha de comando.');
require __DIR__ . '/../inc/bootstrap.php';
$pdo = db();
if ($pdo->query('SELECT COUNT(*) FROM photos')->fetchColumn() > 0) exit("Já existe conteúdo; nada foi alterado.\n");

function placeholder(int $w, int $h, array $a, array $b, string $text): GdImage
{
    $im = imagecreatetruecolor($w, $h);
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        imageline($im, 0, $y, $w, $y, imagecolorallocate($im, (int) ($a[0] + ($b[0] - $a[0]) * $t), (int) ($a[1] + ($b[1] - $a[1]) * $t), (int) ($a[2] + ($b[2] - $a[2]) * $t)));
    }
    imagefilledellipse($im, (int) ($w * .5), (int) ($h * .42), (int) (min($w, $h) * .34), (int) (min($w, $h) * .34), imagecolorallocatealpha($im, 255, 255, 255, 95));
    imagefilledellipse($im, (int) ($w * .5), (int) ($h * .95), (int) (min($w, $h) * .8), (int) (min($w, $h) * .7), imagecolorallocatealpha($im, 255, 255, 255, 100));
    $white = imagecolorallocate($im, 255, 255, 255);
    for ($i = 0; $i < 6; $i++) imagestring($im, 5, 24 + $i % 2, $h - 44 + intdiv($i, 2) % 2, $text, $white);
    return $im;
}

$fotos = [
    [1200, 900,  [120, 96, 70],  [60, 44, 34],   'Primeiro dia no estúdio', 1991, 'Rádio Difusora, São José do Rio Pardo', 'Bia e o operador de som', 'O microfone parecia enorme. A mão tremia, a voz não.'],
    [900, 1200,  [140, 110, 80], [70, 50, 40],   'Vinheta ao vivo', 1994, 'Estúdio A', 'Bia Pizani', 'A vinheta atrasou e o jeito foi cantar no ar.'],
    [1200, 800,  [90, 110, 130], [40, 50, 70],   'Cobertura da festa da cidade', 1998, 'Praça central', 'Bia, a equipe de externa e ouvintes', 'Três dias de transmissão direto da praça.'],
    [1000, 1000, [150, 90, 80],  [80, 40, 44],   'Entrevista de domingo', 2003, 'Estúdio B', 'Bia e o convidado da semana', 'A entrevista que era para durar dez minutos durou uma hora.'],
    [1200, 900,  [70, 100, 110], [30, 44, 60],   'Troca de turno', 2008, 'Corredor da rádio', 'Bia e os colegas da manhã', 'O café da troca de turno era sagrado.'],
    [900, 1200,  [110, 90, 130], [50, 40, 70],   'Vinte e cinco anos de microfone', 2016, 'Festa na emissora', 'Bia, família e colegas', 'Bolo, discurso e muita gente querida.'],
    [1200, 800,  [180, 120, 90], [100, 50, 50],  'No ar, hoje', 2024, 'Estúdio atual', 'Bia Pizani', 'Trinta e tantos anos depois, a luz vermelha ainda dá frio na barriga.'],
];
$ids = [];
foreach ($fotos as $i => [$w, $h, $a, $b, $title, $year, $place, $people, $story]) {
    $base = 'exemplo-' . ($i + 1) . '-' . bin2hex(random_bytes(4));
    $im = placeholder($w, $h, $a, $b, (string) $year);
    [$fw, $fh] = make_variants($im, $base);
    imagedestroy($im);
    $pdo->prepare("INSERT INTO photos(thumb, file, w, h, title, year, decade, place, people, story, featured, status, position, created_at)
                   VALUES('',?,?,?,?,?,?,?,?,?,?,'published',?,?)")
        ->execute([$base, $fw, $fh, $title, $year, decade_of($year), $place, $people, $story, $i % 2, $i + 1, date('c')]);
    $ids[$year] = (int) $pdo->lastInsertId();
}

// Capa
$im = placeholder(1600, 2000, [60, 70, 110], [27, 35, 64], '');
[$w, $h] = make_variants($im, 'exemplo-capa');
foreach (['hero_photo' => 'exemplo-capa', 'hero_w' => $w, 'hero_h' => $h, 'whatsapp' => '(19) 99999-0000',
          'instagram' => '@biapizani', 'facebook' => 'biapizani', 'live_url' => 'https://example.com/ao-vivo'] as $k => $v) {
    set_setting($k, (string) $v);
}

$marcos = [
    ['1991', 'O primeiro microfone', "Tudo começou numa emissora pequena, lendo recados de ouvintes no fim da tarde.\n\nEm poucos meses veio o primeiro programa próprio.", 1991],
    ['1994–1999', 'As manhãs da cidade', 'Cinco anos acordando a cidade, com música, notícia e prestação de serviço.', 1994],
    ['2003', 'Entrevistas de domingo', 'O programa de entrevistas levou ao estúdio artistas, prefeitos, professores e gente comum com boas histórias.', 2003],
    ['2016', 'Vinte e cinco anos de rádio', 'A festa reuniu colegas de todas as fases da carreira.', 2016],
    ['2024', 'Trinta e quatro anos depois', 'O rádio mudou, o jeito de ouvir mudou. A vontade de conversar com quem está do outro lado continua a mesma.', 2024],
];
foreach ($marcos as $i => [$period, $title, $body, $year]) {
    $pdo->prepare('INSERT INTO milestones(period, title, body, photo_id, position) VALUES(?,?,?,?,?)')->execute([$period, $title, $body, $ids[$year] ?? null, $i + 1]);
}

$im = placeholder(1400, 1000, [150, 70, 70], [60, 30, 50], '');
[$w, $h] = make_variants($im, 'exemplo-trabalho');
$pdo->prepare('INSERT INTO works(name, outlet, schedule, description, image, image_w, image_h, link, link_label, active, position) VALUES(?,?,?,?,?,?,?,?,?,1,?)')
    ->execute(['Manhã com a Bia', 'Rádio Difusora FM 99,9', 'Segunda a sexta, das 8h às 11h', "Música, notícias da região, entrevistas e a participação dos ouvintes pelo WhatsApp.", 'exemplo-trabalho', $w, $h, 'https://example.com/ao-vivo', 'Ouvir ao vivo', 2]);
$pdo->prepare('INSERT INTO works(name, outlet, schedule, description, active, position) VALUES(?,?,?,?,1,?)')
    ->execute(['Locuções comerciais', 'Estúdio próprio', 'Sob encomenda', 'Spots, vinhetas, esperas telefônicas e apresentação de eventos.', 1]);

foreach ([['Marta Oliveira', 'Mococa', 'ouvinte', 'Acordo todos os dias com a sua voz. O programa da manhã é a melhor companhia para o café.'],
          ['João Pedro Lima', '', 'colega', 'Dividir o estúdio com a Bia é uma aula diária de rádio e de gentileza.'],
          ['Dona Cida', 'Casa Branca', 'ouvinte', 'Ouço desde o começo. Que Deus abençoe o seu trabalho.'],
          ['Renata Souza', 'São José do Rio Pardo', 'amigo', 'Fora do ar ela é exatamente como no ar: atenta, engraçada e generosa.']] as [$n, $c, $r, $m]) {
    $pdo->prepare("INSERT INTO comments(name, city, relation, message, status, ip_hash, created_at) VALUES(?,?,?,?,'approved','',?)")->execute([$n, $c, $r, $m, date('c')]);
}
echo "Conteúdo de exemplo criado.\n";
