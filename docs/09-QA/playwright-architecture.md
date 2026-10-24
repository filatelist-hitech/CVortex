# CVortex Playwright Test Architecture

## 1. Overview

Playwright UI/E2E автоматизация в CVortex спроектирована в соответствии с принципами Truth-first, multi-user isolation и deterministic execution.

Тестовый фреймворк размещён непосредственно внутри фронтенд-приложения (`apps/frontend/e2e/`), что гарантирует бесшовную работу с локальными TypeScript-типами, моделями данных и npm-скриптами проекта.

## 2. Directory Layout

```text
apps/frontend/
├── playwright.config.ts           # Базовая конфигурация Playwright
├── e2e/
│   ├── fixtures/
│   │   └── test-fixtures.ts       # Расширение Playwright test, AuthHelper, AxeBuilder
│   ├── auth/
│   │   ├── login.spec.ts          # Вход, валидация полей, сессии, логаут
│   │   ├── register.spec.ts       # Invite-only регистрация, проверка токенов
│   │   └── access-control.spec.ts # Ролевая модель, защита /diagnostics, 401/403
│   ├── career/
│   │   └── career-facts.spec.ts   # Карьерные факты, добавление, ревью, Truth Guard
│   ├── vacancies/
│   │   └── vacancies.spec.ts      # Вставка вакансий, анализ, рекомендации, драфты
│   ├── security/
│   │   └── cross-user-isolation.spec.ts # Межпользовательская изоляция, защита от prompt injection
│   ├── diagnostics/
│   │   └── diagnostics.spec.ts    # Журнал инцидентов администратора, смены статусов
│   ├── responsive/
│   │   └── responsive-navigation.spec.ts # Мобильные вьюпорты (390px, 320px), навигация
│   └── accessibility/
│       └── a11y-audit.spec.ts     # WCAG 2.1 AA сканирование (axe-core), ловушки фокуса
```

## 3. Configuration & Browser Matrix

Конфигурация `apps/frontend/playwright.config.ts`:
- **Base URL**: `process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8080'`.
- **Default Browsers**:
  - `chromium`: Desktop Chromium (1440×900).
  - `mobile-chrome`: Мобильная эмуляция (Pixel 7).
- **Opt-in Browsers** (`PLAYWRIGHT_ALL_BROWSERS=1`):
  - `firefox`: Desktop Firefox.
  - `webkit`: Desktop Safari.
- **Reporting**:
  - `html`: генерация отчёта в `playwright-report/`.
  - `list`: консольный вывод при прогоне.
  - `trace`: сбор трейса `on-first-retry` для точечной диагностики.
  - `screenshot`: сохранение скриншота `only-on-failure`.

## 4. Test Layers & Tagging

Тесты аннотированы тегами для гибкого выборочного запуска:
- `@smoke`: базовые быстрые проверки доступности основных страниц и форм.
- `@critical`: критические сценарии (P0) мутации данных, Truth Guard и авторизации.
- `@security`: проверки межпользовательской изоляции и защиты маршрутов.
- `@a11y`: проверки доступности (axe-core WCAG 2.1 AA и управление фокусом).
- `@responsive`: проверки мобильных вьюпортов и отсутствия горизонтального переполнения.
- `@failure`: проверка устойчивости к сбоям бэкенда и провайдеров анализа.

## 5. Locators & Invariants

- Приоритет отдаётся доступным семантическим локаторам: `getByRole`, `getByLabel`, `getByText`.
- Отсутствуют хрупкие абсолютные XPath и несемантические CSS-селекторы.
- Никаких неконтролируемых `waitForTimeout()`: синхронизация построена на веб-персистентных ожиданиях Playwright (`expect(...).toBeVisible()`).
