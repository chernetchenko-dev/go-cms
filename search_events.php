<?php
// ==========================================================
// 1. ИНИЦИАЛИЗАЦИЯ И НАСТРОЙКИ
// ==========================================================
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
        $_ENV[trim($name)] = trim($value);
    }
}

// Защита от прямого вызова
define('TOKEN', getenv('SCRIPT_TOKEN') ?: 'change_me_now_123');
if (php_sapi_name() !== 'cli' && ($_GET['key'] ?? '') !== TOKEN) {
    http_response_code(403); 
    exit('Доступ запрещен');
}

// Защита от наложения крон-задач
$lock = __DIR__.'/search_events.lock';
if (file_exists($lock)) {
    if (time() - filemtime($lock) > 900) unlink($lock);
    else exit("Скрипт уже работает в фоне.\n");
}
touch($lock);
register_shutdown_function(fn() => file_exists($lock) && unlink($lock));

set_time_limit(120);
ini_set('memory_limit', '128M');

// ==========================================================
// 2. СБОР ДАННЫХ ЧЕРЕЗ GOOGLE CUSTOM SEARCH
// ==========================================================
$googleKey = getenv('GOOGLE_API_KEY');
$googleCx = getenv('GOOGLE_CX_ID');
$domainReferer = 'https://chernetchenko.pro/';

// Базовые запросы для агрегаторов
$queries = [
    'BIM конференция',
    'ТИМ форум',
    'девелопмент нетворкинг',
    'строительство выставка',
    'проектирование мероприятие'
];

$rawSnippets = [];
echo "Начинаем опрос Google CSE...\n";

foreach ($queries as $q) {
    // Запрос без dateRestrict для охвата всех страниц
    $url = "https://www.googleapis.com/customsearch/v1?key={$googleKey}&cx={$googleCx}&q=" . urlencode($q);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // Представляемся нашим доменом для обхода защиты API ключа
    curl_setopt($ch, CURLOPT_REFERER, $domainReferer); 
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                // Собираем заголовок, сниппет и ссылку в одну строку для нейронки
                $rawSnippets[] = $item['title'] . " | " . $item['snippet'] . " | " . $item['link'];
            }
        }
    }
    // Пауза, чтобы Гугл не ругался на частоту запросов
    usleep(500000); 
}

$rawSnippets = array_unique($rawSnippets);

if (empty($rawSnippets)) {
    exit("Google ничего не вернул. Проверь наличие сайтов в annotations.xml\n");
}

echo "Собрано сниппетов: " . count($rawSnippets) . ".\nОтправляем в OpenRouter...\n";

// ==========================================================
// 3. ПАРСИНГ ЧЕРЕЗ НЕЙРОСЕТЬ (OPENROUTER + LLAMA 3.3 70B)
// ==========================================================
$openRouterKey = getenv('OPENROUTER_API_KEY');
$currentDate = date('Y-m-d');

$systemPrompt = "Ты строгий дата-инженер. 
Твоя задача вытащить из текста информацию о будущих профильных мероприятиях (BIM, ТИМ, девелопмент, стройка).
Сегодняшняя дата: {$currentDate}. Строго игнорируй прошедшие события.
Города: МСК, СПБ, ЕКБ, НСК, КРАСНОДАР, КАЗАНЬ. Если город другой, напиши его капсом.
Верни только JSON массив объектов без текста и маркдауна. 
Формат объекта:
{
  \"id\": \"уникальный_id_английскими_буквами\",
  \"month\": \"МЕСЯЦ_КАПСОМ\",
  \"days\": \"ДД-ДД\",
  \"title\": \"Название\",
  \"city\": \"ГОРОД\",
  \"desc\": \"Короткое и емкое описание\",
  \"tags\": [{\"name\": \"Тег\", \"type\": \"normal\"}],
  \"link\": \"ссылка\",
  \"isPast\": false
}";

$userPrompt = implode("\n", $rawSnippets);

$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$openRouterKey}",
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode([
        "model" => "meta-llama/llama-3.3-70b-instruct",
        "messages" => [
            ["role" => "system", "content" => $systemPrompt],
            ["role" => "user", "content" => $userPrompt]
        ],
        "temperature" => 0.1
    ])
]);

$aiResponse = curl_exec($ch);
curl_close($ch);

$aiData = json_decode($aiResponse, true);
$aiText = $aiData['choices'][0]['message']['content'] ?? '[]';

// Очистка от мусора, если LLM всё же добавит маркдаун
$aiText = preg_replace('/```json|```/', '', $aiText);
$parsedEvents = json_decode(trim($aiText), true);

if (!$parsedEvents || !is_array($parsedEvents)) {
    exit("Ошибка: Нейросеть вернула невалидный JSON.\n");
}

// ==========================================================
// 4. ДЕДУПЛИКАЦИЯ И ОТПРАВКА В ТЕЛЕГРАМ
// ==========================================================
$eventsFile = __DIR__ . '/events.json';
$existingEvents = file_exists($eventsFile) ? json_decode(file_get_contents($eventsFile), true) : [];
if (!is_array($existingEvents)) $existingEvents = [];

$existingHashes = array_map(function($ev) {
    return md5($ev['title'] . $ev['days'] . $ev['month']);
}, $existingEvents);

$newEventsForModeration = [];

foreach ($parsedEvents as $ev) {
    $hash = md5($ev['title'] . $ev['days'] . $ev['month']);
    if (!in_array($hash, $existingHashes)) {
        $newEventsForModeration[] = $ev;
        $existingEvents[] = $ev;
        $existingHashes[] = $hash;
    }
}

function send_draft($events, $token, $chat) {
    if (!$token || !$chat || empty($events)) return;
    foreach ($events as $ev) {
        $title = str_replace(['<','>'], ['&lt;','&gt;'], $ev['title']);
        $desc  = str_replace(['<','>'], ['&lt;','&gt;'], $ev['desc']);
        $tagsStr = implode(', ', array_column($ev['tags'] ?? [], 'name'));
        
        $msg = "📋 <b>Новое на модерацию</b>\n\n"
             . "<b>{$title}</b>\n"
             . "🗓 {$ev['month']} {$ev['days']}, {$ev['city']}\n"
             . "📝 {$desc}\n🏷 {$tagsStr}\n"
             . "🔗 {$ev['link']}\n\n<i>Перешли для публикации</i>";
        
        $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'chat_id' => $chat, 
                'text' => $msg, 
                'parse_mode' => 'HTML', 
                'disable_web_page_preview' => true
            ]),
            CURLOPT_RETURNTRANSFER => true, 
            CURLOPT_TIMEOUT => 5
        ]);
        curl_exec($ch); 
        curl_close($ch);
        usleep(300000);
    }
}

$tgToken = getenv('TG_BOT_TOKEN');
$tgChat = getenv('TG_CHAT_ID');

if (!empty($newEventsForModeration)) {
    echo "Найдено новых мероприятий: " . count($newEventsForModeration) . ".\nОтправляем в Telegram...\n";
    send_draft($newEventsForModeration, $tgToken, $tgChat);
    
    // Сохраняем обновленный массив в файл
    file_put_contents($eventsFile, json_encode($existingEvents, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
} else {
    echo "Новых уникальных мероприятий не найдено.\n";
}

echo "Скрипт отработал штатно.\n";
?>