<?php
declare(strict_types=1);
/**
 * daily_summary.php — AI-сводка за день
 *
 * Запуск через cron в 09:05:
 *   5 9 * * * php /путь/до/digest/api/daily_summary.php
 *
 * Или через веб:
 *   /digest/api/daily_summary.php?token=ТОКЕН
 *
 * Берёт все события за последние 24 часа,
 * формирует краткую сводку через ИИ,
 * сохраняет в таблицу digest_daily_summary.
 */

if (!defined('DIGEST_ACCESS')) {
    define('DIGEST_ACCESS', true);
}

require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Db.php';
require_once __DIR__ . '/../core/AiClient.php';

// Проверка токена при веб-запросе
if (php_sapi_name() !== 'cli') {
    $token = $_GET['token'] ?? '';
    if (!hash_equals(DIGEST_CRON_TOKEN, $token)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
}

try {
    $db  = Db::getInstance();
    $db->initTables();
    $pdo = $db->getConnection();
} catch (Throwable $e) {
    $msg = 'DB Error: ' . $e->getMessage();
    error_log($msg);
    echo php_sapi_name() === 'cli' ? $msg . "\n" : json_encode(['status' => 'error', 'message' => $msg]);
    exit(1);
}

$today = date('Y-m-d');

// Проверяем — сводка на сегодня уже есть?
$exists = $pdo->prepare("SELECT id FROM digest_daily_summary WHERE summary_date = ?");
$exists->execute([$today]);
if ($exists->fetchColumn()) {
    $msg = "Сводка на $today уже существует";
    echo php_sapi_name() === 'cli' ? $msg . "\n" : json_encode(['status' => 'skip', 'message' => $msg]);
    exit(0);
}

// Берём события за последние 24 часа
$stmt = $pdo->prepare("
    SELECT title, source, category, description, ai_summary
    FROM digest_events
    WHERE created_at >= NOW() - INTERVAL 24 HOUR
      AND (published_at IS NOT NULL OR category IN ('events', 'bim', 'norms'))
    ORDER BY category, id DESC
    LIMIT 60
");
$stmt->execute();
$events = $stmt->fetchAll();

if (empty($events)) {
    $msg = "Нет событий за последние 24 часа";
    echo php_sapi_name() === 'cli' ? $msg . "\n" : json_encode(['status' => 'skip', 'message' => $msg]);
    exit(0);
}

// Группируем по категории для промта
$grouped = [];
foreach ($events as $ev) {
    $cat = $ev['category'] ?? 'ai';
    $grouped[$cat][] = ($ev['ai_summary'] ?: $ev['description'] ?: $ev['title']);
}

$catLabels = ['ai' => 'ИИ и ML', 'bim' => 'BIM и ТИМ', 'events' => 'Мероприятия', 'norms' => 'Нормативка'];
$digest = '';
foreach ($grouped as $cat => $items) {
    $label = $catLabels[$cat] ?? strtoupper($cat);
    $digest .= "\n## $label (" . count($items) . " событий)\n";
    foreach (array_slice($items, 0, 15) as $item) {
        $digest .= '- ' . mb_substr(strip_tags($item), 0, 150) . "\n";
    }
}

$ai = new AiClient($pdo);

$systemPrompt = "Ты — редактор профессионального дайджеста для инженеров-проектировщиков, "
    . "BIM-специалистов и руководителей строительных проектов. "
    . "Пиши коротко, конкретно, без воды. Тон — деловой но живой. Без нейроязыка.";

$userPrompt = "Сегодня " . date('d.m.Y') . ". Вот что собрал дайджест за последние 24 часа:\n"
    . $digest . "\n\n"
    . "Напиши дневную сводку в таком формате:\n"
    . "**Главное за " . date('d.m.Y') . "**\n\n"
    . "Краткое вступление (1-2 предложения — общий тон дня).\n\n"
    . "**ИИ и модели:** что важного вышло (2-3 пункта если есть)\n"
    . "**BIM и стройка:** главные новости (2-3 пункта если есть)\n"
    . "**Мероприятия:** что анонсировано или приближается (если есть)\n"
    . "**Нормативка:** изменения и обновления (если есть)\n\n"
    . "Если по какой-то теме ничего интересного — не упоминай её вообще.\n"
    . "Максимум 300 слов. Только русский язык.";

$summary = $ai->ask($systemPrompt, $userPrompt);

if (!$summary) {
    // ИИ не ответил — сохраняем заглушку
    $summary = "**Главное за " . date('d.m.Y') . "**\n\nЗа сутки собрано " . count($events) . " материалов. ИИ-резюме временно недоступно.";
}

// Сохраняем сводку
$pdo->prepare("
    INSERT INTO digest_daily_summary (summary_date, summary_text, items_count)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE summary_text = VALUES(summary_text), items_count = VALUES(items_count), updated_at = NOW()
")->execute([$today, $summary, count($events)]);

$result = [
    'status'      => 'ok',
    'date'        => $today,
    'items_count' => count($events),
    'summary'     => mb_substr($summary, 0, 200) . '…',
];

echo php_sapi_name() === 'cli'
    ? "✅ Сводка за $today сформирована. Событий: " . count($events) . "\n"
    : json_encode($result, JSON_UNESCAPED_UNICODE);
