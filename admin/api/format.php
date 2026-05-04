<?php
declare(strict_types=1);
error_reporting(0);
ini_set('display_errors', 0);
/**
 * /admin/api/format.php
 * AJAX-эндпоинт для автоформатирования текста через OpenRouter.
 * Берет выделенный текст, получает актуальный OpenRouter Key из БД,
 * берет промпт технического редактора и форматирует текст в чистый Markdown.
 */

require_once __DIR__ . '/../../config.php';
session_start();

// Проверка авторизации
if (empty($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещён']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Получаем текст
$text = $_POST['text'] ?? '';
if (mb_strlen($text) < 50) {
    echo json_encode(['error' => 'Текст слишком короткий (минимум 50 символов).']);
    exit;
}

// --- 1. Получение OpenRouter Key из БД ---
require_once __DIR__ . '/../../digest/core/Db.php';
require_once __DIR__ . '/../../digest/core/Config.php';

$encryptionKey = defined('ENCRYPTION_KEY') && strlen(ENCRYPTION_KEY) >= 32 
    ? ENCRYPTION_KEY 
    : (defined('ADMIN_PASSWORD') ? hash('sha256', ADMIN_PASSWORD) : 'fallback_key_12345678901234567890123456789012');

function decryptValue(string $encrypted): string {
    global $encryptionKey;
    $data = base64_decode($encrypted);
    $ivLength = openssl_cipher_iv_length('AES-256-CBC');
    $iv = substr($data, 0, $ivLength);
    $ciphertext = substr($data, $ivLength);
    return openssl_decrypt($ciphertext, 'AES-256-CBC', $encryptionKey, 0, $iv);
}

$apiKey = '';
try {
    $db = \Db::getInstance();
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare("SELECT key_value, encrypted FROM admin_settings WHERE key_name = 'openrouter_key'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $apiKey = $row['encrypted'] ? decryptValue($row['key_value']) : $row['key_value'];
    }
} catch (Throwable $e) {
    // ignore, fallback to config
}

// Если в БД нет, пробуем из конфига
if (empty($apiKey) && defined('OPENROUTER_KEY')) {
    $apiKey = OPENROUTER_KEY;
}

if (empty($apiKey)) {
    echo json_encode(['error' => 'OpenRouter Key не найден. Добавьте его в настройках.']);
    exit;
}

// --- 2. Получение промпта технического редактора ---
$systemPrompt = "Ты — профессиональный технический редактор. Твоя задача — отформатировать входной текст в чистый Markdown.\n\nПРАВИЛА:\n1. Используй заголовки (#, ##, ###) для структуры разделов.\n2. Выделяй жирным (**текст**) ключевые термины, названия и важные мысли.\n3. Превращай перечисления в маркированные (- ) или нумерованные (1. ) списки.\n4. Оборачивай код, команды и пути в бэктики (`код` или ```язык ... ```).\n5. Исправляй пунктуацию и орфографию, если видишь явные ошибки.\n6. НЕ меняй смысл исходного текста.\n7. НЕ добавляй вступлений или заключений.\n8. Верни ТОЛЬКО отформатированный Markdown, без пояснений.";

// Пытаемся загрузить кастомный промпт из ai_prompts.json
$promptsFile = __DIR__ . '/../../config/ai_prompts.json';
if (file_exists($promptsFile)) {
    $prompts = json_decode(file_get_contents($promptsFile), true);
    if (is_array($prompts)) {
        foreach ($prompts as $p) {
            if (($p['id'] ?? '') === 'tech_editor' && !empty($p['system_prompt'])) {
                $systemPrompt = $p['system_prompt'];
                break;
            }
        }
    }
}

// --- 3. Вызов OpenRouter API ---
$model = 'openrouter/free'; // можно взять из БД или промпта
if (isset($prompts) && !empty($p['model'])) {
    $model = $p['model'];
}

$fullPrompt = $systemPrompt . "\n\nИсходный текст:\n" . $text;

$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'HTTP-Referer: https://chernetchenko.pro',
        'X-Title: FormatBot'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => $fullPrompt]]
    ]),
    CURLOPT_TIMEOUT => 30
]);
$resp = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['error' => 'Ошибка cURL: ' . $err]);
    exit;
}

$data = json_decode($resp, true);
if (isset($data['error'])) {
    echo json_encode(['error' => 'Ошибка API: ' . ($data['error']['message'] ?? 'Unknown')]);
    exit;
}

$formatted = $data['choices'][0]['message']['content'] ?? '';
// Очистка от markdown-оберток
$formatted = preg_replace('/^```(?:markdown)?\s*/i', '', $formatted);
$formatted = preg_replace('/\s*```$/ ', '', $formatted);
$formatted = trim($formatted);

if (empty($formatted)) {
    echo json_encode(['error' => 'ИИ вернул пустой ответ']);
    exit;
}

echo json_encode(['formatted' => $formatted]);