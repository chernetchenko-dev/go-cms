<?php
declare(strict_types=1);

function collectHabr(): array {
    $hubs   = json_decode(HABR_HUBS, true);
    $result = [];
    $seen   = [];

    foreach ($hubs as $hub) {
        $url = "https://habr.com/ru/rss/hub/{$hub}/";
        $raw = @file_get_contents($url);
        if ($raw === false) { error_log("Habr: fetch failed for $hub"); sleep(1); continue; }

        $xml = @simplexml_load_string($raw);
        if (!$xml) { sleep(1); continue; }

        foreach ($xml->channel->item ?? [] as $item) {
            $link = (string)$item->link;
            if (!$link || isset($seen[$link])) continue;
            $seen[$link] = true;

            $result[] = [
                'title'        => (string)$item->title,
                'link'         => $link,
                'source'       => 'Habr /' . $hub,
                'source_type'  => 'habr',
                'publish_date' => date('Y-m-d H:i:s', strtotime((string)$item->pubDate)),
                'description'  => mb_substr(strip_tags((string)$item->description), 0, 500),
            ];
        }
        sleep(1);
    }
    return $result;
}