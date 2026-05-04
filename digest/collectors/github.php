<?php
declare(strict_types=1);

function collectGitHub(): array {
    $topics = json_decode(GITHUB_TOPICS, true);
    $result = [];
    $seen   = [];

    foreach ($topics as $topic) {
        $url = "https://github.com/topics/{$topic}.atom";
        $raw = @file_get_contents($url);
        if ($raw === false) { error_log("GitHub: fetch failed for $topic"); sleep(1); continue; }

        $xml = @simplexml_load_string($raw);
        if (!$xml) { sleep(1); continue; }

        foreach ($xml->entry ?? [] as $entry) {
            $link = (string)($entry->link['href'] ?? '');
            if (!$link || isset($seen[$link])) continue;
            $seen[$link] = true;

            $result[] = [
                'title'        => (string)($entry->title ?? ''),
                'link'         => $link,
                'source'       => 'GitHub #' . $topic,
                'source_type'  => 'github',
                'publish_date' => date('Y-m-d H:i:s', strtotime((string)($entry->updated ?? 'now'))),
                'description'  => mb_substr(strip_tags((string)($entry->content ?? '')), 0, 500),
            ];
        }
        sleep(1);
    }
    return $result;
}