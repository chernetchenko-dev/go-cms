<?php
/**
 * digest/index.php v2.0 — OVC Design System
 * Публичная страница дайджеста BIM & AI
 */
require_once __DIR__ . '/core/Config.php';
require_once __DIR__ . '/core/Db.php';

$cat = $_GET['cat'] ?? 'all';
$pageTitle = match($cat) {
    'bim'    => 'BIM и ТИМ — свежие новости',
    'events' => 'Отраслевые события и конференции',
    'norms'  => 'Нормативная база — обновления',
    'ai'     => 'ИИ и ML — новости и модели',
    default  => 'AI & BIM Дайджест',
};
$pageDesc = 'Агрегатор новостей по ИИ, BIM-технологиям, нормативке и отраслевым мероприятиям. Обновляется каждый день.';
$siteId   = 'main';

// БД
$pdo = null;
$dailySummary = null;
try {
    $db  = Db::getInstance();
    $db->initTables();
    $pdo = $db->getConnection();
    $stmtS = $pdo->prepare("
        SELECT summary_text, items_count, summary_date
        FROM digest_daily_summary
        ORDER BY summary_date DESC
        LIMIT 1
    ");
    $stmtS->execute();
    $dailySummary = $stmtS->fetch() ?: null;
} catch (Throwable) {
    $pdo = null;
}

require_once __DIR__ . '/../header.php';
?>
<meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
<meta name="keywords" content="BIM новости, ИИ строительство, LLM, RAG, нормативы строительство, BIM конференции 2026">
<link rel="canonical" href="https://chernetchenko.pro/digest/<?= $cat !== 'all' ? '?cat=' . htmlspecialchars($cat) : '' ?>">
<meta property="og:title"       content="<?= htmlspecialchars($pageTitle) ?>">
<meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
<meta property="og:url"         content="https://chernetchenko.pro/digest/">
<meta property="og:type"        content="website">

<style>
/* ── Обёртка ─────────────────────────────────────────────────── */
.digest-page { max-width: 1140px; margin: 0 auto; padding: 40px 48px 80px; }
@media (max-width: 900px) { .digest-page { padding: 28px 24px 64px; } }
@media (max-width: 600px) { .digest-page { padding: 16px 14px 48px; } }

/* ── Hero ────────────────────────────────────────────────────── */
.digest-hero { padding: 0 0 28px; border-bottom: 1px solid var(--border); margin-bottom: 28px; }
.digest-hero h1 { font-family: var(--font-code); font-size: clamp(1.6rem,4vw,2.6rem); font-weight: 700; color: var(--text-main); margin: 0 0 8px; line-height: 1.15; }
.digest-hero p  { font-size: .9rem; color: var(--text-muted); margin: 0; }

/* ── Сводка дня ──────────────────────────────────────────────── */
.daily-summary {
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  padding: 22px 26px;
  margin-bottom: 24px;
  position: relative; overflow: hidden;
}
.daily-summary::before { content:""; position:absolute; top:0;left:0;right:0;height:2px;background:var(--c-rag); }
.ds-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; gap:12px; flex-wrap:wrap; }
.ds-title { font-family:var(--font-code); font-size:.82rem; font-weight:700; color:var(--c-rag); text-transform:uppercase; letter-spacing:.06em; }
.ds-date  { font-family:var(--font-code); font-size:.72rem; color:var(--text-dim); }
.ds-toggle { font-family:var(--font-code); font-size:.72rem; color:var(--c-main); background:transparent; border:1px solid var(--border); border-radius:var(--r-sm); padding:3px 10px; cursor:pointer; transition:all var(--ease); }
.ds-toggle:hover { border-color:var(--c-main); }
.ds-body  { font-size:.9rem; color:var(--text-muted); line-height:1.75; }
.ds-count { font-family:var(--font-code); font-size:.72rem; color:var(--text-dim); margin-top:10px; }

/* ── Поиск ───────────────────────────────────────────────────── */
.digest-search { position:relative; margin-bottom:20px; }
.digest-search input {
  width:100%; background:var(--bg-tertiary);
  border:1px solid var(--border); border-radius:var(--r-sm);
  padding:11px 14px 11px 38px;
  font-family:var(--font-code); font-size:.9rem; color:var(--text-main);
  transition:border-color var(--ease), box-shadow var(--ease); outline:none;
  box-sizing:border-box;
}
.digest-search input::placeholder { color:var(--text-dim); }
.digest-search input:focus { border-color:var(--c-main); box-shadow:0 0 0 3px rgba(42,98,154,.12); }
.digest-search .search-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-family:var(--font-code); font-size:.8rem; color:var(--text-dim); pointer-events:none; }
.digest-search .search-hint { font-family:var(--font-code); font-size:.72rem; color:var(--text-dim); margin-top:6px; display:none; }

/* ── Фильтры ─────────────────────────────────────────────────── */
.digest-filters { display:flex; flex-direction:column; gap:10px; margin-bottom:28px; }
.filter-row { display:flex; gap:7px; flex-wrap:wrap; align-items:center; }
.filter-label { font-family:var(--font-code); font-size:.7rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; letter-spacing:.05em; margin-right:4px; flex-shrink:0; }
.filter-btn {
  font-family:var(--font-code); font-size:.72rem; font-weight:600;
  padding:5px 13px; border:1px solid var(--border); border-radius:var(--r-sm);
  background:transparent; color:var(--text-muted); cursor:pointer;
  text-decoration:none; transition:all var(--ease); display:inline-block;
}
.filter-btn:hover       { border-color:var(--text-muted); color:var(--text-main); }
.filter-btn.active      { background:var(--theme-accent, var(--c-main)); color:#fff; border-color:var(--theme-accent, var(--c-main)); }
.filter-btn.fc-all      { --theme-accent: var(--text-main); }
.filter-btn.fc-ai       { --theme-accent: var(--c-ai);   }
.filter-btn.fc-bim      { --theme-accent: var(--c-bim);  }
.filter-btn.fc-events   { --theme-accent: var(--c-rag);  }
.filter-btn.fc-norms    { --theme-accent: var(--text-dim);}

/* ── Карточки событий ────────────────────────────────────────── */
.news-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 14px;
  margin-bottom: 48px;
}
.news-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  padding: 18px 20px;
  display: flex; flex-direction: column;
  position: relative; overflow: hidden;
  transition: transform var(--ease), border-color var(--ease), box-shadow var(--ease);
}
.news-card::before { content:""; position:absolute; top:0;left:0;right:0;height:2px; background:var(--nc-accent, var(--c-main)); }
.news-card:hover   { transform:translateY(-3px); border-color:var(--nc-accent, var(--c-main)); box-shadow:var(--sh-sm); }
.news-card[data-cat="ai"]     { --nc-accent: var(--c-ai);  }
.news-card[data-cat="bim"]    { --nc-accent: var(--c-bim); }
.news-card[data-cat="events"] { --nc-accent: var(--c-rag); }
.news-card[data-cat="norms"]  { --nc-accent: var(--text-dim); }

.nc-head  { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.nc-badge {
  font-family:var(--font-code); font-size:.66rem; font-weight:700;
  text-transform:uppercase; letter-spacing:.06em;
  padding:2px 8px; border-radius:3px; flex-shrink:0;
  color:var(--nc-accent, var(--c-main));
  border:1px solid var(--nc-accent, var(--c-main));
  background:transparent;
}
.nc-source { font-family:var(--font-code); font-size:.7rem; color:var(--text-dim); }
.nc-title  { margin:0 0 10px; font-family:var(--font-code); font-size:.95rem; font-weight:700; line-height:1.35; }
.nc-title a{ color:var(--text-main); text-decoration:none; transition:color var(--ease); }
.nc-title a:hover { color:var(--nc-accent, var(--c-main)); }
.nc-ai   { font-size:.86rem; color:var(--text-muted); line-height:1.6; margin-bottom:10px; flex-grow:1; padding:6px 10px; background:rgba(0,0,0,.15); border-left:2px solid var(--nc-accent, var(--c-main)); border-radius:0 var(--r-sm) var(--r-sm) 0; }
.nc-desc { font-size:.86rem; color:var(--text-muted); line-height:1.6; margin-bottom:10px; flex-grow:1; }
.nc-foot { margin-top:auto; padding-top:10px; border-top:1px solid var(--border-sub); display:flex; justify-content:flex-end; }
.nc-foot a { font-family:var(--font-code); font-size:.78rem; font-weight:700; color:var(--nc-accent, var(--c-main)); text-decoration:none; transition:opacity var(--ease); }
.nc-foot a:hover { opacity:.7; }

.news-empty { grid-column:1/-1; text-align:center; padding:48px 20px; font-family:var(--font-code); color:var(--text-dim); }

/* Поиск — результаты */
#search-results { display:none; margin-bottom:28px; }
.search-result-hint { font-family:var(--font-code); font-size:.72rem; color:var(--text-dim); margin-bottom:12px; }
</style>

<div class="digest-page">

  <!-- Заголовок -->
  <div class="digest-hero">
    <div style="font-family:var(--font-code);font-size:.82rem;color:var(--c-rag);text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin-bottom:10px">
      <span class="cli-only">&gt;_ </span>AI &amp; BIM Дайджест
    </div>
    <h1><?= htmlspecialchars($pageTitle) ?></h1>
    <p><?= htmlspecialchars($pageDesc) ?></p>
  </div>

  <!-- Сводка дня -->
  <?php if ($dailySummary): ?>
  <div class="daily-summary">
    <div class="ds-head">
      <div class="ds-title"><span class="cli-only">[DIGEST] </span>Сводка дня</div>
      <div style="display:flex;gap:10px;align-items:center">
        <span class="ds-date"><?= htmlspecialchars(date('d.m.Y', strtotime($dailySummary['summary_date']))) ?></span>
        <button class="ds-toggle" onclick="toggleSummary()"><span class="cli-only">&gt;_ </span>Свернуть</button>
      </div>
    </div>
    <div class="ds-body" id="ds-body">
      <?= nl2br(htmlspecialchars($dailySummary['summary_text'] ?? '')) ?>
    </div>
    <div class="ds-count"><span class="cli-only">[INFO] </span>Обработано: <?= (int)($dailySummary['items_count'] ?? 0) ?> материалов</div>
  </div>
  <?php endif; ?>

  <!-- Поиск -->
  <div class="digest-search">
    <span class="search-icon"><span class="cli-only">grep </span><span class="mgmt-only">🔍</span></span>
    <input type="text" id="ai-search"
           placeholder="Поиск по дайджесту... (RAG, новые модели, BIM форумы)"
           autocomplete="off">
    <div class="search-hint" id="search-hint"><span class="cli-only">[WAIT] </span>ИИ ищет релевантные записи...</div>
  </div>

  <!-- Результаты поиска -->
  <div id="search-results">
    <div class="search-result-hint" id="search-result-hint"></div>
    <div class="news-grid" id="search-grid"></div>
  </div>

  <!-- Фильтры -->
  <div class="digest-filters">
    <div class="filter-row">
      <span class="filter-label"><span class="cli-only">--cat </span><span class="mgmt-only">Тема:</span></span>
      <a href="?"           class="filter-btn fc-all    <?= $cat==='all'    ? 'active':'' ?>">Все</a>
      <a href="?cat=ai"     class="filter-btn fc-ai     <?= $cat==='ai'     ? 'active':'' ?>"><span class="cli-only">cat=</span>ИИ</a>
      <a href="?cat=bim"    class="filter-btn fc-bim    <?= $cat==='bim'    ? 'active':'' ?>"><span class="cli-only">cat=</span>BIM</a>
      <a href="?cat=events" class="filter-btn fc-events <?= $cat==='events' ? 'active':'' ?>"><span class="cli-only">cat=</span>События</a>
      <a href="?cat=norms"  class="filter-btn fc-norms  <?= $cat==='norms'  ? 'active':'' ?>"><span class="cli-only">cat=</span>Нормы</a>
    </div>
    <div class="filter-row">
      <span class="filter-label"><span class="cli-only">--src </span><span class="mgmt-only">Источник:</span></span>
      <button class="filter-btn" onclick="filterBySrc('all')">Все</button>
      <button class="filter-btn" onclick="filterBySrc('Events-RF')">RSS события</button>
      <button class="filter-btn" onclick="filterBySrc('BIM-RF')">RSS BIM</button>
      <button class="filter-btn" onclick="filterBySrc('OpenRouter')">OpenRouter</button>
      <button class="filter-btn" onclick="filterBySrc('Habr')">Habr</button>
      <button class="filter-btn" onclick="filterBySrc('Google')">Google News</button>
    </div>
  </div>

  <!-- Сетка новостей -->
  <div class="news-grid" id="main-grid">
    <?php
    try {
      if (!$pdo) throw new RuntimeException('DB not connected');
      $sql    = "SELECT id, title, url, source, category, description, ai_summary FROM digest_events";
      $params = [];
      if ($cat !== 'all') { $sql .= " WHERE category = ?"; $params[] = $cat; }
      $sql .= " ORDER BY id DESC LIMIT 60";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      $events = $stmt->fetchAll();

      if (empty($events)) {
        echo '<div class="news-empty"><span class="cli-only">[EMPTY] </span>Пока пусто — коллектор ещё не запускался.</div>';
      } else {
        foreach ($events as $e) {
          $cat_e = htmlspecialchars($e['category'] ?? 'ai', ENT_QUOTES, 'UTF-8');
          $src   = htmlspecialchars($e['source']   ?? '',    ENT_QUOTES, 'UTF-8');
          $url   = htmlspecialchars($e['url']       ?? '#',  ENT_QUOTES, 'UTF-8');
          $title = htmlspecialchars($e['title']     ?? '',    ENT_QUOTES, 'UTF-8');
          $label = match($cat_e) {
            'ai'     => 'ИИ', 'bim' => 'BIM',
            'events' => 'События', 'norms' => 'Нормы',
            default  => strtoupper($cat_e),
          };
          echo '<article class="news-card" data-src="' . $src . '" data-cat="' . $cat_e . '">';
          echo '<div class="nc-head"><span class="nc-badge">' . $label . '</span><span class="nc-source">' . $src . '</span></div>';
          echo '<h3 class="nc-title"><a href="' . $url . '" target="_blank" rel="noopener">' . $title . '</a></h3>';
          if (!empty($e['ai_summary'])) {
            echo '<p class="nc-ai">' . htmlspecialchars(mb_substr($e['ai_summary'], 0, 260), ENT_QUOTES, 'UTF-8') . '</p>';
          } elseif (!empty($e['description'])) {
            echo '<p class="nc-desc">' . htmlspecialchars(mb_substr($e['description'], 0, 220), ENT_QUOTES, 'UTF-8') . '…</p>';
          }
          echo '<div class="nc-foot"><a href="' . $url . '" target="_blank" rel="noopener"><span class="cli-only">curl </span>Читать →</a></div>';
          echo '</article>';
        }
      }
    } catch (Throwable $ex) {
      echo '<div class="content-block block-error"><div class="content-block-header"><span class="cli-only">[ERROR] </span>Ошибка БД</div><p>' . htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8') . '</p></div>';
    }
    ?>
  </div>
</div>

<script>
function toggleSummary() {
  const body = document.getElementById('ds-body');
  const btn  = document.querySelector('.ds-toggle');
  const isHidden = body.style.display === 'none';
  body.style.display = isHidden ? '' : 'none';
  btn.innerHTML = isHidden
    ? '<span class="cli-only">&gt;_ </span>Свернуть'
    : '<span class="cli-only">&gt;_ </span>Развернуть';
}

function filterBySrc(src) {
  document.querySelectorAll('.news-card').forEach(c => {
    c.style.display = (src === 'all' || c.dataset.src.includes(src)) ? '' : 'none';
  });
  document.querySelectorAll('.filter-row:last-child .filter-btn').forEach(b => {
    b.classList.toggle('active', b.getAttribute('onclick') === "filterBySrc('" + src + "')");
  });
}

function renderCard(e) {
  const c = e.category || 'ai';
  const label = { ai:'ИИ', bim:'BIM', events:'События', norms:'Нормы' }[c] || c.toUpperCase();
  const desc  = e.ai_summary || e.description || '';
  const short = desc.length > 240 ? desc.substring(0, 240) + '…' : desc;
  const descHtml = short
    ? `<p class="${e.ai_summary ? 'nc-ai' : 'nc-desc'}">${short}</p>`
    : '';
  return `<article class="news-card" data-cat="${c}" data-src="${e.source || ''}">
    <div class="nc-head"><span class="nc-badge">${label}</span><span class="nc-source">${e.source || ''}</span></div>
    <h3 class="nc-title"><a href="${e.url || '#'}" target="_blank" rel="noopener">${e.title || ''}</a></h3>
    ${descHtml}
    <div class="nc-foot"><a href="${e.url || '#'}" target="_blank" rel="noopener"><span class="cli-only">curl </span>Читать →</a></div>
  </article>`;
}

// AI-поиск
let searchTimer;
const searchInput = document.getElementById('ai-search');
const searchHint  = document.getElementById('search-hint');
const searchResults = document.getElementById('search-results');
const searchGrid  = document.getElementById('search-grid');
const resultHint  = document.getElementById('search-result-hint');
const mainGrid    = document.getElementById('main-grid');

if (searchInput) {
  searchInput.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 3) {
      searchResults.style.display = 'none';
      mainGrid.style.display = '';
      searchHint.style.display = 'none';
      return;
    }
    searchHint.style.display = '';
    searchTimer = setTimeout(() => {
      const fd = new FormData();
      fd.append('q', q);
      fetch('/digest/api/search.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(items => {
          searchHint.style.display = 'none';
          if (!items || items.error || !items.length) {
            searchGrid.innerHTML = '<div class="news-empty"><span class="cli-only">[EMPTY] </span>Ничего не найдено</div>';
          } else {
            searchGrid.innerHTML = items.map(renderCard).join('');
            resultHint.textContent = 'Найдено: ' + items.length + ' материалов по запросу "' + q + '"';
          }
          searchResults.style.display = '';
          mainGrid.style.display = 'none';
        })
        .catch(() => {
          searchHint.style.display = 'none';
          searchGrid.innerHTML = '<div class="news-empty"><span class="cli-only">[ERROR] </span>Ошибка поиска</div>';
          searchResults.style.display = '';
          mainGrid.style.display = 'none';
        });
    }, 800);
  });
}
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
