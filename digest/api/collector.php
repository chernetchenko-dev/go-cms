<?php
declare(strict_types=1);
/**
 * collector.php — точка сбора данных (AI-only)
 *
 * Порядок:
 * 1. AI-скрапер (обход сайтов с имитацией браузера + AI-обработка)
 *
 * Запуск через cron: php8.5 collector.php
 * Запуск через web: collector.php?token=ТОКЕН
 * Сухой режим: collector.php --dry-run или collector.php?dry_run=1
 *
 * RSS-каналы полностью удалены. Сбор только через AI-агентов.
 */

require_once __DIR__ . '/../core/Config.php';

// Токен проверяем только при веб-запросе (не CLI)
$isCli = (php_sapi_name() === 'cli');
$isDryRun = $isCli
    ? in_array('--dry-run', $argv)
    : isset($_GET['dry_run']);

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    $token = isset($_GET['token']) ? $_GET['token'] : '';
    if (!defined('DIGEST_CRON_TOKEN') || !hash_equals(DIGEST_CRON_TOKEN, $token)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
        exit;
    }
}

require_once __DIR__ . '/../core/Db.php';
require_once __DIR__ . '/../core/AiClient.php';
require_once __DIR__ . '/../collectors/ai_scraper.php';

// === ЗАПУСК СБОРА ===
try {
    $db = Db::getInstance();
    $db->initTables();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    $msg = 'DB Error: ' . $e->getMessage();
    error_log($msg);
    if ($isCli) { echo $msg . PHP_EOL; exit(1); }
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

echo $isCli ? "🚀 Запуск AI-коллектора (Dry Run: " . ($isDryRun ? 'ON' : 'OFF') . ")..." . PHP_EOL : '';

$allItems = [];

// AI-скрапер
if (function_exists('runAiScraper')) {
    $allItems = runAiScraper($isDryRun);
}

// === СОХРАНЕНИЕ В БД ===
$newCount = 0;
$newIds   = [];

if (!$isDryRun && !empty($allItems)) {
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO digest_events (title, url, source, category, description) VALUES (?, ?, ?, ?, ?)"
    );

    foreach ($allItems as $item) {
        $url = trim($item['url'] ?? '');
        if (empty($item['title']) || empty($url)) continue;

        $cat  = $item['category'] ?? 'ai';
        $desc = mb_substr(strip_tags($item['description'] ?? ''), 0, 250);

        try {
            $stmt->execute([
                mb_substr($item['title'], 0, 255),
                mb_substr($url, 0, 1024),
                mb_substr($item['source'] ?? '', 0, 50),
                $cat,
                $desc,
            ]);
            $newId = (int)$pdo->lastInsertId();
            if ($newId > 0) {
                $newCount++;
                $newIds[] = $newId;
            }
        } catch (PDOException $e) {
            // Дубль по UNIQUE url — пропускаем
        }
    }
}

// === AI-СУММАРИЗАЦИЯ (фоновая) ===
if (!$isDryRun && !empty($newIds)) {
    $summarizePath = __DIR__ . '/summarize.php';
    foreach ($newIds as $id) {
        $cmd = sprintf('php8.5 %s %d > /dev/null 2>&1 &', escapeshellarg($summarizePath), $id);
        @exec($cmd);
    }
}

// === РЕЗУЛЬТАТ ===
$result = [
    'status'    => 'ok',
    'total'     => count($allItems),
    'new'       => $newCount,
    'ai_queued' => count($newIds),
    'time'      => date('H:i:s'),
];

if ($isCli) {
    echo "Собрано: {$result['total']} | Новых: {$result['new']} | AI: {$result['ai_queued']}" . PHP_EOL;
} else {
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}