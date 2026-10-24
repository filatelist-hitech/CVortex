# CVortex Playwright Local Setup & Execution Guide

## 1. Prerequisites

- Node.js 22+ (в проекте v26)
- Docker & Docker Compose
- Запущенный стек CVortex или отдельный тестовый контур

## 2. Quick Start Commands

Все команды выполняются из директории `apps/frontend` (или через Makefile):

```bash
# Установка зависимостей и браузеров
cd apps/frontend
npm install
npx playwright install chromium

# Запуск всех E2E тестов
npm run test:e2e

# Запуск быстрых дымовых тестов (smoke)
npm run test:e2e:smoke

# Запуск критических сценариев (critical P0)
npm run test:e2e:critical

# Интерактивный UI-режим Playwright
npm run test:e2e:ui

# Просмотр последнего HTML-отчёта
npm run test:e2e:report
```

## 3. Environment Variables

- `PLAYWRIGHT_BASE_URL`: URL тестируемого приложения (по умолчанию `http://127.0.0.1:8080`).
- `PLAYWRIGHT_ALL_BROWSERS`: `1` для включения полного набора (Firefox, WebKit). По умолчанию запускаются только Chromium Desktop и Mobile Chrome.
- `CI`: при установке в `1` включает 1 повтор на упавший тест и запрещает `test.only`.
