<?php
if (!defined('DIGEST_ACCESS')) exit;

// === НАСТРОЙКИ ===
define('RSS_CACHE_DIR', __DIR__ . '/../cache/rss/');
define('RSS_CACHE_TTL', 7200); // 2 часа
if (!is_dir(RSS_CACHE_DIR)) mkdir(RSS_CACHE_DIR, 0755, true);

// === 1. ПАРАЛЛЕЛЬНАЯ ЗАГРУЗКА + КЭШ ===
function fetchRssMulti(array $urls): array {
    $mh = curl_multi_init();
    $handles = []; // [$ch] = $idx
    $results = [];

    foreach ($urls as $idx => $url) {
        $cacheFile = RSS_CACHE_DIR . md5($url) . '.xml';
        // Проверяем кэш
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < RSS_CACHE_TTL) {
            $results[$idx] = ['content' => file_get_contents($cacheFile), 'cached' => true, 'http' => 200];
            continue;
        }
        // Если нет в кэше — грузим
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => COLLECTOR_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'DigestBot/3.0 (RSS Aggregator)'
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$ch] = $idx; // Сохраняем дескриптор как ключ
    }

    // Выполняем мульти-запрос
    $running = null;
    do { curl_multi_exec($mh, $running); curl_multi_select($mh); } while ($running > 0);

    // Собираем ответы
    foreach ($handles as $ch => $idx) {
        $content = curl_multi_getcontent($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($code === 200 && !empty($content)) {
            // Сохраняем в кэш
            file_put_contents(RSS_CACHE_DIR . md5($urls[$idx]) . '.xml', $content);
            $results[$idx] = ['content' => $content, 'cached' => false, 'http' => $code];
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $results;
}

// === 2. ПАРСИНГ ОДНОГО ФИДА ===
function parseRssFeed(string $content, string $category, string $source): array {
    $items = [];
    $raw = trim($content, " \t\n\r\0\x0B\xEF\xBB\xBF");
    if (!preg_match('/<rss|<feed|<channel|<item/i', $raw)) return $items;

    // Отключаем варнинги парсера
    $prev = libxml_use_internal_errors(true);
    $xml  = @simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$xml) return $items;

    // Поддержка RSS 2.0 и Atom
    $entries = isset($xml->channel->item) ? $xml->channel->item : (isset($xml->entry) ? $xml->entry : []);

    foreach ($entries as $node) {
        // Извлекаем ссылку (Atom хранит в атрибуте, RSS в теге)
        $link = (string)($node->link['href'] ?? $node->link ?? '');
        $desc = (string)($node->description ?? $node->summary ?? $node->content ?? '');
        $date = (string)($node->pubDate ?? $node->published ?? $node->updated ?? '');

        // Нормализация
        $link = trim(preg_replace('/\#.*/', '', $link)); 
        if (empty($link) || empty((string)$node->title)) continue;

        $items[] = [
            'title'        => trim(html_entity_decode((string)$node->title, ENT_QUOTES, 'UTF-8')),
            'link'         => $link,
            'description'  => mb_substr(strip_tags(trim($desc)), 0, 300),
            'publish_date' => date('Y-m-d H:i:s', strtotime($date) ?: time()),
            'source'       => $source,
            'category'     => $category,
            'hash'         => md5(strtolower(trim($link)))
        ];
    }
    return $items;
}

// === 3. ГЛАВНЫЙ СБОРЩИК ===
function collectRssAll(): array {
    if (!defined('EVENTS_RSS') && !defined('BIM_RSS') && !defined('NORM_RSS')) return [];

    $urls  = [];
    $meta  = [];

    $addSource = function($type, $const) use (&$urls, &$meta) {
        if (!defined($const)) return;
        $list = json_decode(constant($const), true);
        if (!is_array($list)) return;
        foreach ($list as $u) {
            $idx = count($urls);
            $urls[$idx] = $u;
            $meta[$idx] = [
                'category' => $type,
                'source'   => match($type) {
                    'events' => 'Events-RF',
                    'bim'    => 'BIM-RF',
                    'norms'  => 'Нормы-РФ',
                    default  => 'RSS'
                }
            ];
        }
    };

    $addSource('events', 'EVENTS_RSS');
    $addSource('bim',    'BIM_RSS');
    $addSource('norms',  'NORM_RSS');

    if (empty($urls)) return [];

    $fetched = fetchRssMulti($urls);
    $all     = [];
    foreach ($fetched as $idx => $data) {
        $all = array_merge($all, parseRssFeed($data['content'], $meta[$idx]['category'], $meta[$idx]['source']));
    }

    // Быстрый дедуп в памяти по URL
    $seen = [];
    return array_values(array_filter($all, function($item) use (&$seen) {
        if (isset($seen[$item['hash']])) return false;
        $seen[$item['hash']] = true;
        return true;
    }));
}