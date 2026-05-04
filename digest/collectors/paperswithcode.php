<?php
declare(strict_types=1);

function collectPapersWithCode(): array {
    $raw = @file_get_contents('https://paperswithcode.com/latest.rss');
    if ($raw === false) { error_log('PapersWithCode: fetch failed'); return []; }

    $xml = @simplexml_load_string($raw);
    if (!$xml) return [];

    $keywords = json_decode(PWC_KEYWORDS, true);
    $result   = [];

    foreach ($xml->channel->item ?? [] as $item) {
        $title = (string)$item->title;
        $desc  = strip_tags((string)$item->description);
        $match = false;
        foreach ($keywords as $kw) {
            if (mb_stripos($title, $kw) !== false || mb_stripos($desc, $kw) !== false) {
                $match = true; break;
            }
        }
        if (!$match) continue;

        $result[] = [
            'title'        => $title,
            'link'         => (string)$item->link,
            'source'       => 'Papers with Code',
            'source_type'  => 'paperswithcode',
            'publish_date' => date('Y-m-d H:i:s', strtotime((string)$item->pubDate)),
            'description'  => mb_substr($desc, 0, 500),
        ];
    }
    return $result;
}