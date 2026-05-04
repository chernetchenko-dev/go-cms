<?php
declare(strict_types=1);

function collectReddit(): array {
    $subs   = json_decode(REDDIT_SUBS, true);
    $result = [];
    $seen   = [];

    $ctx = stream_context_create(['http' => [
        'header'  => "User-Agent: digest-bot/1.0\r\n",
        'timeout' => (int)COLLECTOR_TIMEOUT,
    ]]);

    foreach ($subs as $sub) {
        $url = "https://www.reddit.com/r/{$sub}/new.rss";
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) { error_log("Reddit: fetch failed for r/$sub"); sleep(2); continue; }

        $xml = @simplexml_load_string($raw);
        if (!$xml) { sleep(2); continue; }

        $xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
        $entries = $xml->xpath('//atom:entry') ?: [];

        foreach ($entries as $entry) {
            $link = (string)($entry->link['href'] ?? '');
            if (!$link || isset($seen[$link])) continue;
            // Пропускаем мета-ссылки Reddit
            if (str_contains($link, '/user/') || str_contains($link, '.json')) continue;
            $seen[$link] = true;

            $result[] = [
                'title'        => (string)($entry->title ?? ''),
                'link'         => $link,
                'source'       => 'Reddit r/' . $sub,
                'source_type'  => 'reddit',
                'publish_date' => date('Y-m-d H:i:s', strtotime((string)($entry->updated ?? 'now'))),
                'description'  => mb_substr(strip_tags((string)($entry->content ?? '')), 0, 500),
            ];
        }
        sleep(2);
    }
    return $result;
}