<?php
declare(strict_types=1);
error_reporting(0);
ini_set('display_errors', 0);
/**
 * /admin/api/save_php.php
 * API для сохранения PHP-файлов шаблонов.
 * Принимает: site, file, content (сырой PHP код).
 * Сохраняет файл в корень указанного сайта.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../lib/sites.php';
session_start();

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

$site = trim($_POST['site'] ?? '');
$file = trim($_POST['file'] ?? '');
$content = $_POST['content'] ?? '';

// Валидация
if (empty($site) || empty($file)) {
    http_response_code(400);
    echo json_encode(['error' => 'Не указан сайт или файл']);
    exit;
}

// Проверка имени файла (только .php)
if (!str_ends_with($file, '.php')) {
    http_response_code(400);
    echo json_encode(['error' => 'Можно сохранять только PHP-файлы']);
    exit;
}

// Определяем путь к сайту
$projectBase = dirname(dirname(dirname(__DIR__))); // /Users/chernetchenko/Code/SITE_F
if ($site === 'main') {
    $siteRoot = $projectBase . '/public_html/';
} else {
    $siteRoot = $projectBase . '/' . $site . '_chernetchenko_pro/public_html/';
}

// Полный путь к файлу
$filePath = $siteRoot . $file;

// Проверяем, что файл находится в пределах корня сайта (безопасность)
$realPath = realpath(dirname($filePath));
$realSiteRoot = realpath($siteRoot);
if ($realPath !== $realSiteRoot) {
    http_response_code(403);
    echo json_encode(['error' => 'Недопустимый путь файла']);
    exit;
}

// Создаем директорию если нет
if (!is_dir(dirname($filePath))) {
    @mkdir(dirname($filePath), 0755, true);
}

// Сохраняем файл
if (@file_put_contents($filePath, $content, LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка записи файла. Проверьте права']);
    exit;
}

echo json_encode(['ok' => true, 'message' => "Файл $file успешно сохранен"]);