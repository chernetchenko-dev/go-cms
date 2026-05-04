<?php
declare(strict_types=1);

function collectHuggingFace(): array {
    $url = sprintf(
        'https://huggingface.co/api/models?sort=createdAt&direction=-1&limit=%d&filter=text-generation',
        HUGGINGFACE_LIMIT
    );
    $raw = @file_get_contents($url);
    if ($raw === false) { error_log('HuggingFace: fetch failed'); return []; }

    $models = json_decode($raw, true);
    if (!is_array($models)) return [];

    $result = [];
    foreach ($models as $m) {
        $id   = $m['modelId'] ?? $m['id'] ?? '';
        $desc = sprintf(
            'Лайков: %d · Скачиваний: %d · Задача: %s',
            $m['likes'] ?? 0,
            $m['downloads'] ?? 0,
            $m['pipeline_tag'] ?? 'text-generation'
        );

        $result[] = [
            'title'        => $id,
            'link'         => 'https://huggingface.co/' . $id,
            'source'       => 'HuggingFace',
            'source_type'  => 'huggingface',
            'publish_date' => isset($m['createdAt'])
                ? date('Y-m-d H:i:s', strtotime($m['createdAt']))
                : date('Y-m-d H:i:s'),
            'description'  => $desc,
        ];
    }
    return $result;
}