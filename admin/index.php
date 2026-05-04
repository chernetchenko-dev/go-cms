<?php
declare(strict_types=1);
error_reporting(0);
ini_set('display_errors', 0);
/**
 * /admin/index.php — Панель управления (v7.5 Engine Mode)
 * Интегрировано: CMS Engine, Layout Builder, Настройки, ИИ Ассистенты.
 * Динамические сайты, SortableJS, древо файлов.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/views.php';
require_once __DIR__ . '/../lib/brute_force.php';
require_once __DIR__ . '/../lib/frontmatter.php';
require_once __DIR__ . '/../lib/sites.php';

ini_set('session.cookie_lifetime', '86400');
session_start();

// --- 1. АВТОРИЗАЦИЯ И БРУТФОРС ---
if (isset($_GET['logout'])) { session_unset(); session_destroy(); header('Location: index.php'); exit; }

$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$bruteCheck = checkBruteForce($clientIp);

if (isset($_POST['password'])) {
    if (!$bruteCheck['allowed']) {
        $loginError = "Слишком много попыток. Подождите " . ceil($bruteCheck['wait']/60) . " мин.";
    } elseif (hash_equals(ADMIN_PASSWORD, $_POST['password'])) {
        $_SESSION['admin'] = true; $_SESSION['admin_time'] = time();
        clearBruteForce($clientIp); header('Location: index.php'); exit;
    } else {
        recordAttempt($clientIp);
        $loginError = "Неверный пароль. Осталось: " . max(0, BRUTE_MAX_ATTEMPTS - $bruteCheck['attempts'] - 1);
    }
}

if (empty($_SESSION['admin'])) { ?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ГО · Вход</title>
<link rel="stylesheet" href="/admin/terminal.css">
<style>
body{align-items:center;justify-content:center;}
</style></head><body>
<div class="login-box">
    <div class="login-title">Вход в систему</div>
    <form method="post">
        <label>PASSWORD</label>
        <input type="password" name="password" placeholder="enter passphrase..." autofocus required>
        <?php if(isset($loginError)) echo "<div class='login-err'>[DENIED] $loginError</div>"; ?>
        <button type="submit" class="btn btn-blue" style="width:100%;margin-top:8px;justify-content:center">&gt;_ Войти</button>
    </form>
</div></body></html>
<?php exit; }

// --- 2. ДАННЫЕ ДАШБОРДА ---
$msg = ''; $msgType = '';
$tab = $_GET['tab'] ?? 'dashboard';

// Статьи
$articleCount = 0;
$siteArticles = [];
$sites = get_dynamic_sites();
foreach ($sites as $s) {
    $cnt = count(getArticles($s, '', false));
    $siteArticles[$s] = $cnt;
    $articleCount += $cnt;
}

// Дайджест + ключи
$eventCount = 0;
$sourcesCount = 0;
$lastDigestDate = null;
$dbOk = false;
$openrouterKey = '';
$openrouterModel = '';
$openrouterKeyOk = false;
try {
    require_once __DIR__ . '/../digest/core/Db.php';
    require_once __DIR__ . '/../digest/core/Config.php';
    $db  = Db::getInstance();
    $db->initTables();
    $pdo = $db->getConnection();
    $dbOk = true;

    $eventCount   = (int)$pdo->query('SELECT COUNT(*) FROM digest_events')->fetchColumn();
    $sourcesCount = (int)$pdo->query('SELECT COUNT(*) FROM digest_sources WHERE active=1')->fetchColumn();

    $lastRow = $pdo->query('SELECT summary_date, items_count FROM digest_daily_summary ORDER BY summary_date DESC LIMIT 1')->fetch();
    if ($lastRow) $lastDigestDate = $lastRow;

    // Читаем ключ OpenRouter из БД
    $keyRow = $pdo->query("SELECT key_value, encrypted FROM admin_settings WHERE key_name='openrouter_key'")->fetch();
    if ($keyRow) {
        $rawKey = $keyRow['key_value'];
        if ($keyRow['encrypted'] && function_exists('openssl_decrypt')) {
            $encKey   = (defined('ENCRYPTION_KEY') && strlen(ENCRYPTION_KEY)>=32) ? ENCRYPTION_KEY : hash('sha256', ADMIN_PASSWORD);
            $data     = base64_decode($rawKey);
            $ivLen    = openssl_cipher_iv_length('AES-256-CBC');
            $iv       = substr($data, 0, $ivLen);
            $rawKey   = (string)openssl_decrypt(substr($data, $ivLen), 'AES-256-CBC', $encKey, 0, $iv);
        }
        $openrouterKey   = $rawKey ? ('sk-or-…' . substr($rawKey, -6)) : '';
        $openrouterKeyOk = strlen($rawKey) > 20;
    }
    $modelRow = $pdo->query("SELECT key_value FROM admin_settings WHERE key_name='openrouter_model'")->fetch();
    if ($modelRow) $openrouterModel = $modelRow['key_value'];

} catch (Throwable $e) { $dbOk = false; }

// Проверяем наличие config.php
$configOk = defined('ADMIN_PASSWORD') && strlen(ADMIN_PASSWORD) > 4;

// Проверяем доступность events.json
$eventsCount = 0;
$eventsFile  = __DIR__ . '/../events.json';
if (file_exists($eventsFile)) {
    $evArr = json_decode(file_get_contents($eventsFile), true) ?: [];
    $eventsCount = count($evArr);
    $eventsUpcoming = count(array_filter($evArr, fn($e) => empty($e['isPast'])));
} else {
    $eventsUpcoming = 0;
}

// Свежесть дайджеста
$digestFresh   = false;
$digestAgeHours= null;
if ($lastDigestDate) {
    $diffH = (time() - strtotime($lastDigestDate['summary_date'])) / 3600;
    $digestAgeHours = (int)$diffH;
    $digestFresh = $diffH < 26;
}

// Логи дайджеста
$logFile      = __DIR__ . '/../digest/logs/scraper.log';
$lastRunLog   = __DIR__ . '/../digest/logs/last_run.log';
$logExists    = file_exists($logFile) && filesize($logFile) > 0;
$logAge       = $logExists ? (int)((time() - filemtime($logFile)) / 3600) : null;

// --- 3. ЗАГРУЗКА ДАННЫХ ДЛЯ LAYOUT ---
$layoutJson = '';
$layoutFile = __DIR__ . '/../config/main_layout.json';
if (file_exists($layoutFile)) {
    $layoutJson = json_encode(json_decode(file_get_contents($layoutFile), true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
<!DOCTYPE html>
<html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Админка · v8.1 Terminal</title>
<!-- EasyMDE -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
<!-- Terminal UI -->
<link rel="stylesheet" href="/admin/terminal.css">
<script>
// Тема и режим загружаются до рендеринга, чтобы не было мерцания
(function(){
    var t = localStorage.getItem('adm_theme') || 'dark';
    var m = localStorage.getItem('adm_mode')  || 'dev';
    document.documentElement.setAttribute('data-theme', t);
    document.documentElement.setAttribute('data-mode', m);
})();
</script>
</head><body>

<aside>
    <h3>GO &gt;_ CMS</h3>
    <a href="?tab=dashboard" class="<?= $tab=='dashboard'?'active':'' ?>">&#x25a1; Дашборд</a>
    <a href="?tab=layout" class="<?= $tab=='layout'?'active':'' ?>">::  Главная</a>
    <a href="?tab=cms" class="<?= $tab=='cms'?'active':'' ?>">&gt;_ CMS редактор</a>
    <a href="?tab=digest" class="<?= $tab=='digest'?'active':'' ?>">[&gt;] Дайджест</a>
    <a href="?tab=sources" class="<?= $tab=='sources'?'active':'' ?>">[+] Источники</a>
    <a href="?tab=events" class="<?= $tab=='events'?'active':'' ?>">[*] Мероприятия</a>
    <a href="?tab=seo" class="<?= $tab=='seo'?'active':'' ?>">#   SEO (PHP)</a>
    <a href="?tab=sections" class="<?= $tab=='sections'?'active':'' ?>">=   Разделы</a>
    <a href="?tab=settings" class="<?= $tab=='settings'?'active':'' ?>">&#x1f512; Ключи</a>
    <a href="?tab=prompts" class="<?= $tab=='prompts'?'active':'' ?>">&gt;_ Ассистенты</a>
    <a href="?tab=ai" class="<?= $tab=='ai'?'active':'' ?>">■ AI чат</a>
    <hr>
    <a href=".." target="_blank">&#x2197; На сайт</a>
    <a href="?logout=1">✕ Выйти</a>
    <hr>
    <!-- Переключатели темы и режима -->
    <button class="aside-toggle" id="adm-theme-toggle" onclick="adminToggleTheme()" title="Светлая/Тёмная">
        <span class="dev-only">◑ theme --light</span>
        <span class="mgr-only">◑ Светлая</span>
    </button>
    <button class="aside-toggle" id="adm-mode-toggle" onclick="adminToggleMode()" title="Режим вывода">
        <span class="dev-only">&gt;_ role --mgr</span>
        <span class="mgr-only">≡ Управление</span>
    </button>
</aside>

<main>
<?php if($msg): ?><div class="alert alert-<?= $msgType ?>"><?= $msg ?></div><?php endif; ?>

<?php if($tab==='dashboard'): ?>
    <div class="dash-header">
        <h2>Дашборд</h2>
        <div class="dash-time"><?= date('d.m.Y H:i') ?></div>
    </div>

    <!-- Статус системы -->
    <div class="status-grid">

        <!-- БД -->
        <div class="status-card <?= $dbOk ? 'ok' : 'err' ?>">
            <div class="sc-icon"><?= $dbOk ? '✔' : '✕' ?></div>
            <div class="sc-body">
                <div class="sc-title"><span class="dev-only">MySQL</span><span class="mgr-only">База данных</span></div>
                <div class="sc-sub"><?= $dbOk ? 'Подключено' : 'Недоступно — проверьте config.php' ?></div>
            </div>
        </div>

        <!-- OpenRouter -->
        <div class="status-card <?= $openrouterKeyOk ? 'ok' : 'warn' ?>">
            <div class="sc-icon"><?= $openrouterKeyOk ? '✔' : '!' ?></div>
            <div class="sc-body">
                <div class="sc-title"><span class="dev-only">OpenRouter API</span><span class="mgr-only">Ключ AI</span></div>
                <div class="sc-sub">
                    <?php if ($openrouterKeyOk): ?>
                        <span class="dev-only"><?= htmlspecialchars($openrouterKey) ?></span>
                        <span class="mgr-only">Настроен</span>
                        <?php if ($openrouterModel): ?>
                        &nbsp;&middot; <span class="dev-only"><?= htmlspecialchars($openrouterModel) ?></span><span class="mgr-only">Модель задана</span>
                        <?php endif; ?>
                    <?php else: ?>
                        Ключ не задан — <a href="?tab=settings" style="color:var(--t-yellow)">Добавить в Настройках</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- config.php -->
        <div class="status-card <?= $configOk ? 'ok' : 'err' ?>">
            <div class="sc-icon"><?= $configOk ? '✔' : '✕' ?></div>
            <div class="sc-body">
                <div class="sc-title"><span class="dev-only">config.php</span><span class="mgr-only">Конфигурация</span></div>
                <div class="sc-sub"><?= $configOk ? 'Загружен' : 'Не найден — создайте из config.example.php' ?></div>
            </div>
        </div>

        <!-- Источники дайджеста -->
        <div class="status-card <?= $sourcesCount > 0 ? 'ok' : 'warn' ?>">
            <div class="sc-icon"><?= $sourcesCount > 0 ? $sourcesCount : '0' ?></div>
            <div class="sc-body">
                <div class="sc-title"><span class="dev-only">Источники</span><span class="mgr-only">Источники дайджеста</span></div>
                <div class="sc-sub">
                    <?= $sourcesCount > 0
                        ? "$sourcesCount активных источников"
                        : '<a href="?tab=sources" style="color:var(--t-yellow)">Добавьте источники</a>' ?>
                </div>
            </div>
        </div>

        <!-- Свежесть дайджеста -->
        <div class="status-card <?= $digestFresh ? 'ok' : ($lastDigestDate ? 'warn' : 'err') ?>">
            <div class="sc-icon"><?= $digestFresh ? '✔' : ($lastDigestDate ? '!' : '✕') ?></div>
            <div class="sc-body">
                <div class="sc-title"><span class="dev-only">Сводка дня</span><span class="mgr-only">Дайджест</span></div>
                <div class="sc-sub">
                    <?php if ($lastDigestDate): ?>
                        <?= htmlspecialchars($lastDigestDate['summary_date']) ?>
                        &middot; <?= (int)$lastDigestDate['items_count'] ?> материалов
                        <?php if (!$digestFresh): ?>
                            &middot; <span style="color:var(--t-yellow)"><?= $digestAgeHours ?>ч назад</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="?tab=digest" style="color:var(--t-yellow)">Запустите первый сбор</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Лог -->
        <div class="status-card <?= $logExists ? 'ok' : 'idle' ?>">
            <div class="sc-icon"><?= $logExists ? '✔' : '—' ?></div>
            <div class="sc-body">
                <div class="sc-title"><span class="dev-only">Лог коллектора</span><span class="mgr-only">Последний запуск</span></div>
                <div class="sc-sub">
                    <?php if ($logExists): ?>
                        <span class="dev-only"><?= $logAge === 0 ? 'Менее часа назад' : "{$logAge}ч назад" ?></span>
                        <span class="mgr-only"><?= $logAge === 0 ? 'Сейчас' : "{$logAge} часов назад" ?></span>
                    <?php else: ?>
                        Лог пустой
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /.status-grid -->

    <!-- KPI -->
    <div class="kpi-grid">
        <div class="kpi-box">
            <div class="kpi-box-label"><span class="dev-only">статьи [all]</span><span class="mgr-only">Публикации</span></div>
            <div class="kpi-box-value"><?= $articleCount ?></div>
            <div class="kpi-box-desc">Статей во всех сайтах</div>
        </div>
        <div class="kpi-box" style="--kpi-accent:var(--t-blue)">
            <div class="kpi-box-label"><span class="dev-only">digest_events</span><span class="mgr-only">Новости</span></div>
            <div class="kpi-box-value"><?= $eventCount ?></div>
            <div class="kpi-box-desc">Материалов в дайджесте</div>
        </div>
        <div class="kpi-box" style="--kpi-accent:var(--t-yellow)">
            <div class="kpi-box-label"><span class="dev-only">events.json</span><span class="mgr-only">Мероприятия</span></div>
            <div class="kpi-box-value"><?= $eventsUpcoming ?></div>
            <div class="kpi-box-desc">Предстоящих из <?= $eventsCount ?></div>
        </div>
        <div class="kpi-box" style="--kpi-accent:var(--t-cyan)">
            <div class="kpi-box-label"><span class="dev-only">digest_sources</span><span class="mgr-only">Источники AI</span></div>
            <div class="kpi-box-value"><?= $sourcesCount ?></div>
            <div class="kpi-box-desc">Активных источников</div>
        </div>
    </div>

    <!-- Статьи по сайтам -->
    <?php if (!empty($siteArticles)): ?>
    <h3 style="margin-top:24px">Статьи по подсайтам</h3>
    <div class="kpi-grid">
        <?php foreach ($siteArticles as $siteId => $cnt): ?>
        <div class="kpi-box" style="--kpi-accent:var(--t-muted)">
            <div class="kpi-box-label"><span class="dev-only"><?= htmlspecialchars($siteId) ?>/content</span><span class="mgr-only"><?= htmlspecialchars(strtoupper($siteId)) ?></span></div>
            <div class="kpi-box-value" style="font-size:1.8rem"><?= $cnt ?></div>
            <div class="kpi-box-desc"><a href="?tab=cms" style="color:inherit">Статей</a></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Быстрые действия -->
    <h3 style="margin-top:24px">Быстрый доступ</h3>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px">
        <a href="?tab=cms" class="btn"><span class="dev-only">&gt;_ new-article</span><span class="mgr-only">Новая статья</span></a>
        <a href="?tab=digest" class="btn"><span class="dev-only">[&gt;] run-digest</span><span class="mgr-only">Запустить сбор</span></a>
        <a href="?tab=events" class="btn"><span class="dev-only">[*] add-event</span><span class="mgr-only">Добавить мероприятие</span></a>
        <a href="?tab=sources" class="btn"><span class="dev-only">[+] add-source</span><span class="mgr-only">Добавить источник</span></a>
        <?php if (!$openrouterKeyOk): ?>
        <a href="?tab=settings" class="btn" style="border-color:var(--t-yellow);color:var(--t-yellow)">! Добавить OpenRouter ключ</a>
        <?php endif; ?>
    </div>

<?php elseif($tab==='layout'): ?>
    <h2>🎨 Конструктор Главной Страницы</h2>
    <p style="color:#94a3b8;margin-bottom:15px">Визуальное управление секциями с drag-and-drop. Никаких сырых JSON!</p>
    
    <div class="fs">
        <h3>Hero Section</h3>
        <div class="fr">
            <div style="flex:1">
                <label>Заголовок 1</label>
                <input type="text" id="hero-line1" placeholder="Стройка, проекты, код,">
            </div>
            <div style="flex:1">
                <label>Заголовок 2</label>
                <input type="text" id="hero-line2" placeholder="ИИ, мемы.">
            </div>
        </div>
        <label>Подзаголовок</label>
        <input type="text" id="hero-subtitle" style="width:100%">
        <label>Описание</label>
        <textarea id="hero-desc" style="min-height:80px"></textarea>
        <button type="button" class="btn btn-blue" id="btn-save-hero" style="margin-top:10px">💾 Сохранить Hero</button>
    </div>

    <div class="fs">
        <h3>Секции (укажите порядок)</h3>
        <div id="sections-container">
            <!-- Секции будут загружены динамически -->
        </div>
        <hr style="border-color:var(--border);margin:20px 0;">
        <h4>Добавить новую секцию</h4>
        <div class="fr">
            <div style="flex:1">
                <label>ID секции</label>
                <input type="text" id="new-section-id" placeholder="например: portfolio">
            </div>
            <div style="flex: 1">
                <label>Порядок</label>
                <input type="number" id="new-section-order" value="0" min="0">
            </div>
            <div style="flex:1">
                <label>Тип</label>
                <select id="new-section-type">
                    <option value="dev_grid">Dev Grid</option>
                    <option value="net_grid">Net Grid</option>
                    <option value="tool_box">Tool Box</option>
                    <option value="cms_loop">CMS Loop</option>
                </select>
            </div>
        </div>
        <label>Заголовок секции</label>
        <input type="text" id="new-section-title" placeholder="🛠 Мои разработки">
        <button type="button" class="btn btn-blue" id="btn-add-section">➕ Добавить секцию</button>
    </div>
    <div id="layout-status" style="margin-top:10px;font-size:.9rem;"></div>

<?php elseif($tab==='cms'): ?>
    <h2>🗺️ Глобальный Проводник (Engine Mode)</h2>
    <p style="color:#94a3b8;margin-bottom:15px">Единое дерево файлов для всей экосистемы (Markdown + PHP шаблоны).</p>
    <div style="display:flex;">
        <div class="cms-tree" id="cms-tree">
            <h4 style="margin-top:0;font-size:1rem;">📁 Все сайты</h4>
            <div id="tree-loading">Загрузка...</div>
        </div>
        <div class="cms-editor" id="editor-panel">
            <!-- Markdown Editor (EasyMDE) -->
            <div id="md-editor" style="display:none;">
                <form id="cms-form">
                    <div class="fr">
                        <div style="flex:1">
                            <label>Сайт</label>
                            <input type="text" id="inp-site" style="width:100%;background:#1e293b;border:1px solid var(--border);color:#fff;padding:8px;border-radius:6px;" readonly>
                        </div>
                        <div style="flex:1">
                            <label>Заголовок</label>
                            <input type="text" name="title" id="inp-title" required style="width:100%">
                        </div>
                        <div style="flex:1">
                            <label>Slug</label>
                            <input type="text" name="slug" id="inp-slug" required style="width:100%">
                        </div>
                    </div>
                    <div class="fr">
                        <div style="flex:1">
                            <label>Теги (через запятую)</label>
                            <input type="text" name="tags" id="inp-tags" style="width:100%">
                        </div>
                        <div style="display:flex;gap:10px;align-items:center;margin-bottom:10px;margin-top:25px;">
                            <input type="checkbox" name="badge" value="new" style="width:auto"> <span style="font-size:.9rem;">Бейдж: NEW</span>
                            <input type="checkbox" name="stub" style="width:auto"> <span style="font-size:.9rem;">Заглушка</span>
                        </div>
                    </div>
                    <label>Контент (Markdown)</label>
                    <textarea id="inp-md" name="content"></textarea>
                    <div style="display:flex; gap:10px; margin-bottom:10px;">
                        <button type="button" class="btn" id="btn-format" style="background:#1e293b; border:1px solid #334155;">✨ AI Формат</button>
                        <button type="button" class="btn" style="background:#1e293b; border:1px solid #334155;" onclick="clearDraft()">× Сброс</button>
                        <span id="draft-status" style="font-size:.8rem; color:#64748b; align-self:center;"></span>
                    </div>
                    <button type="button" class="btn" id="btn-save-article">✨ Опубликовать</button>
                </form>
            </div>
            <!-- PHP Editor (Textarea) -->
            <div id="php-editor" style="display:none;">
                <form id="php-form">
                    <div class="fr">
                        <div style="flex:1">
                            <label>Сайт</label>
                            <input type="text" id="php-site" style="width:100%;background:#1e293b;border:1px solid var(--border);color:#fff;padding:8px;border-radius:6px;" readonly>
                        </div>
                        <div style="flex:1">
                            <label>Файл</label>
                            <input type="text" id="php-file" style="width:100%;background:#1e293b;border:1px solid var(--border);color:#fff;padding:8px;border-radius:6px;" readonly>
                        </div>
                    </div>
                    <label>PHP Код</label>
                    <textarea id="inp-php" name="content" style="width:100%;min-height:500px;background:#1e293b;border:1px solid var(--border);color:#0f0;font-family:monospace;padding:15px;border-radius:6px;font-size:13px;"></textarea>
                    <button type="button" class="btn btn-blue" id="btn-save-php" style="margin-top:10px;">💾 Сохранить код</button>
                    <div id="php-status" style="margin-top:10px;font-size:.9rem;"></div>
                </form>
            </div>
        </div>
    </div>

<?php elseif($tab==='digest'): ?>
    <h2>Дайджест — запуск сбора</h2>
    <p style="margin-bottom:14px">Ручной запуск AI-сбора новостей. Источники настраиваются во вкладке «Источники».</p>
    <div class="digest-controls">
        <button id="btn-run-digest" class="btn">&gt;_ Запустить сбор</button>
        <button id="btn-run-summary" class="btn" style="margin-left:4px">[S] Сводка дня</button>
        <span id="digest-status" class="status-indicator status-idle">✕ Ожидание</span>
        <button id="btn-refresh-log" class="btn" style="background:transparent;border-color:var(--t-border2)">[R] Обновить</button>
        <button id="btn-clear-log" class="btn btn-red" style="margin-left:4px">[X] Очистить</button>
    </div>
    <div id="console-output" class="console-box">&gt;_ Нажмите «Запустить сбор», чтобы начать...</div>

<?php elseif($tab==='sources'): ?>
    <h2>Источники Дайджеста</h2>
    <p>Адреса сайтов, которые AI обходит при сборе. Категория определяет промпт AI. Кастомный промпт переопределяет дефолт.</p>
    <div class="fs" style="margin-top:14px">
        <h3>Добавить источник</h3>
        <div class="fr">
            <div style="flex:2"><label>URL</label><input type="text" id="src-url" placeholder="https://habr.com/ru/flows/ai/"></div>
            <div style="flex:1"><label>Название</label><input type="text" id="src-name" placeholder="(авто)"></div>
            <div style="flex:1">
                <label>Категория</label>
                <select id="src-cat">
                    <option value="ai">ИИ / ML</option>
                    <option value="bim">BIM / ТИМ</option>
                    <option value="events">Мероприятия</option>
                    <option value="norms">Нормативка</option>
                </select>
            </div>
        </div>
        <label>Промпт AI (оставь пустым для дефолтного)</label>
        <textarea id="src-prompt" style="min-height:60px" placeholder="Например: Ты — редактор дайджеста по BIM. Ищи только российские новости..."></textarea>
        <button class="btn" id="btn-add-source">[+] Добавить</button>
        <div id="src-status" style="margin-top:8px;font-size:.78rem"></div>
    </div>
    <div id="sources-list">Загрузка...</div>

<?php elseif($tab==='events'): ?>
    <h2>Мероприятия (Networking)</h2>
    <p>Календарь конференций — отображается на /networking.php. AI добавляет мероприятия автоматически из дайджеста.</p>
    <div class="fs" style="margin-top:14px">
        <h3>Добавить мероприятие</h3>
        <div class="fr">
            <div style="flex:2"><label>Название</label><input type="text" id="ev-title" placeholder="100+ TechnoBuild 2026"></div>
            <div style="flex:1"><label>Месяц</label><input type="text" id="ev-month" placeholder="СЕНТЯБРЬ"></div>
            <div style="flex:1"><label>Дни</label><input type="text" id="ev-days" placeholder="29-02"></div>
        </div>
        <div class="fr">
            <div style="flex:1"><label>Город</label><input type="text" id="ev-city" placeholder="ЕКБ"></div>
            <div style="flex:3"><label>Ссылка</label><input type="text" id="ev-link" placeholder="https://"></div>
        </div>
        <label>Описание</label>
        <textarea id="ev-desc" style="min-height:60px"></textarea>
        <label>Теги (через запятую, «Спикер» = зелёный бейдж)</label>
        <input type="text" id="ev-tags" placeholder="BIM, ТИМ, Спикер">
        <button class="btn" id="btn-add-event">[+] Добавить</button>
        <div id="ev-status" style="margin-top:8px;font-size:.78rem"></div>
    </div>
    <div id="events-list">Загрузка...</div>

<?php elseif($tab==='ai'): ?>
    <h2>AI ассистент</h2>
    <p>Универсальный чат через OpenRouter. Использует ключ и модель из Настроек. Пишите любые вопросы по BIM, КП, дайджесту, коду.</p>

    <div class="fs" style="margin-bottom:12px">
        <div class="fr">
            <div style="flex:2">
                <label>Системный промпт</label>
                <textarea id="ai-system" style="min-height:48px" placeholder="Ты — эксперт по BIM, проектированию и управлению проектами (редактируется).Отвечай по-русски, кратко, без воды."></textarea>
            </div>
            <div style="flex:1">
                <label>Модель</label>
                <input type="text" id="ai-model-override" placeholder="(из настроек, или введите свою)" value="<?= htmlspecialchars($openrouterModel ?: 'openrouter/free') ?>">
                <button class="btn" style="margin-top:4px;width:100%" id="ai-clear-btn">[X] Очистить чат</button>
            </div>
        </div>
    </div>

    <div id="ai-chat-box" style="background:var(--t-bg);border:1px solid var(--t-border);border-radius:var(--t-r-md);padding:14px;min-height:320px;max-height:500px;overflow-y:auto;font-size:.82rem;line-height:1.65;margin-bottom:10px;font-family:inherit;"></div>

    <div style="display:flex;gap:8px">
        <textarea id="ai-user-input" style="flex:1;min-height:68px;resize:vertical" placeholder="Задай вопрос..."></textarea>
        <div style="display:flex;flex-direction:column;gap:6px">
            <button class="btn" id="ai-send-btn" style="height:48px;min-width:90px">[Enter]</button>
            <div id="ai-tokens" style="font-size:.65rem;color:var(--t-muted);text-align:center"></div>
        </div>
    </div>

    <!-- Быстрые промпты -->
    <div style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap">
        <button class="btn" style="font-size:.7rem;padding:4px 10px" onclick="aiQuickPrompt('Помоги сформулировать ТЗ на разработку СОД для BIM-проекта')">TЗ на СОД</button>
        <button class="btn" style="font-size:.7rem;padding:4px 10px" onclick="aiQuickPrompt('Составь чек-лист проверки BIM-модели по ГОСТ 21.101-2026')">BIM чек-лист</button>
        <button class="btn" style="font-size:.7rem;padding:4px 10px" onclick="aiQuickPrompt('Напиши шаблон frontmatter для новой статьи для go-cms по теме: ')">Frontmatter</button>
        <button class="btn" style="font-size:.7rem;padding:4px 10px" onclick="aiQuickPrompt('Сформируй краткое техническое задание для OpenRouter API: ')">OpenRouter задание</button>
        <button class="btn" style="font-size:.7rem;padding:4px 10px" onclick="aiQuickPrompt('Предложи 5 профессиональных источников новостей по BIM и ИИ для дайджеста')">BIM-источники</button>
    </div>

<?php elseif($tab==='seo'): ?>
    <h2>🔍 SEO PHP-файлов</h2>
    <p style="color:#94a3b8;margin-bottom:15px">Сканирование мета-тегов и управление SEO-оверрайдами.</p>
    <div id="seo-list">Загрузка...</div>

<?php elseif($tab==='sections'): ?>
    <h2>📑 Управление разделами</h2>
    <p style="color:#94a3b8;margin-bottom:15px">Редактирование разделов для подсайтов.</p>
    <div id="sections-editor">Загрузка...</div>

<?php elseif($tab==='settings'): ?>
    <h2>🔐 Система и Ключи</h2>
    <p style="color:#94a3b8;margin-bottom:15px">Настройки API-ключей (шифруются AES-256).</p>
    <div class="fs">
        <div class="fr">
            <div style="flex:1">
                <label>OpenRouter Key (sk-or-...)</label>
                <input type="password" id="set-openrouter_key" placeholder="sk-or-v1-...">
            </div>
    <div style="flex:1">
        <label>OpenRouter Model</label>
        <input type="text" id="set-openrouter_model" list="model-list" placeholder="openrouter/free" style="width:100%;background:#0f172a;border:1px solid var(--border);color:#fff;padding:10px;border-radius:6px;margin-bottom:10px;box-sizing:border-box;">
    </div>
        </div>
        <div class="fr">
            <div style="flex:1">
                <label>GitHub Token</label>
                <input type="password" id="set-github_token" placeholder="ghp_...">
            </div>
            <div style="flex:1">
                <label>Telegram Bot Token</label>
                <input type="password" id="set-telegram_token" placeholder="123456:ABC-DEF...">
            </div>
        </div>
        <div class="fr">
            <div style="flex:1">
                <label>Digest Admin Password</label>
                <input type="password" id="set-digest_admin_pass" placeholder="Пароль для дайджеста">
            </div>
        </div>
        <button type="button" class="btn btn-blue" id="btn-save-settings">💾 Сохранить настройки</button>
        <div id="settings-status" style="margin-top:10px;font-size:.9rem;"></div>
    </div>

<?php elseif($tab==='prompts'): ?>
    <h2>🤖 ИИ Ассистенты</h2>
    <p style="color:#94a3b8;margin-bottom:15px">Управление промптами для ИИ-ассистентов.</p>
    <div class="fs">
        <div id="prompts-list">Загрузка...</div>
        <hr style="border-color:var(--border);margin:20px 0">
        <h3>Добавить / Редактировать ассистента</h3>
        <div class="fr">
            <div style="flex:1">
                <label>ID (slug)</label>
                <input type="text" id="prompt-id" placeholder="например: article_writer">
            </div>
            <div style="flex:1">
                <label>Название</label>
                <input type="text" id="prompt-name" placeholder="Article Writer">
            </div>
        </div>
        <div class="fr">
    <div style="flex:1">
        <label>Базовая модель</label>
        <input type="text" id="prompt-model" list="model-list" placeholder="openrouter/free" style="width:100%;background:#0f172a;border:1px solid var(--border);color:#fff;padding:10px;border-radius:6px;margin-bottom:10px;box-sizing:border-box;">
    </div>
            <div style="flex:1">
                <label>Temperature: <span id="temp-value">0.7</span></label>
                <input type="range" id="prompt-temperature" min="0" max="2" step="0.1" value="0.7" oninput="document.getElementById('temp-value').textContent=this.value">
            </div>
        </div>
        <label>System Prompt</label>
        <textarea id="prompt-system" style="min-height:150px" placeholder="You are a helpful assistant..."></textarea>
        <div style="margin-top:10px">
            <button type="button" class="btn btn-blue" id="btn-save-prompt">💾 Сохранить ассистента</button>
            <button type="button" class="btn btn-red" id="btn-clear-prompt" style="margin-left:10px">✕ Очистить</button>
        </div>
        <div id="prompt-status" style="margin-top:10px;font-size:.9rem;"></div>
    </div>
<?php endif; ?>

</main>
<datalist id="model-list">
    <option value="openrouter/free">
    <option value="google/gemma-3-27b-it:free">
    <option value="meta-llama/llama-3.1-8b-instruct:free">
</datalist>
<script>
// --- ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ ---
let currentSite = 'main';
let currentFile = '';
let promptsArray = [];
let editingIndex = -1;
let layoutData = <?= $layoutJson ?: '{}' ?>;
let sectionsSortable = null;

// Хелпер для безопасного JSON парсинга
async function safeJsonFetch(url, options = {}) {
    const res = await fetch(url, options);
    const text = await res.text(); // Читаем тело один раз
    if (!res.ok) {
        throw new Error(`HTTP ${res.status}: ${text}`);
    }
    try {
        return JSON.parse(text); // Парсим текст, который уже прочитали
    } catch (e) {
        console.error('JSON parse error:', e);
        console.error('Raw response:', text);
        throw e;
    }
}

// Обертка для парсинга с обработкой ошибок
async function safeJsonParse(res) {
    const text = await res.text();
    try {
        return await res.json();
    } catch (e) {
        console.error('JSON parse error:', e);
        console.error('Raw response:', text);
        throw e;
    }
}

// 1. Инициализация EasyMDE
const easyMDE = new EasyMDE({
    element: document.getElementById('inp-md'),
    spellChecker: false,
    autosave: { enabled: false },
    placeholder: "Вставь текст сюда или напиши с нуля...",
    toolbar: [
        'bold', 'italic', 'heading', '|',
        'quote', 'unordered-list', 'ordered-list', '|',
        'link', 'image', 'table', '|',
        'preview', 'side-by-side', 'fullscreen', '|',
        'guide'
    ],
    renderingConfig: {
        codeSyntaxHighlighting: false,
        markedOptions: { silent: true }
    }
});

// 2. КНОПКА AI ФОРМАТИРОВАТЬ
const btnFormat = document.getElementById('btn-format');
if (btnFormat) {
    btnFormat.addEventListener('click', async () => {
        const text = easyMDE.value();
        if (text.length < 50) { alert('Текст слишком короткий для ИИ'); return; }
        
        btnFormat.innerHTML = '⏳ AI форматирует...';
        btnFormat.disabled = true;
        
        try {
            const fd = new FormData();
            fd.append('text', text);
            
            const res = await fetch('/admin/api/format.php', { method: 'POST', body: fd });
            if (!res.ok) {
                const text = await res.text();
                console.error('Format API error:', res.status, text);
                btnFormat.innerHTML = '❌ Ошибка ' + res.status;
                setTimeout(() => { btnFormat.innerHTML = '✨ AI Форматировать'; btnFormat.disabled = false; }, 2000);
                return;
            }
            const data = await res.json();
            
            if (data.formatted) {
                easyMDE.value(data.formatted);
                btnFormat.innerHTML = '✅ Готово';
            } else {
                console.error('Format error:', data.error);
                btnFormat.innerHTML = '❌ ' + (data.error || 'Ошибка');
            }
        } catch(e) {
            console.error('Fetch error:', e);
            btnFormat.innerHTML = '❌ Сеть';
        }
        setTimeout(() => { btnFormat.innerHTML = '✨ AI Форматировать'; btnFormat.disabled = false; }, 2000);
    });
}

// 3. СОХРАНЕНИЕ СТАТЬИ (через API)
const btnSaveArticle = document.getElementById('btn-save-article');
if (btnSaveArticle) {
    btnSaveArticle.addEventListener('click', async () => {
        const form = document.getElementById('cms-form');
        const formData = new FormData(form);
        formData.set('content', easyMDE.value());
        
        btnSaveArticle.innerHTML = '⏳ Сохранение...';
        btnSaveArticle.disabled = true;
        
        try {
            const res = await fetch('/admin/api/save_article.php', { method: 'POST', body: formData });
            const data = await safeJsonParse(res);
            if (data.ok) {
                alert('✅ ' + data.message);
                btnSaveArticle.innerHTML = '✅ Сохранено';
                // Обновляем дерево
                loadTree();
            } else {
                alert('❌ ' + (data.error || 'Ошибка'));
                btnSaveArticle.innerHTML = '❌ Ошибка';
            }
        } catch(e) {
            alert('❌ Сеть: ' + e.message);
            btnSaveArticle.innerHTML = '❌ Сеть';
        }
        setTimeout(() => { btnSaveArticle.innerHTML = '✨ Опубликовать'; btnSaveArticle.disabled = false; }, 2000);
    });
}

// 4. ЗАГРУЗКА ДЕРЕВА ФАЙЛОВ (Global Navigator)
async function loadTree() {
    const treeDiv = document.getElementById('tree-loading');
    if (!treeDiv) return;
    
    try {
        const data = await safeJsonFetch('/admin/api/list_articles.php');
        if (!data.ok) { treeDiv.innerHTML = 'Ошибка: ' + (data.error || ''); return; }
        
        let html = '';
        (data.tree || []).forEach(siteData => {
            html += `<div style="margin-bottom:10px;">
                <div style="font-weight:bold;padding:6px;color:var(--yellow);">📁 ${siteData.site}</div>`;
            (siteData.articles || []).forEach(article => {
                const isActive = (currentSite === siteData.site && currentFile === article.file) ? 'active' : '';
                const marker = article.marker || (article.type === 'php' ? '[PHP]' : '[MD]');
                html += `<div class="cms-tree-item ${isActive}" onclick="loadArticle('${siteData.site}', '${article.file}', '${article.type}')">
                    ${marker} ${article.title || article.file}
                </div>`;
            });
            html += '</div>';
        });
        document.getElementById('cms-tree').innerHTML = '<h4 style="margin-top:0;font-size:1rem;">📁 Все сайты</h4>' + html;
    } catch(e) {
        treeDiv.innerHTML = '❌ Сеть: ' + e.message;
    }
}

async function loadArticle(site, file, type = 'md') {
    currentSite = site;
    currentFile = file;
    
    // Скрываем/показываем редакторы
    document.getElementById('md-editor').style.display = type === 'md' ? 'block' : 'none';
    document.getElementById('php-editor').style.display = type === 'php' ? 'block' : 'none';
    
    if (type === 'md') {
        // Markdown редактор
        try {
            const res = await fetch(`/admin/api/get_article.php?site=${site}&file=${file}`); 
            const data = await res.json();
            if (!data.ok) { alert('Ошибка: ' + (data.error || '')); return; }
        
            // Заполняем форму
            document.getElementById('inp-site').value = site;
            document.getElementById('inp-title').value = data.meta.title || '';
            document.getElementById('inp-slug').value = data.meta.slug || '';
            document.getElementById('inp-tags').value = (data.meta.tags || []).join(', ');
            easyMDE.value(data.body || '');
        } catch(e) {
            if (e instanceof SyntaxError) {
                const rawText = await res.text();
                console.error('Get article API JSON parse error:', e);
                console.error('Raw response:', rawText);
                alert('Ошибка парсинга JSON ответа сервера');
            } else {
                alert('❌ Сеть');
            }
        }
    } else if (type === 'php') {
        // PHP редактор
        try {
            const res = await fetch(`/admin/api/get_article.php?site=${site}&file=${file}`);
            const data = await res.json();
            if (!data.ok) { alert('Ошибка: ' + (data.error || '')); return; }
            
            document.getElementById('php-site').value = site;
            document.getElementById('php-file').value = file;
            document.getElementById('inp-php').value = data.body || '';
        } catch(e) {
            if (e instanceof SyntaxError) {
                const rawText = await res.text();
                console.error('Get PHP file API JSON parse error:', e);
                console.error('Raw response:', rawText);
                alert('Ошибка парсинга JSON');
            } else {
                alert('❌ Сеть');
            }
        }
    }
    
    // Обновляем дерево
    loadTree();
}

if (document.getElementById('cms-tree')) {
    loadTree();
}

// 5. КОНСТРУКТОР LAYOUT (JSON Builder with Order)
function renderLayoutBuilder() {
    const container = document.getElementById('sections-container');
    if (!container || !layoutData.sections) return;
    
    let html = '';
    (layoutData.sections || []).forEach((sec, idx) => {
        const order = sec.order !== undefined ? sec.order : idx;
        html += `<div class="layout-section" data-idx="${idx}">
            <span style="color:var(--yellow);margin-right:10px;">${order}</span>
            <strong>${sec.title || 'Без названия'}</strong> 
            <span style="color:var(--yellow);font-size:.8rem;margin-left:10px;">[${sec.type || ''}]</span>
            <input type="number" class="section-order" value="${order}" min="0" style="width:60px;margin-left:auto;" onchange="updateSectionOrder(${idx}, this.value)">
            <span style="float:right;margin-left:10px;">
                <button type="button" class="btn btn-blue" style="padding:3px 8px;font-size:.7rem;" onclick="editSection(${idx})">✎</button>
                <button type="button" class="btn btn-red" style="padding:3px 8px;font-size:.7rem;" onclick="deleteSection(${idx})">🗑</button>
            </span>
        </div>`;
    });
    container.innerHTML = html;
}

function updateSectionOrder(idx, newOrder) {
    if (layoutData.sections[idx]) {
        layoutData.sections[idx].order = parseInt(newOrder) || 0;
        saveLayout();
    }
}

function editSection(idx) {
    const sec = layoutData.sections[idx];
    if (!sec) return;
    // Простая реализация - заполняем форму добавления
    document.getElementById('new-section-id').value = sec.id || '';
    document.getElementById('new-section-title').value = sec.title || '';
    document.getElementById('new-section-type').value = sec.type || 'dev_grid';
    editingIndex = idx;
    document.getElementById('layout-status').textContent = 'Редактирование: ' + (sec.title || sec.id);
    document.getElementById('layout-status').style.color = 'var(--yellow)';
}

function deleteSection(idx) {
    if (!confirm('Удалить секцию?')) return;
    layoutData.sections.splice(idx, 1);
    renderLayoutBuilder();
}

// Загрузка Layout в Builder
if (document.getElementById('sections-container')) {
    renderLayoutBuilder();
    
    // Заполнение Hero
    if (layoutData.hero) {
        document.getElementById('hero-line1').value = layoutData.hero.title_line1 || '';
        document.getElementById('hero-line2').value = layoutData.hero.title_line2 || '';
        document.getElementById('hero-subtitle').value = layoutData.hero.subtitle || '';
        document.getElementById('hero-desc').value = layoutData.hero.description || '';
    }
    
    // Сохранение Hero
    document.getElementById('btn-save-hero').addEventListener('click', () => {
        layoutData.hero = {
            title_line1: document.getElementById('hero-line1').value,
            title_line2: document.getElementById('hero-line2').value,
            subtitle: document.getElementById('hero-subtitle').value,
            description: document.getElementById('hero-desc').value
        };
        saveLayout();
    });
    
    // Добавление секции
    document.getElementById('btn-add-section').addEventListener('click', () => {
        const id = document.getElementById('new-section-id').value.trim();
        const title = document.getElementById('new-section-title').value.trim();
        const type = document.getElementById('new-section-type').value;
        const order = parseInt(document.getElementById('new-section-order').value) || 0;
        
        if (!id || !title) { alert('Заполните ID и заголовок'); return; }
        
        const newSec = { id, title, type, order, visible: true, cards: [] };
        if (editingIndex >= 0) {
            layoutData.sections[editingIndex] = newSec;
            editingIndex = -1;
        } else {
            layoutData.sections.push(newSec);
        }
        renderLayoutBuilder();
        clearSectionForm();
    });
}

async function saveLayout() {
    try {
        const res = await fetch('/admin/api/layout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(layoutData)
        });
        let data;
        try {
            data = await res.json();
        } catch (jsonError) {
            const rawText = await res.text();
            console.error('Layout API JSON parse error:', jsonError);
            console.error('Raw response:', rawText);
            const statusEl = document.getElementById('layout-status');
            statusEl.textContent = '❌ Ошибка парсинга JSON';
            statusEl.style.color = 'var(--red)';
            setTimeout(() => { statusEl.textContent = ''; }, 3000);
            return;
        }
        const statusEl = document.getElementById('layout-status');
        if (data.ok) {
            statusEl.textContent = '✅ ' + data.message;
            statusEl.style.color = 'var(--green)';
        } else {
            statusEl.textContent = '❌ ' + (data.error || 'Ошибка');
            statusEl.style.color = 'var(--red)';
        }
        setTimeout(() => { statusEl.textContent = ''; }, 3000);
    } catch(e) {
        alert('❌ Сеть');
    }
}

function clearSectionForm() {
    editingIndex = -1;
    document.getElementById('new-section-id').value = '';
    document.getElementById('new-section-title').value = '';
    document.getElementById('layout-status').textContent = '';
}

// 6. SEO ТАБ
if (document.getElementById('seo-list')) {
    fetch('/admin/api/scan_php.php')
        .then(r => r.json())
        .then(data => {
            if (data.error) { 
                document.getElementById('seo-list').innerHTML = 'Ошибка: ' + data.error;
                return;
            }
            let html = '<table style="width:100%; border-collapse:collapse;">';
            html += '<tr style="background:var(--panel);"><th style="padding:8px;text-align:left">Файл</th><th style="padding:8px;text-align:left">SEO Title</th><th style="padding:8px;text-align:left">SEO Desc</th></tr>';
            (data.pages || []).forEach(p => {
                html += `<tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:8px">${p.file}</td>
                    <td style="padding:8px">${p.seoTitle || ''}</td>
                    <td style="padding:8px">${p.seoDesc || ''}</td>
                </tr>`;
            });
            html += '</table>';
            document.getElementById('seo-list').innerHTML = html;
        });
}

// 7. SECTIONS ТАБ
if (document.getElementById('sections-editor')) {
    const siteSelect = prompt('Для какого сайта показать разделы?', 'main') || 'main';
    fetch(`/admin/api/sections.php?site=${siteSelect}`)
        .then(async (r) => {
            if (!r.ok) {
                const text = await r.text();
                throw new Error(`HTTP ${r.status}: ${text}`);
            }
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Sections API parse error:', e);
                console.error('Raw response:', text);
                throw e;
            }
        })
        .then(data => {
            if (Array.isArray(data)) {
                if (data.length === 0) {
                    document.getElementById('sections-editor').innerHTML = '<p style="color:#64748b;">Нет разделов для сайта "' + siteSelect + '"</p>';
                } else {
                    document.getElementById('sections-editor').innerHTML = '<p style="margin-bottom:10px;">Разделы сайта "' + siteSelect + '":</p><p style="color:var(--yellow);">' + data.join(', ') + '</p>';
                }
            } else {
                document.getElementById('sections-editor').innerHTML = 'Ошибка: некорректный формат данных';
            }
        })
        .catch(e => {
            console.error('Sections error:', e);
            document.getElementById('sections-editor').innerHTML = '❌ Ошибка загрузки: ' + e.message;
        });
}

// 8. ДАЙДЖЕСТ ЛОГИКА
const btnRun = document.getElementById('btn-run-digest');
const btnRefresh = document.getElementById('btn-refresh-log');
const consoleBox = document.getElementById('console-output');
const statusInd = document.getElementById('digest-status');

if (btnRun) {
    let logInterval = setInterval(fetchLog, 3000);

    function fetchLog() {
        fetch('/admin/api/digest_action.php?action=log')
            .then(r => r.json())
            .then(data => {
                if (data.logs && data.logs.length > 0) {
                    consoleBox.innerHTML = data.logs.join('\n').replace(/\[ERROR\]/g, '<span class="log-err">[ERROR]</span>').replace(/\[OK\]/g, '<span class="log-ok">[OK]</span>');
                    consoleBox.scrollTop = consoleBox.scrollHeight;
                }
                if (data.running) {
                    statusInd.className = 'status-indicator status-running';
                    statusInd.textContent = '⚡ Сбор идет...';
                } else {
                    statusInd.className = 'status-indicator status-idle';
                    statusInd.textContent = '✅ Готово';
                }
            })
            .catch(err => console.error(err));
    }

    btnRun.addEventListener('click', () => {
        btnRun.disabled = true;
        btnRun.textContent = '⏳ Запуск...';
        statusInd.className = 'status-indicator status-running';
        statusInd.textContent = '🚀 Запуск...';
        
        fetch('/admin/api/digest_action.php?action=run', {method: 'POST'})
            .then(r => r.json())
            .then(data => {
                btnRun.disabled = false;
                btnRun.textContent = '🚀 Запустить сбор новостей';
                if (data.error) alert('Ошибка: ' + data.error);
                fetchLog();
            });
    });

    btnRefresh.addEventListener('click', fetchLog);
}

// 9. НАСТРОЙКИ (settings.php)
if (document.getElementById('btn-save-settings')) {
    fetch('/admin/api/settings.php')
        .then(r => r.json())
        .then(data => {
            if (data.ok && data.settings) {
                const s = data.settings;
                if (s.openrouter_key) document.getElementById('set-openrouter_key').value = s.openrouter_key.value || '';
                if (s.openrouter_model) document.getElementById('set-openrouter_model').value = s.openrouter_model.value || 'openrouter/free';
                if (s.github_token) document.getElementById('set-github_token').value = s.github_token.value || '';
                if (s.telegram_token) document.getElementById('set-telegram_token').value = s.telegram_token.value || '';
                if (s.digest_admin_pass) document.getElementById('set-digest_admin_pass').value = s.digest_admin_pass.value || '';
            }
        });

    document.getElementById('btn-save-settings').addEventListener('click', async () => {
        const btn = document.getElementById('btn-save-settings');
        const statusEl = document.getElementById('settings-status');
        btn.innerHTML = '⏳ Сохранение...';
        btn.disabled = true;

        const payload = {};
        const fields = [
            { id: 'openrouter_key', enc: true },
            { id: 'openrouter_model', enc: false },
            { id: 'github_token', enc: true },
            { id: 'telegram_token', enc: true },
            { id: 'digest_admin_pass', enc: true }
        ];
        fields.forEach(f => {
            const val = document.getElementById('set-' + f.id).value.trim();
            if (val) {
                payload[f.id] = { value: val, encrypted: f.enc };
            }
        });

        try {
            const res = await fetch('/admin/api/settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const rawText = await res.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (jsonError) {
                console.error('Settings API JSON parse error:', jsonError);
                console.error('Raw response:', rawText);
                statusEl.textContent = '❌ Ошибка парсинга JSON';
                statusEl.style.color = 'var(--red)';
                btn.innerHTML = '💾 Сохранить настройки';
                btn.disabled = false;
                setTimeout(() => { statusEl.textContent = ''; }, 3000);
                return;
            }
            if (data.ok) {
                statusEl.textContent = '✅ ' + data.message;
                statusEl.style.color = 'var(--green)';
            } else {
                statusEl.textContent = '❌ ' + (data.error || 'Ошибка');
                statusEl.style.color = 'var(--red)';
            }
        } catch(e) {
            statusEl.textContent = '❌ Сеть';
            statusEl.style.color = 'var(--red)';
        }
        btn.innerHTML = '💾 Сохранить настройки';
        btn.disabled = false;
        setTimeout(() => { statusEl.textContent = ''; }, 3000);
    });
}

// 10. ИИ АССИСТЕНТЫ (prompts.php)
function renderPrompts() {
    const list = document.getElementById('prompts-list');
    if (!list) return;
    if (promptsArray.length === 0) {
        list.innerHTML = '<p style="color:#64748b;">Нет ассистентов. Добавьте первого.</p>';
        return;
    }
    let html = '<table style="width:100%; border-collapse:collapse;">';
    html += '<tr style="background:var(--panel);"><th style="padding:8px;text-align:left">Название</th><th style="padding:8px;text-align:left">ID</th><th style="padding:8px;text-align:left">Модель</th><th style="padding:8px;text-align:left">Temp</th><th></th></tr>';
    promptsArray.forEach((p, idx) => {
        html += `<tr style="border-bottom:1px solid var(--border);">
            <td style="padding:8px">${p.name || ''}</td>
            <td style="padding:8px">${p.id || ''}</td>
            <td style="padding:8px">${p.model || ''}</td>
            <td style="padding:8px">${p.temperature || 0.7}</td>
            <td style="padding:8px">
                <button type="button" class="btn btn-blue" style="padding:5px 10px;font-size:.8rem;" onclick="editPrompt(${idx})">✎</button>
                <button type="button" class="btn btn-red" style="padding:5px 10px;font-size:.8rem;" onclick="deletePrompt(${idx})">🗑</button>
            </td>
        </tr>`;
    });
    html += '</table>';
    list.innerHTML = html;
}

function editPrompt(idx) {
    const p = promptsArray[idx];
    if (!p) return;
    editingIndex = idx;
    document.getElementById('prompt-id').value = p.id || '';
    document.getElementById('prompt-name').value = p.name || '';
    document.getElementById('prompt-model').value = p.model || 'openrouter/free';
    document.getElementById('prompt-temperature').value = p.temperature || 0.7;
    document.getElementById('temp-value').textContent = p.temperature || 0.7;
    document.getElementById('prompt-system').value = p.system_prompt || '';
    document.getElementById('prompt-status').textContent = 'Редактирование: ' + (p.name || p.id);
    document.getElementById('prompt-status').style.color = 'var(--yellow)';
}

async function deletePrompt(idx) {
    const p = promptsArray[idx];
    if (!p) return;
    if (!confirm('Удалить ассистента "' + (p.name || p.id) + '"?')) return;
    promptsArray.splice(idx, 1);
    await savePrompts();
    renderPrompts();
}

async function savePrompts() {
    try {
            const res = await fetch('/admin/api/prompts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(promptsArray)
            });
            let data;
            try {
                data = await res.json();
            } catch (jsonError) {
                const rawText = await res.text();
                console.error('Prompts API JSON parse error:', jsonError);
                console.error('Raw response:', rawText);
                const statusEl = document.getElementById('prompt-status');
                statusEl.textContent = '❌ Ошибка парсинга JSON';
                statusEl.style.color = 'var(--red)';
                setTimeout(() => { statusEl.textContent = ''; }, 3000);
                return;
            }
            const statusEl = document.getElementById('prompt-status');
            if (data.ok) {
                statusEl.textContent = '✅ ' + data.message;
                statusEl.style.color = 'var(--green)';
            } else {
                statusEl.textContent = '❌ ' + (data.error || 'Ошибка');
                statusEl.style.color = 'var(--red)';
            }
            setTimeout(() => { statusEl.textContent = ''; }, 3000);
        } catch(e) {
            alert('❌ Сеть');
        }
}

if (document.getElementById('prompts-list')) {
    (async () => {
        try {
            const res = await fetch('/admin/api/prompts.php');
            let data;
            try {
                data = await res.json();
            } catch (jsonError) {
                const rawText = await res.text();
                console.error('Prompts list API JSON parse error:', jsonError);
                console.error('Raw response:', rawText);
                return;
            }
            if (data.ok && Array.isArray(data.prompts)) {
                promptsArray = data.prompts;
                renderPrompts();
            }
        } catch(e) {
            console.error('Fetch error:', e);
        }
    })();

    document.getElementById('btn-save-prompt').addEventListener('click', () => {
        const id = document.getElementById('prompt-id').value.trim();
        const name = document.getElementById('prompt-name').value.trim();
        const model = document.getElementById('prompt-model').value;
        const temperature = parseFloat(document.getElementById('prompt-temperature').value);
        const system_prompt = document.getElementById('prompt-system').value.trim();

        if (!id) { alert('Введите ID'); return; }

        const promptData = { id, name, model, system_prompt, temperature };
        if (editingIndex >= 0) {
            promptsArray[editingIndex] = promptData;
            editingIndex = -1;
        } else {
            promptsArray.push(promptData);
        }
        savePrompts().then(() => {
            renderPrompts();
            clearPromptForm();
        });
    });

    document.getElementById('btn-clear-prompt').addEventListener('click', clearPromptForm);
}

function clearPromptForm() {
    editingIndex = -1;
    document.getElementById('prompt-id').value = '';
    document.getElementById('prompt-name').value = '';
    document.getElementById('prompt-model').value = 'openrouter/free';
    document.getElementById('prompt-temperature').value = 0.7;
    document.getElementById('temp-value').textContent = '0.7';
    document.getElementById('prompt-system').value = '';
    document.getElementById('prompt-status').textContent = '';
}

// 11. АВТОСОХРАНЕНИЕ (С EasyMDE)
const DRAFT_KEY = 'go_draft_v3'; 
const titleInp = document.getElementById('inp-title');
const slugInp = document.getElementById('inp-slug');
const tagsInp = document.getElementById('inp-tags');
const statusEl = document.getElementById('draft-status');

function saveDraft() { 
    try { 
        const s = {
            title: titleInp ? titleInp.value : '',
            slug: slugInp ? slugInp.value : '',
            tags: tagsInp ? tagsInp.value : '',
            md: easyMDE ? easyMDE.value() : '' 
        };
        localStorage.setItem(DRAFT_KEY, JSON.stringify(s)); 
        if(statusEl) { statusEl.textContent = 'Сохранено'; setTimeout(()=>statusEl.textContent='', 2000); }
    } catch(e){} 
}

function loadDraft() { 
    try { 
        const raw = localStorage.getItem(DRAFT_KEY); 
        if (!raw) return; 
        const s = JSON.parse(raw); 
        if (titleInp && s.title) titleInp.value = s.title;
        if (slugInp && s.slug) slugInp.value = s.slug;
        if (tagsInp && s.tags) tagsInp.value = s.tags;
        if (easyMDE && s.md) easyMDE.value(s.md);
    } catch(e){} 
}

function clearDraft() { 
    localStorage.removeItem(DRAFT_KEY); 
    if (easyMDE) easyMDE.value('');
    if (titleInp) titleInp.value = '';
    if (slugInp) slugInp.value = '';
    if (tagsInp) tagsInp.value = '';
    if (statusEl) statusEl.textContent = 'Кеш очищен';
}

// Транслитерация Slug
var transMap = {а:'a',б:'b',в:'v',г:'g',д:'d',е:'e',ё:'yo',ж:'zh',з:'z',и:'i',й:'y',к:'k',л:'l',м:'m',н:'n',о:'o',п:'p',р:'r',с:'s',т:'t',у:'u',ф:'f',х:'kh',ц:'ts',ч:'ch',ш:'sh',щ:'shch',ъ:'',ы:'y',ь:'',э:'e',ю:'yu',я:'ya'};
if (titleInp && slugInp) {
    titleInp.addEventListener('input', function() {
        slugInp.value = this.value.toLowerCase().split('').map(function(c){ return transMap[c]!==undefined?transMap[c]:(c.match(/[a-z0-9-]/)?c:'-'); }).join('').replace(/-+/g,'-').replace(/^-|-$/g,'');
    });
}

// Автосохранение при вводе
var draftTimer;
if (typeof easyMDE !== 'undefined' && easyMDE.codemirror) {
    easyMDE.codemirror.on('change', function(){
        if(statusEl) statusEl.textContent = '●';
        clearTimeout(draftTimer);
        draftTimer = setTimeout(saveDraft, 5000);
    });
}
if (titleInp) titleInp.addEventListener('input', function(){ clearTimeout(draftTimer); draftTimer = setTimeout(saveDraft, 5000); });

window.addEventListener('load', loadDraft);

// ============================================================
// ВСЕ ДИНАМИЧЕСКИЕ ИНИЦИАЛИЗАЦИИ — в одном IIFE
// (нет повторных const/let в глобальном скопе)
// ============================================================
(function() {
'use strict';

// ─ ДАЙДЖЕСТ ──────────────────────────────────────────────────────────
var btnRun        = document.getElementById('btn-run-digest');
var btnRunSummary = document.getElementById('btn-run-summary');
var btnRefresh    = document.getElementById('btn-refresh-log');
var btnClearLog   = document.getElementById('btn-clear-log');
var consoleBox    = document.getElementById('console-output');
var statusInd     = document.getElementById('digest-status');

if (btnRun) {
    var logInterval;
    function fetchLog() {
        fetch('/admin/api/digest_action.php?action=log')
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (consoleBox && data.log) {
                    consoleBox.innerHTML = data.log
                        .replace(/\[ERR\]/g,'<span class="log-err">[ERR]</span>')
                        .replace(/\[OK\]/g, '<span class="log-ok">[OK]</span>');
                    consoleBox.scrollTop = consoleBox.scrollHeight;
                }
                if (statusInd) {
                    statusInd.className   = data.running ? 'status-indicator status-running' : 'status-indicator status-idle';
                    statusInd.textContent = data.running ? '⚡ Сбор идёт...' : '✕ Готово';
                }
            }).catch(function(e){ console.error(e); });
    }
    btnRun.addEventListener('click', function() {
        btnRun.disabled = true; btnRun.textContent = '⏳ Запуск...';
        if (statusInd) { statusInd.className='status-indicator status-running'; statusInd.textContent='⚡ Запуск...'; }
        fetch('/admin/api/digest_action.php', {method:'POST', body: new URLSearchParams({action:'run'})})
            .then(function(r){ return r.json(); })
            .then(function(data) {
                btnRun.disabled=false; btnRun.textContent='>_ Запустить сбор';
                if (consoleBox) consoleBox.innerHTML = data.message||data.error||'OK';
                clearInterval(logInterval);
                logInterval = setInterval(fetchLog, 5000);
            }).catch(function(e){ btnRun.disabled=false; console.error(e); });
    });
    if (btnRunSummary) btnRunSummary.addEventListener('click', function() {
        btnRunSummary.disabled=true;
        fetch('/admin/api/digest_action.php',{method:'POST',body:new URLSearchParams({action:'summary'})})
            .then(function(r){ return r.json(); })
            .then(function(d){ if(consoleBox)consoleBox.innerHTML=d.output||d.message||d.error; btnRunSummary.disabled=false; })
            .catch(function(){ btnRunSummary.disabled=false; });
    });
    if (btnRefresh)  btnRefresh.addEventListener('click', fetchLog);
    if (btnClearLog) btnClearLog.addEventListener('click', function() {
        fetch('/admin/api/digest_action.php',{method:'POST',body:new URLSearchParams({action:'clear_log'})})
            .then(function(){ if(consoleBox)consoleBox.innerHTML='>_ Лог очищен'; });
    });
    fetchLog();
}

// ─ ИСТОЧНИКИ ──────────────────────────────────────────────────────
var btnAddSource = document.getElementById('btn-add-source');
if (btnAddSource) {
    function loadSources() {
        fetch('/admin/api/sources.php').then(function(r){ return r.json(); }).then(function(data) {
            var el = document.getElementById('sources-list');
            if (!el) return;
            if (!data.sources||!data.sources.length) { el.innerHTML='<p style="color:var(--t-muted);margin-top:12px">Нет источников.</p>'; return; }
            var cats={ai:'var(--t-red)',bim:'var(--t-blue)',events:'var(--t-yellow)',norms:'var(--t-muted)'};
            el.innerHTML='<div class="t-table-wrap" style="margin-top:12px"><table><thead><tr><th>Название</th><th>URL</th><th>Кат</th><th>Статус</th><th></th></tr></thead><tbody>'+
                data.sources.map(function(s){ return '<tr><td>'+(s.name||'—')+'</td>'+
                    '<td style="max-width:220px;overflow:hidden;text-overflow:ellipsis"><a href="'+s.url+'" target="_blank" style="color:var(--t-blue)">'+s.url+'</a></td>'+
                    '<td><span style="color:'+(cats[s.category]||'var(--t-muted)')+';font-weight:700">'+s.category+'</span></td>'+
                    '<td>'+(s.active?'<span class="t-badge t-badge-ok">on</span>':'<span class="t-badge t-badge-warn">off</span>')+'</td>'+
                    '<td style="display:flex;gap:4px;padding:4px 8px">'+
                    '<button class="btn" style="padding:2px 7px;font-size:.7rem" onclick="toggleSource('+s.id+')">'+  (s.active?'[off]':'[on]')+'</button>'+
                    '<button class="btn btn-red" style="padding:2px 7px;font-size:.7rem" onclick="deleteSource('+s.id+')">[x]</button>'+
                    '</td></tr>'; }).join('')+
                '</tbody></table></div>';
        });
    }
    loadSources();
    btnAddSource.addEventListener('click', function() {
        var u=document.getElementById('src-url').value.trim();
        var n=document.getElementById('src-name').value.trim();
        var c=document.getElementById('src-cat').value;
        var p=document.getElementById('src-prompt').value.trim();
        var s=document.getElementById('src-status');
        if (!u) { s.textContent='✕ URL обязателен'; s.style.color='var(--t-red)'; return; }
        fetch('/admin/api/sources.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({url:u,name:n,category:c,prompt:p})})
            .then(function(r){ return r.json(); })
            .then(function(res) {
                s.textContent=res.ok?'✔ '+res.message:'✕ '+(res.error||'Error');
                s.style.color=res.ok?'var(--t-green)':'var(--t-red)';
                if (res.ok) { document.getElementById('src-url').value=''; document.getElementById('src-name').value=''; document.getElementById('src-prompt').value=''; loadSources(); }
            });
    });
}

// ─ МЕРОПРИЯТИЯ ────────────────────────────────────────────────────
var btnAddEvent = document.getElementById('btn-add-event');
if (btnAddEvent) {
    function loadEvents() {
        fetch('/admin/api/events.php').then(function(r){ return r.json(); }).then(function(data) {
            var el = document.getElementById('events-list');
            if (!el) return;
            if (!data.events||!data.events.length) { el.innerHTML='<p style="color:var(--t-muted);margin-top:12px">Нет мероприятий.</p>'; return; }
            el.innerHTML='<div class="t-table-wrap" style="margin-top:12px"><table><thead><tr><th>Название</th><th>Дата</th><th>Город</th><th>Статус</th><th></th></tr></thead><tbody>'+
                data.events.map(function(ev){ return '<tr><td><a href="'+(ev.link||'#')+'" target="_blank" style="color:var(--t-blue)">'+ev.title+'</a></td>'+
                    '<td style="font-size:.72rem">'+(ev.month||'')+' '+(ev.days||'')+'</td>'+
                    '<td>'+(ev.city||'')+'</td>'+
                    '<td>'+(ev.isPast?'<span class="t-badge t-badge-warn">past</span>':'<span class="t-badge t-badge-ok">upcoming</span>')+'</td>'+
                    '<td style="display:flex;gap:4px;padding:4px 8px">'+
                    '<button class="btn" style="padding:2px 7px;font-size:.7rem" onclick="toggleEvent(\"'+ev.id+'\")">'+(ev.isPast?'[up]':'[past]')+'</button>'+
                    '<button class="btn btn-red" style="padding:2px 7px;font-size:.7rem" onclick="deleteEvent(\"'+ev.id+'\")">[×]</button>'+
                    '</td></tr>'; }).join('')+
                '</tbody></table></div>';
        });
    }
    loadEvents();
    btnAddEvent.addEventListener('click', function() {
        var st = document.getElementById('ev-status');
        var payload = {
            title:  document.getElementById('ev-title').value.trim(),
            month:  document.getElementById('ev-month').value.trim().toUpperCase(),
            days:   document.getElementById('ev-days').value.trim(),
            city:   document.getElementById('ev-city').value.trim().toUpperCase(),
            link:   document.getElementById('ev-link').value.trim(),
            desc:   document.getElementById('ev-desc').value.trim(),
            tags:   document.getElementById('ev-tags').value.trim(),
            isPast: false
        };
        if (!payload.title) { st.textContent='✕ Название обязательно'; st.style.color='var(--t-red)'; return; }
        fetch('/admin/api/events.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
            .then(function(r){ return r.json(); })
            .then(function(res) {
                st.textContent=res.ok?'✔ '+res.message:'✕ '+(res.error||'Error');
                st.style.color=res.ok?'var(--t-green)':'var(--t-red)';
                if (res.ok) { ['ev-title','ev-month','ev-days','ev-city','ev-link','ev-desc','ev-tags'].forEach(function(id){ var e=document.getElementById(id); if(e)e.value=''; }); loadEvents(); }
            });
    });
}

// ─ AI ЧАТ ─────────────────────────────────────────────────────────
var aiSendBtn  = document.getElementById('ai-send-btn');
var aiClearBtn = document.getElementById('ai-clear-btn');
var aiChatBox  = document.getElementById('ai-chat-box');
var aiInput    = document.getElementById('ai-user-input');
var aiTokensEl = document.getElementById('ai-tokens');

if (aiSendBtn) {
    var aiHistory = [], aiTotal = 0;

    function aiGetSys() {
        var el=document.getElementById('ai-system');
        return el&&el.value.trim()?el.value.trim():'Ты — эксперт по BIM, проектированию и управлению. Отвечай по-русски, кратко.';
    }
    function aiGetModel() {
        var el=document.getElementById('ai-model-override');
        return el&&el.value.trim()?el.value.trim():'openrouter/free';
    }
    function aiRender(role, text) {
        if (!aiChatBox) return;
        var isUser=role==='user';
        var wrap=document.createElement('div');
        wrap.style.cssText='margin-bottom:12px;'+(isUser?'text-align:right':'');
        var b=document.createElement('div');
        b.style.cssText='display:inline-block;max-width:82%;padding:10px 14px;border-radius:'+(isUser?'14px 14px 4px 14px':'14px 14px 14px 4px')+';'+(isUser?'background:var(--t-green);color:var(--t-bg);':'background:var(--t-panel);border:1px solid var(--t-border);color:var(--t-text);');
        b.innerHTML=text
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/```([\s\S]*?)```/g,'<pre style="background:#000;padding:8px;border-radius:4px;margin:4px 0;overflow-x:auto;font-size:.78rem">$1</pre>')
            .replace(/`([^`]+)`/g,'<code style="background:#000;padding:2px 5px;border-radius:3px">$1</code>')
            .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
            .replace(/\n/g,'<br>');
        wrap.appendChild(b); aiChatBox.appendChild(wrap); aiChatBox.scrollTop=aiChatBox.scrollHeight;
    }
    function aiSend(text) {
        if (!text.trim()) return;
        aiRender('user',text); aiHistory.push({role:'user',content:text});
        if (aiInput) aiInput.value='';
        aiSendBtn.disabled=true; aiSendBtn.textContent='...';
        var msgs=[{role:'system',content:aiGetSys()}].concat(aiHistory);
        fetch('/admin/api/ai_chat.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({messages:msgs,model_override:aiGetModel()})
        }).then(function(r){ return r.json(); })
        .then(function(res) {
            if (res.error) { aiRender('assistant','❌ '+(res.error.message||'Ошибка')); }
            else {
                var reply=(res.choices&&res.choices[0]&&res.choices[0].message&&res.choices[0].message.content)||res.explanation||'Без ответа';
                aiHistory.push({role:'assistant',content:reply}); aiRender('assistant',reply);
                if (res.usage&&aiTokensEl){ aiTotal+=(res.usage.total_tokens||0); aiTokensEl.textContent='Токенов: '+aiTotal; }
            }
        })
        .catch(function(e){ aiRender('assistant','❌ Сеть: '+e.message); })
        .finally(function(){ aiSendBtn.disabled=false; aiSendBtn.textContent='[Enter]'; });
    }
    aiSendBtn.addEventListener('click', function(){ if(aiInput)aiSend(aiInput.value); });
    if (aiInput) aiInput.addEventListener('keydown',function(e){ if(e.key==='Enter'&&(e.ctrlKey||e.metaKey)){ e.preventDefault(); aiSend(aiInput.value); } });
    if (aiClearBtn) aiClearBtn.addEventListener('click',function(){ aiHistory=[]; aiTotal=0; if(aiChatBox)aiChatBox.innerHTML=''; if(aiTokensEl)aiTokensEl.textContent=''; });
}

})(); // end main IIFE

// ============================================================
// ТЕМА / РЕЖИМ / БЫСТРЫЕ ФУНКЦИИ (глобальные — нужны для onclick)
// ============================================================
function adminToggleTheme() {
    var html = document.documentElement;
    var next = html.getAttribute('data-theme')==='dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('adm_theme', next);
    var btn = document.getElementById('adm-theme-toggle');
    if (btn) {
        btn.querySelectorAll('.dev-only').forEach(function(el){ el.textContent = next==='light' ? '◑ theme --dark' : '◑ theme --light'; });
        btn.querySelectorAll('.mgr-only').forEach(function(el){ el.textContent = next==='light' ? '◑ Тёмная'   : '◑ Светлая'; });
    }
}
function adminToggleMode() {
    var html = document.documentElement;
    var next = html.getAttribute('data-mode')==='dev' ? 'mgr' : 'dev';
    html.setAttribute('data-mode', next);
    localStorage.setItem('adm_mode', next);
    var btn = document.getElementById('adm-mode-toggle');
    if (btn) {
        btn.querySelectorAll('.dev-only').forEach(function(el){ el.textContent = next==='mgr' ? '>_ role --dev' : '>_ role --mgr'; });
        btn.querySelectorAll('.mgr-only').forEach(function(el){ el.textContent = next==='mgr' ? '≡ Разработчик' : '≡ Управление'; });
    }
}
function toggleSource(id){ fetch('/admin/api/sources.php?id='+id,{method:'PATCH'}).then(function(){ if(window.loadSources)loadSources(); }); }
function deleteSource(id){ if(!confirm('Удалить?'))return; fetch('/admin/api/sources.php?id='+id,{method:'DELETE'}).then(function(){ if(window.loadSources)loadSources(); }); }
function toggleEvent(id){ fetch('/admin/api/events.php?id='+encodeURIComponent(id),{method:'PATCH'}).then(function(){ if(window.loadEvents)loadEvents(); }); }
function deleteEvent(id){ if(!confirm('Удалить?'))return; fetch('/admin/api/events.php?id='+encodeURIComponent(id),{method:'DELETE'}).then(function(){ if(window.loadEvents)loadEvents(); }); }
function aiQuickPrompt(text){ var inp=document.getElementById('ai-user-input'); if(inp){inp.value=text;inp.focus();} }
</script>
</body></html>