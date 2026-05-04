<?php
declare(strict_types=1);
error_reporting(0);
ini_set('display_errors', 0);
/**
 * /admin/api/scan_php.php
 * Сканер PHP-файлов, парсинг мета-данных и управление SEO-оверрайдами.
 * Безопасно: код НЕ выполняется, только читается и анализируется.
 */

require_once __DIR__ . '/../config.php';
session_start();

if (empty($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$rootDir = __DIR__ . '/../..';
$seoFile = $rootDir . '/config/seo_overrides.json';
$uploadsDir = $rootDir . '/uploads/php_pages'; // Папка для загрузки (опционально)

// --- ИСКЛЮЧЕНИЯ (системные файлы и папки) ---
$excludes = [
    'header.php', 'footer.php', 'index.php', 'article.php', 
    '404.php', 'search.php', 'config.php', '.htaccess',
    'admin', 'lib', 'vendor', 'digest', 'content', 'uploads', 'node_modules'
];

// --- ЧТЕНИЕ SEO-ОVERRIDES ---
$seoOverrides = [];
if (file_exists($seoFile)) {
    $seoOverrides = json_decode(file_get_contents($seoFile), true) ?: [];
}

// --- ФУНКЦИЯ ПАРСИНГА МЕТЫ ---
function parsePhpMeta(string $filePath): array {
    $content = @file_get_contents($filePath);
    if ($content === false) return [];

    $meta = ['title' => '', 'description' => '', 'siteId' => 'main'];

    // Ищем переменные через Regex (безопасно, код не выполняется)
    $patterns = [
        'title'       => '/\$pageTitle\s*=\s*["\']([^"\']+)["\']\s*;/i',
        'description' => '/\$pageDescription\s*=\s*["\']([^"\']+)["\']\s*;/i',
        'siteId'      => '/\$siteId\s*=\s*["\']([^"\']+)["\']\s*;/i',
    ];

    foreach ($patterns as $key => $pattern) {
        if (preg_match($pattern, $content, $matches)) {
            $meta[$key] = trim($matches[1]);
        }
    }
    return $meta;
}

// === GET: СПИСОК СТРАНИЦ ===
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pages = [];
    
    // Сканируем public_html (не рекурсивно для безопасности)
    $files = array_diff(scandir($rootDir), ['.', '..']);
    
    foreach ($files as $file) {
        if (in_array($file, $excludes)) continue;
        if (!str_ends_with($file, '.php')) continue;
        
        $fullPath = $rootDir . '/' . $file;
        $meta = parsePhpMeta($fullPath);
        
        // Если нет меты в файле, но есть оверрайд - берем из оверрайда
        $override = $seoOverrides[$file] ?? [];
        
        $pages[] = [
            'file'        => $file,
            'url'         => '/' . $file,
            'parsedTitle' => $meta['title'],
            'parsedDesc'  => $meta['description'],
            'siteId'      => $meta['siteId'],
            'seoTitle'    => $override['title'] ?? $meta['title'],
            'seoDesc'     => $override['desc'] ?? $meta['description'],
        ];
    }
    
    // Сортируем: сначала те, где есть SEO-оверрайд, потом по имени
    usort($pages, function($a, $b) {
        $aHas = isset($seoOverrides[$a['file']]);
        $bHas = isset($seoOverrides[$b['file']]);
        if ($aHas && !$bHas) return -1;
        if (!$aHas && $bHas) return 1;
        return strcmp($a['file'], $b['file']);
    });

    echo json_encode(['status' => 'ok', 'pages' => $pages]);
    exit;
}

// === POST: СОХРАНЕНИЕ SEO-ОVERRIDE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_seo') {
    $input = json_decode(file_get_contents('php://input'), true);
    $file  = $input['file'] ?? '';
    
    if (!$file || !str_ends_with($file, '.php') || in_array($file, $excludes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file']);
        exit;
    }
    
    $seoOverrides[$file] = [
        'title' => strip_tags(trim($input['title'] ?? '')),
        'desc'  => strip_tags(trim($input['desc'] ?? '')),
    ];
    
    // Удаляем запись, если поля пустые (сброс к дефолту из файла)
    if (empty($seoOverrides[$file]['title']) && empty($seoOverrides[$file]['desc'])) {
        unset($seoOverrides[$file]);
    }
    
    if (!is_dir(dirname($seoFile))) @mkdir(dirname($seoFile), 0755, true);
    if (@file_put_contents($seoFile, json_encode($seoOverrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX)) {
        echo json_encode(['status' => 'ok', 'message' => 'SEO обновлено']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Write error']);
    }
    exit;
}

echo json_encode(['error' => 'Unknown action']);