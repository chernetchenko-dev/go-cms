<?php
/**
 * sitemap.xml — chernetchenko.pro
 * Генерирует карту только страниц сайта. Внешние URL не включаются.
 */
header('Content-Type: application/xml; charset=utf-8');

$base = 'https://chernetchenko.pro';
$today = date('Y-m-d');

// Статические страницы сайта
$static = [
    ['loc' => $base . '/',             'priority' => '1.0',  'freq' => 'daily'],
    ['loc' => $base . '/networking',   'priority' => '0.9',  'freq' => 'weekly'],
    ['loc' => $base . '/digest/',      'priority' => '0.9',  'freq' => 'hourly'],
    ['loc' => $base . '/digest/?cat=ai',     'priority' => '0.8', 'freq' => 'hourly'],
    ['loc' => $base . '/digest/?cat=bim',    'priority' => '0.8', 'freq' => 'daily'],
    ['loc' => $base . '/digest/?cat=events', 'priority' => '0.7', 'freq' => 'weekly'],
    ['loc' => $base . '/digest/?cat=norms',  'priority' => '0.6', 'freq' => 'weekly'],
    ['loc' => $base . '/articles.php', 'priority' => '0.7',  'freq' => 'weekly'],
];

// Статьи из content/ (flat-file, без БД)
$articles = [];
$contentDir = __DIR__ . '/content/';
if (is_dir($contentDir)) {
    foreach (glob($contentDir . '*.md') as $file) {
        $raw   = file_get_contents($file);
        $match = [];
        // Парсим slug и draft из front matter
        if (!preg_match('/^---\s*\n(.*?)\n---/s', $raw, $match)) continue;
        $yaml = $match[1];

        // draft: true — пропускаем
        if (preg_match('/^draft:\s*(true|1)\s*$/im', $yaml)) continue;

        // slug
        preg_match('/^slug:\s*(.+)$/im', $yaml, $slugM);
        $slug = trim($slugM[1] ?? '');
        if (!$slug) $slug = pathinfo($file, PATHINFO_FILENAME);

        // date для lastmod
        preg_match('/^date:\s*(.+)$/im', $yaml, $dateM);
        $date = trim($dateM[1] ?? $today);

        $articles[] = [
            'loc'      => $base . '/article.php?slug=' . urlencode($slug),
            'lastmod'  => $date,
            'priority' => '0.6',
            'freq'     => 'monthly',
        ];
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Статические страницы
foreach ($static as $u) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($u['loc']) . "</loc>\n";
    echo "    <lastmod>{$today}</lastmod>\n";
    echo "    <changefreq>{$u['freq']}</changefreq>\n";
    echo "    <priority>{$u['priority']}</priority>\n";
    echo "  </url>\n";
}

// Статьи
foreach ($articles as $u) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($u['loc']) . "</loc>\n";
    echo "    <lastmod>" . htmlspecialchars($u['lastmod']) . "</lastmod>\n";
    echo "    <changefreq>{$u['freq']}</changefreq>\n";
    echo "    <priority>{$u['priority']}</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>';
