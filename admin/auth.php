<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Сессия будет жить 24 часа (86400 сек)
ini_set('session.cookie_lifetime', '86400');
session_start();

$error = '';

// Обработка выхода
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: auth.php');
    exit;
}

// Обработка входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pwd = $_POST['password'] ?? '';
    // hash_equals защищает от timing-атак
    if (hash_equals(ADMIN_PASSWORD, $pwd)) {
        $_SESSION['admin'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Неверный пароль';
}

// Если уже авторизован — сразу в редактор
if (!empty($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ГО — вход</title>
    <style>
        body { font-family: var(--font-body, sans-serif); max-width: 320px; margin: 4rem auto; padding: 0 1rem; color: var(--ink, #222); }
        form { display: flex; flex-direction: column; gap: 0.8rem; }
        input { padding: 0.6rem; border: 1px solid #999; border-radius: 4px; background: var(--bg, #fff); }
        button { padding: 0.6rem; background: var(--accent, #2563eb); color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .err { color: #c00; margin: 0 0 0.5rem; }
        .brand { text-align: center; margin-bottom: 2rem; }
        .brand h1 { font-family: var(--font-title, serif); font-size: 1.5rem; margin: 0; color: var(--ink); }
        .brand .abbr { font-size: 0.5em; opacity: 0.5; font-weight: 400; }
    </style>
</head>
<body>
    <div class="brand">
        <h1>Господин Оформитель <span class="abbr">ГО</span></h1>
    </div>
    <?php if ($error): ?>
        <p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post">
        <label>Пароль: <input type="password" name="password" required autofocus></label>
        <button type="submit">Войти</button>
    </form>
</body>
</html>