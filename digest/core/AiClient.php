<?php
declare(strict_types=1);

class AiClient {
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function ask(string $system, string $user): ?string {
        $models = json_decode(AI_MODELS, true);
        $queue  = array_filter([$models['primary'], $models['fallback1'] ?? null, $models['fallback2'] ?? null]);
        foreach ($queue as $model) {
            $result = $this->callModel($model, $system, $user);
            if ($result !== null) return $result;
        }
        return null;
    }

    private function callModel(string $model, string $system, string $user): ?string {
        $payload = json_encode([
            'model'       => $model,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'temperature' => 0.3,
            'max_tokens'  => 512,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . AI_KEY,
                'Content-Type: application/json',
                'HTTP-Referer: https://chernetchenko.pro',
                'X-Title: AI-Digest',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $start    = microtime(true);
        $response = curl_exec($ch);
        $ms       = (int)((microtime(true) - $start) * 1000);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($errno || $httpCode < 200 || $httpCode >= 300) {
            $this->log(null, $model, $ms, 0, 'error', $error ?: "HTTP $httpCode");
            return null;
        }

        $data    = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? null;
        $tokens  = $data['usage']['total_tokens'] ?? 0;

        $this->log(null, $model, $ms, $tokens, 'success');
        return $content;
    }

    public function extractJSON(string $text): ?array {
        // Сначала пробуем JSON в блоке ```json ... ```
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if ($decoded) return $decoded;
        }
        // Потом напрямую
        $decoded = json_decode(trim($text), true);
        return $decoded ?: null;
    }

    public function setLastEventId(int $id): void {
        // Обновить последнюю запись лога event_id
        $this->pdo->prepare("
            UPDATE digest_ai_log SET event_id = ? 
            ORDER BY id DESC LIMIT 1
        ")->execute([$id]);
    }

    private function log(?int $eventId, string $model, int $ms, int $tokens, string $status, string $error = ''): void {
        $this->pdo->prepare("
            INSERT INTO digest_ai_log (event_id, model_used, response_time_ms, tokens_used, status, error_message)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$eventId, $model, $ms, $tokens, $status, $error]);
    }
}