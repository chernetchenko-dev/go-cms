<?php
declare(strict_types=1);
error_reporting(0);
ini_set('display_errors', 0);
/**
 * /admin/api/settings.php
 * Управление глобальными настройками и API-ключами (с шифрованием AES-256)
 * Использует digest/core/Db.php для работы с БД
 */

require_once __DIR__ . '/../config.php';
session_start();



// Строгая проверка авторизации
if (empty($_SESSION['admin'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Доступ запрещён']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../digest/core/Db.php';
require_once __DIR__ . '/../../digest/core/Config.php';

// Определение ключа шифрования (фоллбек на базе пароля админа)
$fallbackKey = defined('ADMIN_PASSWORD') ? hash('sha256', ADMIN_PASSWORD) : 'fallback_key';
$encryptionKey = defined('ENCRYPTION_KEY') && strlen(ENCRYPTION_KEY) >= 32 ? ENCRYPTION_KEY : $fallbackKey;

// AES-256-CBC шифрование
function encryptValue(string $value): string {
    global $encryptionKey;
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
    $encrypted = openssl_encrypt($value, 'AES-256-CBC', $encryptionKey, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decryptValue(string $encrypted): string {
    global $encryptionKey;
    $data = base64_decode($encrypted);
    $ivLength = openssl_cipher_iv_length('AES-256-CBC');
    $iv = substr($data, 0, $ivLength);
    $ciphertext = substr($data, $ivLength);
    return openssl_decrypt($ciphertext, 'AES-256-CBC', $encryptionKey, 0, $iv);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
        $db = \Db::getInstance();
    $pdo = $db->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Инициализация таблицы, если нет
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        key_name VARCHAR(100) UNIQUE NOT NULL,
        key_value TEXT,
        encrypted BOOLEAN DEFAULT 0,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    if ($method === 'GET') {
        // Чтение настроек (расшифровываем зашифрованные)
        $stmt = $pdo->query("SELECT key_name, key_value, encrypted, description FROM admin_settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = $row['encrypted'] ? decryptValue($row['key_value']) : $row['key_value'];
            $settings[$row['key_name']] = [
                'value' => $value,
                'encrypted' => (bool)$row['encrypted'],
                'description' => $row['description']
            ];
        }
        echo json_encode(['ok' => true, 'settings' => $settings]);
        exit;
    }

    if ($method === 'POST') {
        // Сохранение настроек
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Некорректный JSON']);
            exit;
        }

        $allowedKeys = ['openrouter_key', 'openrouter_model', 'github_token', 'telegram_token', 'digest_admin_pass'];
        $saved = 0;

        foreach ($input as $key => $data) {
            if (!in_array($key, $allowedKeys)) continue;
            
            $value = trim($data['value'] ?? '');
            $encrypted = (bool)($data['encrypted'] ?? false);
            
            if ($value === '') continue;

            $storedValue = $encrypted ? encryptValue($value) : $value;
            
            $stmt = $pdo->prepare("INSERT INTO admin_settings (key_name, key_value, encrypted, description) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE key_value = VALUES(key_value), encrypted = VALUES(encrypted), updated_at = NOW()");
            
            $description = match($key) {
                'openrouter_key' => 'OpenRouter API Key',
                'openrouter_model' => 'OpenRouter Model',
                'github_token' => 'GitHub Personal Access Token',
                'telegram_token' => 'Telegram Bot Token',
                'digest_admin_pass' => 'Digest Admin Password',
                default => $key
            };
            
            $stmt->execute([$key, $storedValue, $encrypted ? 1 : 0, $description]);
            $saved++;
        }

        echo json_encode(['ok' => true, 'message' => "Сохранено настроек: $saved"]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сервера: ' . $e->getMessage()]);
}