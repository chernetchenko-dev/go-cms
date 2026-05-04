<?php
/**
 * СТРАНИЦА ВЫСТУПЛЕНИЙ
 * chernetchenko.pro/speech
 */
$siteId = 'main';
$pageTitle = 'Выступления и презентации — Олег Чернетченко';
include 'header.php';
?>
<meta name="description" content="Список выступлений Олега Чернетченко на конференциях СПбГАСУ, 100+ TechnoBuild, Legko BIM. Тезисы, материалы докладов, презентации.">
<meta name="keywords" content="выступления, конференции, доклады, ТИМ, ИИ, управление проектами, СПбГАСУ, Технобилд">
<link rel="canonical" href="https://chernetchenko.pro/speech/">
<?php
$pageStyle = '
.speech-list { padding: 3rem 0 5rem; }
.speech-item { border: 1.5px solid var(--border); border-radius: 8px; padding: 2rem; margin-bottom: 1.5rem; background: #fff; }
.speech-item:hover { border-color: var(--accent); }
.speech-meta { font-family: var(--font-mono); font-size: 0.65rem; color: var(--accent); font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.8rem; }
.speech-title { font-family: var(--font-title); font-size: 1.3rem; font-weight: 800; color: var(--ink); margin-bottom: 1rem; line-height: 1.3; }
.speech-desc { font-size: 0.95rem; color: var(--ink2); line-height: 1.6; margin-bottom: 1.2rem; }
.speech-theses { list-style: none; padding: 0; margin: 1.2rem 0; }
.speech-theses li { font-size: 0.9rem; color: var(--ink2); padding-left: 1.2rem; position: relative; margin-bottom: 0.6rem; line-height: 1.5; }
.speech-theses li::before { content: "→"; position: absolute; left: 0; color: var(--accent); font-weight: 900; }
.speech-file { display: inline-flex; align-items: center; gap: 6px; padding: 0.5rem 1rem; background: rgba(26,79,160,0.06); border: 1px solid var(--border); border-radius: 4px; text-decoration: none; color: var(--ink); font-family: var(--font-mono); font-size: 0.7rem; font-weight: 600; transition: all 0.2s; }
.speech-file:hover { background: var(--accent); border-color: var(--accent); color: #fff; }
.speech-file svg { width: 14px; height: 14px; fill: currentColor; }
.section-header { padding: 2rem 0 1rem; border-bottom: 1.5px solid var(--border); margin-bottom: 2rem; }
.section-header h1 { font-family: var(--font-title); font-size: clamp(1.8rem, 4vw, 2.6rem); font-weight: 900; margin-bottom: 0.8rem; }
.section-header p { font-size: 1.05rem; color: var(--ink2); max-width: 700px; line-height: 1.6; }
@media(max-width: 640px) {
    .speech-item { padding: 1.5rem; }
    .speech-title { font-size: 1.15rem; }
}
';
?>
<style>
<?= $pageStyle ?>
</style>
<main class="container" style="max-width: 900px;">
<div class="section-header">
<div style="font-family: var(--font-mono); font-size: 0.65rem; color: var(--accent); letter-spacing: 0.1em; margin-bottom: 0.8rem; text-transform: uppercase; font-weight: 700;">ВЫСТУПЛЕНИЯ</div>
<h1>Презентации и доклады</h1>
<p>Материалы с конференций, лекций и отраслевых встреч. Только факты, без воды. Берите и используйте.</p>
</div>
<div class="speech-list">
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
<a href="/speech/Чернетченко СПБГАСУ 11-11.pdf" class="speech-file" target="_blank">
<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
Скачать презентацию
</a>
</div>
<div class="speech-item">
<div class="speech-meta">100+ TechnoBuild · 2025</div>
<h2 class="speech-title">Куда делись все проектировщики, или почему BIM — всего лишь инструмент</h2>
<p class="speech-desc">Рынок перегрет специалистами по BIM-координации, но испытывает дефицит инженеров с базовым пониманием строительства. Разбор кадрового кризиса и стратегий развития.</p>
<ul class="speech-theses">
<li>BIM-менеджер без знания физики здания теряет ценность для заказчика.</li>
<li>Почему координатор не заменяет ГИПа в принятии технических решений.</li>
<li>Как растить кадры внутри бюро, а не искать готовых на рынке.</li>
</ul>
<a href="/speech/Чернетченко_Рынок_Технобилд.pdf" class="speech-file" target="_blank">
<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
Скачать презентацию
</a>
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
<a href="/speech/Чернетченко_СОД_Технобилд.pdf" class="speech-file" target="_blank">
<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
Скачать презентацию
</a>
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
<a href="/speech/RAG-BIM-Final (2).pdf" class="speech-file" target="_blank">
<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
Скачать презентацию
</a>
</div>
<div class="speech-item">
<div class="speech-meta">BIMAC 2026 · заявлено</div>
<h2 class="speech-title">Автоматизация рутины: от макросов до агентов</h2>
<p class="speech-desc">Эволюция инструментов проектировщика. Как переходить от ручных правок к скриптам, а от скриптов — к автономным агентам на базе LLM.</p>
<ul class="speech-theses">
<li>pyRevit и Dynamo: когда достаточно визуального программирования.</li>
<li>C# add-ins для сложных задач, которые не решить скриптом.</li>
<li>Агенты на базе MCP: нейросеть как коллега в Revit.</li>
<li>Практические примеры: проверка спецификаций, генерация ведомостей.</li>
</ul>
<a href="/speech/BIMAC_2026_Chernetchenko_6.pdf" class="speech-file" target="_blank">
<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
Скачать презентацию
</a>
</div>
<div class="speech-item">
<div class="speech-meta">БИМ-завтрак · 2024</div>
<h2 class="speech-title">Безысходные данные: как работать с неполной исходкой</h2>
<p class="speech-desc">Реальная практика: что делать, когда заказчик присылает ТЗ за день до старта, а обмеры еще не готовы. Стратегии минимизации рисков.</p>
<ul class="speech-theses">
<li>Фиксация допущений в начале проекта как страховка от переделок.</li>
<li>Итеративная модель: запускать работу можно и без 100% данных.</li>
<li>Как аргументированно требовать информацию, не срывая отношения.</li>
</ul>
<a href="/speech/Чернетченко_Безысходные_данные_БИМ_завтрак.pdf" class="speech-file" target="_blank">
<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
Скачать презентацию
</a>
</div>
<div class="speech-item">
<div class="speech-meta">БИМ-завтрак · 2024</div>
<h2 class="speech-title">Как организовать работу техзаказчика в ТИМ</h2>
<p class="speech-desc">Роль технического заказчика в процессе информационного моделирования. Как выстроить коммуникацию, чтобы не получить модель, которую никто не открывает.</p>
<ul class="speech-theses">
<li>ТЗ на СОД: что писать, чтобы проектировщики не делали лишнего.</li>
<li>Контрольные точки: когда проверять модель, а не ждать финал.</li>
<li>Приемка: чек-лист для техзаказчика без погружения в Revit.</li>
</ul>
<a href="/speech/Чернетченко_Как_организовать_работу_техзаказчика_БИМ_завтрак.pdf" class="speech-file" target="_blank">
<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
Скачать презентацию
</a>
</div>
<div class="speech-item">
<div class="speech-meta">Внутренний тренинг · 2024</div>
<h2 class="speech-title">Фит-аут: проектирование интерьеров в условиях сжатых сроков</h2>
<p class="speech-desc">Специфика работы с коммерческими интерьерами. Как уложиться в график, когда концепция меняется каждую неделю, а стройка уже началась.</p>
<ul class="speech-theses">
<li>Модульная сетка и типовые узлы как основа скорости.</li>
<li>Согласование с арендаторами: как фиксировать изменения без хаоса.</li>
<li>Работа с поставщиками: закладывать их требования в модель заранее.</li>
</ul>
<a href="/speech/Чернетченко_Фит-аут_ВТП.pdf" class="speech-file" target="_blank">
<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
Скачать презентацию
</a>
</div>
</div>
</main>
<script>
document.querySelectorAll('.speech-file').forEach(link => {
link.addEventListener('click', (e) => {
ym(108508539, 'reachGoal', 'speech_download');
});
});
</script>
<?php include 'footer.php'; ?>
