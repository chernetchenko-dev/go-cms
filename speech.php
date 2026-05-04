<?php
/**
 * speech.php — Выступления v3.0 OVC Design System
 */
$siteId    = 'main';
$pageTitle = 'Выступления и презентации — Олег Чернетченко';
$pageDesc  = 'Список выступлений на конференциях СПбГАСУ, 100+ TechnoBuild, Legko BIM. Тезисы, материалы докладов.';
include __DIR__ . '/header.php';
?>
<meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
<meta name="keywords" content="выступления, конференции, доклады, ТИМ, ИИ, управление проектами, СПбГАСУ, Технобилд">
<link rel="canonical" href="https://chernetchenko.pro/speech.php">
<meta property="og:title"       content="<?= htmlspecialchars($pageTitle) ?>">
<meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
<meta property="og:url"         content="https://chernetchenko.pro/speech.php">
<meta property="og:type"        content="website">

<style>
html { scroll-behavior: smooth; }
.speech-page { max-width: 900px; margin: 0 auto; padding: 48px 48px 80px; }
@media (max-width: 900px) { .speech-page { padding: 32px 28px 64px; } }
@media (max-width: 600px) { .speech-page { padding: 20px 16px 48px; } }

/* ── Карточка выступления ────────────────────────────────────── */
.speech-item {
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  padding: 28px 32px;
  margin-bottom: 16px;
  position: relative;
  overflow: hidden;
  transition: transform var(--ease), border-color var(--ease), box-shadow var(--ease);
}
.speech-item::before {
  content: "";
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
  background: var(--c-main);
}
.speech-item:hover { transform: translateY(-2px); border-color: var(--c-main); box-shadow: var(--sh-sm); }

/* планирую — другой цвет */
.speech-item.planned::before { background: var(--c-rag); }
.speech-item.planned { border-color: color-mix(in srgb, var(--c-rag) 30%, var(--border)); }

.speech-meta {
  font-family: var(--font-code);
  font-size: .7rem;
  color: var(--c-main);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .07em;
  margin-bottom: 10px;
}
.speech-item.planned .speech-meta { color: var(--c-rag); }

.speech-title {
  font-family: var(--font-code);
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text-main);
  margin: 0 0 12px;
  line-height: 1.3;
}
.speech-desc { font-size: .92rem; color: var(--text-muted); line-height: 1.65; margin-bottom: 14px; }
.speech-theses { list-style: none; padding: 0; margin: 0 0 14px; }
.speech-theses li {
  font-size: .88rem; color: var(--text-muted); padding-left: 16px;
  position: relative; margin-bottom: 6px; line-height: 1.5;
}
.speech-theses li::before { content: ">"; position: absolute; left: 0; color: var(--c-main); font-family: var(--font-code); font-size: .78rem; }
.speech-content { font-size: .9rem; color: var(--text-muted); line-height: 1.7; }
.speech-content p { margin-bottom: 10px; }
</style>

<div class="speech-page">

  <!-- Hero -->
  <section class="hero" style="padding:0 0 32px;border-bottom:1px solid var(--border);margin-bottom:40px;">
    <div class="hero__sub"><span class="cli-only">&gt;_ </span>Выступления</div>
    <h1 class="hero__title">Презентации<br><span class="accent">и доклады</span></h1>
    <p class="hero__desc">Материалы с конференций, лекций и отраслевых встреч. Только факты, без воды. Берите и используйте.</p>
  </section>

  <!-- Список выступлений -->

  <div class="speech-item planned">
    <div class="speech-meta">BIMAC 2026 · заявлено</div>
    <h2 class="speech-title">Автоматизация рутины: от макросов до агентов</h2>
    <p class="speech-desc">Эволюция инструментов проектировщика. Как переходить от ручных правок к скриптам, а от скриптов — к автономным агентам на базе LLM.</p>
    <ul class="speech-theses">
      <li>pyRevit и Dynamo: когда достаточно визуального программирования.</li>
      <li>C# add-ins для сложных задач, которые не решить скриптом.</li>
      <li>Агенты на базе MCP: нейросеть как коллега в Revit.</li>
      <li>Практические примеры: проверка спецификаций, генерация ведомостей.</li>
    </ul>
  </div>

  <div class="speech-item">
    <div class="speech-meta">СПбГАСУ · 11 ноября 2025</div>
    <h2 class="speech-title">Карьера без резюме: как стать директором по проектированию, не открывая hh.ru</h2>
    <p class="speech-desc">Личная история пути от инженера по эксплуатации до руководителя департамента. Почему социальный капитал и готовность брать ответственность важнее идеального старта.</p>
    <ul class="speech-theses">
      <li>Практика на реальной стройке как база для управленческих решений.</li>
      <li>Нетворкинг со студенчества: как связи заменяют формальные собеседования.</li>
      <li>Аванс знаний: брать сложные задачи и учиться в процессе.</li>
      <li>Почему резюме не работает в узкопрофильных инженерных нишах.</li>
    </ul>
    <div class="speech-content">
      <p>Выступление построено на личном опыте перехода от технической роли к управленческой. В узких инженерных нишах (BIM, ТИМ, управление проектами) формальные аттестации и резюме на hh.ru не работают.</p>
      <p>Основной капитал — это люди, с которыми вы строили объекты. Заказчик звонит тому, кого знает по совместной работе.</p>
    </div>
  </div>

  <div class="speech-item">
    <div class="speech-meta">100+ TechnoBuild · 2025</div>
    <h2 class="speech-title">Куда делись все проектировщики, или почему BIM — всего лишь инструмент</h2>
    <p class="speech-desc">Рынок перегрет специалистами по BIM-координации, но испытывает дефицит инженеров с базовым пониманием строительства.</p>
    <ul class="speech-theses">
      <li>BIM-менеджер без знания физики здания теряет ценность для заказчика.</li>
      <li>Почему координатор не заменяет ГИПа в принятии технических решений.</li>
      <li>Как растить кадры внутри бюро, а не искать готовых на рынке.</li>
    </ul>
  </div>

  <div class="speech-item">
    <div class="speech-meta">100+ TechnoBuild · 2025</div>
    <h2 class="speech-title">СОД: система организации данных вместо хаоса в папках</h2>
    <p class="speech-desc">Практический подход к структурированию проектной информации. Как навести порядок в файлах, чтобы не терять время на поиск и не срывать сроки.</p>
    <ul class="speech-theses">
      <li>Единая структура папок для всех разделов проекта.</li>
      <li>Правила именования файлов, которые работают на практике.</li>
      <li>Как СОД экономит время при согласовании с экспертизой.</li>
    </ul>
  </div>

  <div class="speech-item">
    <div class="speech-meta">Legko BIM · 2025</div>
    <h2 class="speech-title">RAG из данных: где и как хранить, чтобы не отравиться</h2>
    <p class="speech-desc">Архитектура безопасного использования ИИ в проектном бюро. Разделение потоков данных и правила работы с нейросетями без нарушения NDA.</p>
    <ul class="speech-theses">
      <li>Почему нельзя грузить внутренние регламенты в облачные LLM.</li>
      <li>Локальные модели для конфиденциальных данных: Ollama, LM Studio.</li>
      <li>Векторные базы и чанкинг: как подготовить знания для RAG.</li>
      <li>MCP-серверы как мост между нейросетью и Revit.</li>
    </ul>
  </div>

  <div class="speech-item">
    <div class="speech-meta">БИМ-завтрак · 2024</div>
    <h2 class="speech-title">Безысходные данные: как работать с неполной исходкой</h2>
    <p class="speech-desc">Реальная практика: что делать, когда заказчик прислал ТЗ за день до старта, а обмеры ещё не готовы.</p>
    <ul class="speech-theses">
      <li>Фиксация допущений в начале проекта как страховка от переделок.</li>
      <li>Итеративная модель: запускать работу можно и без 100% данных.</li>
      <li>Как аргументированно требовать информацию, не срывая отношения.</li>
    </ul>
  </div>

  <div class="speech-item">
    <div class="speech-meta">БИМ-завтрак · 2024</div>
    <h2 class="speech-title">Как организовать работу техзаказчика в ТИМ</h2>
    <p class="speech-desc">Роль технического заказчика в процессе информационного моделирования. Как выстроить коммуникацию, чтобы не получить модель, которую никто не открывает.</p>
    <ul class="speech-theses">
      <li>ТЗ на СОД: что писать, чтобы проектировщики не делали лишнего.</li>
      <li>Контрольные точки: когда проверять модель, а не ждать финал.</li>
      <li>Приёмка: чек-лист для техзаказчика без погружения в Revit.</li>
    </ul>
  </div>

  <div class="speech-item">
    <div class="speech-meta">Фит-аут ВТП · 2024</div>
    <h2 class="speech-title">Фит-аут: проектирование интерьеров в условиях сжатых сроков</h2>
    <p class="speech-desc">Специфика работы с коммерческими интерьерами. Как уложиться в график, когда концепция меняется каждую неделю, а стройка уже началась.</p>
    <ul class="speech-theses">
      <li>Модульная сетка и типовые узлы как основа скорости.</li>
      <li>Согласование с арендаторами: как фиксировать изменения без хаоса.</li>
      <li>Работа с поставщиками: закладывать их требования в модель заранее.</li>
    </ul>
  </div>

  <div class="btn-group" style="margin-top:40px">
    <a href="/" class="btn btn-nav c-main"><span class="cli-only">~/ </span>На главную</a>
    <a href="/networking.php" class="btn btn-nav c-main"><span class="cli-only">cd </span>networking</a>
  </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
