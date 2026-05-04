<?php
declare(strict_types=1);
/**
 * /admin/api/save_article.php
 * API для сохранения статей (Markdown + YAML Frontmatter).
 * Вызывается через fetch из admin/index.php (вкладка CMS).
 */

require_once __DIR__ . '/../../config.php';
session_start();

// Проверка авторизации
if (empty($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещён']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается']);
    exit;
}

// Получаем данные
$slug = preg_replace('/[^a-z0-9\-_]/i', '', $_POST['slug'] ?? '');
$title = trim($_POST['title'] ?? '');
$content = $_POST['content'] ?? '';
$site = preg_replace('/[^a-z0-9_]/i', '', $_POST['site'] ?? 'main');
$tags = trim($_POST['tags'] ?? '');
$badge = trim($_POST['badge'] ?? '');
$stub = isset($_POST['stub']) ? true : false;

// Валидация
if (empty($slug) || empty($title) || empty($content)) {
    http_response_code(400);
    echo json_encode(['error' => 'Заполните обязательные поля (slug, title, content)']);
    exit;
}

// Формируем YAML Frontmatter
$yaml = "title: \"$title\"\n";
$yaml .= "slug: \"$slug\"\n";
$yaml .= "site: \"$site\"\n";
$yaml .= "date: " . date('Y-m-d') . "\n";

// Tags
if (!empty($tags)) {
    $tagArray = array_map('trim', explode(',', $tags));
    $tagArray = array_filter($tagArray);
    if (!empty($tagArray)) {
        $yaml .= "tags: [" . implode(', ', array_map(fn($t) => "\"$t\"", $tagArray)) . "]\n";
    }
}

if (!empty($badge)) {
    $yaml .= "badge: \"$badge\"\n";
}

if ($stub) {
    $yaml .= "stub: true\n";
}

// Путь к файлу
$basePath = __DIR__ . '/../../content';
if ($site !== 'main') {
    $basePath = __DIR__ . "/../../../{$site}_chernetchenko_pro/public_html/content";
}

if (!is_dir($basePath)) {
    // Пытаемся создать директорию
    if (!@mkdir($basePath, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Не удалось создать директорию: ' . $basePath]);
        exit;
    }
}

$filePath = "$basePath/$slug.md";

// Сохраняем файл
$fullContent = "---\n$yaml---\n$content";
if (@file_put_contents($filePath, $fullContent, LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка записи файла: ' . $filePath]);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => "Статья сохранена: $slug.md",
    'file' => $filePath
]);