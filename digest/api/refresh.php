<?php
declare(strict_types=1);
require_once __DIR__ . '/../core/Config.php';
if (!defined('DIGEST_ACCESS')) { http_response_code(403); exit; }

$token = $_GET['token'] ?? '';
if (!hash_equals(DIGEST_CRON_TOKEN, $token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
    exit;
}

header('Content-Type: application/json');
$cmd    = sprintf('php %s/collector.php > /dev/null 2>&1 & echo $!', __DIR__);
$output = shell_exec($cmd);
$pid    = (int)trim($output ?: '0');

echo json_encode(['status' => 'ok', 'message' => 'Collector started', 'pid' => $pid]);