<?php
declare(strict_types=1);
error_reporting(0);
ini_set('display_errors', 0);
/**
 * admin/api/events.php
 * CRUD для events.json (мероприятия на странице Networking).
 *
 * GET    — список всех мероприятий
 * POST   — добавить/обновить мероприятие
 * DELETE ?id=slug — удалить
 * PATCH  ?id=slug — пометить прошедшим/предстоящим
 */

require_once __DIR__ . '/../config.php';
session_start();

if (empty($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$eventsFile = __DIR__ . '/../../events.json';

function loadEvents(string $file): array {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveEvents(array $events, string $file): bool {
    return (bool)file_put_contents(
        $file,
        json_encode(array_values($events), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

$method = $_SERVER['REQUEST_METHOD'];
$id     = $_GET['id'] ?? '';

// ── GET: список ────────────────────────────────────────────────
if ($method === 'GET') {
    $events = loadEvents($eventsFile);
    // Сортируем: предстоящие сначала
    usort($events, fn($a, $b) => ($a['isPast'] ?? false) <=> ($b['isPast'] ?? false));
    echo json_encode(['ok' => true, 'events' => $events, 'total' => count($events)]);
    exit;
}

// ── DELETE: удалить ────────────────────────────────────────────
if ($method === 'DELETE' && $id) {
    $events  = loadEvents($eventsFile);
    $before  = count($events);
    $events  = array_filter($events, fn($e) => ($e['id'] ?? '') !== $id);
    $removed = $before - count($events);
    saveEvents($events, $eventsFile);
    echo json_encode(['ok' => true, 'removed' => $removed]);
    exit;
}

// ── PATCH: toggle isPast ───────────────────────────────────────
if ($method === 'PATCH' && $id) {
    $events = loadEvents($eventsFile);
    foreach ($events as &$ev) {
        if (($ev['id'] ?? '') === $id) {
            $ev['isPast'] = !($ev['isPast'] ?? false);
            break;
        }
    }
    saveEvents($events, $eventsFile);
    echo json_encode(['ok' => true]);
    exit;
}

// ── POST: добавить/обновить ────────────────────────────────────
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;

    $evId    = preg_replace('/[^a-z0-9_\-]/i', '', $input['id'] ?? '');
    $title   = trim($input['title'] ?? '');
    $month   = trim($input['month'] ?? '');
    $days    = trim($input['days']  ?? '');
    $city    = strtoupper(trim($input['city'] ?? ''));
    $desc    = trim($input['desc']  ?? '');
    $link    = trim($input['link']  ?? '#');
    $isPast  = (bool)($input['isPast'] ?? false);
    $tagsRaw = $input['tags'] ?? [];

    if (!$title) {
        http_response_code(400);
        echo json_encode(['error' => 'Нужен заголовок']);
        exit;
    }

    if (!$evId) {
        $evId = 'ev_' . substr(md5($title . $link), 0, 8);
    }

    // Нормализуем теги
    $tags = [];
    if (is_string($tagsRaw)) {
        // "BIM,ТИМ,Спикер" → массив
        foreach (array_filter(array_map('trim', explode(',', $tagsRaw))) as $t) {
            $tags[] = ['name' => $t, 'type' => strtolower($t) === 'спикер' ? 'speak' : 'normal'];
        }
    } elseif (is_array($tagsRaw)) {
        $tags = $tagsRaw;
    }

    $newEvent = [
        'id'     => $evId,
        'month'  => $month,
        'days'   => $days,
        'title'  => $title,
        'city'   => $city,
        'desc'   => $desc,
        'tags'   => $tags,
        'link'   => $link,
        'isPast' => $isPast,
    ];

    $events = loadEvents($eventsFile);

    // Обновление если id уже есть
    $found = false;
    foreach ($events as &$ev) {
        if (($ev['id'] ?? '') === $evId) {
            $ev    = $newEvent;
            $found = true;
            break;
        }
    }
    if (!$found) $events[] = $newEvent;

    saveEvents($events, $eventsFile);
    echo json_encode(['ok' => true, 'id' => $evId, 'message' => $found ? 'Обновлено' : 'Добавлено']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
