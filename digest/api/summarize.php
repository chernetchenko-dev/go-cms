<?php
declare(strict_types=1);
/**
 * summarize.php — AI-обогащение одного события
 * Запускается фоново: php summarize.php {event_id}
 *
 * Реальная схема digest_events:
 * id, title, url, source, category, description,
 * tags (json), ai_summary, published_at, created_at
 */

// При запуске через exec() DIGEST_ACCESS не определён — определяем сами
if (!defined('DIGEST_ACCESS')) {
    define('DIGEST_ACCESS', true);
}

require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Db.php';
require_once __DIR__ . '/../core/AiClient.php';

$eventId = isset($argv[1]) ? (int)$argv[1] : 0;
if ($eventId <= 0) {
    error_log('summarize.php: missing or invalid event_id');
    exit(1);
}

try {
    $pdo = Db::getInstance()->getConnection();
} catch (Throwable $e) {
    error_log('summarize.php: DB error — ' . $e->getMessage());
    exit(1);
}

$stmt = $pdo->prepare("SELECT * FROM digest_events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    error_log("summarize.php: event $eventId not found");
    exit(1);
}

// Уже обработано — пропускаем
if (!empty($event['ai_summary'])) {
    exit(0);
}

$ai = new AiClient($pdo);

$systemPrompt = "Ты — редактор дайджеста по ИИ, BIM и строительным технологиям. "
    . "Аудитория: инженеры-проектировщики, BIM-координаторы, руководители проектов. "
    . "Верни ТОЛЬКО валидный JSON без markdown, пояснений и переносов строк снаружи JSON.";

$userPrompt = sprintf(
    "Событие: %s\nИсточник: %s\nОписание: %s\n\n"
    . "Верни JSON:\n"
    . "{\"relevance\":<1-10>,\"summary\":\"1-2 предложения на русском\",\"tags\":[\"тег1\",\"тег2\"]}\n\n"
    . "Критерии:\n"
    . "8-10 — важная новость, новая модель, прорыв\n"
    . "5-7  — полезно практикам\n"
    . "3-4  — интересно, но не срочно\n"
    . "1-2  — не по теме",
    mb_substr($event['title']       ?? '', 0, 300),
    mb_substr($event['source']      ?? '', 0, 100),
    mb_substr($event['description'] ?? '', 0, 500)
);

$response = $ai->ask($systemPrompt, $userPrompt);

if (!$response) {
    // ИИ не ответил — публикуем как есть
    $pdo->prepare("UPDATE digest_events SET published_at = NOW() WHERE id = ? AND published_at IS NULL")
        ->execute([$eventId]);
    error_log("summarize.php: no AI response for event $eventId");
    exit(0);
}

$ai->setLastEventId($eventId);
$json = $ai->extractJSON($response);

if ($json && isset($json['summary'])) {
    $summary   = mb_substr(trim($json['summary']), 0, 500);
    $tags      = json_encode($json['tags'] ?? [], JSON_UNESCAPED_UNICODE);
    $relevance = max(1, min(10, (int)($json['relevance'] ?? 5)));

    // Публикуем только если релевантность >= MIN_RELEVANCE
    $publishedAt = ($relevance >= MIN_RELEVANCE) ? 'NOW()' : 'NULL';

    $pdo->prepare("
        UPDATE digest_events
        SET ai_summary   = ?,
            tags         = ?,
            published_at = {$publishedAt}
        WHERE id = ?
    ")->execute([$summary, $tags, $eventId]);

} else {
    // JSON не распарсился — публикуем без summary
    $pdo->prepare("UPDATE digest_events SET published_at = NOW() WHERE id = ? AND published_at IS NULL")
        ->execute([$eventId]);
    error_log("summarize.php: bad JSON for event $eventId: " . mb_substr($response ?? '', 0, 200));
}

exit(0);
