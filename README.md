# go-cms

Flat-file CMS на PHP
Статьи в Markdown, контент без БД, дайджест новостей через AI, мультисайтовость из коробки.

> **OVC Design System v3.0** — терминальная эстетика, дуальный режим dev/manager, светлая и тёмная тема.

---

## Что умеет

| Функция | Описание |
|---|---|
| **CMS редактор** | Markdown + EasyMDE, YAML front matter, WYSIWYG preview |
| **Мультисайт** | Динамический Engine Mode — сайты определяются по директориям |
| **Layout Builder** | Конструктор главной страницы без кода, drag-and-drop |
| **AI-дайджест** | Сбор новостей с любых URL через OpenRouter, автосаммаризация |
| **Источники** | Добавление/удаление URL-источников через админку |
| **Мероприятия** | Календарь конференций, автодобавление из дайджеста |
| **Networking** | Страница нетворкинга с поиском и фильтрацией по городам |
| **SEO-оверрайды** | Управление мета-тегами для PHP-страниц из панели |
| **Безопасность** | Brute-force защита, AES-256-CBC шифрование API-ключей |
| **Дашборд** | Статусы всех систем, KPI, быстрый доступ |

---

## Быстрый старт

### 1. Требования

- PHP 8.0+
- MySQL 8.0+ (только для дайджеста)
- Composer
- Права на запись в `content/`, `digest/logs/`, `digest/cache/`

### 2. Установка

```bash
git clone https://github.com/chernetchenko-dev/go-cms.git
cd go-cms
composer install

# Создать конфигурацию
cp config.example.php config.php
cp admin/config.example.php admin/config.php
# Заполнить значения в обоих файлах
```

### 3. Локальный запуск

```bash
php -S 127.0.0.1:8000
# Сайт: http://127.0.0.1:8000
# Админка: http://127.0.0.1:8000/admin/
```

### 4. Настройка дайджеста

1. Откройте **Настройки → Ключи** в админке
2. Добавьте OpenRouter API ключ (`sk-or-v1-...`)
3. Выберите модель (рекомендуется `google/gemma-3-27b-it:free` или `openrouter/free`)
4. Перейдите во вкладку **Источники** — добавьте URL сайтов
5. Вкладка **Дайджест → Запустить сбор**

---

## Архитектура

```
go-cms/
├── index.php           — Главная (JSON-driven, config/main_layout.json)
├── article.php         — Шаблон статьи (Markdown → HTML через Parsedown)
├── networking.php      — Мероприятия (events.json + поиск)
├── speech.php          — Выступления и доклады
├── 404.php             — Страница ошибки
├── header.php          — Единый хедер (OVC DS v3.0)
├── footer.php          — Единый футер
├── config.php          — ⚠️ В .gitignore (создать из config.example.php)
│
├── lib/
│   ├── frontmatter.php — Парсер YAML + getArticles() со статическим кешем
│   ├── views.php       — Счётчик просмотров (flat-file, flock-safe)
│   ├── sites.php       — Динамический Engine Mode (сканирование директорий)
│   ├── seo.php         — Хелпер renderSeoMeta()
│   ├── brute_force.php — Защита от перебора
│   └── openrouter.php  — Клиент OpenRouter API
│
├── config/
│   ├── main_layout.json    — Структура главной страницы
│   ├── seo_overrides.json  — SEO переопределения по файлам
│   └── ai_prompts.json     — Промпты AI-ассистентов
│
├── content/            — ⚠️ В .gitignore (личные статьи)
│   └── example.md      — Пример статьи с front matter
│
├── admin/
│   ├── index.php       — Панель управления (Terminal Dark v8.1)
│   ├── terminal.css    — UI-стили: тёмная/светлая тема, dev/mgr режим
│   └── api/            — 15 API-эндпоинтов (CRUD для всего)
│       ├── sources.php     — Источники дайджеста
│       ├── events.php      — Мероприятия (events.json)
│       ├── digest_action.php — Запуск сбора, логи, сводка
│       └── ...
│
├── digest/
│   ├── index.php       — Публичная страница дайджеста
│   ├── core/
│   │   ├── Db.php      — Singleton PDO (создаёт таблицы автоматически)
│   │   ├── Config.php  — Загрузка конфигурации
│   │   └── AiClient.php — AI-клиент с fallback-цепочкой
│   ├── collectors/
│   │   └── ai_scraper.php — AI-скрапер (источники из БД)
│   └── api/
│       ├── collector.php     — Точка запуска сбора
│       ├── summarize.php     — AI-саммаризация одного события
│       ├── daily_summary.php — Дневная сводка
│       └── search.php        — AI-поиск по дайджесту
│
└── frontend/
    └── ui-kit/         — OVC Design System v3.0
        ├── 00_manifest.md
        ├── css/ (01_tokens, 02_base, 03_components)
        └── js/  (04_components, 05_interactions)
```

---

## Мультисайтовость

`lib/sites.php` сканирует соседние директории по паттерну `*_chernetchenko_pro/` и определяет подсайты динамически. Структура:

```
/Code/SITE_F/
├── public_html_01/          → main (chernetchenko.pro)
├── waf_chernetchenko_pro/   → waf (waf.chernetchenko.pro)
├── toc_chernetchenko_pro/   → toc (toc.chernetchenko.pro)
└── fun_chernetchenko_pro/   → fun (fun.chernetchenko.pro)
```

Пути к контенту каждого сайта задаются в `config.php`:

```php
define('CONTENT_PATHS', json_encode([
    'main' => '/path/to/public_html_01/content',
    'waf'  => '/path/to/waf/content',
    // ...
]));
```

---

## Структура статьи (Markdown + YAML)

```markdown
---
title: Заголовок статьи
slug: article-slug
site: main
section: Название раздела
date: 2026-05-01
tags: [bim, ai, rag]
description: SEO-описание для мета-тега
draft: false
badge: new
---

Контент статьи в **Markdown**.
```

---

## Добавление источника дайджеста

Без кода — через админку:

1. **Источники** → поле URL → выбрать категорию (ИИ / BIM / Мероприятия / Нормативка)
2. Опционально: добавить кастомный промпт (переопределяет дефолтный по категории)
3. Нажать **[+] Добавить**
4. При следующем запуске сбора AI обойдёт этот URL

Автодетекция мероприятий: если AI определяет, что материал является анонсом конференции/форума (`is_event: true`), он автоматически добавляется в `events.json` и появляется на странице `/networking.php`.

---

## Дайджест — схема работы

```
Источники (digest_sources)
    ↓  [collector.php — ручной или cron]
AI-скрапер (ai_scraper.php)
    ↓  загружает HTML каждого URL
    ↓  отправляет текст в OpenRouter
    ↓  получает JSON: [{title, url, description, is_event}]
digest_events (MySQL)
    ↓  [summarize.php — фоново для каждой записи]
AI-саммаризация (relevance 1-10, summary, tags)
    ↓  [daily_summary.php — вручную или cron в 09:05]
digest_daily_summary (дневная сводка)
    ↓
digest/index.php (публичная страница)
    → фильтры по категории и источнику
    → AI-поиск через search.php
```

---

## Таблицы БД (создаются автоматически)

| Таблица | Назначение |
|---|---|
| `digest_events` | Собранные новости (title, url, category, ai_summary, tags) |
| `digest_sources` | Источники для сбора (url, category, prompt, active) |
| `digest_daily_summary` | Дневные AI-сводки |
| `digest_ai_log` | Лог вызовов AI (модель, токены, время) |
| `admin_settings` | Зашифрованные настройки (API-ключи AES-256) |

---

## AI-провайдер

По умолчанию — **OpenRouter** (агрегатор моделей). Поддерживает бесплатные модели:
- `openrouter/free`
- `google/gemma-3-27b-it:free`
- `meta-llama/llama-3.1-8b-instruct:free`

Модель настраивается в **Настройки → Ключи** в админке.  
Ключ получить: [openrouter.ai/keys](https://openrouter.ai/keys)

---

## Безопасность

- Авторизация через `$_SESSION['admin']` с `session.cookie_lifetime = 86400`
- Brute-force: блокировка IP через `lib/brute_force.php` (файловый кеш, `flock`)
- API-ключи: шифрование AES-256-CBC, ключ из `ENCRYPTION_KEY` в `admin/config.php`
- Пути: `realpath()` проверка при сохранении PHP-файлов
- JSON-API: `error_reporting(0)` — PHP-ошибки не попадают в ответ

---

## Лицензия

MIT — используйте, форкайте, адаптируйте.

---

*Проект: chernetchenko.pro | UI: OVC Design System v3.0 | Engine: go-cms*
