# CVortex — Playwright UI / E2E Coverage Matrix

## Overview

Матрица покрытия пользовательского интерфейса CVortex автоматизированными тестами на Playwright.
Статусы соответствуют фактической реализации:
- `Covered`: функциональность реализована и покрыта автоматизированным тестом;
- `Partial`: частично покрыто тестами;
- `Uncovered`: функциональность реализована, но пока не покрыта;
- `Documented / Not Implemented`: описано в документации, но в UI/продукте отсутствует;
- `Blocked`: заблокировано технической зависимостью;
- `N/A`: не применимо к текущей версии.

---

## 1. Authentication & Session Management

| Feature | Route | Scenario | Priority | Type | Status | Test file | Notes |
|---|---|---|---|---|---|---|---|
| Login Form | `/` | Отображение формы входа, полей email и password | P0 | Positive | Covered | `e2e/auth/login.spec.ts` | Проверка видимости, лейблов, доступных ролей |
| Login Success | `/` | Успешный вход с валидными credentials | P0 | Positive | Covered | `e2e/auth/login.spec.ts` | Проверка сессии Sanctum, редирект в рабочее пространство |
| Login Invalid Password | `/` | Ошибка аутентификации при неверном пароле | P0 | Negative | Covered | `e2e/auth/login.spec.ts` | Отображение ошибки [VALIDATION_FAILED] / [AUTH_FAILED], форма не сбрасывает email |
| Login Nonexistent User | `/` | Попытка входа с несуществующим email | P0 | Negative | Covered | `e2e/auth/login.spec.ts` | Контролируемый отказ без утечки информации |
| Login Validation | `/` | Валидация пустых полей и некорректного email | P1 | Boundary | Covered | `e2e/auth/login.spec.ts` | HTML5 validation + серверные проверки |
| Logout | `/` | Завершение сессии через диалог профиля | P0 | Positive | Covered | `e2e/auth/login.spec.ts` | Инвалидация сессии, возврат к экрану входа |
| Session Persistence | `/` | Сохранение авторизованного состояния при перезагрузке страницы | P0 | Positive | Covered | `e2e/auth/login.spec.ts` | Проверка /api/v1/me |
| Invite-only Register Tab | `/` | Вкладка регистрации без токена блокирует создание аккаунта | P0 | Security | Covered | `e2e/auth/register.spec.ts` | Предупреждение о необходимости ссылки от оператора, кнопка disabled |
| Registration with Token | `/register#token=...` | Успешная регистрация по валидному инвайт-токену | P0 | Positive | Covered | `e2e/auth/register.spec.ts` | Создание пользователя, автоматический вход |
| Registration Invalid Token | `/register#token=invalid` | Попытка регистрации с невалидным/истёкшим токеном | P0 | Negative | Covered | `e2e/auth/register.spec.ts` | Отказ сервера, запрет создания аккаунта |
| Registration Password Mismatch | `/register#token=...` | Несовпадение пароля и подтверждения | P1 | Negative | Covered | `e2e/auth/register.spec.ts` | Ошибка валидации |

---

## 2. Access Control & Roles

| Feature | Route | Scenario | Priority | Type | Status | Test file | Notes |
|---|---|---|---|---|---|---|---|
| Diagnostics Admin Only | `/diagnostics` | Обычный пользователь (role: user) получает экран «Доступ запрещён» | P0 | Security | Covered | `e2e/auth/access-control.spec.ts` | Сообщение о доступе только для администраторов |
| Diagnostics Admin Access | `/diagnostics` | Администратор (role: admin) видит журнал инцидентов | P0 | Security | Covered | `e2e/auth/access-control.spec.ts` | Доступ к журналу диагностики и фильтрам |
| Diagnostics Menu Visibility | `/` | Ссылка на диагностику в меню профиля видна только администратору | P1 | Security | Covered | `e2e/auth/access-control.spec.ts` | Скрыта для role: user |
| Unauthenticated Guard | `/career`, `/diagnostics` | Неавторизованный пользователь видит форму входа вместо приватных данных | P0 | Security | Covered | `e2e/auth/access-control.spec.ts` | Никаких утечек данных пользователя |

---

## 3. Career Workspace (`/career`)

| Feature | Route | Scenario | Priority | Type | Status | Test file | Notes |
|---|---|---|---|---|---|---|---|
| Facts List View | `/career` | Отображение подтверждённых и неподтверждённых фактов | P0 | Positive | Covered | `e2e/career/career-facts.spec.ts` | Группировка, бейджи статусов |
| Manual Fact Add | `/career` | Ручное добавление подтверждённого факта (skill, statement) | P0 | Positive | Covered | `e2e/career/career-facts.spec.ts` | POST /api/v1/career/facts/manual, обновление списка |
| Manual Fact Validation | `/career` | Добавление факта с пустыми обязательными полями | P1 | Negative | Covered | `e2e/career/career-facts.spec.ts` | Валидация полей |
| Fact Review Actions | `/career` | Подтверждение (CONFIRMED) и отклонение (REJECTED) факта | P0 | Positive | Covered | `e2e/career/career-facts.spec.ts` | Изменение статуса через UI |
| Claim Resolution (QA-004) | `/career` | Разрешение конфликта утверждения выбором подтверждённого факта | P0 | Positive | Covered | `e2e/career/career-facts.spec.ts` | PATCH /api/v1/career/claims/{id}/resolve |
| Resume Text Extraction | `/career` | Извлечение фактов из текста резюме через провайдера анализа | P0 | Positive | Covered | `e2e/career/career-facts.spec.ts` | Обработка через детерминированный fakeprovider |
| Extraction Provider Error | `/career` | Обработка отказа ИИ-провайдера при извлечении | P1 | Failure | Covered | `e2e/career/career-facts.spec.ts` | Понятное сообщение пользователю, предложение ручного ввода |

---

## 4. Vacancy & Application Workspace (`/`)

| Feature | Route | Scenario | Priority | Type | Status | Test file | Notes |
|---|---|---|---|---|---|---|---|
| Vacancy List | `/` | Просмотр списка сохранённых вакансий | P0 | Positive | Covered | `e2e/vacancies/vacancies.spec.ts` | Карточки вакансий, выбор активной вакансии |
| Empty Vacancies State | `/` | Отображение пустого состояния при отсутствии вакансий | P1 | Positive | Covered | `e2e/vacancies/vacancies.spec.ts` | Призыв добавить первую вакансию |
| Paste Vacancy | `/` | Сохранение и анализ вакансии через вставку текста | P0 | Positive | Covered | `e2e/vacancies/vacancies.spec.ts` | POST /api/v1/vacancies, парсинг требований и компании |
| Paste Vacancy Validation | `/` | Попытка отправки пустого текста вакансии | P1 | Boundary | Covered | `e2e/vacancies/vacancies.spec.ts` | Валидация формы |
| Workspace Tabs | `/` | Переключение табов: Обзор, Требования, Материалы, Работодатель, История | P1 | Positive | Covered | `e2e/vacancies/vacancies.spec.ts` | Навигация по секциям рабочей области |
| Recommendations Review | `/` | Просмотр рекомендаций (Before, After, Reason, Risk) | P0 | Positive | Covered | `e2e/vacancies/vacancies.spec.ts` | Traceability и разделение предложений |
| Recommendation Accept | `/` | Принятие предложенной правки сопроводительного текста | P0 | Positive | Covered | `e2e/vacancies/vacancies.spec.ts` | Изменение состояния драфта, раздельное одобрение |
| Recommendation Edit | `/` | Ручное редактирование текста перед одобрением | P0 | Positive | Covered | `e2e/vacancies/vacancies.spec.ts` | Редактирование, сохранение драфта |
| Truth Guard Invariant | `/` | Неподтверждённые факты блокируют авто-одобрение | P0 | Security | Covered | `e2e/vacancies/vacancies.spec.ts` | Кнопка одобрения недоступна без подтверждённых фактов |
| Truth Guard Evidence Inspector | `/` | Открытие панели подтверждений и связей с фактами | P1 | Positive | Covered | `e2e/vacancies/vacancies.spec.ts` | Инспектор доказательств и карьерных фактов |

---

## 5. Security & Multi-User Isolation

| Feature | Route | Scenario | Priority | Type | Status | Test file | Notes |
|---|---|---|---|---|---|---|---|
| Cross-User Facts Isolation | `/career` | Пользователь B не видит карьерные факты пользователя A | P0 | Security | Covered | `e2e/security/cross-user-isolation.spec.ts` | Мульти-пользовательская изоляция базы фактов |
| Cross-User Vacancy Isolation | `/` | Пользователь B не имеет доступа к вакансиям пользователя A | P0 | Security | Covered | `e2e/security/cross-user-isolation.spec.ts` | Изоляция вакансий и откликов |
| Cross-User Claim Mutation Denied | API / UI | Попытка изменить чужой Claim возвращает 404/403 | P0 | Security | Covered | `e2e/security/cross-user-isolation.spec.ts` | RLS / авторизация на бэкенде |
| Prompt Injection Resistance | `/` | Вставка вакансии с попыткой prompt injection ("Ignore previous...") | P0 | Security | Covered | `e2e/security/cross-user-isolation.spec.ts` | Текст остаётся недоверенным вводом, нет утечки системных промптов |
| CSRF Protection | API / UI | Запросы на мутацию без валидного XSRF-TOKEN отклоняются (419) | P0 | Security | Covered | `e2e/security/cross-user-isolation.spec.ts` | Защита сессии Sanctum |

---

## 6. Diagnostics (`/diagnostics`)

| Feature | Route | Scenario | Priority | Type | Status | Test file | Notes |
|---|---|---|---|---|---|---|---|
| Incidents List | `/diagnostics` | Просмотр списка зарегистрированных инцидентов администратором | P1 | Positive | Covered | `e2e/diagnostics/diagnostics.spec.ts` | Отображение кодов ошибок, дат, статусов |
| Incident Detail | `/diagnostics` | Просмотр подробностей инцидента, стека и контекста | P1 | Positive | Covered | `e2e/diagnostics/diagnostics.spec.ts` | Детальный просмотр без утечки секретов |
| Incident Status Update | `/diagnostics` | Смена статуса инцидента (open / resolved / ignored) | P1 | Positive | Covered | `e2e/diagnostics/diagnostics.spec.ts` | PATCH /api/v1/diagnostics/incidents/{id} |
| Manual Incident Report | `/diagnostics` | Ручная отправка диагностического отчёта | P2 | Positive | Covered | `e2e/diagnostics/diagnostics.spec.ts` | POST /api/v1/diagnostics/report |

---

## 7. Responsive Navigation

| Feature | Viewport | Scenario | Priority | Type | Status | Test file | Notes |
|---|---|---|---|---|---|---|---|
| Mobile Menu Drawer | Mobile (390px) | Открытие и закрытие мобильного меню через summary/details | P1 | Responsive | Covered | `e2e/responsive/responsive-navigation.spec.ts` | Закрытие по клику, по кнопке и по Escape |
| Mobile Level Transitions | Mobile (390px) | Навигация по уровням list -> work -> context и кнопка «Назад» | P1 | Responsive | Covered | `e2e/responsive/responsive-navigation.spec.ts` | Отсутствие горизонтального скролла на 390px и 320px |
| Tablet Vacancy Drawer | Tablet (768px) | Выбор вакансии через модальный список на планшете | P2 | Responsive | Covered | `e2e/responsive/responsive-navigation.spec.ts` | Управление фокусом и модальным состоянием |

---

## 8. Accessibility (A11y)

| Feature | Route | Scenario | Priority | Type | Status | Test file | Notes |
|---|---|---|---|---|---|---|---|
| WCAG 2.1 AA Audit (Auth) | `/` | Автоматическое сканирование страницы входа axe-core | P1 | Accessibility | Covered | `e2e/accessibility/a11y-audit.spec.ts` | 0 критических нарушений доступности |
| WCAG 2.1 AA Audit (Career) | `/career` | Сканирование рабочей области карьеры axe-core | P1 | Accessibility | Covered | `e2e/accessibility/a11y-audit.spec.ts` | Проверка контрастности, ролей, лейблов |
| Keyboard Dialog Traps | `/` | Фокус захватывается внутри модального окна профиля (Tab / Shift+Tab) | P0 | Accessibility | Covered | `e2e/accessibility/a11y-audit.spec.ts` | Корректный возврат фокуса на триггер при закрытии |
| Escape Key Dismissal | `/` | Закрытие диалогов и мобильного меню клавишей Escape | P1 | Accessibility | Covered | `e2e/accessibility/a11y-audit.spec.ts` | Стандартное поведение доступных модалок |
| Visible Focus Indicators | All | Все интерактивные элементы имеют видимый фокус при Tab-навигации | P1 | Accessibility | Covered | `e2e/accessibility/a11y-audit.spec.ts` | Фокусные контуры в соответствии с дизайн-токенами |

---

## 9. Inventory of Gaps & Unimplemented Features

| Feature | Route / Area | Reason / Current Status | Planned Milestone |
|---|---|---|---|
| File Import for Vacancies (DOCX/PDF) | `/` | `Documented / Not Implemented` — в UI доступна только вставка текста | M2 |
| URL Vacancy Scraping | `/` | `Documented / Not Implemented` — веб-скрейпинг вакансий отсутствует в UI | M2 |
| Dedicated Career Tracks UI | `/career/tracks` | `Documented / Not Implemented` — модель Tracks существует только концептуально | M2 |
| Dedicated Employer Management CRUD | `/employers` | `Partially Implemented` — вкладка в Vacancy Workspace есть, отдельного раздела нет («Недоступно») | M2 |
| Interview Preparation Interface | `/interviews` | `Documented / Not Implemented` — отсутствует в кодовой базе фронтенда | M3 |
| Settings & BYOK API Keys UI | `/settings` | `Documented / Not Implemented` — пункт меню показывает «Экран настроек пока недоступен» | M2 |
| User Profile Self-Editing | Profile dialog | `Documented / Not Implemented` — кнопка показывает «Редактирование профиля пока недоступно» | M2 |
| DOCX/PDF Generated Resume Download | `/` | `Documented / Not Implemented` — бэкенд LibreOffice готов, кнопки скачивания в UI нет | M2 |
