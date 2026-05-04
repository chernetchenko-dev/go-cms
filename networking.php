<?php
/**
 * networking.php — Инженерия кулуаров v3.0 OVC Design System
 */
$siteId    = 'main';
$pageTitle = 'Инженерия кулуаров 2026: конференции BIM, ИИ и стройка | Олег Чернетченко';
include 'header.php';

// События из дайджеста
$digestEvents = [];
try {
    require_once __DIR__ . '/digest/core/Config.php';
    require_once __DIR__ . '/digest/core/Db.php';
    $pdo  = Db::getInstance()->getConnection();
    $stmt = $pdo->prepare("
        SELECT title, url, source, description, ai_summary, created_at
        FROM digest_events WHERE category = 'events'
        ORDER BY id DESC LIMIT 30
    ");
    $stmt->execute();
    $digestEvents = $stmt->fetchAll();
} catch (Throwable) { $digestEvents = []; }

// Локальные события
$localEvents = [];
$jsonFile = __DIR__ . '/events.json';
if (file_exists($jsonFile)) {
    $localEvents = json_decode(file_get_contents($jsonFile), true) ?: [];
}

// JSON-LD
$jsonLdEvents = [];
foreach ($localEvents as $ev) {
    if (!empty($ev['isPast'])) continue;
    $jsonLdEvents[] = ['@type'=>'Event','name'=>$ev['title'],'description'=>$ev['desc'],
        'url'=>$ev['link'],'location'=>['@type'=>'Place','name'=>$ev['city']??'']];
}
$jsonLd = ['@context'=>'https://schema.org','@graph'=>array_merge([[
    '@type'=>'WebPage','@id'=>'https://chernetchenko.pro/networking',
    'url'=>'https://chernetchenko.pro/networking','name'=>$pageTitle,
    'description'=>'Календарь конференций по BIM, ТИМ и ИИ в строительстве на 2026 год.',
    'inLanguage'=>'ru','author'=>['@type'=>'Person','name'=>'Олег Чернетченко'],
]],$jsonLdEvents)];
?>
<meta name="description" content="Календарь конференций по BIM, ТИМ и ИИ в строительстве 2026.">
<meta name="keywords" content="BIM конференции 2026, ТИМ мероприятия, нетворкинг строительство, BIMAC, 100+ TechnoBuild">
<link rel="canonical" href="https://chernetchenko.pro/networking">
<meta property="og:type"        content="website">
<meta property="og:url"         content="https://chernetchenko.pro/networking">
<meta property="og:title"       content="<?= htmlspecialchars($pageTitle) ?>">
<meta property="og:description" content="Календарь конференций по BIM, ТИМ и ИИ 2026.">
<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?></script>

<style>
html { scroll-behavior: smooth; }
.net-page { max-width: 900px; margin: 0 auto; padding: 48px 48px 80px; }
@media (max-width: 900px) { .net-page { padding: 32px 28px 64px; } }
@media (max-width: 600px) { .net-page { padding: 20px 16px 48px; } }

/* ── Поиск ───────────────────────────────────────────────────── */
.net-search-wrap { position: relative; margin-bottom: 20px; }
.net-search-wrap input {
  width: 100%;
  background: var(--bg-tertiary);
  border: 1px solid var(--border);
  border-radius: var(--r-sm);
  padding: 10px 14px 10px 38px;
  font-family: var(--font-code);
  font-size: .88rem;
  color: var(--text-main);
  transition: border-color var(--ease), box-shadow var(--ease);
  outline: none;
}
.net-search-wrap input::placeholder { color: var(--text-dim); }
.net-search-wrap input:focus { border-color: var(--c-main); box-shadow: 0 0 0 3px rgba(42,98,154,.14); }
.net-search-wrap .search-icon {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
  color: var(--text-dim); font-family: var(--font-code); font-size: .8rem; pointer-events: none;
}

/* ── Фильтры городов ─────────────────────────────────────────── */
.city-filters { display: flex; gap: 6px; margin-bottom: 28px; flex-wrap: wrap; }
.city-filter {
  font-family: var(--font-code); font-size: .7rem; font-weight: 700;
  padding: 6px 13px; border: 1px solid var(--border); border-radius: var(--r-sm);
  background: transparent; color: var(--text-muted); cursor: pointer;
  text-transform: uppercase; letter-spacing: .05em; transition: all var(--ease);
}
.city-filter:hover  { border-color: var(--c-main); color: var(--c-main); }
.city-filter.active { background: var(--c-main); color: #fff; border-color: var(--c-main); }

/* ── Карточки событий ────────────────────────────────────────── */
.events-grid { display: grid; gap: 12px; margin-bottom: 48px; }
.event-card {
  background: var(--bg-secondary); border: 1px solid var(--border);
  border-radius: var(--r-lg); padding: 20px 24px;
  transition: transform var(--ease), border-color var(--ease), box-shadow var(--ease);
  position: relative; overflow: hidden;
}
.event-card::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: var(--c-main); }
.event-card:hover   { transform: translateY(-2px); border-color: var(--c-main); box-shadow: var(--sh-sm); }
.event-card.past    { opacity: .5; }
.event-title { font-family: var(--font-code); font-size: .97rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }
.event-title a { color: inherit; text-decoration: none; transition: color var(--ease); }
.event-title a:hover { color: var(--c-main); }
.event-meta { display: flex; gap: 8px; align-items: center; color: var(--text-dim); font-size: .74rem; margin-bottom: 10px; font-family: var(--font-code); flex-wrap: wrap; }
.event-city  { font-weight: 700; color: var(--c-main); }
.past-badge  { font-size: .65rem; background: var(--bg-tertiary); border: 1px solid var(--border); padding: 1px 6px; border-radius: 3px; color: var(--text-dim); }
.event-desc  { color: var(--text-muted); line-height: 1.6; font-size: .88rem; margin-bottom: 10px; }
.event-tags  { display: flex; gap: 5px; flex-wrap: wrap; }
.event-tag   { font-family: var(--font-code); font-size: .68rem; padding: 2px 8px; border-radius: 3px; background: var(--bg-tertiary); border: 1px solid var(--border); color: var(--text-muted); }
.event-tag.speak { background: var(--c-main); color: #fff; border-color: var(--c-main); font-weight: 700; }
.no-results  { text-align: center; padding: 40px 20px; color: var(--text-dim); font-family: var(--font-code); font-size: .82rem; }

/* ── Блок дайджеста ──────────────────────────────────────────── */
.digest-block {
  background: var(--bg-secondary); border: 1px solid var(--border);
  border-radius: var(--r-lg); padding: 28px; margin-bottom: 48px;
  position: relative; overflow: hidden;
}
.digest-block::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: var(--c-rag); }
.digest-block h2   { font-family: var(--font-code); font-size: 1rem; font-weight: 700; color: var(--text-main); margin: 0 0 4px; }
.digest-label      { font-family: var(--font-code); font-size: .68rem; color: var(--c-rag); text-transform: uppercase; letter-spacing: .07em; margin-bottom: 20px; }
.digest-item       { padding: 12px 0; border-bottom: 1px solid var(--border-sub); }
.digest-item:last-of-type { border-bottom: none; }
.digest-item a     { font-family: var(--font-code); font-size: .88rem; font-weight: 600; color: var(--text-main); text-decoration: none; line-height: 1.4; transition: color var(--ease); }
.digest-item a:hover { color: var(--c-main); }
.digest-item-meta  { font-family: var(--font-code); font-size: .7rem; color: var(--text-dim); margin-top: 3px; }
.digest-item-desc  { font-size: .82rem; color: var(--text-muted); margin-top: 4px; line-height: 1.5; }
.digest-more       { display: inline-flex; align-items: center; gap: 6px; margin-top: 16px; font-family: var(--font-code); font-size: .74rem; font-weight: 700; color: var(--c-main); text-decoration: none; text-transform: uppercase; letter-spacing: .05em; transition: opacity var(--ease); }
.digest-more:hover { opacity: .7; }
</style>

<div class="net-page">

  <!-- Hero -->
  <section class="hero" style="padding:0 0 32px;border-bottom:1px solid var(--border);margin-bottom:32px;">
    <div class="hero__sub"><span class="cli-only">&gt;_ </span>Нетворкинг</div>
    <h1 class="hero__title">Инженерия<br><span class="accent">кулуаров</span></h1>
    <p class="hero__desc">Нетворкинг — это не раздача визиток. Это поиск решений в кулуарах, пока на сцене читают скучный доклад. Календарь мест, где в 2026 году можно встретить коллег по BIM, управлению и стройке.</p>
  </section>

  <!-- Поиск + фильтры -->
  <div class="net-search-wrap">
    <span class="search-icon"><span class="cli-only">grep</span><span class="mgmt-only">🔍</span></span>
    <input type="text" id="eventSearch" placeholder="Поиск по мероприятиям..." autocomplete="off">
  </div>
  <div class="city-filters" role="group">
    <button class="city-filter active" data-city="all">Все</button>
    <button class="city-filter" data-city="СПБ">Санкт-Петербург</button>
    <button class="city-filter" data-city="МСК">Москва</button>
    <button class="city-filter" data-city="ЕКБ">Екатеринбург</button>
    <button class="city-filter" data-city="КРАСНОДАР">Краснодар</button>
    <button class="city-filter" data-city="НСК">Новосибирск</button>
  </div>

  <h2 class="section-head" style="margin-bottom:20px">Календарь 2026</h2>
  <div id="events-container" class="events-grid"></div>

  <?php if (!empty($digestEvents)): ?>
  <div class="digest-block">
    <h2>Свежие форумы и конференции</h2>
    <div class="digest-label"><span class="cli-only">[RAG] </span>Из дайджеста · обновляется каждый день в 9:00</div>
    <?php foreach ($digestEvents as $de): ?>
    <div class="digest-item">
      <a href="<?= htmlspecialchars($de['url']??'', ENT_QUOTES,'UTF-8') ?>" target="_blank" rel="noopener">
        <?= htmlspecialchars($de['title']??'', ENT_QUOTES,'UTF-8') ?>
      </a>
      <div class="digest-item-meta"><?= htmlspecialchars($de['source']??'', ENT_QUOTES,'UTF-8') ?></div>
      <?php $desc=$de['ai_summary']?:($de['description']??''); if($desc): ?>
      <div class="digest-item-desc"><?= htmlspecialchars(mb_substr(strip_tags($desc),0,180), ENT_QUOTES,'UTF-8') ?>…</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <a href="/digest/?cat=events" class="digest-more"><span class="cli-only">cat </span>Все события в дайджесте →</a>
  </div>
  <?php endif; ?>

  <div class="btn-group">
    <a href="/" class="btn btn-nav c-main"><span class="cli-only">~/ </span>На главную</a>
  </div>
</div>

<script>
(function () {
  const data = <?= json_encode($localEvents, JSON_UNESCAPED_UNICODE) ?>;
  let cityFilter = 'all', query = '';
  function render() {
    const container = document.getElementById('events-container');
    const q = query.toLowerCase();
    const filtered = data.filter(ev => {
      const matchCity = cityFilter === 'all' || ev.city === cityFilter;
      const matchQ    = !q || ev.title.toLowerCase().includes(q) || ev.desc.toLowerCase().includes(q)
                        || (ev.tags||[]).some(t => t.name.toLowerCase().includes(q));
      return matchCity && matchQ;
    });
    if (!filtered.length) {
      container.innerHTML = '<div class="no-results"><span class="cli-only">[EMPTY] </span>Ничего не найдено</div>';
      return;
    }
    container.innerHTML = filtered.map(ev => {
      const tags     = (ev.tags||[]).map(t=>`<span class="event-tag ${t.type==='speak'?'speak':''}">${t.name}</span>`).join('');
      const pastLabel = ev.isPast ? '<span class="past-badge">Прошло</span>' : '';
      return `<article class="event-card ${ev.isPast?'past':''}" itemscope itemtype="https://schema.org/Event">
        <div class="event-title" itemprop="name"><a href="${ev.link}" target="_blank" rel="noopener" itemprop="url">${ev.title}</a></div>
        <div class="event-meta"><span class="event-city">${ev.city}</span><span>·</span><span>${ev.month} ${ev.days}</span>${pastLabel}</div>
        <div class="event-desc" itemprop="description">${ev.desc}</div>
        <div class="event-tags">${tags}</div>
      </article>`;
    }).join('');
  }
  document.querySelectorAll('.city-filter').forEach(btn => btn.addEventListener('click', function () {
    document.querySelectorAll('.city-filter').forEach(b=>b.classList.remove('active'));
    this.classList.add('active'); cityFilter = this.dataset.city; render();
  }));
  let timer;
  document.getElementById('eventSearch').addEventListener('input', function () {
    clearTimeout(timer); timer = setTimeout(()=>{ query=this.value; render(); },250);
  });
  render();
})();
</script>

<?php include 'footer.php'; ?>
