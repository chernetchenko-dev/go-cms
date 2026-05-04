<?php
declare(strict_types=1);
error_reporting(0);
ini_set('display_errors', 0);
/**
 * /admin/api/list_articles.php
 * Возвращает древо файлов (Markdown + PHP) для Глобального Проводника.
 * Использует динамический список сайтов (Engine Mode).
 * Для каждого сайта сканирует content/*.md и корень сайта для *.php (шаблоны).
 */
 
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/sites.php';
session_start();

if (empty($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещён']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$sites = get_dynamic_sites();
$result = [];

// Базовый путь проекта
$projectBase = dirname(dirname(dirname(__DIR__))); // /Users/chernetchenko/Code/SITE_F

foreach ($sites as $site) {
    $siteRoot = '';
    $contentPath = '';
    
    if ($site === 'main') {
        $siteRoot = __DIR__ . '/../../'; // public_html
        $contentPath = __DIR__ . '/../../content';
    } else {
        $siteRoot = $projectBase . '/' . $site . '_chernetchenko_pro/public_html/';
        $contentPath = $siteRoot . 'content';
    }
    
    $articles = [];
    
    // 1. Markdown файлы (content/)
    if (is_dir($contentPath)) {
        $mdFiles = glob($contentPath . '/*.md') ?: [];
        foreach ($mdFiles as $file) {
            $filename = basename($file);
            $title = $filename;
            $content = @file_get_contents($file);
            if ($content && preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $m)) {
                if (preg_match('/title:\s*"([^"]+)"/', $m[1], $t)) {
                    $title = $t[1];
                }
            }
            $articles[] = [
                'file' => $filename,
                'title' => $title,
                'type' => 'md',
                'marker' => '[MD]',
                'path' => $file
            ];
        }
    }
    
    // 2. PHP файлы шаблонов (корень сайта)
    if (is_dir($siteRoot)) {
        $phpFiles = glob($siteRoot . '*.php') ?: [];
        $excludePhp = ['config.php', 'admin.php', 'header.php', 'footer.php', 'index.php']; // исключаем системные
        foreach ($phpFiles as $file) {
            $filename = basename($file);
            if (in_array($filename, $excludePhp)) continue;
            $articles[] = [
                'file' => $filename,
                'title' => $filename,
                'type' => 'php',
                'marker' => '[PHP]',
                'path' => $file
            ];
        }
    }
    
    $result[] = [
        'site' => $site,
        'articles' => $articles
    ];
}

echo json_encode(['ok' => true, 'tree' => $result]);
