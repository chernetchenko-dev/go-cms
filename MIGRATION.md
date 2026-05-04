# Миграция chernetchenko.pro → OVC Design System v3.0

## Что сделано

### Фаза 1 — Подключение CSS/JS
- `header.php` подключает `/frontend/ui-kit/css/01_tokens.css`, `02_base.css`, `03_components.css`
- `header.php` подключает `/frontend/ui-kit/js/04_components.js`, `05_interactions.js`
- Добавлен адаптер-слой CSS: старые переменные `--bg`, `--ink`, `--accent` и т.д. маппятся на OVC-токены через `:root` в `<head>`, page-specific стили продолжают работать без переписывания

### Фаза 2 — header.php
- Структура переписана: `.header-inner` → `.site-header__inner` (OVC)
- Навигация: `.h-badge` → `.site-header__link` с `cli-only`/`mgmt-only` классами
- Mode toggle (Разработчик/Менеджер) и Theme toggle из OVC JS подключены и работают
- Два Telegram-бейджа сохранены: личный `@chernetchenko` и канал `@wearefired`
- Burger-меню для мобилки реализовано по OVC-паттерну
- Логотип: `ovc.pro>_` для main-сайта, название подсайта для остальных

### Фаза 3 — footer.php
- Структура переписана: `.footer-grid` → `.site-footer__inner + __top + __cols` (OVC)
- Ссылки: `.f-badge` → `.site-footer__col a` с OVC-стилями
- Добавлен `.site-footer__status` с зелёной пульсирующей точкой
- PHP-данные (`$sites[]`, `$siteId`, `parse_url`) сохранены без изменений

### Фаза 4 — index.php
- Hero → терминальный стиль `.hero__sub` с `>_` префиксом
- About (3 колонки) → `.about-card` с OVC border + `>` маркером списка
- Sticky nav chips → `.anchor-chips` с `.chip.active` при скролле
- `dev_grid` → `.article-card.colored` с цветами `c-rag/c-waf/c-toc/c-fun`
- `net_grid` → `.net-card` по аналогии с article-card (цветная полоса сверху)
- `tool_box` → `.tool-box-wrap` + `.btn.btn-action.c-bim`
- `cms_loop` → `.art-card` в OVC-стилях, пустой результат → `content-block block-info`, ошибка → `block-error`
- Подсветка активного чипа при скролле — нативный JS без зависимостей

### Фаза 5 — article.php
- Структура: `.article-container` с темой `theme-article-*`
- Тема акцента подбирается автоматически по полю `section` из frontmatter:
  - rag/waf/ai → `--article-accent: var(--c-ai)`
  - toc → `--article-accent: var(--c-toc)`
  - bim → `--article-accent: var(--c-bim)`
  - fun → `--article-accent: var(--c-fun)`
  - остальные → `--article-accent: var(--c-main)`
- Теги → `.art-tag` с рамкой цвета акцента
- Таблицы → terminal-table стиль (тёмная шапка, hover)
- 404 → `.empty-state-404` из OVC с терминальным сообщением
- PHP-логика (slug, parseArticle, incrementView, Parsedown) без изменений

### Фаза 6 — Gap-правки UI Kit
- `01_tokens.css`: добавлен `--c-waf: var(--c-ai)` (WAF = AI по цвету)
- `03_components.css`: добавлены `.c-waf`, `.article-card.c-waf`, `.article-card.colored.c-waf`

## Правила централизованных правок

| Что правим | Где |
|---|---|
| Цвета, шрифты, радиусы, тени | `frontend/ui-kit/css/01_tokens.css` |
| Типографика, отступы, медиа | `frontend/ui-kit/css/02_base.css` |
| Компоненты (кнопки, карточки, хедер, футер) | `frontend/ui-kit/css/03_components.css` |
| Mode/theme toggle логика | `frontend/ui-kit/js/05_interactions.js` |
| Контент главной | `config/main_layout.json` |
| Структура хедера/футера | `header.php` / `footer.php` |

## Страницы НЕ переведённые (следующий этап)
- `networking.php`
- `speech.php`
- `404.php` / `404.html`
- Страницы подсайтов (waf, toc, fun)
