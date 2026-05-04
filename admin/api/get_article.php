<?php
declare(strict_types=1);
/**
 * /admin/api/get_article.php
 * Получение содержимого статьи (YAML frontmatter + Markdown body).
 * Параметры: site, file (имя файла с .md)
 */

require_once __DIR__ . '/../config.php';
session_start();

if (empty($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещён']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$site = preg_replace('/[^a-z0-9_]/i', '', $_GET['site'] ?? '');
$file = basename($_GET['file'] ?? ''); // защита от path traversal

if (empty($site) || empty($file)) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверные параметры']);
    exit;
}

// Определяем пути к файлу
$projectBase = dirname(dirname(dirname(__DIR__))); // /Users/chernetchenko/Code/SITE_F

if (str_ends_with($file, '.md')) {
    // Markdown файл - ищем в /content/
    if ($site === 'main') {
        $filePath = __DIR__ . '/../../content/' . $file;
    } else {
        $filePath = $projectBase . '/' . $site . '_chernetchenko_pro/public_html/content/' . $file;
    }
} elseif (str_ends_with($file, '.php')) {
    // PHP файл - ищем в корне сайта
    if ($site === 'main') {
        $filePath = __DIR__ . '/../../' . $file;
    } else {
        $filePath = $projectBase . '/' . $site . '_chernetchenko_pro/public_html/' . $file;
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Неподдерживаемый тип файла']);
    exit;
}

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Файл не найден']);
    exit;
}

$content = file_get_contents($filePath);
if ($content === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка чтения файла']);
    exit;
}

// Для PHP файлов возвращаем сырой текст без парсинга YAML
if (str_ends_with($file, '.php')) {
    echo json_encode([
        'ok' => true,
        'meta' => [],
        'body' => $content,
        'file' => $file,
        'site' => $site,
        'type' => 'php'
    ]);
    exit;
}

// Для Markdown файлов парсим frontmatter
$meta = [];
$body = $content;
if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $m)) {
    $yaml = $m[1];
    $body = $m[2];
    foreach (explode("\n", $yaml) as $line) {
        $line = trim($line);
        if (!$line || !str_contains($line, ':')) continue;
        [$key, $value] = explode(':', $line, 2);
        $key = trim(strtolower($key));
        $value = trim($value);
        // Массивы
        if (preg_match('/^\[(.*)\]$/', $value, $arr)) {
            $meta[$key] = array_map(fn($i) => trim(trim($i), '"\''), explode(',', $arr[1]));
        } elseif (strtolower($value) === 'true') {
            $meta[$key] = true;
        } elseif (strtolower($value) === 'false') {
            $meta[$key] = false;
        } else {
            $meta[$key] = trim($value, '"\'');
        }
    }
}

echo json_encode([
    'ok' => true,
    'meta' => $meta,
    'body' => $body,
    'file' => $file,
    'site' => $site,
    'type' => 'md'
]);
