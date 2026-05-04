<?php
declare(strict_types=1);
error_reporting(0);
ini_set('display_errors', 0);
/**
 * admin/api/digest_action.php
 * Управление дайджестом из админки.
 *
 * Действия:
 *   run          — запустить сбор в фоне
 *   log          — прочитать лог
 *   clear_log    — очистить лог
 *   summary      — запустить daily_summary вручную
 *   status       — статус последнего запуска
 */
session_start();
if (empty($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$rootPath      = __DIR__ . '/../..';
$collectorPath = $rootPath . '/digest/api/collector.php';
$summaryPath   = $rootPath . '/digest/api/daily_summary.php';
$logPath       = $rootPath . '/digest/logs/scraper.log';
$lastRunLog    = $rootPath . '/digest/logs/last_run.log';
$statusFile    = $rootPath . '/digest/logs/status.json';

if (!is_dir(dirname($logPath))) @mkdir(dirname($logPath), 0777, true);

// Определяем php-бинарник: php8.5 на сервере, php локально
$phpCmd = is_executable('/usr/bin/php8.5') ? 'php8.5'
        : (is_executable('/usr/local/bin/php8.5') ? '/usr/local/bin/php8.5'
        : 'php');

switch ($action) {

    // ── Запустить сбор ─────────────────────────────────────────────
    case 'run':
        if (!file_exists($collectorPath)) {
            echo json_encode(['error' => 'collector.php не найден']);
            exit;
        }
        // Пишем статус "running"
        file_put_contents($statusFile, json_encode([
            'running'   => true,
            'started_at'=> date('Y-m-d H:i:s'),
            'pid'       => null,
        ]), LOCK_EX);

        $cmd = "nohup {$phpCmd} " . escapeshellarg($collectorPath)
             . " > " . escapeshellarg($lastRunLog) . " 2>&1 & echo $!";
        $pid = trim((string)shell_exec($cmd));

        // Обновляем PID
        file_put_contents($statusFile, json_encode([
            'running'   => true,
            'started_at'=> date('Y-m-d H:i:s'),
            'pid'       => $pid ?: null,
        ]), LOCK_EX);

        echo json_encode([
            'ok'      => true,
            'message' => 'Коллектор запущен (PID: ' . ($pid ?: '?') . '). Логи появятся через ~1 мин.',
            'pid'     => $pid,
        ]);
        break;

    // ── Запустить сводку вручную ───────────────────────────────────
    case 'summary':
        if (!file_exists($summaryPath)) {
            echo json_encode(['error' => 'daily_summary.php не найден']);
            exit;
        }
        $out = shell_exec("{$phpCmd} " . escapeshellarg($summaryPath) . " 2>&1");
        echo json_encode(['ok' => true, 'output' => trim($out ?: 'Запущено')]);
        break;

    // ── Лог ───────────────────────────────────────────────────────
    case 'log':
        $log = '';
        if (file_exists($logPath)) {
            $log = shell_exec("tail -n 100 " . escapeshellarg($logPath)) ?: '';
        } elseif (file_exists($lastRunLog)) {
            $log = file_get_contents($lastRunLog) ?: '';
            $log = "[last_run.log]\n" . $log;
        }

        // Проверяем жив ли процесс
        $status = ['running' => false];
        if (file_exists($statusFile)) {
            $status = json_decode(file_get_contents($statusFile), true) ?: [];
        }
        if (!empty($status['pid'])) {
            $alive = (bool)shell_exec("ps -p " . (int)$status['pid'] . " -o pid=");
            if (!$alive && $status['running']) {
                $status['running'] = false;
                file_put_contents($statusFile, json_encode($status), LOCK_EX);
            }
        }

        echo json_encode([
            'ok'      => true,
            'log'     => $log ?: 'Лог пуст. Запустите сбор.',
            'running' => $status['running'] ?? false,
        ]);
        break;

    // ── Статус ────────────────────────────────────────────────────
    case 'status':
        $status = ['running' => false, 'started_at' => null];
        if (file_exists($statusFile)) {
            $status = json_decode(file_get_contents($statusFile), true) ?: $status;
        }
        echo json_encode(['ok' => true, 'status' => $status]);
        break;

    // ── Очистить лог ──────────────────────────────────────────────
    case 'clear_log':
        if (file_exists($logPath))    file_put_contents($logPath, '');
        if (file_exists($lastRunLog)) file_put_contents($lastRunLog, '');
        echo json_encode(['ok' => true, 'message' => 'Лог очищен']);
        break;

    default:
        echo json_encode(['error' => 'Unknown action: ' . $action]);
}
