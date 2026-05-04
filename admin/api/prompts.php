<?php
declare(strict_types=1);
/**
 * /admin/api/prompts.php
 * Управление ИИ-промптами (чтение и запись)
 * Хранение в config/ai_prompts.json
 */

require_once __DIR__ . '/../../config.php';
session_start();

// Проверка авторизации
if (empty($_SESSION['admin'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Доступ запрещён']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$configDir = __DIR__ . '/../../config';
$promptsFile = $configDir . '/ai_prompts.json';

// Убедимся, что директория существует
if (!is_dir($configDir)) {
    @mkdir($configDir, 0755, true);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Чтение промптов
    if (file_exists($promptsFile)) {
        $content = file_get_contents($promptsFile);
        $prompts = json_decode($content, true);
        if (!is_array($prompts)) {
            $prompts = [];
        }
    } else {
        $prompts = [];
    }
    echo json_encode(['ok' => true, 'prompts' => $prompts]);
    exit;
}

if ($method === 'POST') {
    // Сохранение промптов
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Некорректные данные']);
        exit;
    }

    // Валидация структуры
    $cleanPrompts = [];
    foreach ($input as $prompt) {
        if (!is_array($prompt)) continue;
        $id = preg_replace('/[^a-z0-9_-]/', '', strtolower($prompt['id'] ?? ''));
        if (empty($id)) continue;
        $name = trim($prompt['name'] ?? '');
        $model = $prompt['model'] ?? 'openrouter/free';
        $systemPrompt = trim($prompt['system_prompt'] ?? '');
        $temperature = floatval($prompt['temperature'] ?? 0.7);
        if ($temperature < 0) $temperature = 0;
        if ($temperature > 2) $temperature = 2;
        
        $cleanPrompts[] = [
            'id' => $id,
            'name' => $name,
            'model' => $model,
            'system_prompt' => $systemPrompt,
            'temperature' => $temperature
        ];
    }

    $jsonData = json_encode($cleanPrompts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (@file_put_contents($promptsFile, $jsonData, LOCK_EX) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка записи файла']);
        exit;
    }

    echo json_encode(['ok' => true, 'message' => 'Промпты сохранены', 'count' => count($cleanPrompts)]);
    exit;
}

if ($method === 'DELETE') {
    // Удаление промпта (по ID)
    $id = $_GET['id'] ?? '';
    if (empty($id) || !file_exists($promptsFile)) {
        http_response_code(400);
        echo json_encode(['error' => 'Неверный ID']);
        exit;
    }
    $prompts = json_decode(file_get_contents($promptsFile), true) ?: [];
    $prompts = array_filter($prompts, fn($p) => ($p['id'] ?? '') !== $id);
    $prompts = array_values($prompts);
    $jsonData = json_encode($prompts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($promptsFile, $jsonData, LOCK_EX);
    echo json_encode(['ok' => true, 'message' => 'Промпт удалён']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Метод не поддерживается']);