<?php
declare(strict_types=1);
error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(90);
/**
 * admin/api/ai_chat.php
 * Универсальный AI-эндпоинт для вкладки "■ AI чат" в админке.
 * Принимает: { messages: [...], model_override?: string }
 * Ключ и модель читаются из admin_settings (MySQL), fallback — OPENROUTER_KEY.
 */

session_start();
if (empty($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => ['message' => 'Unauthorized']]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Читаем тело запроса
$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['messages']) || !is_array($input['messages'])) {
    echo json_encode(['error' => ['message' => 'Нужен массив messages']]);
    exit;
}

$messages      = $input['messages'];
$modelOverride = trim($input['model_override'] ?? '');

// ── Получаем ключ и модель ─────────────────────────────────
$apiKey = '';
$model  = 'openrouter/free';

try {
    require_once __DIR__ . '/../../digest/core/Config.php';
    require_once __DIR__ . '/../../digest/core/Db.php';
    $pdo = Db::getInstance()->getConnection();

    $keyRow = $pdo->query("SELECT key_value, encrypted FROM admin_settings WHERE key_name='openrouter_key'")->fetch();
    if ($keyRow && !empty($keyRow['key_value'])) {
        $raw = $keyRow['key_value'];
        if ($keyRow['encrypted'] && function_exists('openssl_decrypt')) {
            require_once __DIR__ . '/../config.php';
            $encKey = (defined('ENCRYPTION_KEY') && strlen(ENCRYPTION_KEY) >= 32)
                ? ENCRYPTION_KEY
                : (defined('ADMIN_PASSWORD') ? hash('sha256', ADMIN_PASSWORD) : '');
            $data   = base64_decode($raw);
            $ivLen  = openssl_cipher_iv_length('AES-256-CBC');
            $raw    = (string)openssl_decrypt(substr($data, $ivLen), 'AES-256-CBC', $encKey, 0, substr($data, 0, $ivLen));
        }
        $apiKey = $raw;
    }

    $modelRow = $pdo->query("SELECT key_value FROM admin_settings WHERE key_name='openrouter_model'")->fetch();
    if ($modelRow && !empty($modelRow['key_value'])) {
        $model = $modelRow['key_value'];
    }
} catch (Throwable) {}

// Fallback на константы из config.php
if (empty($apiKey) && defined('OPENROUTER_KEY'))   $apiKey = OPENROUTER_KEY;
if ($model === 'openrouter/free' && defined('OPENROUTER_MODEL')) $model = OPENROUTER_MODEL;

// Переопределение модели из запроса
if ($modelOverride) $model = $modelOverride;

if (empty($apiKey)) {
    echo json_encode(['error' => ['message' => 'Ключ OpenRouter не найден. Добавьте в Настройки → Ключи']]);
    exit;
}

// ── Запрос к OpenRouter ────────────────────────────────────
$payload = json_encode([
    'model'       => $model,
    'messages'    => $messages,
    'max_tokens'  => 1500,
    'temperature' => 0.3,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'HTTP-Referer: https://chernetchenko.pro',
        'X-Title: AdminAI',
    ],
    CURLOPT_POSTFIELDS     => $payload,
]);

$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['error' => ['message' => 'cURL: ' . $err]]);
    exit;
}

$data = json_decode($resp, true);
if ($code >= 400) {
    echo json_encode(['error' => ['message' => 'HTTP ' . $code . ': ' . ($data['error']['message'] ?? 'error')]]);
    exit;
}

// Добавляем explanation для совместимости с TOC-фронтом
if (isset($data['choices'][0]['message']['content'])) {
    $data['explanation'] = $data['choices'][0]['message']['content'];
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
