<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Db.php';
require_once __DIR__ . '/../core/AiClient.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_POST['q'] ?? '');
if (!$q || mb_strlen($q) < 3) {
    echo json_encode([]);
    exit;
}

try {
    $db  = Db::getInstance();
    $db->initTables();
    $pdo = $db->getConnection();

    // Берём последние 200 записей
    $rows = $pdo->query("
        SELECT id, title, source, category, description, ai_summary
        FROM digest_events
        ORDER BY id DESC LIMIT 200
    ")->fetchAll();

    if (empty($rows)) {
        echo json_encode([]);
        exit;
    }

    // Формируем контекст для ИИ
    $context = '';
    foreach ($rows as $i => $row) {
        $text = $row['ai_summary'] ?: ($row['description'] ?: '');
        $context .= "[{$row['id']}] {$row['title']} | {$row['source']} | " . mb_substr($text, 0, 100) . "\n";
    }

    $ai = new AiClient($pdo);
    $result = $ai->ask(
        "Ты — поисковый помощник по новостной базе. Отвечай ТОЛЬКО валидным JSON-массивом id записей.",
        "Запрос пользователя: «{$q}»\n\nБаза записей:\n{$context}\n\n" .
        "Верни JSON-массив с id наиболее релевантных записей (максимум 12). " .
        "Пример: [42, 17, 8]. Только массив, без пояснений."
    );

    $ids = [];
    if ($result) {
        // Ищем JSON-массив в ответе
        preg_match('/\[[\d,\s]+\]/', $result, $m);
        if ($m) {
            $ids = json_decode($m[0], true) ?: [];
        }
    }

    if (empty($ids)) {
        echo json_encode([]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $found = $pdo->prepare("SELECT id, title, url, source, category, ai_summary, description FROM digest_events WHERE id IN ($placeholders)");
    $found->execute($ids);

    $results = $found->fetchAll();
    
    // Сортируем в том же порядке как вернул ИИ
    $sorted = [];
    foreach ($ids as $id) {
        foreach ($results as $r) {
            if ((int)$r['id'] === (int)$id) {
                $sorted[] = $r;
                break;
            }
        }
    }

    echo json_encode($sorted, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}