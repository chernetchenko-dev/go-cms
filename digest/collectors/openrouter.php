<?php
declare(strict_types=1);

function collectOpenRouter(): array {
    $raw = @file_get_contents('https://openrouter.ai/api/v1/models');
    if ($raw === false) { error_log('OpenRouter: fetch failed'); return []; }

    $data   = json_decode($raw, true);
    $models = $data['data'] ?? [];
    $cutoff = strtotime('-' . OPENROUTER_DAYS . ' days');
    $result = [];

    foreach ($models as $m) {
        $created = (int)($m['created'] ?? 0);
        if ($created < $cutoff) continue;

        $isFree = isset($m['pricing']['prompt']) && $m['pricing']['prompt'] === '0';
        $desc   = ($isFree ? '[FREE] ' : '[PAID] ') . mb_substr($m['description'] ?? '', 0, 300);

        $result[] = [
            'title'        => $m['name'] ?? $m['id'] ?? 'Unknown',
            'link'         => 'https://openrouter.ai/' . ($m['id'] ?? ''),
            'source'       => 'OpenRouter',
            'source_type'  => 'openrouter',
            'publish_date' => date('Y-m-d H:i:s', $created),
            'description'  => $desc,
        ];
    }

    usort($result, fn($a, $b) => strtotime($b['publish_date']) <=> strtotime($a['publish_date']));
    return $result;
}