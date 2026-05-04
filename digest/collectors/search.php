<?php
declare(strict_types=1);
/**
 * search.php — Сборщик через Google News RSS
 *
 * Принцип: Google News RSS по ключевым запросам — основной источник.
 * Не требует API-ключа. Работает стабильно.
 * RSS-фиды сайтов — резервный канал.
 */

if (!defined('DIGEST_ACCESS')) exit;

/**
 * Поиск через Google News RSS.
 * URL: https://news.google.com/rss/search?q={query}&hl=ru&gl=RU&ceid=RU:ru
 *
 * @param string $query    Поисковый запрос
 * @param string $category Категория (ai|bim|events|norms)
 * @param int    $limit    Максимум результатов на запрос
 * @return array
 */
function collectBySearch(string $query, string $category, int $limit = 10): array {
    $url = 'https://news.google.com/rss/search?q='
        . urlencode($query)
        . '&hl=ru&gl=RU&ceid=RU:ru';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => (int)COLLECTOR_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; DigestBot/3.0)',
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($errno || $code === 0) {
        // Тихий выход, не спамить лог
        return [];
    }

    if ($code !== 200 || empty($raw)) {
        error_log("collectBySearch: HTTP $code for query: $query");
        return [];
    }

    $prev = libxml_use_internal_errors(true);
    $xml  = @simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    if (!$xml || !isset($xml->channel->item)) return [];

    $items = [];
    $count = 0;
    foreach ($xml->channel->item as $node) {
        if ($count >= $limit) break;

        // Google News хранит реальную ссылку в <link> но иногда это redirect
        // <guid> обычно содержит тот же redirect-URL
        $link = trim((string)$node->link);
        if (empty($link) && isset($node->guid)) {
            $link = trim((string)$node->guid);
        }
        if (empty($link)) continue;

        $title = trim(html_entity_decode((string)$node->title, ENT_QUOTES, 'UTF-8'));
        if (empty($title)) continue;

        // Источник: Google News добавляет его в конец заголовка через " - "
        $source = 'Google News';
        if (preg_match('/ - ([^-]+)$/', $title, $m)) {
            $source = trim($m[1]);
            $title  = trim(preg_replace('/ - [^-]+$/', '', $title));
        }

        $desc = trim(strip_tags((string)($node->description ?? '')));
        $date = (string)($node->pubDate ?? '');

        $items[] = [
            'title'        => mb_substr($title, 0, 255),
            'link'         => mb_substr($link, 0, 1024),
            'description'  => mb_substr($desc, 0, 300),
            'publish_date' => date('Y-m-d H:i:s', strtotime($date) ?: time()),
            'source'       => mb_substr($source, 0, 50),
            'category'     => $category,
            'hash'         => md5(strtolower(trim($link))),
        ];
        $count++;
    }

    return $items;
}

/**
 * Запускает поиск по всем запросам из SEARCH_QUERIES.
 * Результаты дедуплицируются по URL.
 */
function collectSearchAll(): array {
    if (!defined('SEARCH_QUERIES')) return [];

    $queries = json_decode(SEARCH_QUERIES, true);
    if (!is_array($queries)) return [];

    $all  = [];
    $seen = [];

    foreach ($queries as $category => $categoryQueries) {
        foreach ($categoryQueries as $query) {
            $items = collectBySearch($query, $category, 10);
            foreach ($items as $item) {
                if (isset($seen[$item['hash']])) continue;
                $seen[$item['hash']] = true;
                $all[] = $item;
            }
            // Вежливая пауза между запросами к Google
            usleep(300000); // 0.3 сек
        }
    }

    return $all;
}
