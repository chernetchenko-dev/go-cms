<?php
declare(strict_types=1);
error_reporting(0);
ini_set('display_errors', 0);
/**
 * admin/api/sources.php
 * CRUD для таблицы digest_sources (источники дайджеста).
 *
 * GET    /admin/api/sources.php           — список всех источников
 * POST   /admin/api/sources.php           — добавить источник
 * DELETE /admin/api/sources.php?id=N      — удалить
 * PATCH  /admin/api/sources.php?id=N      — toggle active
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../digest/core/Config.php';
require_once __DIR__ . '/../../digest/core/Db.php';
session_start();

if (empty($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    $db  = Db::getInstance();
    $db->initTables();
    $pdo = $db->getConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$id     = (int)($_GET['id'] ?? 0);

// ── GET: список ────────────────────────────────────────────────
if ($method === 'GET') {
    $rows = $pdo->query("SELECT * FROM digest_sources ORDER BY category, name")->fetchAll();
    echo json_encode(['ok' => true, 'sources' => $rows]);
    exit;
}

// ── DELETE: удалить источник ───────────────────────────────────
if ($method === 'DELETE' && $id > 0) {
    $pdo->prepare("DELETE FROM digest_sources WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true, 'message' => 'Источник удалён']);
    exit;
}

// ── PATCH: переключить active ──────────────────────────────────
if ($method === 'PATCH' && $id > 0) {
    $pdo->prepare("UPDATE digest_sources SET active = NOT active WHERE id = ?")->execute([$id]);
    $row = $pdo->prepare("SELECT active FROM digest_sources WHERE id = ?");
    $row->execute([$id]);
    $active = (bool)$row->fetchColumn();
    echo json_encode(['ok' => true, 'active' => $active]);
    exit;
}

// ── POST: добавить источник ────────────────────────────────────
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) { $input = $_POST; }

    $name     = trim($input['name']     ?? '');
    $url      = trim($input['url']      ?? '');
    $category = trim($input['category'] ?? 'ai');
    $prompt   = trim($input['prompt']   ?? '');
    $active   = isset($input['active']) ? (int)(bool)$input['active'] : 1;

    // Валидация
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Неверный URL']);
        exit;
    }
    if (!in_array($category, ['ai', 'bim', 'events', 'norms'])) {
        $category = 'ai';
    }
    if (!$name) {
        $name = parse_url($url, PHP_URL_HOST) ?: $url;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO digest_sources (name, url, category, prompt, active)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $url, $category, $prompt ?: null, $active]);
        $newId = (int)$pdo->lastInsertId();
        echo json_encode(['ok' => true, 'id' => $newId, 'message' => "Источник «{$name}» добавлен"]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка сохранения: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
