# CVortex Playwright CI Integration

## 1. Overview

Playwright E2E автоматизация интегрирована в базовый GitHub Actions workflow: `.github/workflows/quality.yml`.

## 2. Pipeline Architecture

В пайплайне `Quality` добавлен выделенный job `e2e-tests`:
- Выполняется на `ubuntu-latest`.
- Подготавливает локальный стек: PostgreSQL, Redis, Backend, Frontend.
- Устанавливает закэшированные браузерные зависимости Playwright:
  ```bash
  npx playwright install --with-deps chromium
  ```
- Выполняет smoke и critical наборы:
  ```bash
  npm run test:e2e:critical
  ```
- В случае сбоя сохраняет диагностические артефакты через `actions/upload-artifact@v4`:
  - `playwright-report/` (HTML-отчёт с видео и скриншотами)
  - traces для анализа упавших шагов

## 3. Resource & Execution Boundaries

- Запускается только Chromium (Desktop + Mobile Chrome эмуляция), чтобы сохранить скорость сборки в пределах 5-10 минут.
- Не запускает дорогостоящие внешние API или платные ИИ-модели.
- Применяет `--forbid-only`, исключая случайный коммит `test.only`.
