<?php
declare(strict_types=1);
/**
 * digest/collectors/ai_scraper.php v2.0
 *
 * Сбор новостей через AI (без RSS).
 * Источники берутся из БД (таблица digest_sources).
 * Мероприятия автоматически добавляются в events.json.
 *
 * Вызывается из digest/api/collector.php через runAiScraper().
 * Никакого прямого HTTP-доступа.
 */

if (!function_exists('runAiScraper')) {

function runAiScraper(bool $isDryRun = false): array
{
    $logFile = __DIR__ . '/../logs/scraper.log';
    if (!is_dir(dirname($logFile))) @mkdir(dirname($logFile), 0777, true);

    $log = function(string $msg) use ($logFile): void {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        if (php_sapi_name() === 'cli') echo $line;
    };

    $log("=== AI-SCRAPER СТАРТ (dry=" . ($isDryRun ? 'Y' : 'N') . ") ===");

    // ── БД и AiClient ──────────────────────────────────────────────
    try {
        $db  = Db::getInstance();
        $db->initTables();
        $pdo = $db->getConnection();
    } catch (Throwable $e) {
        $log("[ERR] БД: " . $e->getMessage());
        return [];
    }

    $ai = new AiClient($pdo);

    // ── Получаем ключ OpenRouter ───────────────────────────────────
    $apiKey = '';
    try {
        $row = $pdo->query("SELECT key_value, encrypted FROM admin_settings WHERE key_name='openrouter_key'")->fetch();
        if ($row) {
            $apiKey = $row['encrypted'] ? _digestDecrypt($row['key_value']) : $row['key_value'];
        }
    } catch (Throwable) {}

    if (empty($apiKey) && defined('OPENROUTER_KEY')) {
        $apiKey = OPENROUTER_KEY;
    }

    if (empty($apiKey)) {
        $log("[ERR] OpenRouter ключ не найден. Добавьте через Настройки → Ключи в админке.");
        return [];
    }

    $model = defined('OPENROUTER_MODEL') ? OPENROUTER_MODEL : 'openrouter/free';

    // ── Источники из БД ────────────────────────────────────────────
    $sources = _digestGetSources($pdo, $log);
    if (empty($sources)) {
        $log("[WARN] Нет активных источников. Добавьте через админку → Дайджест → Источники.");
        return [];
    }

    $log("[INFO] Источников: " . count($sources));

    $allItems = [];

    // ── Цикл по источникам ─────────────────────────────────────────
    foreach ($sources as $source) {
        $url      = $source['url'];
        $category = $source['category'] ?? 'ai';
        $srcName  = $source['name'] ?? parse_url($url, PHP_URL_HOST);
        $prompt   = $source['prompt'] ?? '';  // кастомный промпт источника

        $log("\n── Источник: [{$category}] {$srcName}");

        // Загружаем страницу
        $html = _digestFetch($url, $log);
        if (!$html) continue;

        // Извлекаем текстовый контент (убираем теги, скрипты, стили)
        $text = _digestExtractText($html);
        if (mb_strlen($text) < 100) {
            $log("[WARN] Слишком мало текста: " . mb_strlen($text) . " символов");
            continue;
        }

        // Обрезаем до ~3000 символов чтобы влезть в контекст
        $text = mb_substr($text, 0, 3000);

        // ── Промпт для AI ──────────────────────────────────────────
        $basePrompt = $prompt ?: _digestDefaultPrompt($category);

        $userMsg = $basePrompt . "\n\n"
            . "Источник: {$url}\n"
            . "Категория: {$category}\n"
            . "Текст страницы:\n"
            . $text . "\n\n"
            . "Верни ТОЛЬКО JSON-массив (не больше 5 элементов):\n"
            . "[{\"title\":\"...\",\"url\":\"...\",\"description\":\"...\",\"category\":\"" . $category . "\",\"is_event\":false}]\n"
            . "Поле is_event=true если это анонс конференции/форума/выставки.\n"
            . "Если ничего интересного — верни пустой массив [].";

        if ($isDryRun) {
            $log("[DRY] Пропускаем AI-запрос для: $srcName");
            continue;
        }

        $log("[AI] Запрос к {$model}...");
        $aiResp = _digestAskAI($apiKey, $model, $userMsg, $log);
        if (!$aiResp) continue;

        // Парсим ответ
        $items = _digestParseAIResponse($aiResp, $url, $category, $log);
        if (empty($items)) continue;

        $log("[OK] Получено: " . count($items) . " элементов");

        // Проверяем мероприятия и добавляем в events.json
        foreach ($items as $item) {
            if (!empty($item['is_event'])) {
                _digestAddToEvents($item, $log);
            }
        }

        $allItems = array_merge($allItems, $items);

        // Пауза между запросами чтобы не спамить
        sleep(2);
    }

    $log("\n=== AI-SCRAPER ФИНИШ. Всего: " . count($allItems) . " ===");
    return $allItems;
}

// ── Вспомогательные функции ────────────────────────────────────────

/**
 * Получаем источники из БД, fallback на sources.json
 */
function _digestGetSources(PDO $pdo, callable $log): array
{
    // Сначала пробуем БД
    try {
        $stmt = $pdo->query("SELECT * FROM digest_sources WHERE active = 1 ORDER BY category, name");
        $rows = $stmt->fetchAll();
        if (!empty($rows)) return $rows;
    } catch (Throwable $e) {
        $log("[WARN] digest_sources не найдена, пробуем sources.json: " . $e->getMessage());
    }

    // Fallback: читаем sources.json (старый формат)
    $jsonFile = __DIR__ . '/../core/sources.json';
    if (!file_exists($jsonFile)) return [];

    $raw = json_decode(file_get_contents($jsonFile), true) ?: [];
    $sources = [];
    foreach ($raw as $cat => $urls) {
        foreach ((array)$urls as $url) {
            if (!$url) continue;
            $sources[] = [
                'url'      => $url,
                'category' => $cat,
                'name'     => parse_url($url, PHP_URL_HOST),
                'prompt'   => '',
                'active'   => 1,
            ];
        }
    }
    return $sources;
}

/**
 * Загрузка URL с имитацией браузера
 */
function _digestFetch(string $url, callable $log): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_ENCODING       => '',  // принимаем gzip
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ru-RU,ru;q=0.9,en;q=0.8',
            'Cache-Control: no-cache',
        ],
    ]);
    $html = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        $log("[ERR] cURL: $err");
        return '';
    }
    if ($code >= 400) {
        $log("[WARN] HTTP $code для $url");
        return '';
    }
    return $html ?: '';
}

/**
 * Извлекаем читаемый текст из HTML
 */
function _digestExtractText(string $html): string
{
    // Убираем скрипты, стили, навигацию
    $html = preg_replace('/<script[^>]*>[\s\S]*?<\/script>/i', '', $html);
    $html = preg_replace('/<style[^>]*>[\s\S]*?<\/style>/i', '', $html);
    $html = preg_replace('/<nav[^>]*>[\s\S]*?<\/nav>/i', '', $html);
    $html = preg_replace('/<footer[^>]*>[\s\S]*?<\/footer>/i', '', $html);
    $html = preg_replace('/<header[^>]*>[\s\S]*?<\/header>/i', '', $html);

    // Конвертируем теги в пробелы/переносы
    $html = preg_replace('/<(h[1-6]|p|li|br|div|article)[^>]*>/i', "\n", $html);

    // Убираем все оставшиеся теги
    $text = strip_tags($html);

    // Чистим пробелы
    $text = preg_replace('/\s+/', ' ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    return trim($text);
}

/**
 * Дефолтный промпт по категории
 */
function _digestDefaultPrompt(string $category): string
{
    return match($category) {
        'bim'    => "Ты — редактор профессионального дайджеста для BIM-специалистов и проектировщиков. "
                  . "Извлеки из текста новости про BIM, ТИМ, Revit, IFC, строительство, проектирование. "
                  . "Игнорируй рекламу и офтоп.",
        'events' => "Ты — редактор дайджеста мероприятий для строительной отрасли. "
                  . "Ищи анонсы конференций, форумов, выставок, вебинаров по темам: BIM, ТИМ, строительство, проектирование, ИИ. "
                  . "Для каждого мероприятия укажи дату если есть. Всегда ставь is_event=true.",
        'norms'  => "Ты — редактор нормативного дайджеста. "
                  . "Ищи изменения в ГОСТ, СП, ФЗ, приказы Минстроя, Росстандарта, обновления нормативной базы. "
                  . "Игнорируй новости не связанные с нормативами.",
        default  => "Ты — редактор дайджеста по ИИ и технологиям для инженеров. "
                  . "Извлеки из текста важные новости про LLM, RAG, новые модели, автоматизацию, ИИ в строительстве. "
                  . "Игнорируй рекламу и офтоп.",
    };
}

/**
 * Запрос к OpenRouter
 */
function _digestAskAI(string $apiKey, string $model, string $prompt, callable $log): string
{
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: https://chernetchenko.pro',
            'X-Title: DigestCollector',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model'      => $model,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 1000,
            'temperature'=> 0.2,  // низкая температура = стабильный JSON
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) { $log("[ERR] AI cURL: $err"); return ''; }
    if ($code >= 400) { $log("[ERR] AI HTTP $code"); return ''; }

    $data    = json_decode($resp, true);
    $content = $data['choices'][0]['message']['content'] ?? '';

    if (empty($content)) {
        $errMsg = $data['error']['message'] ?? 'empty response';
        $log("[ERR] AI: $errMsg");
    }
    return $content;
}

/**
 * Парсим JSON-массив из ответа AI
 */
function _digestParseAIResponse(string $response, string $sourceUrl, string $category, callable $log): array
{
    // Ищем JSON-массив в ответе
    if (preg_match('/\[[\s\S]*?\]/m', $response, $m)) {
        $items = json_decode($m[0], true);
        if (is_array($items) && !empty($items)) {
            // Нормализуем и валидируем
            $result = [];
            foreach ($items as $item) {
                if (empty($item['title'])) continue;
                $url = $item['url'] ?? '';
                // Делаем URL абсолютным если относительный
                if ($url && !str_starts_with($url, 'http')) {
                    $parsed = parse_url($sourceUrl);
                    $url = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . '/' . ltrim($url, '/');
                }
                if (!$url) $url = $sourceUrl;

                $result[] = [
                    'title'       => mb_substr(strip_tags($item['title']), 0, 255),
                    'url'         => mb_substr($url, 0, 1024),
                    'description' => mb_substr(strip_tags($item['description'] ?? ''), 0, 500),
                    'source'      => parse_url($sourceUrl, PHP_URL_HOST),
                    'category'    => $item['category'] ?? $category,
                    'is_event'    => !empty($item['is_event']),
                    'event_date'  => $item['event_date'] ?? '',
                    'event_city'  => $item['event_city'] ?? '',
                ];
            }
            return $result;
        }
    }
    $log("[WARN] Не удалось распарсить JSON из ответа AI");
    return [];
}

/**
 * Добавляем мероприятие в events.json (если ещё нет)
 */
function _digestAddToEvents(array $item, callable $log): void
{
    $eventsFile = __DIR__ . '/../../events.json';
    $events = [];

    if (file_exists($eventsFile)) {
        $events = json_decode(file_get_contents($eventsFile), true) ?: [];
    }

    // Проверяем дубль по URL
    foreach ($events as $ev) {
        if (($ev['link'] ?? '') === $item['url']) return;
    }

    // Формируем slug из URL
    $slug = 'auto_' . substr(md5($item['url']), 0, 8);

    // Определяем дату
    $dateStr = $item['event_date'] ?? '';
    $month   = '';
    $days    = '';
    if ($dateStr) {
        // Пробуем распарсить дату
        $ts = strtotime($dateStr);
        if ($ts) {
            $months = ['', 'ЯНВАРЬ', 'ФЕВРАЛЬ', 'МАРТ', 'АПРЕЛЬ', 'МАЙ', 'ИЮНЬ',
                       'ИЮЛЬ', 'АВГУСТ', 'СЕНТЯБРЬ', 'ОКТЯБРЬ', 'НОЯБРЬ', 'ДЕКАБРЬ'];
            $month = $months[(int)date('n', $ts)];
            $days  = date('d', $ts);
        }
    }

    $newEvent = [
        'id'     => $slug,
        'month'  => $month ?: 'ТБД',
        'days'   => $days ?: '',
        'title'  => $item['title'],
        'city'   => strtoupper($item['event_city'] ?? 'RU'),
        'desc'   => mb_substr($item['description'] ?? '', 0, 200),
        'tags'   => [['name' => 'AI-анонс', 'type' => 'normal']],
        'link'   => $item['url'],
        'isPast' => false,
        'source' => 'digest_auto',  // маркер автодобавления
    ];

    $events[] = $newEvent;

    if (file_put_contents($eventsFile, json_encode($events, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX)) {
        $log("[EVENT] Добавлено мероприятие: " . $item['title']);
    } else {
        $log("[WARN] Не удалось записать events.json");
    }
}

/**
 * Расшифровка AES-256-CBC (копия из admin/api/settings.php)
 */
function _digestDecrypt(string $encrypted): string
{
    $key = defined('ENCRYPTION_KEY') && strlen(ENCRYPTION_KEY) >= 32
        ? ENCRYPTION_KEY
        : (defined('ADMIN_PASSWORD') ? hash('sha256', ADMIN_PASSWORD) : 'fallback_key');

    $data      = base64_decode($encrypted);
    $ivLength  = openssl_cipher_iv_length('AES-256-CBC');
    $iv        = substr($data, 0, $ivLength);
    $ciphertext= substr($data, $ivLength);
    return (string)openssl_decrypt($ciphertext, 'AES-256-CBC', $key, 0, $iv);
}

} // end if (!function_exists)
