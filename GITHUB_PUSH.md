# GitHub Push — Инструкция

## Первый пуш (если репо ещё не создано локально)

```bash
cd /Users/chernetchenko/Code/SITE_F/public_html_01

# Инициализация
git init
git branch -M main

# Добавить удалённое репо
git remote add origin https://github.com/chernetchenko-dev/go-cms.git

# Проверить что .gitignore работает — эти файлы НЕ должны быть в списке:
git status
# config.php, .env, admin/config.php, content/*.md (кроме example.md),
# events.json, vendor/, digest/logs/, digest/cache/ — всё в .gitignore

# Добавить всё и закоммитить
git add .
git commit -m "feat: go-cms v1.0 — OVC DS, AI-дайджест, мультисайт"

# Пуш
git push -u origin main
```

## Если репо уже существует (обновление)

```bash
cd /Users/chernetchenko/Code/SITE_F/public_html_01

git add .
git commit -m "feat: dashboard v2, theme/mode toggle, digest sources, events API"
git push
```

## Что попадёт в репо (публично)

✅ Весь PHP-код (index, article, header, footer, admin, lib, digest)  
✅ OVC Design System (frontend/ui-kit/)  
✅ terminal.css, admin/index.php  
✅ config.example.php, admin/config.example.php  
✅ content/example.md  
✅ README.md, ARCHITECTURE.md, AI_MIGRATION_GUIDE.md, MIGRATION.md  
✅ .htaccess, robots.txt  

❌ config.php (секреты)  
❌ .env (секреты)  
❌ admin/config.php (секреты)  
❌ content/*.md (личные статьи, кроме example.md)  
❌ events.json (личные данные)  
❌ vendor/ (Composer, ставится локально)  
❌ digest/logs/, digest/cache/ (рабочие файлы)  
❌ google*.html, yandex_*.html (верификация)  

## Проверка перед пушем

```bash
# Убедиться что секреты не утекут
git diff --cached --name-only | grep -E "config\.php|\.env|content/"

# Посмотреть что будет закоммичено
git status
```
