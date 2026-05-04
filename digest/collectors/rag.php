<?php
declare(strict_types=1);

function collectRAG(): array {
    $result   = [];
    $keywords = json_decode(RAG_KEYWORDS, true);

    // --- arxiv cs.IR ---
    $raw = @file_get_contents('https://arxiv.org/rss/cs.IR');
    if ($raw !== false) {
        $xml = @simplexml_load_string($raw);
        if ($xml) {
            $count = 0;
            foreach ($xml->channel->item ?? [] as $item) {
                if ($count >= RAG_ARXIV_LIMIT) break;
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
                    'source'       => 'arxiv cs.IR',
                    'source_type'  => 'rag',
                    'publish_date' => date('Y-m-d H:i:s', strtotime((string)$item->pubDate)),
                    'description'  => mb_substr($desc, 0, 500),
                ];
                $count++;
            }
        }
    }

    // --- GitHub Search API ---
    $ctx = stream_context_create(['http' => [
        'header'  => "User-Agent: digest-bot/1.0\r\n",
        'timeout' => (int)COLLECTOR_TIMEOUT,
    ]]);
    $raw = @file_get_contents(
        'https://api.github.com/search/repositories?q=retrieval+augmented+generation+language:python&sort=updated&per_page=10',
        false, $ctx
    );
    if ($raw !== false) {
        $data = json_decode($raw, true);
        foreach ($data['items'] ?? [] as $repo) {
            $result[] = [
                'title'        => $repo['full_name'],
                'link'         => $repo['html_url'],
                'source'       => 'GitHub Search RAG',
                'source_type'  => 'rag',
                'publish_date' => date('Y-m-d H:i:s', strtotime($repo['updated_at'])),
                'description'  => mb_substr($repo['description'] ?? '', 0, 300) . ' ★' . ($repo['stargazers_count'] ?? 0),
            ];
        }
    }

    return $result;
}