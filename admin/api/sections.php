<?php
/**
 * /admin/api/sections.php
 * API для динамического управления разделами всех подсайтов
 * Вызывается из админки (?tab=sections) и редактора статей (fetch при смене подсайта)
 */

require_once __DIR__ . '/../config.php';
session_start();

// Проверка доступа
if (empty($_SESSION['admin'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Доступ запрещён']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$sectionsFile = __DIR__ . '/../sections.json';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --- GET: получить разделы для конкретного подсайта ---
if ($method === 'GET') {
    $site = preg_replace('/[^a-z0-9_]/', '', $_GET['site'] ?? 'main');
    
    if (!file_exists($sectionsFile)) {
        echo json_encode([]);
        exit;
    }
    
    $content = @file_get_contents($sectionsFile);
    if ($content === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Не удалось прочитать файл разделов']);
        exit;
    }
    
    $data = json_decode($content, true);
    if (!is_array($data)) {
        http_response_code(500);
        echo json_encode(['error' => 'Некорректный формат файла разделов']);
        exit;
    }
    
    // Возвращаем разделы для запрошенного подсайта или пустой массив
    echo json_encode($data[$site] ?? [], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- POST: сохранить разделы для всех подсайтов ---
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (!$input || !is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Некорректные входные данные']);
        exit;
    }
    
    require_once __DIR__ . '/../../lib/sites.php';
// Фильтруем: только разрешённые подсайты, только строки
    $clean = [];
    $allowedSites = get_dynamic_sites();
    
    foreach ($allowedSites as $site) {
        if (isset($input[$site]) && is_array($input[$site])) {
            $clean[$site] = array_values(array_filter(
                array_map('trim', $input[$site]),
                function($v) {
                    return $v !== '' && is_string($v);
                }
            ));
        }
    }
    
    // Сохраняем в файл с блокировкой
    $jsonData = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $bytesWritten = @file_put_contents($sectionsFile, $jsonData, LOCK_EX);
    
    if ($bytesWritten !== false) {
        echo json_encode(['ok' => true, 'message' => 'Разделы сохранены']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Не удалось записать файл разделов']);
    }
    exit;
}

// Метод не поддерживается
http_response_code(405);
echo json_encode(['error' => 'Метод не поддерживается']);
