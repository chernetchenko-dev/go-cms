<?php
require_once __DIR__ . '/../config.php';
session_start();
if (empty($_SESSION['admin'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Доступ запрещён']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

$site = preg_replace('/[^a-z]/', '', $_GET['site'] ?? 'fun');
$linksFile = __DIR__ . '/../links.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $all = file_exists($linksFile) ? json_decode(file_get_contents($linksFile), true) : [];
    $all[$site] = $data['links'] ?? [];
    file_put_contents($linksFile, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['ok' => true]);
    exit;
}

$all = file_exists($linksFile) ? json_decode(file_get_contents($linksFile), true) : [];
echo json_encode($all[$site] ?? []);