<?php
declare(strict_types=1);
/**
 * /article.php — Универсальный шаблон статьи (v2.0 Design)
 * Превращает обычный Markdown в красивую верстку автоматически.
 */

// 1. Подключение зависимостей
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
require_once __DIR__ . '/lib/frontmatter.php';
require_once __DIR__ . '/lib/views.php';

// 2. Получение и валидация slug
$rawSlug = trim($_GET['slug'] ?? '');
$slug    = preg_replace('/[^a-z0-9\-_]/i', '', $rawSlug);

// 3. Увеличиваем счетчик
if ($slug !== '') {
    incrementView($slug);
}

// 4. Поиск файла
$filePath = ($slug !== '') ? __DIR__ . '/content/' . $slug . '.md' : '';
$article  = ($filePath !== '' && is_file($filePath)) ? parseArticle($filePath) : null;

// 5. Проверка на черновик
$isDraft = ($article !== null && !empty($article['meta']['draft']));
$is404   = ($article === null || $isDraft);

// 6. Переменные для шаблона
$siteId          = 'main';
$pageTitle       = 'Статья не найдена';
$pageDescription = '';
$htmlContent     = '';
$meta            = [];

if (!$is404) {
    $meta            = $article['meta'];
    $siteId          = $meta['site'] ?? 'main';
    $pageTitle       = $meta['title'] ?? 'Без заголовка';
    $pageDescription = $meta['description'] ?? '';

    // Рендер Markdown
    if (class_exists('\Parsedown')) {
        $parsedown   = new \Parsedown();
        // Включаем безопасный режим, но разрешаем базовый HTML для сложных блоков
        $parsedown->setSafeMode(false); 
        $parsedown->setMarkupEscaped(true);
        $htmlContent = $parsedown->text($article['body']);
    } else {
        $htmlContent = '<p>Ошибка рендеринга: библиотека Parsedown не найдена.</p>';
    }
} else {
    http_response_code(404);
    $htmlContent = '<div class="error-404"><h1>404</h1><p>Статья не найдена или скрыта в черновиках.</p><a href="/" class="btn-home">На главную</a></div>';
}

// 7. Хедер
include __DIR__ . '/header.php';
?>

<!-- SEO -->
<?php if (!$is404): ?>
<meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
<link rel="canonical" href="https://chernetchenko.pro/article.php?slug=<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:type" content="article">
<?php endif; ?>

<!-- Стили статьи -->
<style>
    /* ─── ОБЩИЕ ПЕРЕМЕННЫЕ ─── */
    :root {
        --art-max-w: 800px;
        --art-bg: #faf7f2;
        --art-ink: #1a1612;
        --art-ink2: #4a4540;
        --art-ink3: #8a837a;
        --art-border: #e4ddd0;
        --art-accent: var(--accent); /* Берется из хедера */
        --art-teal: #20b2aa;
        --art-code-bg: #1e1e1e;
        --art-code-text: #d4d4d4;
    }

    /* ─── КОНТЕЙНЕР ─── */
    .article-container {
        max-width: var(--art-max-w);
        margin: 0 auto;
        padding: 3rem 20px 6rem;
        font-family: var(--font-body, sans-serif);
        color: var(--art-ink);
    }

    /* ─── HERO ─── */
    .article-hero {
        margin-bottom: 3.5rem;
        padding-bottom: 2rem;
        border-bottom: 2px solid var(--art-border);
    }
    .article-hero .meta-top {
        font-family: var(--font-mono);
        font-size: 0.75rem;
        color: var(--art-accent);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.8rem;
        display: block;
    }
    .article-hero h1 {
        font-family: var(--font-title);
        font-size: clamp(2rem, 5vw, 3rem);
        font-weight: 900;
        line-height: 1.1;
        margin: 0 0 1.5rem;
        letter-spacing: -0.02em;
    }
    .article-hero .lead {
        font-size: 1.2rem;
        color: var(--art-ink2);
        line-height: 1.6;
        margin: 0 0 1.5rem;
        font-weight: 500;
    }
    .article-hero .meta-row {
        display: flex;
        gap: 1rem;
        align-items: center;
        font-size: 0.85rem;
        color: var(--art-ink3);
        font-family: var(--font-mono);
    }
    .article-hero .tags span {
        background: rgba(0,0,0,0.05);
        padding: 2px 6px;
        border-radius: 4px;
        margin-right: 4px;
        font-size: 0.75rem;
    }

    /* ─── КОНТЕНТ (MAGIC MAPPING) ─── */
    .article-content {
        font-size: 1.15rem;
        line-height: 1.75;
        color: var(--art-ink);
    }
    
    /* Заголовки */
    .article-content h2 {
        font-family: var(--font-title);
        font-size: 1.75rem;
        font-weight: 800;
        margin: 3rem 0 1.5rem;
        color: var(--art-ink);
        line-height: 1.2;
        border-left: 4px solid var(--art-accent);
        padding-left: 1rem;
    }
    .article-content h3 {
        font-family: var(--font-title);
        font-size: 1.35rem;
        font-weight: 700;
        margin: 2rem 0 1rem;
    }
    
    /* Текст */
    .article-content p { margin-bottom: 1.5rem; }
    .article-content strong { font-weight: 800; color: var(--art-ink); }
    .article-content em { font-style: italic; color: var(--art-ink2); }
    
    /* Ссылки */
    .article-content a {
        color: var(--art-accent);
        text-decoration: underline;
        text-decoration-thickness: 2px;
        text-underline-offset: 3px;
        transition: all 0.2s;
    }
    .article-content a:hover {
        color: var(--art-ink);
        text-decoration-color: var(--art-ink);
    }

    /* Списки */
    .article-content ul, .article-content ol {
        margin-bottom: 1.5rem;
        padding-left: 1.5rem;
    }
    .article-content li { margin-bottom: 0.5rem; }
    .article-content ul li::marker { color: var(--art-accent); content: "•"; font-size: 1.2em; }

    /* БЛОК "INSIGHT" (из цитат >) */
    .article-content blockquote {
        margin: 2rem 0;
        padding: 1.5rem 1.5rem 1.5rem 2rem;
        background: #fff;
        border: 1px solid var(--art-border);
        border-left: 5px solid var(--art-accent);
        border-radius: 0 12px 12px 0;
        box-shadow: 6px 6px 0 rgba(0,0,0,0.03);
        font-style: italic;
        font-size: 1.1rem;
        color: var(--art-ink2);
    }
    .article-content blockquote p:last-child { margin-bottom: 0; }

    /* КОД / ТЕРМИНАЛ */
    .article-content code {
        font-family: var(--font-mono);
        background: rgba(0,0,0,0.05);
        padding: 0.2em 0.4em;
        border-radius: 4px;
        font-size: 0.85em;
        color: #d32f2f;
    }
    .article-content pre {
        background: var(--art-code-bg);
        color: var(--art-code-text);
        padding: 1.5rem;
        border-radius: 12px;
        overflow-x: auto;
        margin-bottom: 2rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        border: 1px solid #333;
    }
    .article-content pre code {
        background: none;
        padding: 0;
        color: inherit;
        font-size: 0.9rem;
        line-height: 1.6;
    }

    /* ТАБЛИЦЫ */
    .article-content table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 2rem;
        border: 1px solid var(--art-border);
        border-radius: 12px;
        overflow: hidden;
        font-size: 0.95rem;
        background: #fff;
    }
    .article-content th {
        background: var(--art-bg);
        padding: 12px 15px;
        text-align: left;
        font-weight: 700;
        font-family: var(--font-mono);
        font-size: 0.85em;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--art-border);
    }
    .article-content td {
        padding: 12px 15px;
        border-bottom: 1px solid var(--art-border);
        vertical-align: top;
    }
    .article-content tr:last-child td { border-bottom: none; }

    /* КАРТИНКИ */
    .article-content img {
        max-width: 100%;
        height: auto;
        border-radius: 12px;
        border: 1px solid var(--art-border);
        margin: 2rem 0;
        box-shadow: 8px 8px 0 rgba(0,0,0,0.05);
        display: block;
    }

    /* РАЗДЕЛИТЕЛЬ */
    .article-content hr {
        border: none;
        height: 2px;
        background: var(--art-border);
        margin: 3rem 0;
        border-radius: 2px;
    }

    /* ─── НАВИГАЦИЯ И ПОДВАЛ ─── */
    .article-nav {
        margin-top: 5rem;
        padding-top: 2rem;
        border-top: 2px solid var(--art-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-family: var(--font-mono);
        font-size: 0.85rem;
    }
    .article-nav a {
        text-decoration: none;
        font-weight: 700;
        color: var(--art-ink);
        padding: 10px 20px;
        border: 2px solid var(--art-border);
        border-radius: 8px;
        transition: all 0.2s;
    }
    .article-nav a:hover {
        border-color: var(--art-accent);
        color: var(--art-accent);
        transform: translateY(-2px);
        box-shadow: 4px 4px 0 var(--art-border);
    }
    
    .subscribe-box {
        margin-top: 4rem;
        background: var(--art-ink);
        color: #fff;
        padding: 3rem 2rem;
        border-radius: 16px;
        text-align: center;
    }
    .subscribe-box h3 { font-family: var(--font-title); font-size: 1.5rem; margin-bottom: 1rem; }
    .subscribe-box p { opacity: 0.8; margin-bottom: 2rem; max-width: 600px; margin-left: auto; margin-right: auto; }
    .btn-sub {
        display: inline-block; padding: 12px 24px;
        background: #2AABEE; color: #fff; border-radius: 8px;
        text-decoration: none; font-weight: 700; transition: 0.2s;
    }
    .btn-sub:hover { background: #229ED9; transform: translateY(-2px); }
    
    .error-404 { text-align: center; padding: 4rem 1rem; }
    .error-404 h1 { font-size: 4rem; color: var(--art-accent); margin-bottom: 1rem; }
    .btn-home { padding: 10px 20px; background: var(--art-accent); color: #fff; border-radius: 4px; text-decoration: none; display: inline-block; margin-top: 1rem; }
</style>

<main class="article-container">
    <article>
        <?php if (!$is404): ?>
        <header class="article-hero">
            <?php if (!empty($meta['section'])): ?>
                <span class="meta-top"><?= htmlspecialchars($meta['section'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            
            <h1 itemprop="headline"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            
            <p class="lead"><?= htmlspecialchars($meta['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
            
            <div class="meta-row">
                <?php if (!empty($meta['date'])): ?>
                    <time datetime="<?= htmlspecialchars($meta['date'], ENT_QUOTES, 'UTF-8') ?>">
                        📅 <?= htmlspecialchars(date('d.m.Y', strtotime($meta['date'])), ENT_QUOTES, 'UTF-8') ?>
                    </time>
                <?php endif; ?>
                
                <span>👁 <?= getViewCount($slug) ?></span>
                
                <?php if (!empty($meta['tags'])): ?>
                    <div class="tags">
                        <?php foreach ($meta['tags'] as $tag): ?>
                            <span>#<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </header>
        <?php endif; ?>

        <section class="article-content" itemprop="articleBody">
            <?= $htmlContent ?>
        </section>

        <!-- Блок подписки (только для статей) -->
        <?php if (!$is404): ?>
        <div class="subscribe-box">
            <h3>📬 Понравилось? Подпишись</h3>
            <p>Никакого спама. Только разборы BIM, ИИ и проектов. Раз в неделю.</p>
            <a href="https://t.me/waf_chernetchenko" class="btn-sub" target="_blank">Канал в Telegram →</a>
        </div>
        <?php endif; ?>

        <nav class="article-nav">
            <a href="javascript:history.back()">← Назад</a>
            <a href="/">На главную</a>
        </nav>
    </article>
</main>

<?php include 'footer.php'; ?>