<?php
declare(strict_types=1);
error_reporting(0);
ini_set('display_errors', 0);
/**
 * /admin/api/layout.php
 * API для управления структурой главной страницы (main_layout.json).
 *
 * Методы:
 * GET  — возвращает текущую структуру JSON (или дефолт, если файла нет).
 * POST — сохраняет обновленную структуру JSON.
 *
 * Доступ: Только для авторизованных администраторов.
 */

require_once __DIR__ . '/../config.php';
session_start();

// --- ПРОВЕРКА АВТОРИЗАЦИИ ---
if (empty($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// --- ПУТИ ---
$configDir  = __DIR__ . '/../config';
$layoutFile = $configDir . '/main_layout.json';

// --- DEFAUL LAYOUT (Fallback) ---
function getDefaultLayout(): array {
    return [
        'meta' => [
            'site_id'     => 'main',
            'title'       => 'Олег Чернетченко — Проектирование, BIM, ИИ, Управление',
            'description' => 'Директор по проектированию. BIM, RAG, ИИ в строительстве без хайпа. Теория ограничений, ПИР-калькуляторы, нетворкинг.',
            'keywords'    => 'BIM, RAG, ИИ, проектирование, ТОС, ГИП, chernetchenko, CRAG, LRAG',
            'canonical'   => 'https://chernetchenko.pro/',
        ],
        'hero' => [
            'title_line1'    => 'Стройка, проекты, код,',
            'title_line2'    => 'ИИ, мемы.',
            'subtitle'       => 'Олег Чернетченко · ТИМ разработчик · Директор по проектированию',
            'description'    => 'Разбираю BIM, RAG, ИИ и Теорию ограничений без маркетинговой воды. Практика, калькуляторы, инструменты и живой нетворкинг. 15 лет в проектировании крупных объектов.',
        ],
        'about' => [
            'title'   => '👤 Обо мне',
            'visible' => true,
            'columns' => [
                [
                    'icon'  => '🧠',
                    'title' => 'Интересы',
                    'items' => [
                        'ТИМ/BIM моделирование и координация',
                        'RAG и CRAG: нейросети по нормам',
                        'Автоматизация (Python, pyRevit, MCP)',
                        'Управление проектами и ТОС',
                    ],
                ],
                [
                    'icon'  => '🚀',
                    'title' => 'Проекты экосистемы',
                    'items_links' => [
                        ['text' => 'WAF: Прикладной ИИ', 'url' => 'https://waf.chernetchenko.pro'],
                        ['text' => 'TOC: Калькулятор ПИР', 'url' => 'https://toc.chernetchenko.pro'],
                        ['text' => 'FUN: Лаборатория', 'url' => 'https://fun.chernetchenko.pro'],
                        ['text' => 'Дайджест BIM & AI', 'url' => '/digest/'],
                    ],
                ],
                [
                    'icon'  => '📬',
                    'title' => 'Контакты',
                    'items_links' => [
                        ['text' => 'Санкт-Петербург, Россия', 'url' => ''],
                        ['text' => '+7 (921) 774-70-90', 'url' => 'tel:+79217747090'],
                        ['text' => 'oleg@chernetchenko.ru', 'url' => 'mailto:oleg@chernetchenko.ru'],
                        ['text' => 'Telegram: @chernetchenko', 'url' => 'https://t.me/chernetchenko'],
                        ['text' => 'Канал: @wearefired', 'url' => 'https://t.me/wearefired'],
                        ['text' => 'Открыт к коллаборациям и выступлениям', 'url' => ''],
                    ],
                ],
            ],
        ],
        'nav_chips' => [
            ['id' => 'about', 'label' => '👤 Обо мне', 'link' => '#about'],
            ['id' => 'dev', 'label' => '🛠 Мои разработки', 'link' => '#dev'],
            ['id' => 'net', 'label' => '🤝 Нетворкинг', 'link' => '#net'],
            ['id' => 'tools', 'label' => '📦 Полезное', 'link' => '#tools'],
            ['id' => 'articles', 'label' => '📰 Статьи', 'link' => '#articles'],
        ],
        'sections' => [
            [
                'id'      => 'dev',
                'type'    => 'dev_grid',
                'title'   => '🛠 Мои разработки',
                'visible' => true,
                'cards'   => [
                    [
                        'tag'        => 'RAG / CRAG / LRAG',
                        'title'      => 'Генерация с дополненной выборкой',
                        'desc'       => 'Что это, зачем и почему оно вам точно надо. От простого к сложному.',
                        'url'        => 'https://rag.chernetchenko.pro',
                        'target'     => '_blank',
                        'color_class'=> 'c-rag',
                    ],
                    [
                        'tag'        => 'Прикладной ИИ',
                        'title'      => 'ИИ в строительстве без магии',
                        'desc'       => 'Обучение, практика, кейсы. Портал для инженеров от практиков.',
                        'url'        => 'https://waf.chernetchenko.pro',
                        'target'     => '_blank',
                        'color_class'=> 'c-waf',
                    ],
                    [
                        'tag'        => 'Теория ограничений',
                        'title'      => 'Расчёты и управление проектами',
                        'desc'       => 'Про ТОС, экономику и Элияху Голдратта. Калькуляторы ПИР.',
                        'url'        => 'https://toc.chernetchenko.pro',
                        'target'     => '_blank',
                        'color_class'=> 'c-toc',
                    ],
                    [
                        'tag'        => 'Лаборатория',
                        'title'      => 'Лаборатория приколов',
                        'desc'       => 'Инженерный нуар, игры, инструменты и немного сатиры про проектную рутину.',
                        'url'        => 'https://fun.chernetchenko.pro',
                        'target'     => '_blank',
                        'color_class'=> 'c-fun',
                    ],
                ],
            ],
            [
                'id'      => 'net',
                'type'    => 'net_grid',
                'title'   => '🤝 Общение с естественным разумом',
                'visible' => true,
                'cards'   => [
                    ['title' => '🎤 Мои выступления', 'desc' => 'СПбГАСУ, 100+ TechnoBuild, Legko BIM, доклады и мастер-классы', 'url' => '/speech.php'],
                    ['title' => '🤝 Инженерия кулуаров', 'desc' => 'Контакты, коллаборации, обмен опытом и поиск решений', 'url' => '/networking.php'],
                    ['title' => '📰 Дайджест', 'desc' => 'BIM & AI новости. AI-only сбор без RSS. Сводки каждый день.', 'url' => '/digest/'],
                ],
            ],
            [
                'id'      => 'tools',
                'type'    => 'tool_box',
                'title'   => '📦 Полезное',
                'visible' => true,
                'content' => [
                    'headline' => 'IFC просмотрщик онлайн',
                    'version'  => 'BIM AI Viewer v7.2 "Federated-BIM"',
                    'desc'     => 'Открывай тяжелые модели прямо в браузере. Без установки софта.',
                    'url'      => '/bim/',
                    'btn_text' => 'Открыть просмотрщик →',
                ],
            ],
            [
                'id'      => 'articles',
                'type'    => 'cms_loop',
                'title'   => '📰 О чём мы дискутируем',
                'visible' => true,
                'cms_params' => [
                    'site'           => 'main',
                    'section'        => '',
                    'include_drafts' => false,
                    'limit'          => 0, // 0 = без лимита
                ],
            ],
        ],
    ];
}

// --- ВАЛИДАЦИЯ И ОЧИСТКА ВХОДНЫХ ДАННЫХ ---
function sanitizeLayoutData(array $data): array {
    // Очистка мета-данных
    if (isset($data['meta'])) {
        $data['meta']['title']       = strip_tags(trim($data['meta']['title'] ?? ''));
        $data['meta']['description'] = strip_tags(trim($data['meta']['description'] ?? ''));
        $data['meta']['keywords']    = strip_tags(trim($data['meta']['keywords'] ?? ''));
        // Валидация URL canonical
        if (!empty($data['meta']['canonical'])) {
            $data['meta']['canonical'] = filter_var(trim($data['meta']['canonical']), FILTER_VALIDATE_URL) ?: 'https://chernetchenko.pro/';
        }
    }

    // Очистка Hero
    if (isset($data['hero'])) {
        $data['hero']['title_line1'] = strip_tags(trim($data['hero']['title_line1'] ?? ''));
        $data['hero']['title_line2'] = strip_tags(trim($data['hero']['title_line2'] ?? ''));
        $data['hero']['subtitle']    = strip_tags(trim($data['hero']['subtitle'] ?? ''));
        $data['hero']['description'] = strip_tags(trim($data['hero']['description'] ?? ''));
    }

    // Очистка About
    if (isset($data['about']['columns'])) {
        foreach ($data['about']['columns'] as $idx => $col) {
            $data['about']['columns'][$idx]['title'] = strip_tags(trim($col['title'] ?? ''));
            if (isset($col['items'])) {
                foreach ($col['items'] as $k => $item) {
                    $data['about']['columns'][$idx]['items'][$k] = strip_tags(trim($item));
                }
            }
            if (isset($col['items_links'])) {
                foreach ($col['items_links'] as $k => $link) {
                    $data['about']['columns'][$idx]['items_links'][$k]['text'] = strip_tags(trim($link['text'] ?? ''));
                    $url = trim($link['url'] ?? '');
                    $data['about']['columns'][$idx]['items_links'][$k]['url'] = $url && filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
                }
            }
        }
    }

    // Очистка секций и карточек
    if (isset($data['sections'])) {
        foreach ($data['sections'] as $sIdx => $sec) {
            $data['sections'][$sIdx]['title'] = strip_tags(trim($sec['title'] ?? ''));
            $data['sections'][$sIdx]['visible'] = !empty($sec['visible']);

            // Карточки (для dev_grid, net_grid)
            if (isset($sec['cards'])) {
                foreach ($sec['cards'] as $cIdx => $card) {
                    $data['sections'][$sIdx]['cards'][$cIdx]['tag']  = strip_tags(trim($card['tag'] ?? ''));
                    $data['sections'][$sIdx]['cards'][$cIdx]['title'] = strip_tags(trim($card['title'] ?? ''));
                    $data['sections'][$sIdx]['cards'][$cIdx]['desc']  = strip_tags(trim($card['desc'] ?? ''));
                    
                    $cardUrl = trim($card['url'] ?? '');
                    // Пропускаем URL, если это якорь или валидный абсолютный/относительный путь
                    if ($cardUrl && !filter_var($cardUrl, FILTER_VALIDATE_URL)) {
                        if (!str_starts_with($cardUrl, '/') && !str_starts_with($cardUrl, '#')) {
                             $cardUrl = ''; // Сброс, если не URL и не путь
                        }
                    }
                    $data['sections'][$sIdx]['cards'][$cIdx]['url'] = $cardUrl;
                    $data['sections'][$sIdx]['cards'][$cIdx]['target'] = ($card['target'] ?? '_self') === '_blank' ? '_blank' : '_self';
                    $data['sections'][$sIdx]['cards'][$cIdx]['color_class'] = preg_replace('/[^a-z0-9\-_]/i', '', $card['color_class'] ?? '');
                }
            }

            // Контент Tool Box
            if (isset($sec['content'])) {
                $data['sections'][$sIdx]['content']['headline'] = strip_tags(trim($sec['content']['headline'] ?? ''));
                $data['sections'][$sIdx]['content']['version']  = strip_tags(trim($sec['content']['version'] ?? ''));
                $data['sections'][$sIdx]['content']['desc']     = strip_tags(trim($sec['content']['desc'] ?? ''));
                $data['sections'][$sIdx]['content']['btn_text'] = strip_tags(trim($sec['content']['btn_text'] ?? ''));
                
                $toolUrl = trim($sec['content']['url'] ?? '');
                $data['sections'][$sIdx]['content']['url'] = ($toolUrl && (str_starts_with($toolUrl, '/') || filter_var($toolUrl, FILTER_VALIDATE_URL))) ? $toolUrl : '/';
            }
        }
    }

    return $data;
}

// --- ОБРАБОТКА ЗАПРОСОВ ---

// GET: Чтение конфигурации
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($layoutFile)) {
        $json = file_get_contents($layoutFile);
        $data = json_decode($json, true);
        if (is_array($data) && !empty($data)) {
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
    }
    // Fallback
    echo json_encode(getDefaultLayout(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// POST: Сохранение конфигурации
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data  = json_decode($input, true);

    if (!$data || !is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Неверный формат JSON'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Валидация обязательных ключей
    $required = ['meta', 'hero', 'sections'];
    foreach ($required as $key) {
        if (!isset($data[$key])) {
            http_response_code(400);
            echo json_encode(['error' => "Отсутствует обязательное поле: $key"], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Очистка данных
    $cleanData = sanitizeLayoutData($data);

    // Создание папки config, если её нет
    if (!is_dir($configDir)) {
        @mkdir($configDir, 0755, true);
    }

    // Запись в файл
    $jsonOutput = json_encode($cleanData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (@file_put_contents($layoutFile, $jsonOutput, LOCK_EX) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка записи файла. Проверьте права на config/'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => true, 'message' => 'Конфигурация главной сохранена'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Метод не поддерживается
http_response_code(405);
echo json_encode(['error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);