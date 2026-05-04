<?php
declare(strict_types=1);
/**
 * /admin/index.php — Панель управления (v7.0 Optimized)
 * Интегрировано: CMS, Дайджест, Ключи, Настройки Главной (Layout).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/views.php';
require_once __DIR__ . '/../lib/brute_force.php';

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
<style>
    *{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,sans-serif;background:#0f172a;display:flex;align-items:center;justify-content:center;min-height:100vh;color:#f1f5f9}
    .box{background:#1e293b;padding:2rem;border-radius:1rem;width:100%;max-width:400px;box-shadow:0 20px 40px #0005}
    input{width:100%;padding:12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#fff;margin:10px 0}
    button{width:100%;padding:12px;background:#3b82f6;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:bold}
    .err{color:#f87171;font-size:.8rem;margin-top:5px;text-align:center}
</style></head><body>
<div class="box" style="text-align:center">
    <h1 style="margin-bottom:1rem">🔒 Вход</h1>
    <form method="post">
        <input type="password" name="password" placeholder="Пароль" autofocus required>
        <?php if(isset($loginError)) echo "<div class='err'>$loginError</div>"; ?>
        <button type="submit">Войти</button>
    </form>
</div></body></html>
<?php exit; }

// --- 2. ОБРАБОТКА ДЕЙСТВИЙ (AJAX & FORMS) ---
$msg = ''; $msgType = ''; // ok, err
$tab = $_GET['tab'] ?? 'dashboard';

// --- Сохранение Layout (Главная страница) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_layout'])) {
    $layoutData = $_POST['layout_data'] ?? '';
    $decoded = json_decode($layoutData, true);
    if ($decoded) {
        $file = __DIR__ . '/../config/main_layout.json';
        if (!is_dir(dirname($file))) @mkdir(dirname($file), 0755, true);
        if (@file_put_contents($file, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX)) {
            $msg = "✅ Главная страница обновлена"; $msgType = 'ok';
        } else {
            $msg = "❌ Ошибка записи JSON"; $msgType = 'err';
        }
    } else {
        $msg = "❌ Ошибка валидации JSON"; $msgType = 'err';
    }
}

// --- Сохранение API Ключей ---
if (isset($_POST['save_keys'])) {
    require_once __DIR__ . '/../digest/core/Config.php';
    require_once __DIR__ . '/../digest/core/Db.php';
    try {
        $db = Db::getInstance(); $db->initTables(); $pdo = $db->getConnection();
        $keys = [
            ['openrouter_key', $_POST['openrouter_key'], true, 'OpenRouter Key'],
            ['openrouter_model', $_POST['openrouter_model'], false, 'Model'],
            ['github_token', $_POST['github_token'], true, 'GitHub Token'],
            ['digest_admin_pass', $_POST['digest_pass'], true, 'Digest Pass'],
        ];
        foreach ($keys as [$name, $val, $enc, $desc]) {
            if ($val !== '') {
                $stored = $enc ? base64_encode(random_bytes(16).'::'.openssl_encrypt($val,'AES-256-CBC',hash('sha256',ADMIN_PASSWORD),0,random_bytes(16))) : $val;
                $pdo->prepare("INSERT INTO admin_settings (key_name,key_value,encrypted,description) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE key_value=?,encrypted=?,updated_at=NOW()")
                    ->execute([$name,$stored,$enc?$1:0,$desc,$stored,$enc?1:0]);
            }
        }
        $msg = "✅ Ключи сохранены"; $msgType = 'ok'; $tab = 'keys';
    } catch (Throwable $e) { $msg = "❌ Ошибка БД: ".$e->getMessage(); $msgType = 'err'; }
}

// --- Запуск Дайджеста ---
if (isset($_POST['run_digest'])) {
    $collPath = realpath(__DIR__ . '/../digest/api/collector.php');
    if ($collPath) {
        $output = shell_exec("php8.5 " . escapeshellarg($collPath) . " 2>&1");
        $msg = "✅ Коллектор запущен. " . mb_substr(strip_tags($output ?? ''), 0, 200);
        $msgType = 'ok'; $tab = 'digest';
    } else { $msg = "❌ collector.php не найден"; $msgType = 'err'; }
}

// --- Сохранение Статьи ---
if (isset($_POST['save_article'])) {
    $slug = preg_replace('/[^a-z0-9\-_]/i', '', $_POST['slug']);
    $site = $_POST['site'] ?? 'main';
    $title = $_POST['title'];
    $content = $_POST['content'];
    
    // Формируем YAML
    $yaml = "title: \"$title\"\nslug: \"$slug\"\nsite: \"$site\"\ndate: " . date('Y-m-d') . "\ntags: [".($_POST['tags']??'')."]\n";
    if (isset($_POST['badge'])) $yaml .= "badge: \"{$_POST['badge']}\"\n";
    if (isset($_POST['stub'])) $yaml .= "stub: true\n";
    
    // Определяем путь (упрощенно для main, для других надо расширять)
    $basePath = __DIR__ . '/../content'; 
    if ($site !== 'main') $basePath = __DIR__ . "/../../{$site}_chernetchenko_pro/public_html/content";
    
    $filePath = "$basePath/$slug.md";
    if (@file_put_contents($filePath, "---\n$yaml---\n$content")) {
        $msg = "✅ Статья сохранена в $slug.md"; $msgType = 'ok'; $tab = 'cms';
    } else {
        $msg = "❌ Ошибка записи файла"; $msgType = 'err';
    }
}

// --- 3. ЗАГРУЗКА ДАННЫХ ---

// Загрузка текущего Layout
$layoutJson = '';
$layoutFile = __DIR__ . '/../config/main_layout.json';
if (file_exists($layoutFile)) {
    $layoutJson = json_encode(json_decode(file_get_contents($layoutFile), true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// Статистика
$stats = ['articles' => 0, 'digest' => 0, 'events' => 0];
// (тут можно добавить запросы к БД и файловой системе для точных цифр)

?>
<!DOCTYPE html>
<html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Админка · v7.0</title>
<style>
    :root{--bg:#0f172a;--panel:#1e293b;--border:#334155;--text:#f1f5f9;--blue:#3b82f6;--green:#22c55e;--red:#ef4444}
    body{display:flex;background:var(--bg);color:var(--text);font-family:system-ui,sans-serif;min-height:100vh;margin:0}
    aside{width:240px;background:var(--panel);padding:20px;border-right:1px solid var(--border);flex-shrink:0}
    aside a{display:block;padding:10px;color:#94a3b8;text-decoration:none;border-radius:6px;margin-bottom:4px}
    aside a:hover, aside a.active{background:rgba(59,130,246,0.1);color:#fff}
    main{flex:1;padding:30px;max-width:1000px}
    input,textarea,select{width:100%;background:#0f172a;border:1px solid var(--border);color:#fff;padding:10px;border-radius:6px;margin-bottom:10px;box-sizing:border-box}
    textarea{min-height:150px;font-family:monospace}
    .btn{padding:10px 20px;background:var(--blue);color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:600}
    .btn:hover{background:#2563eb}
    .alert{padding:10px;border-radius:6px;margin-bottom:20px}
    .alert-ok{background:rgba(34,197,94,0.2);border:1px solid var(--green);color:var(--green)}
    .alert-err{background:rgba(239,68,68,0.2);border:1px solid var(--red);color:var(--red)}
    h2{margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:10px}
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px}
    label{display:block;font-size:.8rem;color:#64748b;margin-bottom:4px}
</style></head><body>

<aside>
    <h3 style="margin-top:0;color:#fff">📋 Admin</h3>
    <a href="?tab=dashboard" class="<?= $tab=='dashboard'?'active':'' ?>">🏠 Дашборд</a>
    <a href="?tab=layout" class="<?= $tab=='layout'?'active':'' ?>">🎨 Главная (JSON)</a>
    <a href="?tab=cms" class="<?= $tab=='cms'?'active':'' ?>">✍️ Статьи</a>
    <a href="?tab=digest" class="<?= $tab=='digest'?'active':'' ?>">📰 Дайджест</a>
    <a href="?tab=keys" class="<?= $tab=='keys'?'active':'' ?>">🔑 Ключи</a>
    <hr style="border-color:#334155;margin:15px 0">
    <a href=".." target="_blank">🌐 На сайт</a>
    <a href="?logout=1">🚪 Выйти</a>
</aside>

<main>
<?php if($msg): ?><div class="alert alert-<?= $msgType ?>"><?= $msg ?></div><?php endif; ?>

<?php if($tab==='dashboard'): ?>
    <h2>Дашборд</h2>
    <div class="grid-2">
        <div style="background:var(--panel);padding:20px;border-radius:8px">
            <h3 style="margin-top:0">Статьи</h3>
            <p style="font-size:2rem;font-weight:bold"><?= $stats['articles'] ?></p>
        </div>
        <div style="background:var(--panel);padding:20px;border-radius:8px">
            <h3 style="margin-top:0">Дайджест</h3>
            <p style="font-size:2rem;font-weight:bold"><?= $stats['digest'] ?></p>
        </div>
    </div>

<?php elseif($tab==='layout'): ?>
    <h2>🎨 Настройка Главной Страницы</h2>
    <p style="color:#94a3b8;margin-bottom:15px">Редактируй JSON структуру. Изменения применяются мгновенно.</p>
    <form method="post">
        <textarea name="layout_data" style="min-height:400px"><?= htmlspecialchars($layoutJson) ?></textarea>
        <button type="submit" name="save_layout" class="btn">💾 Сохранить структуру</button>
    </form>

<?php elseif($tab==='cms'): ?>
    <h2>Редактор Статей</h2>
    <form method="post">
        <div class="grid-2">
            <div>
                <label>Заголовок</label><input type="text" name="title" required>
                <label>Slug</label><input type="text" name="slug" required>
                <label>Подсайт</label>
                <select name="site">
                    <option value="main">Main</option>
                    <option value="waf">WAF</option>
                </select>
                <label>Теги (через запятую)</label><input type="text" name="tags">
                
                <div style="display:flex;gap:10px;align-items:center;margin-bottom:10px">
                    <input type="checkbox" name="badge" value="new" style="width:auto"> <span>Бейдж: NEW</span>
                    <input type="checkbox" name="stub" style="width:auto"> <span>Заглушка (нет текста)</span>
                </div>
            </div>
            <div>
                <label>Контент (Markdown)</label>
                <textarea name="content" style="min-height:300px"></textarea>
            </div>
        </div>
        <button type="submit" name="save_article" class="btn">✨ Опубликовать</button>
    </form>

<?php elseif($tab==='digest'): ?>
    <h2>Управление Дайджестом</h2>
    <form method="post">
        <button type="submit" name="run_digest" class="btn">🔄 Запустить Сбор Новостей</button>
    </form>
    <p style="color:#64748b;margin-top:10px">Запуск происходит в фоне. Результаты появятся в базе данных.</p>

<?php elseif($tab==='keys'): ?>
    <h2>API Ключи</h2>
    <form method="post">
        <label>OpenRouter Key (sk-or-...)</label><input type="password" name="openrouter_key" placeholder="sk-or-v1-...">
        <label>OpenRouter Model</label>
        <select name="openrouter_model">
            <option value="openrouter/free">Auto Free</option>
            <option value="google/gemma-3-27b-it:free">Gemma 3 (Free)</option>
            <option value="meta-llama/llama-3.1-8b-instruct:free">Llama 3.1 (Free)</option>
        </select>
        <label>GitHub Token</label><input type="password" name="github_token" placeholder="ghp_...">
        <button type="submit" name="save_keys" class="btn">🔐 Сохранить ключи</button>
    </form>
<?php endif; ?>

</main>
</body></html>