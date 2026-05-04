<?php
declare(strict_types=1);
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Db.php';

if (!defined('DIGEST_ACCESS')) die('Config error');
session_start();

// --- ВЫХОД ---
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// --- ВХОД ---
if (isset($_POST['pass'])) {
    if (hash_equals(DIGEST_ADMIN_PASS, $_POST['pass'])) {
        $_SESSION['digest_admin'] = true;
        header('Location: index.php');
        exit;
    }
    $loginError = 'Неверный пароль';
}

if (empty($_SESSION['digest_admin'])) {
    ?><!DOCTYPE html>
<html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ГО · Вход</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#0f172a;display:flex;align-items:center;justify-content:center;min-height:100vh}
.box{background:#1e293b;border-radius:16px;padding:40px;width:360px;box-shadow:0 20px 60px rgba(0,0,0,.5)}
.logo{font-size:2rem;margin-bottom:8px}
h1{color:#f1f5f9;font-size:1.4rem;margin-bottom:4px}
p{color:#64748b;font-size:.85rem;margin-bottom:28px}
input{width:100%;padding:12px 16px;background:#0f172a;border:1.5px solid #334155;border-radius:8px;color:#f1f5f9;font-size:1rem;transition:border-color .2s;outline:none}
input:focus{border-color:#3b82f6}
button{width:100%;margin-top:12px;padding:12px;background:#3b82f6;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer;transition:background .2s}
button:hover{background:#2563eb}
.err{color:#f87171;font-size:.82rem;margin-top:8px}
</style></head><body>
<div class="box">
    <div class="logo">📋</div>
    <h1>Господин Оформитель</h1>
    <p>Панель управления дайджестом</p>
    <form method="post">
        <input type="password" name="pass" placeholder="Пароль" autofocus required>
        <?php if (isset($loginError)): ?>
        <div class="err">⚠ <?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>
        <button type="submit">Войти →</button>
    </form>
</div></body></html>
    <?php exit;
}

// === АВТОРИЗОВАН ===

$db  = Db::getInstance();
$db->initTables();
$pdo = $db->getConnection();

$tab = $_GET['tab'] ?? 'dashboard';
$msg = '';
$msgType = 'ok';

// --- СОХРАНЕНИЕ ИСТОЧНИКОВ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sources'])) {
    $sources = [
        'events' => array_values(array_filter(array_map('trim', explode("\n", $_POST['src_events'] ?? '')))),
        'bim'    => array_values(array_filter(array_map('trim', explode("\n", $_POST['src_bim']    ?? '')))),
        'norms'  => array_values(array_filter(array_map('trim', explode("\n", $_POST['src_norms']  ?? '')))),
        'ai'     => array_values(array_filter(array_map('trim', explode("\n", $_POST['src_ai']     ?? '')))),
    ];
    file_put_contents(__DIR__ . '/../core/sources.json',
        json_encode($sources, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $msg = '✅ Источники сохранены';
    $tab = 'sources';
}

// --- СОХРАНЕНИЕ ПОИСКОВЫХ ЗАПРОСОВ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_queries'])) {
    $queries = [
        'ai'     => array_values(array_filter(array_map('trim', explode("\n", $_POST['q_ai']     ?? '')))),
        'bim'    => array_values(array_filter(array_map('trim', explode("\n", $_POST['q_bim']    ?? '')))),
        'events' => array_values(array_filter(array_map('trim', explode("\n", $_POST['q_events'] ?? '')))),
        'norms'  => array_values(array_filter(array_map('trim', explode("\n", $_POST['q_norms']  ?? '')))),
    ];
    // Обновляем SEARCH_QUERIES в Config.php через замену блока
    $configPath = __DIR__ . '/../core/Config.php';
    $configContent = file_get_contents($configPath);
    $newDefine = "define('SEARCH_QUERIES', " . json_encode($queries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . ");";
    $configContent = preg_replace("/define\('SEARCH_QUERIES',.+?\);/s", $newDefine, $configContent);
    file_put_contents($configPath, $configContent);
    $msg = '✅ Поисковые запросы сохранены';
    $tab = 'queries';
}

// --- ЗАПУСК КОЛЛЕКТОРА ---
if (isset($_GET['run_collector'])) {
    $collectorPath = __DIR__ . '/../api/collector.php';
    ob_start();
    define('COLLECTOR_RUN_OUTPUT', true);
    require $collectorPath;
    $runOutput = ob_get_clean();
    $msg = '✅ Коллектор запущен. ' . strip_tags($runOutput);
    $tab = 'dashboard';
}

// --- ЗАПУСК СВОДКИ ---
if (isset($_GET['run_summary'])) {
    $summaryPath = __DIR__ . '/../api/daily_summary.php';
    // Удаляем сегодняшнюю сводку чтобы пересоздать
    $pdo->prepare("DELETE FROM digest_daily_summary WHERE summary_date = ?")->execute([date('Y-m-d')]);
    ob_start();
    require $summaryPath;
    $runOutput = ob_get_clean();
    $msg = '✅ Сводка сформирована. ' . strip_tags($runOutput);
    $tab = 'dashboard';
}

// --- УДАЛЕНИЕ ЗАПИСЕЙ ---
if (isset($_GET['delete_event']) && is_numeric($_GET['delete_event'])) {
    $pdo->prepare("DELETE FROM digest_events WHERE id = ?")->execute([(int)$_GET['delete_event']]);
    $msg = '🗑 Запись удалена';
    $tab = 'events';
}

// --- ОЧИСТКА СТАРЫХ ---
if (isset($_GET['cleanup'])) {
    $days = (int)($_GET['cleanup_days'] ?? 30);
    $deleted = $pdo->prepare("DELETE FROM digest_events WHERE created_at < NOW() - INTERVAL ? DAY");
    $deleted->execute([$days]);
    $msg = '🗑 Удалено старых записей: ' . $deleted->rowCount();
    $tab = 'events';
}

// --- ДАННЫЕ ДЛЯ ВКЛАДОК ---

// Статистика
$stats = [
    'total'     => $pdo->query("SELECT COUNT(*) FROM digest_events")->fetchColumn(),
    'ai'        => $pdo->query("SELECT COUNT(*) FROM digest_events WHERE category='ai'")->fetchColumn(),
    'bim'       => $pdo->query("SELECT COUNT(*) FROM digest_events WHERE category='bim'")->fetchColumn(),
    'events'    => $pdo->query("SELECT COUNT(*) FROM digest_events WHERE category='events'")->fetchColumn(),
    'norms'     => $pdo->query("SELECT COUNT(*) FROM digest_events WHERE category='norms'")->fetchColumn(),
    'with_ai'   => $pdo->query("SELECT COUNT(*) FROM digest_events WHERE ai_summary IS NOT NULL AND ai_summary != ''")->fetchColumn(),
    'published' => $pdo->query("SELECT COUNT(*) FROM digest_events WHERE published_at IS NOT NULL")->fetchColumn(),
    'today'     => $pdo->query("SELECT COUNT(*) FROM digest_events WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
];

// Последние записи AI лога
$aiLog = $pdo->query("
    SELECT model_used, response_time_ms, tokens_used, status, error_message, created_at
    FROM digest_ai_log ORDER BY id DESC LIMIT 20
")->fetchAll();

// Средний response_time
$avgTime = $pdo->query("SELECT AVG(response_time_ms) FROM digest_ai_log WHERE status='success'")->fetchColumn();
$aiErrors = $pdo->query("SELECT COUNT(*) FROM digest_ai_log WHERE status='error'")->fetchColumn();
$aiSuccess = $pdo->query("SELECT COUNT(*) FROM digest_ai_log WHERE status='success'")->fetchColumn();

// Последняя сводка
$lastSummary = $pdo->query("SELECT summary_date, items_count, created_at FROM digest_daily_summary ORDER BY summary_date DESC LIMIT 1")->fetch();

// Источники
$srcFile = __DIR__ . '/../core/sources.json';
$srcData = file_exists($srcFile) ? json_decode(file_get_contents($srcFile), true) : [];

// Поисковые запросы из Config
$searchQueries = defined('SEARCH_QUERIES') ? json_decode(SEARCH_QUERIES, true) : [];

// Последние события
$recentEvents = $pdo->query("
    SELECT id, title, url, source, category, ai_summary, published_at, created_at
    FROM digest_events ORDER BY id DESC LIMIT 50
")->fetchAll();

// Проверки
function checkUrl(string $url, int $timeout = 8): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'DigestBot/3.0',
        CURLOPT_NOBODY         => true,
    ]);
    curl_exec($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'ok' => ($code >= 200 && $code < 400 && !$errno), 'error' => $error];
}

if ($tab === 'checks') {
    // Проверка AI
    $aiCheck = checkUrl('https://openrouter.ai/api/v1/models');

    // Проверка БД
    try {
        $pdo->query("SELECT 1");
        $dbCheck = ['ok' => true, 'msg' => 'Подключена (' . DB_NAME . ')'];
    } catch (Throwable $e) {
        $dbCheck = ['ok' => false, 'msg' => $e->getMessage()];
    }

    // Проверка Google News RSS
    $gnCheck = checkUrl('https://news.google.com/rss/search?q=BIM&hl=ru&gl=RU&ceid=RU:ru');

    // Проверка RSS-источников
    $rssChecks = [];
    foreach (array_merge($srcData['events'] ?? [], $srcData['bim'] ?? [], $srcData['norms'] ?? []) as $url) {
        $rssChecks[$url] = checkUrl($url, 6);
    }

    // Проверка папки кеша
    $cacheDir = __DIR__ . '/../cache/rss/';
    $cacheCheck = [
        'ok'       => is_writable($cacheDir),
        'writable' => is_writable($cacheDir),
        'exists'   => is_dir($cacheDir),
    ];
}

?><!DOCTYPE html>
<html lang="ru"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ГО · Панель управления</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
    --bg:#0f172a;--surface:#1e293b;--surface2:#263548;--border:#334155;
    --text:#f1f5f9;--text2:#94a3b8;--text3:#64748b;
    --blue:#3b82f6;--green:#22c55e;--yellow:#f59e0b;--red:#ef4444;--purple:#a855f7;
}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
a{color:var(--blue);text-decoration:none}
a:hover{text-decoration:underline}

/* LAYOUT */
.shell{display:flex;min-height:100vh}
.sidebar{width:220px;background:var(--surface);border-right:1px solid var(--border);padding:24px 0;flex-shrink:0;position:fixed;height:100vh;overflow-y:auto}
.main{margin-left:220px;padding:32px;flex:1;max-width:1100px}

/* SIDEBAR */
.sb-logo{padding:0 20px 24px;border-bottom:1px solid var(--border);margin-bottom:16px}
.sb-logo h2{font-size:1rem;font-weight:700;color:var(--text)}
.sb-logo p{font-size:.72rem;color:var(--text3);margin-top:2px}
.sb-nav a{display:flex;align-items:center;gap:10px;padding:10px 20px;color:var(--text2);font-size:.88rem;transition:all .15s;border-left:2px solid transparent}
.sb-nav a:hover{color:var(--text);background:var(--surface2);text-decoration:none}
.sb-nav a.active{color:var(--blue);background:rgba(59,130,246,.08);border-left-color:var(--blue);font-weight:600}
.sb-nav .sep{height:1px;background:var(--border);margin:12px 20px}
.sb-logout{padding:16px 20px 0;border-top:1px solid var(--border);margin-top:auto}
.sb-logout a{color:var(--text3);font-size:.82rem}

/* HEADER */
.page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px}
.page-header h1{font-size:1.5rem;font-weight:800;color:var(--text)}
.page-header p{color:var(--text3);font-size:.85rem;margin-top:4px}

/* ALERTS */
.alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:.88rem;font-weight:500}
.alert.ok{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:var(--green)}
.alert.err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:var(--red)}

/* CARDS */
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:28px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:18px 16px}
.card-val{font-size:2rem;font-weight:800;color:var(--blue);line-height:1}
.card-val.green{color:var(--green)}.card-val.yellow{color:var(--yellow)}.card-val.purple{color:var(--purple)}
.card-label{font-size:.75rem;color:var(--text3);text-transform:uppercase;letter-spacing:.05em;margin-top:6px}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .15s;border:none;text-decoration:none}
.btn:hover{transform:translateY(-1px);text-decoration:none}
.btn-primary{background:var(--blue);color:#fff}
.btn-primary:hover{background:#2563eb}
.btn-success{background:var(--green);color:#fff}
.btn-success:hover{background:#16a34a}
.btn-danger{background:var(--red);color:#fff}
.btn-danger:hover{background:#dc2626}
.btn-ghost{background:var(--surface2);color:var(--text2);border:1px solid var(--border)}
.btn-ghost:hover{color:var(--text)}
.btn-sm{padding:5px 12px;font-size:.78rem}
.btn-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px}

/* TABLES */
.table-wrap{background:var(--surface);border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:24px}
table{width:100%;border-collapse:collapse;font-size:.85rem}
th{background:var(--surface2);padding:10px 14px;text-align:left;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text3);font-weight:600;white-space:nowrap}
td{padding:10px 14px;border-top:1px solid var(--border);vertical-align:top;color:var(--text2)}
td:first-child{color:var(--text)}
tr:hover td{background:rgba(255,255,255,.02)}
.truncate{max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* BADGES */
.badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:.7rem;font-weight:700;text-transform:uppercase}
.badge-ai{background:rgba(59,130,246,.15);color:var(--blue)}
.badge-bim{background:rgba(34,197,94,.15);color:var(--green)}
.badge-events{background:rgba(245,158,11,.15);color:var(--yellow)}
.badge-norms{background:rgba(100,116,139,.15);color:var(--text3)}
.badge-ok{background:rgba(34,197,94,.15);color:var(--green)}
.badge-err{background:rgba(239,68,68,.15);color:var(--red)}
.badge-skip{background:rgba(100,116,139,.15);color:var(--text3)}

/* CHECK ROWS */
.check-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)}
.check-row:last-child{border:none}
.check-name{font-size:.88rem;color:var(--text);font-weight:500}
.check-detail{font-size:.78rem;color:var(--text3);margin-top:2px}
.check-status{font-size:1.2rem;flex-shrink:0}

/* FORMS */
.form-section{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:24px;margin-bottom:20px}
.form-section h3{font-size:.95rem;font-weight:700;margin-bottom:16px;color:var(--text)}
.form-row{margin-bottom:16px}
label{display:block;font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text3);margin-bottom:6px;font-weight:600}
textarea,input[type=text],input[type=password],select{
    width:100%;background:var(--bg);border:1.5px solid var(--border);border-radius:8px;
    color:var(--text);font-family:monospace;font-size:.85rem;padding:10px 12px;
    transition:border-color .2s;outline:none;resize:vertical
}
textarea:focus,input:focus{border-color:var(--blue)}
textarea{min-height:120px}
.hint{font-size:.75rem;color:var(--text3);margin-top:5px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:768px){.grid2{grid-template-columns:1fr}.sidebar{display:none}.main{margin-left:0}}

/* LOG */
.log-row-ok td{color:var(--text2)}
.log-row-err td{color:rgba(239,68,68,.8)}
.log-row-fallback td{color:rgba(245,158,11,.8)}
</style>
</head>
<body>
<div class="shell">

<!-- SIDEBAR -->
<nav class="sidebar">
    <div class="sb-logo">
        <h2>📋 Дайджест ГО</h2>
        <p>Панель управления</p>
    </div>
    <div class="sb-nav">
        <a href="?tab=dashboard" class="<?= $tab==='dashboard'?'active':'' ?>">📊 Дашборд</a>
        <a href="?tab=events"    class="<?= $tab==='events'   ?'active':'' ?>">📰 Записи</a>
        <a href="?tab=sources"   class="<?= $tab==='sources'  ?'active':'' ?>">📡 RSS-источники</a>
        <a href="?tab=queries"   class="<?= $tab==='queries'  ?'active':'' ?>">🔍 Запросы поиска</a>
        <a href="?tab=ai_log"    class="<?= $tab==='ai_log'   ?'active':'' ?>">🤖 Лог ИИ</a>
        <a href="?tab=checks"    class="<?= $tab==='checks'   ?'active':'' ?>">✅ Проверки</a>
        <div class="sep"></div>
        <a href="../" target="_blank">🌐 Сайт</a>
        <a href="../digest/" target="_blank">📖 Дайджест</a>
    </div>
    <div class="sb-logout"><a href="?logout=1">← Выйти</a></div>
</nav>

<!-- MAIN -->
<main class="main">

<?php if ($msg): ?>
<div class="alert ok"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($tab === 'dashboard'): ?>
<!-- ==================== ДАШБОРД ==================== -->
<div class="page-header">
    <div><h1>Дашборд</h1><p>Общий статус дайджеста</p></div>
</div>

<div class="cards">
    <div class="card"><div class="card-val"><?= $stats['total'] ?></div><div class="card-label">Всего записей</div></div>
    <div class="card"><div class="card-val green"><?= $stats['today'] ?></div><div class="card-label">Сегодня</div></div>
    <div class="card"><div class="card-val"><?= $stats['ai'] ?></div><div class="card-label">ИИ и ML</div></div>
    <div class="card"><div class="card-val green"><?= $stats['bim'] ?></div><div class="card-label">BIM</div></div>
    <div class="card"><div class="card-val yellow"><?= $stats['events'] ?></div><div class="card-label">События</div></div>
    <div class="card"><div class="card-val"><?= $stats['norms'] ?></div><div class="card-label">Нормы</div></div>
    <div class="card"><div class="card-val purple"><?= $stats['with_ai'] ?></div><div class="card-label">С AI-резюме</div></div>
    <div class="card"><div class="card-val green"><?= $stats['published'] ?></div><div class="card-label">Опубликовано</div></div>
</div>

<div class="btn-row">
    <a href="?run_collector=1" class="btn btn-primary"
       onclick="return confirm('Запустить сбор новостей прямо сейчас?')">
        🔄 Запустить сбор
    </a>
    <a href="?run_summary=1" class="btn btn-success"
       onclick="return confirm('Сформировать сводку за сегодня? Существующая будет пересоздана.')">
        📋 Обновить сводку дня
    </a>
    <a href="?tab=checks" class="btn btn-ghost">✅ Запустить проверки</a>
</div>

<?php if ($lastSummary): ?>
<div class="form-section">
    <h3>📋 Последняя сводка дня</h3>
    <div style="color:var(--text3);font-size:.8rem;margin-bottom:8px">
        <?= htmlspecialchars(date('d.m.Y', strtotime($lastSummary['summary_date']))) ?>
        · Событий: <?= (int)$lastSummary['items_count'] ?>
        · Создана: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($lastSummary['created_at']))) ?>
    </div>
</div>
<?php endif; ?>

<div class="form-section">
    <h3>🗓 Cron-задания (настроить на хостинге)</h3>
    <textarea readonly style="min-height:80px;font-size:.82rem;color:var(--text3)">0 9 * * *  php <?= __DIR__ ?>/../api/collector.php
5 9 * * *  php <?= __DIR__ ?>/../api/daily_summary.php</textarea>
    <div class="hint">Сбор в 9:00, сводка в 9:05. Путь указан для текущего сервера.</div>
</div>

<div class="form-section">
    <h3>📊 Последние 5 записей</h3>
    <div class="table-wrap" style="margin:0">
    <table>
        <thead><tr><th>Категория</th><th>Заголовок</th><th>Источник</th><th>Создана</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($recentEvents, 0, 5) as $ev): ?>
        <tr>
            <td><span class="badge badge-<?= htmlspecialchars($ev['category']) ?>"><?= strtoupper(htmlspecialchars($ev['category'])) ?></span></td>
            <td class="truncate"><a href="<?= htmlspecialchars($ev['url'] ?? '#') ?>" target="_blank"><?= htmlspecialchars(mb_substr($ev['title'] ?? '', 0, 60)) ?></a></td>
            <td style="font-size:.78rem;color:var(--text3)"><?= htmlspecialchars($ev['source'] ?? '') ?></td>
            <td style="font-size:.78rem;color:var(--text3);white-space:nowrap"><?= htmlspecialchars(date('d.m H:i', strtotime($ev['created_at'] ?? 'now'))) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php elseif ($tab === 'events'): ?>
<!-- ==================== ЗАПИСИ ==================== -->
<div class="page-header">
    <div><h1>Записи дайджеста</h1><p>Всего: <?= $stats['total'] ?></p></div>
    <div style="display:flex;gap:8px;align-items:center">
        <form method="get" style="display:flex;gap:6px;align-items:center">
            <input type="hidden" name="tab" value="events">
            <select name="cat" onchange="this.form.submit()" style="width:140px;padding:7px 10px">
                <option value="">Все категории</option>
                <option value="ai"     <?= ($_GET['cat']??'')==='ai'     ?'selected':''?>>ИИ</option>
                <option value="bim"    <?= ($_GET['cat']??'')==='bim'    ?'selected':''?>>BIM</option>
                <option value="events" <?= ($_GET['cat']??'')==='events' ?'selected':''?>>События</option>
                <option value="norms"  <?= ($_GET['cat']??'')==='norms'  ?'selected':''?>>Нормы</option>
            </select>
        </form>
        <a href="?tab=events&cleanup=1&cleanup_days=30"
           class="btn btn-danger btn-sm"
           onclick="return confirm('Удалить записи старше 30 дней?')">🗑 Очистить 30д+</a>
    </div>
</div>

<?php
$filterCat = $_GET['cat'] ?? '';
$filterSql = $filterCat ? " AND category = ?" : "";
$filterParams = $filterCat ? [$filterCat] : [];
$stmt = $pdo->prepare("SELECT id, title, url, source, category, ai_summary, published_at, created_at FROM digest_events WHERE 1=1 $filterSql ORDER BY id DESC LIMIT 100");
$stmt->execute($filterParams);
$filteredEvents = $stmt->fetchAll();
?>

<div class="table-wrap">
<table>
    <thead><tr><th>#</th><th>Категория</th><th>Заголовок</th><th>Источник</th><th>ИИ</th><th>Статус</th><th>Дата</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($filteredEvents as $ev): ?>
    <tr>
        <td style="color:var(--text3);font-size:.75rem"><?= $ev['id'] ?></td>
        <td><span class="badge badge-<?= htmlspecialchars($ev['category']) ?>"><?= strtoupper(htmlspecialchars($ev['category'])) ?></span></td>
        <td class="truncate"><a href="<?= htmlspecialchars($ev['url'] ?? '#') ?>" target="_blank"><?= htmlspecialchars(mb_substr($ev['title'] ?? '', 0, 55)) ?></a></td>
        <td style="font-size:.75rem;color:var(--text3)"><?= htmlspecialchars(mb_substr($ev['source'] ?? '', 0, 25)) ?></td>
        <td style="font-size:.8rem"><?= $ev['ai_summary'] ? '<span style="color:var(--green)">✓</span>' : '<span style="color:var(--text3)">—</span>' ?></td>
        <td><?= $ev['published_at'] ? '<span class="badge badge-ok">Опубл.</span>' : '<span class="badge badge-skip">Ждёт</span>' ?></td>
        <td style="font-size:.75rem;color:var(--text3);white-space:nowrap"><?= date('d.m H:i', strtotime($ev['created_at'] ?? 'now')) ?></td>
        <td><a href="?tab=events&delete_event=<?= $ev['id'] ?>&<?= $filterCat ? 'cat='.$filterCat : '' ?>"
               class="btn btn-ghost btn-sm"
               onclick="return confirm('Удалить?')">×</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php elseif ($tab === 'sources'): ?>
<!-- ==================== RSS-ИСТОЧНИКИ ==================== -->
<div class="page-header">
    <div><h1>RSS-источники</h1><p>Резервный канал. Каждая ссылка с новой строки.</p></div>
</div>

<form method="post">
<div class="grid2">
    <div class="form-section">
        <h3>📅 События и форумы</h3>
        <div class="form-row">
            <textarea name="src_events" rows="10"><?= htmlspecialchars(implode("\n", $srcData['events'] ?? [])) ?></textarea>
            <div class="hint">Добавляй /feed/ или /rss/ к URL WordPress-сайтов</div>
        </div>
    </div>
    <div class="form-section">
        <h3>🏗 BIM и ТИМ</h3>
        <div class="form-row">
            <textarea name="src_bim" rows="10"><?= htmlspecialchars(implode("\n", $srcData['bim'] ?? [])) ?></textarea>
        </div>
    </div>
    <div class="form-section">
        <h3>📜 Нормативка</h3>
        <div class="form-row">
            <textarea name="src_norms" rows="6"><?= htmlspecialchars(implode("\n", $srcData['norms'] ?? [])) ?></textarea>
        </div>
    </div>
    <div class="form-section">
        <h3>🤖 ИИ (дополнительные RSS)</h3>
        <div class="form-row">
            <textarea name="src_ai" rows="6"><?= htmlspecialchars(implode("\n", $srcData['ai'] ?? [])) ?></textarea>
        </div>
    </div>
</div>
<button type="submit" name="save_sources" class="btn btn-primary">💾 Сохранить источники</button>
</form>

<?php elseif ($tab === 'queries'): ?>
<!-- ==================== ПОИСКОВЫЕ ЗАПРОСЫ ==================== -->
<div class="page-header">
    <div><h1>Запросы Google News</h1><p>Основной источник. По одному запросу на строку. site: оператор не работает.</p></div>
</div>

<form method="post">
<div class="grid2">
    <div class="form-section">
        <h3>🤖 ИИ и ML</h3>
        <textarea name="q_ai" rows="12"><?= htmlspecialchars(implode("\n", $searchQueries['ai'] ?? [])) ?></textarea>
    </div>
    <div class="form-section">
        <h3>🏗 BIM и ТИМ</h3>
        <textarea name="q_bim" rows="12"><?= htmlspecialchars(implode("\n", $searchQueries['bim'] ?? [])) ?></textarea>
    </div>
    <div class="form-section">
        <h3>📅 События и форумы</h3>
        <textarea name="q_events" rows="12"><?= htmlspecialchars(implode("\n", $searchQueries['events'] ?? [])) ?></textarea>
    </div>
    <div class="form-section">
        <h3>📜 Нормативка</h3>
        <textarea name="q_norms" rows="12"><?= htmlspecialchars(implode("\n", $searchQueries['norms'] ?? [])) ?></textarea>
    </div>
</div>
<button type="submit" name="save_queries" class="btn btn-primary">💾 Сохранить запросы</button>
</form>

<?php elseif ($tab === 'ai_log'): ?>
<!-- ==================== ЛОГ ИИ ==================== -->
<div class="page-header">
    <div><h1>Лог ИИ</h1><p>Вызовы OpenRouter</p></div>
</div>

<div class="cards" style="grid-template-columns:repeat(3,1fr)">
    <div class="card"><div class="card-val green"><?= $aiSuccess ?></div><div class="card-label">Успешных</div></div>
    <div class="card"><div class="card-val" style="color:var(--red)"><?= $aiErrors ?></div><div class="card-label">Ошибок</div></div>
    <div class="card"><div class="card-val yellow"><?= $avgTime ? round((float)$avgTime) . 'ms' : '—' ?></div><div class="card-label">Среднее время</div></div>
</div>

<div class="table-wrap">
<table>
    <thead><tr><th>Модель</th><th>Время, мс</th><th>Токены</th><th>Статус</th><th>Ошибка</th><th>Когда</th></tr></thead>
    <tbody>
    <?php foreach ($aiLog as $log):
        $rowClass = $log['status'] === 'success' ? 'log-row-ok' : ($log['status'] === 'error' ? 'log-row-err' : 'log-row-fallback');
    ?>
    <tr class="<?= $rowClass ?>">
        <td style="font-size:.78rem;font-family:monospace"><?= htmlspecialchars(mb_substr($log['model_used'] ?? '—', 0, 40)) ?></td>
        <td><?= $log['response_time_ms'] ? number_format((int)$log['response_time_ms']) : '—' ?></td>
        <td><?= $log['tokens_used'] ? number_format((int)$log['tokens_used']) : '—' ?></td>
        <td><span class="badge badge-<?= $log['status'] === 'success' ? 'ok' : 'err' ?>"><?= htmlspecialchars($log['status']) ?></span></td>
        <td style="font-size:.75rem;color:var(--red)"><?= htmlspecialchars(mb_substr($log['error_message'] ?? '', 0, 50)) ?></td>
        <td style="font-size:.75rem;white-space:nowrap"><?= date('d.m H:i', strtotime($log['created_at'] ?? 'now')) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php elseif ($tab === 'checks'): ?>
<!-- ==================== ПРОВЕРКИ ==================== -->
<div class="page-header">
    <div><h1>Проверки</h1><p>Состояние всех компонентов</p></div>
    <a href="?tab=checks" class="btn btn-ghost">🔄 Обновить</a>
</div>

<div class="form-section">
    <h3>Ключевые сервисы</h3>
    <div class="check-row">
        <div>
            <div class="check-name">База данных MySQL</div>
            <div class="check-detail"><?= DB_NAME ?> @ <?= DB_HOST ?>:<?= DB_PORT ?></div>
        </div>
        <span class="check-status"><?= $dbCheck['ok'] ? '✅' : '❌' ?></span>
    </div>
    <div class="check-row">
        <div>
            <div class="check-name">OpenRouter API</div>
            <div class="check-detail">openrouter.ai/api/v1/models · HTTP <?= $aiCheck['code'] ?></div>
        </div>
        <span class="check-status"><?= $aiCheck['ok'] ? '✅' : '❌' ?></span>
    </div>
    <div class="check-row">
        <div>
            <div class="check-name">Google News RSS</div>
            <div class="check-detail">news.google.com/rss/search · HTTP <?= $gnCheck['code'] ?></div>
        </div>
        <span class="check-status"><?= $gnCheck['ok'] ? '✅' : '❌' ?></span>
    </div>
    <div class="check-row">
        <div>
            <div class="check-name">Папка кеша RSS</div>
            <div class="check-detail"><?= __DIR__ ?>/../cache/rss/</div>
        </div>
        <span class="check-status"><?= $cacheCheck['ok'] ? '✅' : '❌' ?></span>
    </div>
</div>

<div class="form-section">
    <h3>RSS-источники (<?= count($rssChecks) ?>)</h3>
    <?php if (empty($rssChecks)): ?>
        <p style="color:var(--text3);font-size:.85rem">Нет настроенных RSS-источников</p>
    <?php else: ?>
    <?php foreach ($rssChecks as $url => $check): ?>
    <div class="check-row">
        <div style="min-width:0;flex:1;padding-right:12px">
            <div class="check-name" style="font-size:.82rem;font-family:monospace;word-break:break-all"><?= htmlspecialchars($url) ?></div>
            <div class="check-detail">HTTP <?= $check['code'] ?><?= $check['error'] ? ' · ' . htmlspecialchars($check['error']) : '' ?></div>
        </div>
        <span class="check-status"><?= $check['ok'] ? '✅' : '❌' ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="form-section">
    <h3>Конфигурация</h3>
    <div class="check-row">
        <div>
            <div class="check-name">AI_KEY</div>
            <div class="check-detail"><?= defined('AI_KEY') ? substr(AI_KEY, 0, 12) . '...' . substr(AI_KEY, -4) : 'не задан' ?></div>
        </div>
        <span class="check-status"><?= defined('AI_KEY') && AI_KEY ? '✅' : '❌' ?></span>
    </div>
    <div class="check-row">
        <div>
            <div class="check-name">Первичная модель ИИ</div>
            <div class="check-detail"><?= json_decode(AI_MODELS ?? '{}', true)['primary'] ?? '—' ?></div>
        </div>
        <span class="check-status">ℹ️</span>
    </div>
    <div class="check-row">
        <div>
            <div class="check-name">SEARCH_QUERIES</div>
            <div class="check-detail"><?= defined('SEARCH_QUERIES') ? count(array_merge(...array_values(json_decode(SEARCH_QUERIES, true) ?? []))) . ' запросов' : 'не задан' ?></div>
        </div>
        <span class="check-status"><?= defined('SEARCH_QUERIES') ? '✅' : '❌' ?></span>
    </div>
    <div class="check-row">
        <div>
            <div class="check-name">sources.json</div>
            <div class="check-detail"><?= file_exists(__DIR__ . '/../core/sources.json') ? 'существует' : 'не найден' ?></div>
        </div>
        <span class="check-status"><?= file_exists(__DIR__ . '/../core/sources.json') ? '✅' : '❌' ?></span>
    </div>
    <div class="check-row">
        <div>
            <div class="check-name">Таблица digest_daily_summary</div>
            <div class="check-detail">Последняя сводка: <?= $lastSummary ? htmlspecialchars($lastSummary['summary_date']) : 'нет' ?></div>
        </div>
        <span class="check-status"><?= $lastSummary ? '✅' : 'ℹ️' ?></span>
    </div>
</div>

<?php endif; ?>
</main>
</div>
</body></html>
