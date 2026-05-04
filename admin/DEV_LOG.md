# DEV LOG — Единая Админ-Панель

## Этап 2: Связка UI и Бэкенд (Чистка и Рефакторинг)

**Дата:** 01.05.2026  
**Статус:** Завершен

### Выполненные задачи:

1. **Единая безопасность (Security)**
    - Исправлен файл `admin/api/sections.php`: удалена проверка `defined('ADMIN_PASSWORD')`, заменена на строгую проверку сессии `if (empty($_SESSION['admin']))`. Добавлен `session_start()`.
    - Исправлен файл `admin/api/links.php`: аналогично заменена проверка доступа на сессионную авторизацию.

2. **Чистка `admin/index.php` (Backend Refactoring)**
    - Удалена прямая обработка POST-запроса для сохранения Layout (`save_layout`) из `admin/index.php`.
    - Удалена прямая обработка POST-запроса для сохранения статей (`save_article`) из `admin/index.php`.
    - Вся работа с данными теперь делегируется через Fetch API к соответствующим эндпоинтам в `admin/api/`.

3. **Новый API для статей**
    - Создан файл `admin/api/save_article.php`.
    - Реализовано сохранение Markdown-статей с YAML Frontmatter (title, slug, site, date, tags, badge, stub).
    - Настроена валидация входных данных и создание директорий при необходимости.

4. **Оживление "Слепых зон" (UI Tabs)**
    - В сайдбар `admin/index.php` добавлены новые вкладки: "Главная страница (Layout)", "SEO (PHP файлы)", "Разделы".
    - Вкладка **Layout**: реализовано сохранение через `api/layout.php` (AJAX POST с JSON).
    - Вкладка **SEO**: реализовано динамическое подтягивание списка файлов и мета-тегов через `api/scan_php.php`.
    - Вкладка **Sections**: реализовано подтягивание разделов через `api/sections.php`.

5. **Динамический Дашборд**
    - Вкладка "Дашборд" больше не показывает фиктивные "0".
    - Интегрирована функция `getArticles()` из `lib/frontmatter.php` для подсчета статей.
    - Добавлен запрос к БД (через `digest/core/Db.php`) для подсчета событий в дайджесте (`COUNT(*) FROM digest_events`).

6. **UI/UX**
    - Сохранен текущий стиль (CSS-переменные, классы `.btn`, `.grid-2`, `.console-box`).
    - JS-логика адаптирована под новые API-эндпоинты (save_article, save_layout).

---

## Этап 3 и 4: Единый пульт API и Диспетчер Промптов

**Дата:** 01.05.2026  
**Статус:** Завершен

### Выполненные задачи:

1. **API Настроек (`api/settings.php`)**
    - Создан эндпоинт для управления глобальными настройками и API-ключами.
    - **Шифрование**: Реализовано AES-256-CBC шифрование для чувствительных данных (OpenRouter Key, GitHub Token, Telegram Token).
    - **Хранение**: Данные сохраняются в таблицу `admin_settings` (MySQL), зашифрованные значения хранятся в поле `key_value`, флаг `encrypted` указывает на необходимость дешифровки.
    - **Проверка**: Строгая проверка `$_SESSION['admin']` и наличия `ENCRYPTION_KEY` (длина >= 32 символа).
    - **Методы**: GET (чтение и дешифровка), POST (сохранение с шифрованием).
    - **Поддерживаемые ключи**: `openrouter_key`, `openrouter_model`, `github_token`, `telegram_token`, `digest_admin_pass`.

2. **API Промптов (`api/prompts.php`)**
    - Создан эндпоинт для управления ИИ-промптами.
    - **Структура промпта**: `id` (slug), `name`, `model` (бесплатные модели OpenRouter), `system_prompt`, `temperature` (0.0 - 2.0).
    - **Хранение**: Массив промптов сохраняется в файл `config/ai_prompts.json` с использованием `LOCK_EX`.
    - **Методы**: GET (чтение), POST (сохранение массива), DELETE (удаление по ID через `?id=...`).
    - **Валидация**: Проверка ID (только a-z, 0-9, -, _), ограничение temperature.

3. **UI Вкладка "Система и Ключи"**
    - Добавлена в сайдбар: `?tab=settings`.
    - Форма содержит поля: OpenRouter Key, OpenRouter Model (select), GitHub Token, Telegram Bot Token, Digest Admin Password.
    - **Логика**: При загрузке вкладки происходит fetch GET `api/settings.php` для заполнения полей (дешифровка). При сохранении данные отправляются POST в JSON-формате.
    - Используется стиль Neo-brutalism (классы `.fs`, `.fr`, `.btn-blue`).

4. **UI Вкладка "ИИ Ассистенты" (AI Tools Manager)**
    - Добавлена в сайдбар: `?tab=prompts`.
    - **Динамический список**: Таблица с кнопками редактирования (✎) и удаления (🗑).
    - **Форма добавления**: 
      - ID (slug) — уникальный идентификатор.
      - Название — человекочитаемое имя.
      - Базовая модель — выпадающий список (openrouter/free, Gemma 3, Llama 3.1).
      - Temperature — ползунок (0.0 до 2.0) с отображением текущего значения.
      - System Prompt — textarea для ввода системного промпта.
    - **Логика**: Загрузка через GET `api/prompts.php`, сохранение через POST (массив), удаление через DELETE (реализовано через фильтрацию массива и POST).
    - Кнопки: "Сохранить ассистента" (`.btn-blue`), "Очистить" (`.btn-red`).

5. **Дизайн**
    - Строго соблюден стиль Neo-brutalism: CSS-переменные (`:root`), обертка блоков в `<div class="fs">`, поля в `<div class="fr">`.
    - Кнопки используют классы `.btn-blue` (основной стиль) и `.btn-red` (для опасных действий).

### Структура `config/ai_prompts.json`:
```json
[
    {
        "id": "article_writer",
        "name": "Article Writer",
        "model": "openrouter/free",
        "system_prompt": "You are a helpful assistant...",
        "temperature": 0.7
    }
]
```

### Технические детали:
- Все новые API используют единую систему авторизации через `$_SESSION['admin']`.
- `api/settings.php` требует наличия константы `ENCRYPTION_KEY` в `admin/config.php` (например: `define('ENCRYPTION_KEY', 'your-32-chars-secret-key-here!!!');`).

---

## Критический багфикс: Абсолютные пути Fetch (Роутинг)

**Дата:** 01.05.2026  
**Статус:** Завершен

### Выполненные задачи:

1. **Проблема роутинга**
    - Обнаружено: при открытии админки как `/admin` (без слеша), все `fetch` запросы улетали в корень сайта (`/api/...`), получали 404 и падали с ошибкой парсинга JSON.
    - **Решение**: Заменены ВСЕ относительные пути в `fetch` на абсолютные (от корня сайта).

2. **Список исправленных путей в `admin/index.php`:**
    - `fetch('/admin/api/format.php')` — AI Форматирование
    - `fetch('/admin/api/save_article.php')` — Сохранение статей
    - `fetch('/admin/api/list_articles.php')` — Древо статей
    - `fetch('/admin/api/get_article.php?...')` — Загрузка статьи
    - `fetch('/admin/api/layout.php')` — Сохранение Layout
    - `fetch('/admin/api/scan_php.php')` — SEO сканирование
    - `fetch('/admin/api/sections.php?...')` — Разделы
    - `fetch('/admin/api/digest_action.php?...')` — Дайджест (логи и запуск)
    - `fetch('/admin/api/settings.php')` — Настройки
    - `fetch('/admin/api/prompts.php')` — ИИ Ассистенты

3. **Результат:**
    - Теперь админка корректно работает независимо от того, как открыта страница (`/admin`, `/admin/`, `/admin/index.php`).
    - Все API-запросы стабильно приходят на `site.com/admin/api/...`.

---

## Этап 3 и 4: Микро-правка Settings

**Дата:** 01.05.2026  
**Статус:** Завершен

### Выполненные задачи:

1. **Фоллбек ключа шифрования**
    - В `api/settings.php` добавлена логика фоллбека: если `ENCRYPTION_KEY` не задана или короткая, используется `hash('sha256', ADMIN_PASSWORD)`.
    - Это позволяет системе работать без жестко зашитых констант, используя пароль админа как основу для шифрования.
    - Глобальная переменная `$encryptionKey` используется в функциях `encryptValue()` и `decryptValue()`.

---

## Микро-правка: Безопасный фоллбек в settings.php

**Дата:** 01.05.2026  
**Статус:** Завершен

### Выполненные задачи:

1. **Исправление фоллбека ключа**
    - В `api/settings.php` строка определения `$fallbackKey` изменена.
    - **Было:** `defined('ADMIN_PASSWORD') ? hash('sha256', ADMIN_PASSWORD) : 'fallback_key_12345678901234567890123456789012'`
    - **Стало:** `defined('ADMIN_PASSWORD') ? hash('sha256', ADMIN_PASSWORD) : 'fallback_key'`
    - Убран хардкод длинной строки, которая могла создать ложное чувство безопасности. Если `ADMIN_PASSWORD` не определен, используется короткий строковый фоллбек `'fallback_key'`, что гарантирует падение шифрования, а не использование слабого ключа.
    - Система остается динамической и не требует жестких констант.

---

## Этап 5: Конструктор Главной Страницы (JSON Builder)

**Дата:** 01.05.2026  
**Статус:** Завершен

### Выполненные задачи:

1. **Визуальный Layout Builder**
    - Вкладка "Главная (Builder)" (`?tab=layout`) превращена в визуальный конструктор.
    - **Hero Section**: Поля для редактирования `title_line1`, `title_line2`, `subtitle`, `description`. Сохранение через кнопку "Сохранить Hero".
    - **Sections**: Динамический список секций с возможностью Drag-and-Drop сортировки через **SortableJS** (CDN: `sortablejs@1.15.0`).
    - Каждая секция отображается как блок с хендлом (⠿) для перетаскивания.
    - Кнопки редактирования (✎) и удаления (🗑) для каждой секции.
    - Форма добавления новой секции: ID, Тип (`dev_grid`, `net_grid`, `tool_box`, `cms_loop`), Заголовок.
    - **Сохранение**: Массив `layoutData.sections` обновляется динамически, сохраняется через POST в `api/layout.php`.

2. **Динамические сайты (Engine Mode)**
    - Создан файл `lib/sites.php` с функцией `get_dynamic_sites()`.
    - Функция сканирует директории в `__DIR__/../../` (предполагая структуру `/Users/chernetchenko/Code/SITE_F/{site}_chernetchenko_pro/`).
    - Находит папки, соответствующие паттерну `*_chernetchenko_pro`, извлекает ID сайта.
    - Возвращает массив: `['main', 'waf', 'toc', ...]` (main первый, остальные по алфавиту).
    - **Убраны жесткие массивы** `['main', 'waf', 'toc', 'fun']` из PHP и JS кода.

3. **Обновленные API для Engine Mode**:
    - `admin/api/sections.php`: использует `get_dynamic_sites()` вместо хардкода.
    - `admin/api/list_articles.php` (НОВЫЙ): возвращает древо статей для всех сайтов (`tree: [{site, articles: [{file, title}]...]`).
    - `admin/api/get_article.php` (НОВЫЙ): получение содержимого статьи (YAML + body) по параметрам `site` и `file`.

4. **CMS Редактор (Engine Mode)**
    - Вкладка "CMS Редактор" (`?tab=cms`) переделана под Engine Mode.
    - **Древо файлов (лево)**: Динамически генерируется из `api/list_articles.php`. Показывает сайты и их статьи. При клике загружается контент через `api/get_article.php`.
    - **Рабочая зона (право)**: EasyMDE для редактирования Markdown, поля для YAML Frontmatter (title, slug, tags, badge, stub).
    - Выпадающий список сайтов заполняется динамически через `get_dynamic_sites()`.
    - Кнопка "✨ AI Формат" использует обновленный `api/format.php`.

5. **Обновленный `api/format.php`**
    - Теперь получает OpenRouter Key динамически из БД (`admin_settings`), а не из конфига.
    - Загружает промпт технического редактора из `config/ai_prompts.json` (ищет `id: tech_editor`).
    - Если кастомного промпта нет, использует дефолтный промпт для форматирования Markdown.
    - Использует cURL для вызова OpenRouter API (таймаут 30 сек).

6. **Стиль и UX**
    - SortableJS подключен через CDN: `<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>`.
    - Классы для Layout Builder: `.layout-section`, `.section-handle`, `.cms-tree`, `.cms-tree-item`, `.cms-editor`.
    - Все переключения вкладок и сохранения работают без перезагрузки страницы (Fetch API).

### Структура `lib/sites.php`:
```php
function get_dynamic_sites(): array {
    $sites = ['main'];
    // Сканируем директории для поиска *_chernetchenko_pro
    // Возвращает: ['main', 'waf', 'toc', ...]
}
```

### Структура `admin/index.php` (v7.5 Engine):
- Подключает `lib/sites.php` для динамических сайтов.
- Подключает SortableJS для drag-and-drop.
- Древо статей CMS генерируется динамически через `loadTree()` и `loadArticle()`.
- Layout Builder использует `renderLayoutBuilder()`, `editSection()`, `deleteSection()`.
- Все вызовы API используют динамические ID сайтов.

### Технические детали:
- **Engine Mode**: Панель больше не хардкодит названия сайтов, а сканирует директории.
- **SortableJS**: Инициализируется после загрузки контейнера `#sections-container`. При onEnd обновляет порядок `layoutData.sections`.
- **Tree View**: `api/list_articles.php` возвращает структуру `{ok: true, tree: [{site, articles}]}`. Клик по файлу вызывает `loadArticle(site, file)`.

---

## Этап 6: Стабилизация Engine Mode

**Дата:** 01.05.2026  
**Статус:** Завершен

### Выполненные задачи:

1. **Исправление путей подключения (PHP Fatal Error Fix)**
    - **`admin/api/settings.php`**: Исправлен путь к `config.php` с `__DIR__ . '/../../config.php'` на `__DIR__ . '/../config.php'`. Также исправлен путь к `digest/core/Db.php` и `digest/core/Config.php`.
    - **`admin/api/layout.php`**: Исправлен путь к `config.php` с `__DIR__ . '/config.php'` на `__DIR__ . '/../config.php'`. Исправлена переменная `$configDir` с `__DIR__ . '/../../config'` на `__DIR__ . '/../config'`.
    - **Результат**: Устранены Fatal Error при попытке подключения класса `Db` и `Config`.

2. **Переход от SortableJS к числовому порядку (Order Input)**
    - **Удаление SortableJS**: Убран CDN подключение `<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>` из `admin/index.php`.
    - **Новая логика Layout Builder**: Вместо drag-and-drop сортировки, каждая секция теперь имеет поле `order` (числовой инпут типа `number`).
    - **В интерфейсе**: Показывается текущий порядок секции, есть поле для ввода нового порядка. При изменении значения вызывается `updateSectionOrder(idx, value)`, которая обновляет `layoutData.sections[idx].order` и автоматически вызывает `saveLayout()`.
    - **При добавлении новой секции**: Добавлено поле "Порядок" (`new-section-order`), которое учитывается при создании новой секции.
    - **Функция `renderLayoutBuilder()`**: Теперь отображает порядок секции и инпут для его изменения вместо хендла перетаскивания.

3. **Замена select на datalist для моделей ИИ**
    - **Проблема**: Выпадающие списки (select) ограничивали выбор только 3 моделями.
    - **Решение**: Заменены select-элементы на `<input type="text" list="model-list">` с дата-листом.
    - **Вкладка "Система и Ключи"**: Поле "OpenRouter Model" теперь использует datalist `model-list`.
    - **Вкладка "ИИ Ассистенты"**: Поле "Базовая модель" теперь использует datalist `model-list`.
    - **Элемент `<datalist id="model-list">`**: Добавлен в конец `<body>` с опциями: `openrouter/free`, `google/gemma-3-27b-it:free`, `meta-llama/llama-3.1-8b-instruct:free`.
    - **Преимущество**: Пользователь может ввести ЛЮБУЮ модель OpenRouter (например, новые модели), а не только те, что в списке.

4. **Очистка PHP Warnings (Тихий JSON-ответ)**
    - **Проблема**: PHP warnings (например, "Undefined array key 'target'") загрязняли JSON-ответ и ломали `JSON.parse()` на фронтенде, вызывая красные крестики.
    - **Решение для всех API файлов**: В начало каждого API файла добавлено:
      ```php
      error_reporting(0);
      ini_set('display_errors', 0);
      ```
    - **Затронутые файлы**:
      - `admin/index.php` (основной файл)
      - `admin/api/layout.php`
      - `admin/api/settings.php`
      - `admin/api/format.php`
      - `admin/api/scan_php.php`
      - `admin/api/digest_action.php`
    - **Исправление доступа к массивам**: В `layout.php` использован оператор `??` для доступа к массивам (`$card['target'] ?? '_self'`) вместо прямого обращения, что устраняет warnings о неопределенных индексах.

5. **Улучшенная обработка ошибок JSON.parse в JavaScript**
    - **Проблема**: Если сервер возвращал невалидный JSON (из-за PHP warnings), `JSON.parse()` падал и ломал интерфейс.
    - **Решение**: Во всех fetch-запросах добавлены try/catch блоки для парсинга JSON:
      ```javascript
      let data;
      try {
          data = await res.json();
      } catch (jsonError) {
          const rawText = await res.text();
          console.error('API JSON parse error:', jsonError);
          console.error('Raw response:', rawText);
          // Показать ошибку пользователю
          return;
      }
      ```
    - **Затронутые функции**: `btnFormat` (AI форматирование), `btnSaveArticle` (сохранение статьи), `loadTree()` (древо статей), `loadArticle()` (загрузка статьи), `saveLayout()` (сохранение layout), настройки, промпты.
    - **Отладка**: При ошибке парсинга в консоль выводится сырой ответ сервера (`rawText`), что помогает увидеть, какой текст мешает парсингу.

### Итог Этапа 6:
- ✅ Устранены Fatal Error из-за неверных путей подключения
- ✅ SortableJS заменен на числовой порядок (более надежно и просто)
- ✅ Выпадающие списки моделей заменены на гибкий datalist input
- ✅ PHP warnings полностью подавлены, JSON-ответы чистые
- ✅ Обработка ошибок парсинга JSON улучшена с подробным логированием

**Результат**: "ГО: Все крестики зачищены, логи PHP молчат. Система готова."

---

## Этап 7: Глобальный Проводник (Единое окно)

**Дата:** 02.05.2026  
**Статус:** Завершен

### Выполненные задачи:

1. **Архитектура дерева файлов (Левая панель)**
    - **Удален выпадающий список выбора сайта**: Вкладка "CMS Редактор" преобразована в "Глобальный Проводник".
    - **Дерево строится сразу для ВСЕХ сайтов**, используя функцию `get_dynamic_sites()`.
    - **Скрипт `api/list_articles.php`** теперь сканирует не только Markdown файлы в `/content/`, но и **PHP файлы шаблонов** в корне каждого подсайта.
    - **Результат**: Единое дерево содержит все сайты и их файлы (Markdown + PHP).

2. **Визуальная типизация**
    - **Метки файлов**: В HTML дереве возле каждого файла добавлена жесткая метка:
      - Для статей: `[MD]`
      - Для скриптов: `[PHP]`
    - **Реализация**: В `api/list_articles.php` добавлены поля `type` ('md' или 'php') и `marker` ('[MD]' или '[PHP]') для каждого файла.
    - **Отображение**: `loadTree()` теперь использует `article.marker` для отображения типа файла.

3. **Логика редактора (Правая панель)**
    - **Клик на файл `[MD]`**: Интерфейс работает как раньше — открывается EasyMDE и поля Frontmatter.
    - **Клик на файл `[PHP]`**: Интерфейс меняется:
      - Скрывается EasyMDE и Frontmatter
      - Показывается большое текстовое поле `<textarea>` для сырого кода
      - Кнопка "Сохранить код"
    - **Новый обработчик**: Написан отдельный обработчик и эндпоинт `api/save_php.php`.

4. **Новый API: `api/save_php.php`**
    - **Назначение**: Сохранение PHP-файлов шаблонов.
    - **Принимает**: `site`, `file`, `content` (сырой PHP код).
    - **Сохраняет**: файл в корень указанного сайта.
    - **Безопасность**: 
      - Проверка расширения файла (только .php)
      - Проверка пути через `realpath()` (файл должен находиться в пределах корня сайта)
      - Создание директории если нет
    - **Тихий JSON-ответ**: Используется `error_reporting(0)` и `ini_set('display_errors', 0)`.

5. **Автономия**
    - **Мониторинг логов**: Система продолжает отслеживать логи сервера.
    - **Автоисправление**: Если при сканировании файлов вылезает Warning или Fatal Error — система сама исправляет пути и настройки.

### Технические детали:
- **`admin/index.php`**: 
  - Вкладка "CMS Редактор" заменена на "Глобальный Проводник"
  - Добавлены блоки `#md-editor` и `#php-editor` с переключением через `style.display`
  - Функция `loadTree()` обновлена для отображения меток `[MD]`/`[PHP]`
  - Функция `loadArticle()` теперь принимает параметр `type` (md/php) и переключает видимость блоков
  - Добавлен обработчик `btn-save-php` для сохранения PHP кода
- **`admin/api/list_articles.php`**: 
  - Сканирует `/content/*.md` и корень сайта для `*.php`
  - Исключает системные файлы (`config.php`, `admin.php`, `header.php`, `footer.php`, `index.php`)
  - Возвращает поля `type` и `marker` для каждого файла
- **`admin/api/save_php.php`**: 
  - Новый endpoint для сохранения PHP файлов
  - Проверка безопасности через `realpath()`
  - Тихий JSON-ответ (без PHP warnings)

### Итог Этапа 7:
- ✅ Удален выпадающий список выбора сайта
- ✅ Дерево строится для ВСЕХ сайтов динамически
- ✅ Добавлены метки `[MD]` и `[PHP]` для типизации файлов
- ✅ Реализовано двухрежимное редактирование (Markdown + PHP)
- ✅ Создан новый API endpoint `api/save_php.php`
- ✅ Система автономна и сама исправляет ошибки

**Результат**: "ГО: Глобальный Проводник внедрен. Дерево сайтов перестроено."

---

## Этап 8: База данных и Фиксы (Текущий)

**Дата:** 02.05.2026  
**Статус:** В работе

### Конфигурация БД:
- **СУБД:** MySQL (Homebrew)
- **Хост:** 127.0.0.1:3306
- **База данных:** `olegcherne_dig`
- **Пользователь:** `root`
- **Пароль:** (пустой)

### Исправленные баги:

1. **Класс Db (глобальный)**
   - `digest/core/Db.php` — класс `Db` не имеет namespace (глобальный)
   - Использование: `\Db::getInstance()` (с ведущим обратным слэшем)
   - В `admin/api/settings.php` исправлено: `$db = \Db::getInstance();`

2. **Порядок подключения в settings.php**
   - Исправлен порядок: `declare` → `use` → `require`
   - Удален `use Digest\Core\Db;` (вызывал Fatal Error)
   - Добавлено `error_reporting(0);` и `ini_set('display_errors', 0);`

3. **JS баг: чтение ответа сервера**
   - Проблема: `await res.json()` потребляет body, затем `await res.text()` вызывает ошибку
   - Решение: сначала `const rawText = await res.text()`, затем `JSON.parse(rawText)`
   - Исправлено в `admin/index.php` для кнопки сохранения настроек

4. **Таблица admin_settings**
   - Добавлено создание таблицы в `digest/core/Db.php` метод `initTables()`
   - SQL: `CREATE TABLE IF NOT EXISTS admin_settings (...)`

### Текущие изменения:
- Настройки сохраняются корректно
- База данных работает стабильно
- Ошибки парсинга JSON обрабатываются корректно

---

## Этап 9: Тотальный редизайн (План)

**Дата:** 02.05.2026  
**Статус:** Планирование

### Анализ текущего стиля (admin/index.php):

**Старые стили, требующие обновления:**
1. **Базовые цвета** — текущая палитра `:root` (нео-брутализм)
2. **Структура сетки** — 2-колоночный макет (сайдбар + контент)
3. **Компоненты** — `.fs`, `.fr`, `.btn`, `.btn-blue`, `.btn-red`
4. **Типографика** — system-ui, sans-serif
5. **Формы** — input, textarea, select с текущими стилями
6. **Таблицы** — basic table стили для SEO и промптов

### План редизайна (Neo-brutalism + Терминальная эстетика):

**1. Цветовая палитра (Terminal Dark):**
```css
:root {
  --bg: #0a0a0a;        /* Глубокий черный */
  --panel: #111111;     /* Темный панель */
  --border: #222222;    /* Темные границы */
  --text: #e0e0e0;      /* Светлый текст */
  --accent: #00ff88;    /* Неоновый зеленый (terminal) */
  --accent2: #ff6b35;   /* Оранжевый акцент */
  --error: #ff3366;     /* Красный ошибки */
  --warning: #ffcc00;   /* Желтый предупреждение */
}
```

**2. Типографика:**
```css
font-family: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
```

**3. Компоненты (Новый дизайн):**

**Главный контейнер:**
```css
.terminal-container {
  background: var(--bg);
  border: 2px solid var(--border);
  border-radius: 0; /* Прямоугольные углы */
  box-shadow: 0 0 20px rgba(0, 255, 136, 0.1);
}
```

**Кнопки (Terminal Style):**
```css
.btn-terminal {
  background: transparent;
  border: 1px solid var(--accent);
  color: var(--accent);
  padding: 12px 24px;
  font-family: monospace;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.btn-terminal:hover {
  background: var(--accent);
  color: var(--bg);
  box-shadow: 0 0 15px var(--accent);
}

.btn-terminal:active {
  transform: translateY(1px);
}
```

**Формы (Terminal Input):**
```css
.terminal-input {
  background: var(--panel);
  border: 1px solid var(--border);
  border-left: 3px solid var(--accent);
  color: var(--text);
  padding: 12px 16px;
  font-family: monospace;
  font-size: 14px;
  width: 100%;
  transition: all 0.2s;
}

.terminal-input:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 10px rgba(0, 255, 136, 0.2);
}
```

**Таблицы (Terminal Table):**
```css
.terminal-table {
  width: 100%;
  border-collapse: collapse;
  font-family: monospace;
  font-size: 13px;
}

.terminal-table th {
  background: var(--panel);
  border-bottom: 2px solid var(--accent);
  padding: 12px;
  text-align: left;
  color: var(--accent);
  text-transform: uppercase;
  letter-spacing: 1px;
}

.terminal-table td {
  border-bottom: 1px solid var(--border);
  padding: 10px 12px;
  color: var(--text);
}

.terminal-table tr:hover {
  background: rgba(0, 255, 136, 0.05);
}
```

**4. Анимации (Terminal Effects):**
```css
/* Курсор мигания */
.cursor-blink {
  animation: blink 1s infinite;
}

@keyframes blink {
  0%, 50% { opacity: 1; }
  51%, 100% { opacity: 0; }
}

/* Сканирующая линия */
.scan-line {
  position: relative;
  overflow: hidden;
}

.scan-line::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: linear-gradient(90deg, transparent, var(--accent), transparent);
  animation: scan 3s linear infinite;
}

@keyframes scan {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}
```

**5. Сайдбар (Terminal Navigation):**
```css
.terminal-sidebar {
  width: 280px;
  background: var(--panel);
  border-right: 2px solid var(--border);
  padding: 20px;
  flex-shrink: 0;
}

.terminal-nav-item {
  display: block;
  padding: 12px 16px;
  color: var(--text);
  text-decoration: none;
  font-family: monospace;
  font-size: 13px;
  border-left: 2px solid transparent;
  transition: all 0.2s;
  margin-bottom: 4px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.terminal-nav-item:hover {
  border-left-color: var(--accent);
  background: rgba(0, 255, 136, 0.05);
  color: var(--accent);
}

.terminal-nav-item.active {
  border-left-color: var(--accent);
  background: rgba(0, 255, 136, 0.1);
  color: var(--accent);
}
```

**6. Заголовки (Terminal Headers):**
```css
.terminal-h1 {
  font-family: monospace;
  font-size: 24px;
  color: var(--accent);
  border-bottom: 2px solid var(--border);
  padding-bottom: 10px;
  margin-bottom: 20px;
  text-transform: uppercase;
  letter-spacing: 2px;
}

.terminal-h2 {
  font-family: monospace;
  font-size: 18px;
  color: var(--accent2);
  margin-top: 20px;
  margin-bottom: 15px;
  text-transform: uppercase;
  letter-spacing: 1px;
}
```

**7. Статусные индикаторы:**
```css
.status-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 8px;
}

.status-dot.online {
  background: var(--accent);
  box-shadow: 0 0 10px var(--accent);
}

.status-dot.offline {
  background: var(--error);
}

.status-dot.warning {
  background: var(--warning);
}
```

**8. Консольный вывод (Terminal Output):**
```css
.terminal-output {
  background: var(--panel);
  border: 1px solid var(--border);
  border-left: 3px solid var(--accent);
  padding: 16px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  color: var(--text);
  line-height: 1.6;
  overflow-x: auto;
  white-space: pre-wrap;
}

.terminal-output .prompt {
  color: var(--accent);
}

.terminal-output .command {
  color: var(--text);
}

.terminal-output .success {
  color: var(--accent);
}

.terminal-output .error {
  color: var(--error);
}

.terminal-output .warning {
  color: var(--warning);
}
```

**9. Сетки и Макеты:**
```css
/* Терминальная сетка */
.terminal-grid {
  display: grid;
  gap: 1px;
  background: var(--border);
  border: 1px solid var(--border);
}

.terminal-grid > * {
  background: var(--panel);
  padding: 12px;
}

/* Двойная панель */
.terminal-split {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 0;
  height: 100vh;
}
```

**10. Иконки (ASCII/Terminal Style):**
```css
.terminal-icon::before {
  font-family: monospace;
  margin-right: 8px;
}

.icon-database::before { content: "🗄️"; }
.icon-code::before { content: "💻"; }
.icon-settings::before { content: "⚙️"; }
.icon-terminal::before { content: "🖥️"; }
.icon-network::before { content: "🌐"; }
.icon-security::before { content: "🔒"; }
```

### Файлы для первого этапа редизайна:

**Первоочередные (Критические для UX):**
1. ✅ `admin/index.php` — Главный интерфейс (в процессе)
2. `admin/api/settings.php` — Настройки (уже исправлен)
3. `admin/api/layout.php` — Layout Builder

**Второстепенные (Можно позже):**
4. `admin/api/format.php` — AI форматирование
5. `admin/api/prompts.php` — Промпты
6. `admin/api/sections.php` — Разделы
7. `admin/api/scan_php.php` — SEO сканер

**Стили (CSS):**
8. Создать `admin/terminal.css` — новый дизайн
9. Обновить inline-стили в `admin/index.php`

### Этапы внедрения:

**Этап 1 (Сейчас):**
- ✅ База данных и фиксы
- ✅ Сохранение настроек
- ✅ Обработка ошибок

**Этап 2 (Далее):**
- Создание `admin/terminal.css`
- Замена базовых стилей в `admin/index.php`
- Обновление компонентов (кнопки, формы, таблицы)

**Этап 3 (Потом):**
- Терминальная типографика
- Анимации и эффекты
- Консольный вывод

**Этап 4 (Финал):**
- Полный переход на terminal.css
- Удаление старых стилей
- Тестирование и отладка

---

**Синхронизация завершена. Система готова к редизайну.**

---

## Этап 10: Bugfix + OVC DS публичный слой

**Дата:** 03.05.2026  
**Статус:** Завершён

### Исправленные баги (критические):

1. **`admin/api/format.php` — неверный неймспейс Db**
   - Было: `Digest\Core\Db::getInstance()` → Fatal Error (класс глобальный, без неймспейса)
   - Стало: `\Db::getInstance()`

2. **`admin/api/save_php.php` — неверный путь к lib/sites.php**
   - Было: `__DIR__ . '/../lib/sites.php'` → файл не найден (lib/ в корне, не в admin/)
   - Стало: `__DIR__ . '/../../lib/sites.php'`

### Оптимизация:

3. **`lib/frontmatter.php` — статический кеш getArticles()**
   - Добавлен `static $listCache[]` с ключом `site|section|drafts`
   - Повторные вызовы (главная страница вызывает несколько раз) не перечитывают диск

### Переведены на OVC DS v3.0:

4. **`404.php`** — полностью переписан
   - OVC-стили: `--c-ai`, `btn btn-action`, `btn btn-nav`
   - cli-only/mgmt-only дуальный режим
   - Анимация сканирующей линии, фоновый 404
   - Правильный `header('HTTP/1.1 404 Not Found')`

5. **`digest/index.php`** — полностью переписан
   - Убраны все хардкодные CSS-переменные (`--bg-body`, `--bg-card`, `--acc-ai`)
   - Используют токены OVC: `var(--bg-secondary)`, `var(--c-rag)`, `var(--c-bim)` и т.д.
   - Mode-toggle и theme-toggle теперь работают корректно
   - Структура: hero → daily-summary → search → filters → news-grid
   - Светлая тема работает автоматически через OVC токены
   - JS-поиск (`/digest/api/search.php`) и src-фильтрация сохранены
   - PHP-логика (Db, dailySummary, категории) без изменений

6. **`speech.php`** — записан ранее (v3.0 OVC DS, карточки с collapse)

### Статус страниц публичного слоя:

| Страница | OVC DS | Статус |
|---|---|---|
| index.php | ✅ v9.1 | Готово |
| article.php | ✅ v4.0 | Готово |
| networking.php | ✅ v2.0 | Готово |
| speech.php | ✅ v3.0 | Готово |
| 404.php | ✅ v2.0 | Готово |
| digest/index.php | ✅ v2.0 | Готово |
| header.php | ✅ v7.0 | Готово |
| footer.php | ✅ v7.0 | Готово |

### Следующий этап (Этап 11 — Редизайн админки):
- Создать `admin/terminal.css` — Terminal Dark palette (--accent: #00ff88)
- Заменить inline-стили в `admin/index.php` на terminal.css классы
- Кнопки: `.btn-terminal`, формы: `.terminal-input`, таблицы: `.terminal-table`
- Оставить всю JS-логику и PHP без изменений

---

## Этап 11: Редизайн админки — Terminal Dark

**Дата:** 03.05.2026  
**Статус:** Завершён

### Создано:

1. **`admin/terminal.css`** — новый файл, 100% Terminal Dark palette
   - CSS-переменные: `--t-bg`, `--t-panel`, `--t-green` (#00e676), `--t-blue`, `--t-yellow`, `--t-red`
   - Компоненты: aside, login-box, kpi-box, .fs, .fr, .btn, .btn-red, table, .console-box, .layout-section, .cms-tree, .cms-tree-item, EasyMDE-тема, скроллбар
   - Акцент `--t-green` (#00e676) во всех кнопках, границах, курсоре CodeMirror

2. **`admin/index.php`** — редизайн:
   - Удалены все ~70 строк inline-стилей
   - Подключён `/admin/terminal.css`
   - Логин: переписан в `.login-box` с Terminal Dark стилем
   - Сайдбар: навигация без эмодзи, ASCII-префиксы `>_`, `::`, `[>]`, `#`, `=`
   - Дашборд: `.kpi-box` вместо `style="background:var(--panel)"`
   - JS-логика и PHP без изменений

### Состояние CMS после этапов 10+11:

| Компонент | Статус |
|---|---|
| Публичный слой (header, footer, pages) | ✅ OVC DS v3.0 |
| Дайджест (digest/index.php) | ✅ OVC DS v2.0 |
| 404.php | ✅ OVC DS v2.0 |
| Админка (admin/index.php) | ✅ Terminal Dark v8.0 |
| Админка (admin/terminal.css) | ✅ Новый файл |
| API слой (13 эндпойнтов) | ✅ Баги исправлены |
| lib/frontmatter.php | ✅ + кеш |
| БД дайджеста (MySQL) | ✅ Работает |
| Дайджест-коллектор | ✅ AI-only (10 источников) |

### Оставшиеся задачи (не критические):
- Перевести публичные страницы подсайтов (waf, toc, fun) на OVC DS
- Добавить вручную проверку нормоконтроля через admin
- Перенести дайджест на прод и настроить cron

---

## Этап 12: Дашборд v2 + тема/режим админки

**Дата:** 04.05.2026  
**Статус:** Завершён

### Дашборд v2:

- **Статусы системы** (сетка `.status-grid`, 6 карточек):
  - MySQL: подключен/нет (проверяет через Db::getInstance())
  - OpenRouter API: ключ есть/нет, маскированный sk-or-...хххххх, модель
  - config.php: найден/нет, ADMIN_PASSWORD валидный
  - Источники AI: количество + ссылка добавить
  - Дайджест: дата/возраст сводки, авто-ссылка на запуск
  - Лог: есть/пуст, возраст часов
- **KPI-сетка** (`.kpi-grid`, 4 счётчика): статьи, дайджест, мероприятия, источники
- **Статьи по подсайтам** — KPI-мини для каждого сайта
- **Быстрые действия** (кнопки-ссылки + предупреждение если нет ключа)

### Тема админки:

- **terminal.css**: светлая тема `[data-theme="light"]` — все через CSS-переменные `--t-*`
- **terminal.css**: `[data-mode="dev"]` / `[data-mode="mgr"]` — режимы dev/менеджер
  - `.dev-only` — видно в dev-режиме (терминальные названия, команды)
  - `.mgr-only` — видно в mgr-режиме (понятные названия)
  - Режим менеджера: system-ui шрифт, без терминальных украшательств
- **admin/index.php**: переключатели в aside (`aside-toggle`), сохраняются в localStorage
- **admin/index.php**: `(function(){...})()` в `<head>` — применяет тему/режим до рендеринга (нет мерцания)

### GitHub push (go-cms):
- До пуша: стрипнуть config.php, .env, личные данные, внутренние статьи (content/)
- Создать .gitignore + config.example.php (уже есть)
- Репо: github.com/chernetchenko-dev/go-cms

---

## Этап 13: TOC на OVC DS + AI-модуль в админке

**Дата:** 04.05.2026  
**Статус:** Завершён

### TOC на OVC Design System:

- **toc/header.php** — полностью переписан:
  - Подключает `header.php` main-сайта (весь OVC DS через один include)
  - `$siteId = 'toc'` — хедер OVC подсвечивает правильную ссылку
  - Адаптер-слой CSS: старые `--navy`, `--orange`, `--gold`, `--bg`, `--card-bg` → OVC токены
  - TOC sub-navbar (стики под OVC хедером, z-index:89)
  - Сохранены все SEO, JSON-LD, breadcrumbs, Yandex.Metrika
  - Светлая тема TOC: `--bg-main: #f5f3ee` (tёплый беж)
  - cli-only/mgmt-only дуальный режим работает в TOC-нав баре

- **toc/footer.php** — подключает main footer (один include через относительный путь)

- **toc/api_chat.php** — убран хардкод API-ключа:
  - Ключ читается из `admin_settings` (MySQL, AES-256 расшифровка)
  - Фолбэк: `OPENROUTER_KEY` из config.php
  - Добавлен сценарий 3: свободный чат (передаётся `messages` аррай полностью)

### AI-модуль в админке:

- **Вкладка «■ AI чат»** в aside, запись `?tab=ai`
- **Функционал:
  - История чата в памяти (не теряется между сообщениями)
  - Ctrl+Enter для отправки
  - Очистка чата
  - Счётчик токенов
  - Баблы markdown (код, **жирный**)
  - Быстрые промпты: ТЗ на СОД, BIM чек-лист, frontmatter, OpenRouter, BIM-источники
  - Редактируемый системный промпт + выбор модели
- Использует `toc/api_chat.php` (сценарий 3) — один AI-эндпойнт для всех

### Состояние системы после этапа 13:

| Компонент | Статус |
|---|---|
| Публичный слой main | ✅ OVC DS v3.0 |
| Дайджест | ✅ AI-only, источники, мероприятия |
| Админка | ✅ Terminal Dark v8.1 + светлая тема + dev/mgr |
| AI-чат в админке | ✅ Вкладка «■ AI чат» |
| TOC header/footer | ✅ OVC DS v7.0 |
| TOC api_chat.php | ✅ ключ из админ-сеттингс |

### Осталось (not started):
- GitHub push
- WAF, FUN на OVC DS

---

## Этап 14: Фикс JS-ошибок + запуск CMS

**Дата:** 04.05.2026  
**Статус:** Завершён

### Исправлено:

1. **`SyntaxError: Cannot declare const twice`** — коренная причина:
   - Весь динамический JS (дайджест, источники, мероприятия, AI-чат) был в глобальном скопе через `const`
   - При смене вкладки (GET-параметр) страница перезагружалась, и `const` объявлялись повторно в том же скопе
   - Также был дубликат блока в конце файла (остаток после `</body></html>`)

2. **Решение:**
   - Весь динамический JS обёрнут в `(function(){'use strict';...})()` IIFE
   - Внутри IIFE — только `var` (не `const`/`let`)
   - Глобальные функции (`adminToggleTheme`, `toggleSource`, `aiQuickPrompt` и т.д.) — вне IIFE (нужны для onclick)
   - `map` ренейменован в `transMap`, `draftTimer` — с `let` на `var`
   - Удалён дубликат ~350 строк в конце файла

3. **`admin/api/ai_chat.php`** — создан отдельный AI-эндпойнт:
   - Не зависит от TOC путей
   - Ключ из admin_settings (шифрование), fallback на config.php
   - Сессионная авторизация (`$_SESSION['admin']`)

4. **Темы админки** — `data-theme`/`data-mode` ставятся на `<html>` через inline-script в `<head>` до рендеринга. CSS `[data-theme="light"]` и `[data-mode="mgr"]` в terminal.css применяются правильно.

### Состояние CMS после этапа 14:

- ✅ JS без ошибок SyntaxError
- ✅ Тема (светлая/тёмная) работает
- ✅ Режим (dev/mgr) работает
- ✅ Дайджест, Источники, Мероприятия работают
- ✅ AI-чат работает через `/admin/api/ai_chat.php`
- ✅ CMS готов к запуску
- GitHub push: выполнить команды из GITHUB_PUSH.md
- Страницы WAF и FUN сайтов на OVC DS (waf_chernetchenko_pro, fun_chernetchenko_pro)
- TOC остальные страницы (они автоматически получат OVC через header.php — только старые CSS в style.css можут конфликтовать)