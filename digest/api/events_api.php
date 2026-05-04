<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Db.php';

if (!defined('DIGEST_ACCESS')) { http_response_code(403); exit; }

$db = Db::getInstance();
$pdo = $db->getConnection();

// Параметры запроса
$sourceType = $_GET['source_type'] ?? 'all';
$query = $_GET['q'] ?? '';
$period = $_GET['period'] ?? '7';
$sort = $_GET['sort'] ?? 'date';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 12)));

// Базовый запрос
$sql = "
    SELECT SQL_CALC_FOUND_ROWS id, title, link, source, source_type, 
           publish_date, summary, tags, relevance
    FROM digest_events 
    WHERE is_published = 1
";

$params = [];

// Фильтр по источнику
if ($sourceType !== 'all') {
    $sql .= " AND source_type = ?";
    $params[] = $sourceType;
}

// Поиск
if ($query) {
    $sql .= " AND (title LIKE ? OR summary LIKE ?)";
    $searchTerm = "%$query%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Фильтр по периоду
if ($period === '7') {
    $sql .= " AND publish_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($period === '30') {
    $sql .= " AND publish_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

// Сортировка
if ($sort === 'relevance') {
    $sql .= " ORDER BY relevance DESC, publish_date DESC";
} else {
    $sql .= " ORDER BY publish_date DESC";
}

// Пагинация
$offset = ($page - 1) * $perPage;
$sql .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

// Выполнение запроса
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Получение общего количества
$stmt = $pdo->query("SELECT FOUND_ROWS()");
$total = (int)$stmt->fetchColumn();
$pages = ceil($total / $perPage);

// Форматирование ответа
foreach ($events as &$event) {
    $event['tags'] = $event['tags'] ? json_decode($event['tags'], true) : [];
    $event['publish_date'] = date('Y-m-d H:i:s', strtotime($event['publish_date']));
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'data' => $events,
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'pages' => $pages
    ]
], JSON_UNESCAPED_UNICODE);