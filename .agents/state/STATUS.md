# CVortex — проверенное текущее состояние

## Vacancy Extraction Prompt 2.0.0 and Structured Output Validator (PR #69) — 2026-11-01

Mode: IMPLEMENT → REVIEW; branch: `feature/m1.3-vacancy-extraction-prompt-2.0`; target: `stage`.

### Completed Outcomes
1. **Канонический Skill v2.0.0 (`runtime-ai/skills/vacancy-requirement-extraction/v2/`)**:
   - Внедрен промпт 2.0.0 (`system.prompt.md`) с разделением категорий (требования соискателя vs стек работодателя vs обязанности), детерминированной защитой от prompt injection (`security_flags: PROMPT_INJECTION_ATTEMPT`) и блоком самоконтроля (`quality_control`).
   - Разработана строгая JSON Schema Draft 2020-12 (`output.schema.json`), совместимая с OpenAI Structured Outputs (`strict: true`, без `minLength`/`maxLength`) и Gemini (`responseSchema`).
   - Зафиксированы синтетические эвалы `evals/adversarial.json` и манифест `skill.json` (версии 2.0.0 / 2.0.0).
2. **Интеграция в Runtime AI и Backend (`RuntimeSkillRegistry.php`, `config/ai.php`)**:
   - Реестр навыков по умолчанию загружает версию v2, сохраняя явные методы `vacancyRequirementExtractionV1()` и `vacancyRequirementExtractionV2()`.
   - В `config/ai.php` обновлена версия вакансионного экстрактора на `2.0.0` с возможностью переопределения через `AI_VACANCY_EXTRACTION_VERSION`.
3. **Полиморфная валидация в `VacancyRequirementValidator.php` и устранение дефектов ревью**:
   - Реализована строгая валидация `validateV200()`: обязательная проверка всех корневых полей схемы Draft 2020-12 (`quality_control`, `vacancy`, `security_flags` и др.), строгая типизация коллекций и валидация `extraction_status` (`SUCCESS`/`FAILED`).
   - Устранены скрытые fallback-значения и молчаливые допущения: невалидные enum в `work_format` (`value`, `confidence`) и `compensation` (`confidence`) отклоняются со статусом `SCHEMA_INVALID`.
   - Внедрена защита от галлюцинаций в синтезируемых требованиях: несовпадающие цитаты в `compensation`, `location` и `security_flags` отклоняются как галлюцинации (`SCHEMA_INVALID`).
   - Синтезированные измерения (`LOCATION`, `SALARY`, `WORK_FORMAT`) изолированы от проверки дословного присутствия системных лейблов, нормализация городов переведена на `mb_strtolower()`, а форматы работы поддерживают русские словоформы и стеммы.
   - Внедрена проверка числовых доказательств в зарплатной цитате: цитаты без цифр при `salary_present: true` бракуются как `SEMANTIC_REJECTED`.
   - Сохранена 100% обратная совместимость со схемой v1.0.0 (`validateV100()`).
   - Добавлена мультиязычная поддержка языковых уровней («на уровне B2», «B2-level») и маркеров важности во множественном числе («будут плюсом»).
4. **Реестр навыков и конфигурация (`RuntimeSkillRegistry.php`, `config/ai.php`)**:
   - Добавлена детерминированная нормализация версий навыков с выбросом `InvalidArgumentException` при неподдерживаемых версиях.
   - Синхронизированы версии `career_extraction` в `config/ai.php` с манифестом навыка 1.1.0 (`career-fact-extraction/v1/skill.json`).
5. **Синхронизация ветки PR #69 с актуальным `stage`**:
   - Ветка `feature/m1.3-vacancy-extraction-prompt-2.0` синхронизирована с `origin/stage` (разрешен конфликт поправок в ADR-0026).
   - Устранено дублирование 19 файлов из PR #66 в диффе PR #69 на GitHub; итоговый diff изолирован до канонического скоупа Vacancy Extraction 2.0.0.
6. **Верификация**:
   - `VacancyExtractionPrompt200Test`: 17/17 PASS (70 assertions, включая unit, negative, adversarial, multilingual и edge cases).
   - `VacancyCoreTest`: 118/118 PASS (699 assertions, 1 skipped).
   - `CareerFactExtractionPrompt110Test`: 19/19 PASS (79 assertions).
   - `CareerCoreRemediationTest`: 18/18 PASS (165 assertions).
   - `GeminiResponsesProviderTest`: 15/15 PASS (78 assertions).
   - `ApplicationDraftTest`: 24/24 PASS (285 assertions).
   - `Frontend Vitest`: 17 test suites, 183/183 PASS.
   - `PHPStan`: `[OK] No errors` across 155 files.
   - `Laravel Pint`: `[PASS]` across all files.
   - PR Contract: `bash scripts/check-pr-contract.sh 69` — valid (`feature/m1.3-vacancy-extraction-prompt-2.0 → stage`).
   - CI Pipeline: все 4 проверки GitHub Actions (Governance + Quality/m0 + Playwright E2E) успешны.

---

## Discovery & Posting Guard Security Hardening and Code Review Remediation — 2026-10-31

Mode: IMPLEMENT → REVIEW; branch: `fix/discovery-code-review-remediation`; target: `stage`. Independent reviewer subagent: APPROVED.

### Completed Remediation Outcomes
1. **[HIGH-01] Изоляция SQLite `:memory:` тестового окружения backend (`apps/backend/phpunit.xml`)**:
   - Внедрены теги `<server ... force="true"/>` для переменных `DB_CONNECTION`, `DB_DATABASE`, `DB_URL`, `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER`, `MAIL_MAILER`.
   - Тесты полностью изолированы от внешних env-переменных Docker/хоста (`DB_CONNECTION=pgsql`). Все 8 тестов `DiscoveryApiTest` и 2 теста `VacancyIngestionPostingGuardTest` проходят стабильно без сбоев RLS `RefreshDatabase` / DROP TABLE privilege.
   - Полный набор Backend тестов: 480 passed, 13 skipped (Postgres-only live boundary), 3779 assertions.

2. **[HIGH-02] Архитектурное выравнивание ADR-0028 (`docs/03-ADR/ADR-0028-ai-job-search-integration-and-worker.md`)**:
   - Оформлен блок `## Amendment — 2026-10-31` и дополнен Section 1, зафиксировавший dual-mode транспорт:
     - Синхронный HTTP POST (`/scrapers/search`) на внутренней Docker сети с `throttle:3,1` и 30с таймаутом для мгновенного отклика UI `/vacancies/discover` (Фаза 2).
     - Асинхронные очереди Laravel Horizon & Redis для пакетных фоновых задач и LaTeX генерации (Фаза 3+).
   - Задокументирован Egress SSRF Security Baseline.

3. **[HIGH-03 / MEDIUM-03] Санитизация `title`, `company`, `location`, `salary_text` и разделение `cleanDescription` (`JobDiscoveryService.php`)**:
   - Входные поля вакансий очищаются через `PostingGuardService->inspect()` от невидимых символов (`\u{200B}`, `\u{200C}`), Bidi-тегов и скрытых HTML-блоков перед записью в `discovered_vacancies`.
   - Поле `description` инспектируется изолированно без склеивания с префиксом заголовка, устраняя дублирование текста в базе данных.
   - Добавлены строгие ассерты санитизации и отсутствия префикса в `DiscoveryApiTest`.

4. **[MEDIUM-01] SSRF Guard в воркере (`apps/worker/scrapers/url-guard.ts`)**:
   - Разработан модуль защиты от SSRF: блокируются адреса облачных метаданных (`169.254.169.254`), RFC 1918 приватные подсети, loopback (`127.0.0.0/8`, `::1`), Docker hostnames (`postgres`, `redis`, `backend` и др.) и нестандартные порты.
   - Усилена защита против векторов второго порядка: добавлено декодирование IPv6-mapped IPv4 (`[::ffff:...]`, `[64:ff9b:...]`), блокировка unspecified адреса (`[::]`), multicast/ULA, а также обнаружение wildcard DNS rebinding доменов (`nip.io`, `sslip.io` и др.) и внедренных приватных IP-адресов.
   - Интегрирован в `freehire.ts`, `linkedin.ts`, `server.ts` и `runner.ts`. Небезопасные ссылки замещаются безопасным каноническим URL портала.
   - Написаны тесты `url-guard.test.ts` (9/9 PASS).

5. **[MEDIUM-02] Выделенный Rate Limiting на `POST /api/v1/discovery/search` (`apps/backend/routes/api.php`)**:
   - Навешан middleware `throttle:3,1` (не более 3 запросов поиска в минуту на пользователя), предотвращающий истощение пула PHP-FPM воркеров и блокировку IP внешними порталами.
   - Добавлен тест `test_search_endpoint_is_throttled_to_three_requests_per_minute` (HTTP 429 на 4-й запрос).

6. **[MEDIUM-04] Отмена устаревших запросов через AbortController в `TodayWorkspace` (`today-workspace.tsx`)**:
   - Добавлены `activeAbortControllerRef` и `actionAbortControllerRef` для отмены висячих сетевых вызовов при быстрой смене вкладок или размонтировании компонента. `AbortError` штатно обрабатывается.

7. **[LOW-02] Удаление мертвого кода в `JobDiscoveryService.php`**:
   - Удален неиспользуемый arm `'MALICIOUS'` из `match ($guardResult->threatLevel)`.

8. **[LOW-03] Пиннинг версии Bun в `apps/worker/Dockerfile`**:
   - Зафиксирован `ARG BUN_VERSION=1.4.4` и установка `bash -s -- bun-v${BUN_VERSION}`.

9. **Верификация**:
   - Backend PHPUnit: 480/480 тестов PASS (13 skipped Postgres live suite, 3779 assertions).
   - Backend Pint: 0 style issues across 248 files (`[PASS]`).
   - Backend PHPStan: 0 errors across 155 files (`[OK] No errors`).
   - Frontend Vitest Suite: 183/183 тестов PASS (17 файлов, 0 errors).
   - Frontend TypeScript & ESLint: 0 errors / 0 warnings.
   - Worker SSRF Tests: 6/6 tests PASS (33 assertions).
   - Python unit tests & QA Evidence: 20/20 PASS.
   - Независимый аудит (Subagent Reviewer): **APPROVED**.

---

## AI Job Search Scrapers & Discover Inbox: Code Review Remediation (PR #63) — 2026-10-26

Mode: IMPLEMENT → REVIEW; branch: `feature/ai-job-search-scrapers-and-discover-inbox`; target: `stage`. One agent / sequential execution.

### Completed Remediation Outcomes
1. **[CRITICAL] Исправление маппинга угроз PostingGuard и санитизация (`JobDiscoveryService.php`)**:
   - Внедрено строгое сопоставление констант `PostingGuardResult::THREAT_HIGH_RISK` и `'MALICIOUS'` с `DiscoveredVacancy::SECURITY_MALICIOUS`.
   - Санитизированный текст (`$guardResult->cleanText`) сохраняется в `discovered_vacancies` и передается в `FastFitScorer`, исключая утечки raw HTML-инъекций и невидимых символов в аналитический контур.

2. **[CRITICAL] Запуск Bun HTTP-сервера воркера (`compose.yaml` & `Dockerfile`)**:
   - В `compose.yaml` добавлена директива `command: ["bun", "run", "server.ts"]` для сервиса `worker`.
   - В `apps/worker/Dockerfile` обновлен дефолтный `CMD ["bun", "run", "server.ts"]`. Микросервис поиска штатно поднимается при `docker compose up` и проходит healthcheck `http://127.0.0.1:3001/health`.

3. **[HIGH] Мемоизация и устранение N+1 запросов в FastFitScorer**:
   - В `FastFitScorer.php` добавлены локальные кэши `$candidateFactsCache` и `$candidateKeywordsCache` по ключу `(string)$user->id`.
   - Запросы к БД через `TrustedCareerQuery` и нормализация словаря выполняются ровно 1 раз на пользователя при скоринге пакета вакансий.

4. **[MEDIUM] Границы слов и спецсимволы в FastFitScorer (`C#`, `C++`, `.NET`, `QA`)**:
   - Заменены границы `(?:\b|_|-)` на пунктуационно-независимые lookarounds: `(?<=^|[\s,.;:!?\(\)\[\]\/\-\_])` и `(?=$|[\s,.;:!?\(\)\[\]\/\-\_])`.
   - Длина нормализуемых токенов расширена с `{3,}` до `{2,}`. В словари и роли добавлены `C#`, `C++`, `.NET`, `QA`.

5. **[MEDIUM/LOW] Защита от состояний гонки и корректная навигация в DiscoverInbox**:
   - В `apps/frontend/src/app/discover-inbox.tsx` добавлен `AbortController` (через `activeAbortControllerRef`) для отмены устаревших сетевых запросов при переключении табов.
   - Устранен дублирующий вызов `loadList` при сабмите поиска.
   - Ссылка на принятую вакансию теперь передает целевой ID: `href={`/?selected=${acceptedCoreId}`}`.

6. **Верификация**:
   - Backend PHPUnit: 24/24 тестов PASS (139 assertions) в `DiscoveryApiTest`, `FastFitScorerTest`, `PostingGuardServiceTest`, `VacancyIngestionPostingGuardTest`.
   - Backend Pint: 0 style issues across 248 files (`[PASS]`).
   - Backend PHPStan: 0 errors across 155 files (`[OK] No errors`).
   - Frontend Vitest Suite: 5/5 тестов PASS в `discover-inbox.test.tsx`, 172/172 тестов PASS по всему фронтенду.
   - Frontend Linters: ESLint (0 errors/warnings), TypeScript (0 errors).
   - PR Contract: `bash scripts/check-pr-contract.sh 63` — valid.

---

## AI Job Search Integration: Фаза 2 — Портальные скраперы, Fast Fit Ranking и Discover Inbox — 2026-10-26

Mode: IMPLEMENT; branch: `feature/ai-job-search-scrapers-and-discover-inbox`; target: `stage`. One agent / sequential execution.

### Completed Outcomes
1. **Портальные скраперы и HTTP сервис воркера (`apps/worker`)**:
   - Реализованы асинхронные модули поиска по порталам в `apps/worker/scrapers/`:
     - `freehire.ts` — клиент API FreeHire (поиск по 50+ ATS платформам: Greenhouse, Lever, Ashby, Workable и др.).
     - `habr.ts` — клиент API Habr Career с полной структурой стека, локаций, удалёнки и зарплатных вилок.
     - `hh.ts` — устойчивый HTML-парсер поисковой выдачи HeadHunter.
     - `linkedin.ts` — гостевой парсер карточек вакансий LinkedIn.
     - `runner.ts` — CLI-раннер параллельного поиска с дедупликацией по URL.
   - Поднят внутренний микросервис `apps/worker/server.ts` на базе встроенного `Bun.serve` (порт 3001) в сети `internal`:
     - `GET /health` — проверка работоспособности контейнера воркера.
     - `POST /scrapers/search` — параллельный опрос порталов, нормализация и дедупликация вакансий.
   - Сервис `worker` в `compose.yaml` дополнен healthcheck (`curl -f http://127.0.0.1:3001/health`).

2. **Модель обнаруженных вакансий и RLS изоляция (`discovered_vacancies`)**:
   - Создана миграция `2026_10_26_000001_create_discovered_vacancies_table.php` со строгой изоляцией по `owner_id` (PostgreSQL FORCE Row Level Security) и правами роли `cvortex_app`.
   - Создана Eloquent-модель `App\Models\DiscoveredVacancy` со статусами триажа (`INBOX`, `ACCEPTED`, `DISMISSED`), уровнями соответствия (`HIGH`, `MEDIUM`, `LOW`, `UNKNOWN`) и уровнями безопасности (`CLEAN`, `SUSPICIOUS`, `MALICIOUS`).

3. **Быстрый алгоритм соответствия (Fast Fit Scorer)**:
   - Создан `App\Services\Discovery\FastFitScorer`:
     - Извлекает подтверждённые карьерные факты кандидата через `TrustedCareerQuery`.
     - Сопоставляет ключевые роли и стек технологий с заголовком и текстом вакансии.
     - Вычисляет скор от 5 до 98%, уровень соответствия, формирует списки `matched_skills`, `missing_skills` и текстовое резюме соответствия.
     - Отрабатывает детерминированно за < 5 мс без внешних LLM-вызовов.

4. **Сервис обнаружения и триажа (`JobDiscoveryService` & `DiscoveryController`)**:
   - `JobDiscoveryService`:
     - Опрашивает воркер, очищает входящий контент через `PostingGuardService`, вычисляет скор соответствия и сохраняет во входящие со статусом `INBOX`.
     - Метод `accept`: переводит вакансию в `ACCEPTED` и мгновенно регистрирует в основном пайплайне анализа через `VacancyIngestionService::queue()`.
     - Метод `dismiss`: переводит вакансию в `DISMISSED`.
     - Метод `list`: пагинированная выборка с фильтрацией по статусу и порталу.
   - Зарегистрированы маршруты API в `routes/api.php` под защитой middleware `db-owner-context`:
     - `GET /api/v1/discovery`
     - `POST /api/v1/discovery/search`
     - `POST /api/v1/discovery/{id}/accept`
     - `POST /api/v1/discovery/{id}/dismiss`

5. **Фронтенд Discover/Inbox UI (`/vacancies/discover`)**:
   - Разработан компонент `apps/frontend/src/app/discover-inbox.tsx` и страница `apps/frontend/src/app/vacancies/discover/page.tsx`:
     - Панель поиска с ключевыми словами, локацией, чекбоксами порталов и переключателем "Только Remote".
     - Вкладки статусов триажа: `📥 Входящие (Inbox)`, `✓ В работе (Accepted)`, `✕ Пропущенные`.
     - Фильтр по источнику (Все, FreeHire, Habr, HH, LinkedIn).
     - Карточки вакансий с цветными бейджами порталов, бейджами Fit Score (`★ Match 85%`), бейджами безопасности `🛡️ Clean`, тегами совпавших навыков и кнопками быстрого триажа «✓ В работу» и «✕ Пропустить».
     - При принятии вакансии в работу выводится прямая ссылка на анализ вакансии в основном пайплайне CVortex.
   - В глобальное меню `WorkspaceShell` добавлен раздел `Поиск (Discover)` с иконкой `search`.
   - В заголовок `vacancy-workspace.tsx` добавлена кнопка быстрого перехода `🔍 Найти на порталах`.

6. **Верификация**:
   - Backend PHPUnit Suite: 21/21 тестов PASS (123 assertions) в `FastFitScorerTest`, `DiscoveryApiTest`, `PostingGuardServiceTest`, `VacancyIngestionPostingGuardTest`.
   - Backend Pint: 0 style issues across 248 files (`[PASS]`).
   - Backend PHPStan: 0 errors across 155 files (`[OK] No errors`).
   - Frontend Typecheck (`tsc --noEmit`): 0 errors (`[PASS]`).
   - Frontend Lint (`eslint`): 0 warnings/errors (`[PASS]`).
   - Frontend Vitest Suite: 14/14 файлов тестов PASS, 171/171 тест успешно (`[PASS]`).

---

## AI Job Search Integration: Фаза 1 — Инфраструктура Воркера и Posting Guard (Security Gate & Hardening) — 2026-10-26

Mode: IMPLEMENT; branch: `feature/ai-job-search-worker-and-posting-guard`; target: `stage`. One agent / sequential execution.

### Completed Outcomes
1. **Архитектурный стандарт интеграции (ADR-0028)**:
   - Принят `docs/03-ADR/ADR-0028-ai-job-search-integration-and-worker.md` и зарегистрирован в `docs/03-ADR/INDEX.md`.
   - Зафиксированы 4 столпа интеграции `ai-job-search`: изолированный worker-контейнер, защитный барьер Posting Guard от prompt injection, трёхступенчатый контур генерации (Drafter → Reviewer → Truth Guard) и гибридный экспорт PDF (DOCX + LaTeX).

2. **Ремедиация уязвимостей и архитектурный рефакторинг Posting Guard (`PostingGuardService` и `PostingGuardResult`)**:
   - Полная замена наивного регулярного выражения на детерминированный стековый HTML-токенизатор (`cleanHtml`), портированный из `posting_guard.py`:
     - Стековый обход тегов с поддержкой void (`br`, `img`, `meta` и др.), блочных и неявно закрывающихся элементов (`p`, `li`, `tr` и др.).
     - Защита от утечки пейлоада через вложенные теги (`<div style="display:none"><div>...</div>PAYLOAD</div>`) и незакрытые скрытые блоки (`<div style="display:none">payload`).
     - Нормализация CSS-стилей: декодирование HTML-сущностей (`display&#58;none` → `display:none`) и удаление CSS-комментариев (`display/**/:none` → `display:none`).
     - Защита от False Positives: легитимный `overflow: hidden` (без одновременного `height:0`/`width:0`) и классы разметки (`class="hidden-xs"`, `class="tag-hidden-wrapper"`) больше не приводят к вырезанию элементов.
     - Ликвидация слепой зоны сканирования (Blindspot Remediation): собираются два потока текста (`$cleanText` и `$hiddenText`), сканирование на Prompt Injection и Scam производится по объединенному потоку. Инъекция в скрытом блоке вырезается из видимого текста, но фиксируется в `$findings['injections']`, устанавливая `threatLevel = THREAT_HIGH_RISK` и `isSafeForLlm = false`.

3. **Консистентность контракта `VacancyIngestionService`**:
   - В блоке перехвата гонки дубликатов `catch (QueryException)` гарантирован возврат ключа `'posting_guard' => $guardResult`, обеспечивая строгую типизацию и консистентность контракта метода `queue()`.

4. **Харденинг вспомогательного Docker-воркера (`apps/worker`)**:
   - Создан непривилегированный системный пользователь `cvortex` (UID 1000).
   - Бинарник **Bun 1.4.4** установлен глобально в `/usr/local/bin/bun` с правами 755 и доступен пользователю `cvortex`.
   - Контекст выполнения контейнера переключен на `USER cvortex`.
   - Команда запуска заменена на сигнал-безопасную конструкцию ожидания: `CMD ["python3", "-c", "import signal; signal.pause()"]`.

5. **Тестовое покрытие и верификация (PR #62 Remediation Verified)**:
   - Превращение `VacancyIngestionPostingGuardTest` в реальный интеграционный тест с сохранением санитизированного `VacancySnapshot` в БД и валидацией метаданных события `VACANCY_POSTING_SECURITY_FLAG` в `AuditLogger`.
   - Расширение `PostingGuardServiceTest` 5 новыми edge-case тестами: вложенные скрытые теги, незакрытые скрытые теги, HTML-сущности и комментарии в стилях, сохранение легитимного `overflow: hidden`, детекция инъекций в скрытом слое с `THREAT_HIGH_RISK`.
   - Backend PHPUnit PostingGuard Suite: 12/12 тестов PASS (85 assertions).
   - Backend Full PHPUnit Regression Suite: 483/483 теста PASS (3724 assertions).
   - Backend Pint: 0 замечаний стиля (`[PASS]`).
   - Backend PHPStan: Level 8, 0 ошибок (`[OK] No errors`).
   - Worker Docker Validation: сборка успешна, `whoami` = `cvortex` (uid 1000), `bun --version` = `1.4.4`, `pypdf` готов к работе.

4. **Верификация**:
   - Backend PHPUnit Unit Suite: 22/22 тестов PASS (73 assertions) в `PostingGuardServiceTest` и `VacancyIngestionPostingGuardTest`.
   - Backend Pint: 0 style issues across 241 files (`[PASS]`).
   - Backend PHPStan: 0 errors across 151 files (`[OK] No errors`).
   - Docker Compose: `docker compose config --quiet` exit 0.
   - Frontend Typecheck & ESLint: 0 errors, 0 warnings.
   - Frontend Vitest: 13 файлов, 167/167 тестов успешно (exit 0).

---

## UX/UI Overhaul: Dashboard "Сегодня", Выделенный Импорт Вакансий и Рефакторинг Карточки Вакансии — 2026-10-26

Mode: IMPLEMENT; branch: `feature/ux-ui-overhaul-dashboard-and-vacancies`; target: `stage`. One agent / sequential execution.

### Completed Outcomes
1. **Главный командный центр «Сегодня» (`/`)**:
   - Маршрут `/` переведён на `TodayWorkspace`: активная навигация `id: "today"`.
   - Интерактивный виджет AI (Gemini + OpenAI): переключение провайдера в 1 клик, ввод BYOK API-ключа, кнопка «Проверить подключение» с замером пинга (мс) и динамическим обновлением доступных моделей.
   - Индикаторы активных фич Truth Guard: «Извлечение фактов из резюме», «Разбор требований вакансий», «Генерация откликов Truth Guard».
   - Сводка готовности кандидата: счётчики Career Facts и вакансий, умное предупреждение при 0 фактов карьеры.
   - Быстрые CTA: «+ Импортировать вакансию» (`/vacancies/import`) и «Загрузить резюме в Карьеру» (`/career`).

2. **Выделенный экран «Импорт вакансии» (`/vacancies/import`)**:
   - Чистая форма вставки текста до 50 000 символов с живым счетчиком и опциональным URL источника.
   - Информационный бейдж активного AI-провайдера.
   - Интерактивный 4-шаговый трекер прогресса сохранения и анализа вакансии.
   - Guard-проверка опыта: предупреждение о необходимости заполнения Career Facts при нулевом опыте.

3. **Полный рефакторинг Карточки Вакансии (`/vacancies`)**:
   - Снесены легаси-спойлер `<details>` со вторым экземпляром `<VacancyWorkspace />` и кнопка перезагрузки страницы из `OverviewPanel`.
   - Удалена декоративная растровая заглушка `career-art` из шапки вакансии.
   - Внедрена наглядная сетка 7 измерений соответствия на русском языке (Навыки, Опыт, Домен, Образование, Локация/Формат, Языки, Условия) с бейджами `Совпадение`, `Частично`, `Пробел/Гэп`.
   - Человекочитаемый вердикт на русском языке без сырых enum-значений (`STRONGLY_APPLY` → «Рекомендуется откликнуться (Высокий приоритет)»).
   - Дефолтная вкладка карточки вакансии переведена с `#materials` на `#overview`.
   - Компактный `VacancyChat` перенесен во вкладку «Материалы».

4. **Глобальная навигация (`WorkspaceShell` и `GlobalSidebar`)**:
   - Обновлены пути меню: «Сегодня» (`/`), «Вакансии» (`/vacancies`), «+ Импорт вакансии» (`/vacancies/import`).

5. **Верификация**:
   - TypeScript Typecheck: `npm --prefix apps/frontend run typecheck` (0 ошибок, exit 0).
   - ESLint: `npm --prefix apps/frontend run lint` (0 ошибок, 0 предупреждений, exit 0).
   - Vitest: `npm --prefix apps/frontend test` (16 файлов, 178 тестов успешно, exit 0).
   - Next.js Production Build: `npm --prefix apps/frontend run build` (10 статических маршрутов, exit 0).
   - PHPUnit: 17/17 тестов AiSettingsTest (116 assertions) и 15/15 unit-тестов бэкенда успешно.
   - Playwright Smoke E2E: `npm --prefix apps/frontend run test:e2e:smoke` (18/18 тестов успешно).

---

## Comprehensive Documentation Audit, Defect Remediation & Humanization (PR #60) — 2026-10-26

Mode: DOCS; branch: `docs/documentation-audit-remediation` (merged into `stage` via PR #60, commit `f67a2c733b914b01658c163b45092ca8114b8b7c`). One agent / sequential execution.

### Completed Outcomes
1. **Audit & Remediation across 40 Documentation Files**:
   - Resolved all broken image references, 0 broken links, and 0 broken anchors across the repository.
   - Eliminated unhighlighted code blocks (added explicit language tags) and normalized heading hierarchy skips.
   - Applied `/humanizer` principles to eliminate AI clichés, passive bureaucracy, and unneeded Anglicisms.
   - Translated foundational product and design specifications into clear, precise technical Russian (`Glossary.md`, `Concept-B-Reconciliation.md`, `Error-Center-UX.md`, `Process-Map.md`, `User-Guide.md`, `M0-Runbook.md`, `M1-Access-Core.md`, `Logging-and-Diagnostics.md`, `GitHub-Governance.md`).
   - Preserved B01 frozen canonical reference source files (`Design-Foundation.md`, `Application-Workspace.md`, `Design-Handoff.md`) and added anchor `<a id="token-reconciliation-ledger"></a>` to ensure B01 integrity tests pass without drift.
   - Synchronized cryptographic QA evidence manifest (`scripts/qa_evidence.py --write`), ensuring 88 manifest files verified.

2. **Verification & Delivery**:
   - `python3 scratch/check_all_links.py`: PASS (0 broken links, 0 broken anchors).
   - `python3 scratch/check_syntax_and_hierarchy.py`: PASS (0 skips, 0 unhighlighted blocks).
   - `git diff --check`: PASS (clean).
   - `node docs/07-Design/reference/build-reference.cjs --check`: PASS.
   - `node --test docs/07-Design/reference/build-reference.test.cjs`: PASS (10/10 tests OK).
   - `python3 scripts/qa_evidence.py`: PASS (88 manifest files verified).
   - `python3 -m unittest discover -s scripts -p 'test_*.py'`: PASS (20/20 unit tests OK).
   - GitHub Actions CI: `Roadmap metadata` (SUCCESS), `PR contract` (SUCCESS), `m0-quality` (SUCCESS), `e2e-tests` (SUCCESS).
   - PR #60 squash-merged into `stage`.

---

## Admin System Settings Review Remediation (PR #56 Findings) — 2026-10-26

Mode: IMPLEMENT; branch: `fix/admin-settings-review-followups`; target: `stage`. One agent / sequential execution.

### Completed Outcomes
1. **Dynamic Settings Integration (FINDING-01 RESOLVED)**:
   - Wired `SettingsResolver` into `OpenAiResponsesProvider`: dynamically resolves `api_key`, `base_url`, and `timeout_seconds` from database overrides / environment with defensive fallback to `config(...)`.
   - Wired `SettingsResolver` into `ModelPolicyResolver`: dynamically resolves model IDs (`ai.model_policies.low_cost.model`, `ai.model_policies.standard.model`) and token cost rates (`input_cost_micros`, `output_cost_micros`) from database overrides before policy validation.
   - Wired `SettingsResolver` into `InvitationService` and `CreateInvitation` command: invitation expiration dynamically defaults to `auth.invitation_default_expires_days` (default 7 days) if not explicitly overridden by CLI options.
   - Wired `SettingsResolver` into `CareerExtractionController` (`ai.career_extraction.max_source_characters`) and `VacancyController` (`ai.vacancy_extraction.max_source_characters`) with defensive minimum character clamping (`max(1000, $maxChars)`).
   - Hardened `SettingsRepository`: catches specific unmigrated table missing exceptions (`QueryException` with table not found / relation does not exist) while rethrowing non-migration errors to prevent silent failure masking and cache pollution.

2. **Nginx FastCGI Timeout Alignment (FINDING-02 RESOLVED)**:
   - Removed trailing slashes in `location ^~ /api/v1/admin/settings` and `location ^~ /api/v1/ai` in `infra/nginx/nginx.conf`, ensuring both root index/batch patch endpoints (`/api/v1/admin/settings`) and sub-paths inherit the extended 60-second `fastcgi_read_timeout`.

3. **Admin Test Throttling (FINDING-03 RESOLVED)**:
   - Added `middleware('throttle:10,1')` to `POST /api/v1/admin/settings/test` in `apps/backend/routes/api.php` to prevent outbound connection hammering or scanning.

4. **Frontend Declarative Routing (FINDING-04 RESOLVED)**:
   - Cleaned up `apps/frontend/src/app/admin-settings.tsx`: connection test buttons now route strictly through declarative `item.test_target` without heuristic string fallbacks.
   - Updated frontend Vitest mock fixture and Playwright E2E fixture to declare explicit `test_target: "openai"`.

5. **Operational Documentation & Architecture Alignment**:
   - Documented OpenAI vs Gemini dynamic model policy resolution scope in `docs/10-Operations/Runtime-Configuration.md`.

6. **Verification**:
   - Backend PHPUnit Settings & AI Suite: 73/73 tests PASS (770 assertions), including newly added regression tests for rate limiting, model policy overrides, dynamic TTL, and dynamic character limits in `AdminSettingsApiTest` and dynamic config consumption in `OpenAiResponsesProviderTest`.
   - Backend Full PHPUnit Suite: 462/462 tests PASS across entire backend.
   - Backend Pint: 0 style issues across 237 files.
   - Backend PHPStan: 0 errors across 149 files (`[OK] No errors`).
   - Frontend Vitest: 25/25 tests PASS across `admin-settings.test.tsx`, `access-shell.test.tsx`, and `ai-provider-settings.test.tsx`.
   - Frontend TypeScript: `tsc --noEmit` PASS (0 errors).
   - Frontend ESLint: 0 errors, 0 warnings.
   - Playwright E2E: PASS across critical suites.
   - QA Evidence Integrity: `python3 scripts/qa_evidence.py` PASS, 20/20 Python helper regressions PASS.
   - B01 Design Reference: 10/10 tests PASS.

---

## Superlist Swiss UI Refresh — 2026-10-26

Mode: IMPLEMENT; branch: `feature/superlist-ui-refresh`. One agent / sequential execution.

### Completed Outcomes
1. **Design System & Tokens Evolution**:
   - Implemented **Superlist Swiss Precision** aesthetic across `apps/frontend/src/app/application/tokens.css` and `globals.css`.
   - Deep obsidian neutral void (`--cv-surface-canvas: #090A0F`, pure void `#05060A`).
   - Translucent frosted glassmorphism surfaces (`rgba(19, 23, 34, 0.72)`, elevated `rgba(27, 33, 49, 0.82)`, interactive `rgba(36, 44, 66, 0.90)`).
   - High-precision hairline borders (`rgba(255, 255, 255, 0.09)`, hover `0.24`).
   - Punchy coral primary accent (`--cv-accent-primary: #FF5533`, hover `#FF6B4D`, glow `rgba(255, 85, 51, 0.35)`).
   - Sky blue (`#38BDF8`) for provenance/telemetry and emerald (`#22C55E`) for confirmed truth.
   - Calibrated `--cv-text-muted` / `--text-muted` to `#8590A4` (contrast 6.14:1 on canvas, 5.22:1 on elevated glass, fully compliant with WCAG 2.1 AA > 4.5:1).
   - Preserved all existing CSS token interfaces and variable names for seamless drop-in compatibility, restoring genuine 64-hex SHA-256 token checksum.

2. **Kinetic Backdrop & Global Shell**:
   - Added harmonic chromatic aurora backdrop mesh (coral/sky/indigo/emerald radial gradients) with fixed attachment.
   - Refined `.status-card`, `.workspace-shell`, `.panel`, `.fact-card`, and modal dialogs with backdrop blur (`blur(24px)`).
   - Upgraded primary CTA buttons to Superlist coral pills with high-contrast obsidian text (`--cv-text-inverse: #05060A`, contrast ratio 6.36:1, strictly WCAG AA compliant), ambient glow, and hover micro-lift (`translateY(-1px) scale(1.02)`).
   - Integrated `@media (prefers-reduced-motion: reduce)` resets across button hover micro-lifts and logo rotations.

3. **Swiss Geometric Vortex Branding & Header Lockup**:
   - Extracted shared Swiss Geometric Vortex vector emblem component (`VortexSwissLogo` in `apps/frontend/src/app/vortex-logo.tsx`) with dual semantic fallback token resolution.
   - Refined header branding lockup across `app-shell.tsx` and `workspace-shell.tsx` with dedicated `.brand-copy` flex column, preventing horizontal crowding/overflow in the 240px navigation column.

4. **Verification**:
   - Frontend Vitest: 13/13 test files (167/167 tests) PASS cleanly.
   - Frontend TypeScript: `tsc --noEmit` PASS (0 errors).
   - Frontend ESLint: 0 errors, 0 warnings.
   - Next.js Production Build: 8/8 static routes compiled successfully (`next build`).
   - Playwright E2E: 10/10 tests PASS across Desktop Chromium and Mobile Chrome.
   - Formatting & Hygiene: `git diff --check` PASS (0 whitespace errors).

---

## Runtime Configuration & Admin System Settings — 2026-10-25

Mode: IMPLEMENT; branch: `feature/admin-system-settings`. One agent / sequential execution.

### Completed Outcomes
1. **Architecture & Invariant Enforcement**:
   - Enforced architectural baseline **Option C**: bootstrap and infrastructure configuration remains in `.env` (immutable by application), while runtime settings live in code (`SettingDefinitionRegistry`) with database overrides in `system_settings`.
   - Adopted and accepted `docs/03-ADR/ADR-0027-runtime-system-settings.md` and registered in `docs/03-ADR/INDEX.md`.
   - Documented operational manual in `docs/10-Operations/Runtime-Configuration.md`.
   - Updated `.env.example` with clear section headers distinguishing `BOOTSTRAP/ENV_ONLY` from `RUNTIME_FALLBACK`.

2. **Backend Engine (`apps/backend/app/Settings/`)**:
   - Created migration `2026_10_24_000001_create_system_settings_table.php` (ULID, key UNIQUE, JSON value, encrypted_value text, version integer for optimistic concurrency, updated_by FK to users).
   - Implemented `SettingDefinitionRegistry` with typed definitions across groups (`general`, `auth`, `ai_models`, `ai_providers`, `email`, `storage`, `limits`) with explicit `test_target` mapping (`openai`, `smtp`, `storage`).
   - Implemented `SettingsResolver` with strict 4-tier precedence: Hard environment lock → Database override → Config/ENV fallback → Application default. Defensive cache deserialization recovery.
   - Enforced zero secret leakage: encrypted at rest via AES-256-CBC (`Crypt`), masked in API responses, excluded from audit logs (`redacted: true`), excluded from standard logs.
   - Reinforced optimistic locking & concurrency: initial override creation validates `expected_version === 1` and bumps to 2; concurrent first inserts hitting database unique constraints are caught and converted to `SettingConcurrencyException` (HTTP 409).
   - Atomic batch operations: `AdminSettingsController::update` wraps all setting updates in `DB::transaction`, guaranteeing full rollback if any setting in a batch fails validation or concurrency checks.
   - Implemented `SettingTestService` for live connectivity tests (OpenAI API latency/ping, SMTP HELO, dynamically resolved storage disk write/read/delete) with audit logging.
   - Built `AdminSettingsController` with endpoints:
     - `GET /api/v1/admin/settings` (list all settings with secrets masked)
     - `PATCH /api/v1/admin/settings` (atomic batch update with optimistic locking)
     - `POST /api/v1/admin/settings/reset` (reset override to fallback/default)
     - `POST /api/v1/admin/settings/test` (connectivity testing with target validation)
     - `GET /api/v1/admin/settings/environment` (read-only diagnostics without exposing `APP_KEY`)
   - Wired dynamic settings into core services (`AppServiceProvider`, rate limits, `ModelPolicyResolver`, `OpenAiResponsesProvider`, `InvitationService`, character extraction limits).

3. **Frontend Control Plane (`apps/frontend/`)**:
   - Built modern Admin UI `apps/frontend/src/app/admin-settings.tsx` with category navigation (Общие, Аутентификация, Модели ИИ, Провайдеры ИИ, Почта и SMTP, Файлы и шаблоны, Лимиты и хранение, Среда и диагностика).
   - Implemented search filter, source badges (`badge-db`, `badge-env`, `badge-locked`, `badge-default`), staged editing with sticky Discard/Save bar, secret masking & replacement, override reset, and test ping buttons with latency reporting.
   - Explicit test target routing: connection test buttons route strictly through `item.test_target` without defaulting non-matching items to storage.
   - Lifecycle cleanup & unmount guard: `isMountedRef` and `AbortController` cancellation prevent state leakage or updates on unmounted components.
   - Created route `apps/frontend/src/app/admin/settings/page.tsx` and integrated route guard in `access-shell.tsx` (403 for non-admins).
   - Protected access shell integrity: `access-shell.tsx` preserves `AbortError` passthrough without converting to `NETWORK_ERROR`, and preserves `key={user.id}` + `userId={user.id}` on `CareerWorkspace`.
   - Added navigation links to "Настройки системы" in `workspace-shell.tsx` and `app-shell.tsx`.
   - Designed responsive styling in `apps/frontend/src/app/globals.css` using `--cv-*` design tokens.

4. **Verification**:
   - Backend PHPUnit Settings: 26/26 tests PASS (488 assertions) across `SettingDefinitionRegistryTest`, `SettingsResolverTest`, and `AdminSettingsApiTest`.
   - Full Backend PHPUnit Suite: 421/421 tests PASS (3371 assertions, 12 skipped).
   - Backend Pint: 0 style issues across 231 files.
   - Backend PHPStan: 0 errors across 146 files (`[OK] No errors`).
   - Frontend Vitest: 13/13 test files (167/167 tests) PASS, including `access-shell.test.tsx` (6/6 PASS), `admin-settings.test.tsx` (11/11 PASS), and `ai-provider-settings.test.tsx` (8/8 PASS).
   - Frontend TypeScript: `tsc --noEmit` PASS (0 errors).
   - Frontend ESLint: 0 errors, 0 warnings.
   - Playwright E2E: 10/10 tests PASS across Desktop Chromium and Mobile Chrome in `e2e/admin/system-settings.spec.ts`.
   - B01 Reference Integrity: PASS (10/10 tests).
   - Gemini BYOK Dynamic Discovery & Network Resilience: `GeminiResponsesProvider` dynamically discovers all models supporting `generateContent` by key via `GET /v1beta/models`, caches model catalog, provides automatic fallback on 404, supports optional `AI_HTTP_PROXY`, and Nginx `/api/v1/ai/` and `/api/v1/admin/settings/` fastcgi timeouts extended to 60s eliminating 504 Gateway Timeouts.
   - PR Contract: PASS (`check-pr-contract.sh 56` exit 0).

---

## QA-002 & QA-003 — Harness Safety, Aggregate Relations & Timestamp-Tie Negative Control — 2026-10-25

Mode: IMPLEMENT; branch: `fix/qa-002-qa-003-safety-negative-control`; target: `stage`. One agent / sequential execution.

### Completed Outcomes
1. **QA-002: Lifecycle Safety, Aggregate Relations & Evidence Validation**:
   - `R1 MAJOR`: `scripts/vacancy_migration_harness.py::resources()` дополнен флагом `-a` для `docker container ls`, гарантируя учёт остановленных контейнеров проекта при инвентаризации, валидации меток владения (`cvortex.qa002.run`) и контроле чистоты ресурсов при cleanup. Добавлен юнит-тест `test_resources_lists_all_containers_including_stopped`.
   - `R2 MAJOR`: `scripts/fixtures/vacancy-duplicate-migration/reconciled.sql` дополнен строгой проверкой FK и aggregate mapping `vacancy → snapshots → analyses → LLM runs` (проверка соответствия каждого снимка его вакансии и `source_url`, каждого анализа — снимку и вакансии, LLM-прогона — снимку). В `vacancy_migration_harness.py` отрицательный контроль `QA002-14` перенесён в пост-миграционную фазу после применения `REPAIRS` и прохождения базового `reconciled.sql`, снабжён явной проверкой предусловий (существование целевого снимка и обновление ровно 1 строки) и проверяет точную сигнатуру ошибки (`snapshot source_url or owner_id differs from parent vacancy`). Добавлены юнит-тесты защиты от ложного PASS в `test_vacancy_migration_harness.py`.
   - `R3 MINOR`: `scripts/qa_evidence.py` дополнен валидатором `validate_qa002`, выполняющим строгую проверку JSON, соответствие SHA-256 файлов `log-exports.json` (включая разархивирование raw gzip), валидацию структуры `exact-runs.json`, неизменность `development-preservation.json` и целостность ссылок в `README.md`. Добавлены тесты целостности в `scripts/test_qa_evidence.py`.
   - Запуск полного lifecycle harness `scripts/test-vacancy-postgres-duplicate-migration.sh --regression-controls` в гарантированно изолированном disposable Compose-окружении завершён успешно (PASS, exit 0, чистый cleanup).

2. **QA-003: Deterministic Timestamp Ordering Negative Control & Latest Operation**:
   - В `apps/backend/tests/Feature/DiagnosticsPostgresTest.php` (`test_runtime_role_uses_id_to_break_latest_occurrence_timestamp_ties`):
     - Физический порядок обновления кортежей инвертирован относительно лексикографического порядка ULID: запись с большим ID обновляется первой, а с меньшим — второй, получая больший TID в таблице PostgreSQL.
     - Добавлен строгий negative control: прямой SQL-запрос `ORDER BY created_at DESC LIMIT 1` без условия `id DESC` возвращает более старую запись (`$smallerId`), доказывая, что отсутствие `id DESC` приводит к получению неверного результата. Аналогичный отрицательный контроль добавлен для детального списка occurrences.
     - Добавлена проверка tie-breaker для поля `latest_operation` (`operation_newer` vs `operation_older`).
     - Проведено мутационное тестирование (удаление `id DESC` из `DiagnosticsController` вызывает немедленный FAIL тестов), подтверждена защита от регрессий.
     - Выполнение теста подтверждено на изолированном PostgreSQL 18.6 (5/5 PASS, 58 assertions, 0 SKIP).

Status: QA-002 и QA-003 **PASS / RESOLVED**.

---

## QA-004 — Career Claim resolution race conditions eliminated (PR #53) — 2026-10-25

Mode: IMPLEMENT; branch: `fix/qa-004-claim-resolution-race-conditions`; target: `stage` (PR #53 squashed and merged as commit `679c3fcdca1effe26c253a4f3052b275c6c3abe9`). One agent / sequential execution.

### Completed Outcomes
1. **Request Sequencing & Invalidation (`apps/frontend/src/app/career-workspace.tsx`)**:
   - Monotonic request tracking (`requestId` / `latestAppliedRequestIdRef`) ignoring stale GET responses.
   - Mutation epoch tokens (`mutationEpochRef`) dropping any GET responses initiated before an explicit resolution PATCH.
   - Local resolution invariant cache (`resolvedClaimsRef`) protecting resolved claim statuses against lagging server snapshots (preventing regression from resolved state back to `USER_RESOLUTION_REQUIRED` and preventing stale `PASS` from overwriting newer `BLOCK`).
   - `AbortController` cancellation for in-flight GET requests upon new fetch, resolution mutation, user switch, or component unmount.
   - Mutex coordination between active mutations (`activeMutationsRef`) and background polling, pausing polling during PATCH operations.
   - User switch isolation (`activeUserIdRef`) and component unmount safety (`isMountedRef`), ensuring responses and resolution callbacks are never applied to unmounted components or across different user sessions.
   - `apps/frontend/src/app/access-shell.tsx`: passed `userId` and `key={user.id}` to `CareerWorkspace`; permitted `AbortError` to pass through without generating spurious `NETWORK_ERROR` toasts.
   - `apps/frontend/src/app/career-claim.tsx`: added `onMutating` notification callback and guarded `onResolved` against claim ID mismatch or unmount.

2. **Verification & Regression Coverage**:
   - **Frontend Vitest (`apps/frontend/src/app/__tests__/career-claim.test.tsx`)**: 10/10 test files (142/142 tests) PASS. Added deterministic `deferred<T>()` promise tests covering RACE-01 through RACE-08:
     - RACE-01: Stale GET completing after successful PATCH does not revert resolution.
     - RACE-02: Stale GET with PASS does not overwrite newer BLOCK.
     - RACE-03: Out-of-order GET responses are ignored; only latest sequence applies.
     - RACE-04: Failed PATCH handles error cleanly without false success state.
     - RACE-05: Claim/context switch during in-flight request ignores old context.
     - RACE-06: Background polling does not collide with or revert in-flight mutations.
     - RACE-07: Unmount prior to request completion does not commit state.
     - RACE-08: User switch drops pending requests from previous user session.
     - Truth Guard negative test verifying unresolved ambiguous claims announce downstream resolution requirement.
   - **Backend Feature Test (`apps/backend/tests/Feature/ClaimResolutionUiTest.php`)**: added `test_unresolved_ambiguous_claim_blocks_downstream_matching_until_explicit_resolution` verifying downstream matching (`TrustedCareerQuery::forMatching`) remains empty until explicit PATCH resolution. 83/83 backend feature tests PASS (689 assertions, isolated SQLite container).
   - **Static Quality & Build**: ESLint (0 errors), TypeScript (`tsc --noEmit`, 0 errors), Next.js production build (6/6 static pages), Laravel Pint (0 issues), PHPStan (`--memory-limit=512M`, 0 errors).
   - **Remote CI**: PR #53 passed `Governance/PR contract`, `Governance/Roadmap metadata`, and `Quality/m0-quality`.

Status on `stage`: QA-004 is **PASS / RESOLVED**.

---

## Google Gemini LLM Provider Integration — 2026-10-24

Mode: IMPLEMENT; branch: `feature/gemini-llm-provider`. One agent / sequential execution.

### Completed Outcomes
1. **Research & Architecture Baseline**:
   - Researched official Google Gemini REST API v1beta and recorded findings in `research/technical/12-GOOGLE-GEMINI-INTEGRATION.md`.
   - Authored and accepted `docs/03-ADR/ADR-0026-gemini-llm-provider-integration.md` amending ADR-0010 and ADR-0011.
   - Updated `docs/03-ADR/INDEX.md` and `docs/06-AI/Phase-06-AI-Design.md`.

2. **Backend Provider Implementation**:
   - Built native `App\AI\Providers\GeminiResponsesProvider` using Laravel HTTP Client (`Illuminate\Support\Facades\Http`) without community SDKs.
   - Implemented `generateResolved` with native OpenAPI 3.0 structured JSON output (`generationConfig.responseSchema`, automatic schema sanitization stripping `$schema` and `$id`), system instructions, token usage metadata, and deterministic cost calculation.
   - Implemented finish-reason mapping (SAFETY → REFUSAL, MAX_TOKENS → INCOMPLETE, MALFORMED_OUTPUT) and HTTP status error normalization (429/408/5xx retryable, 400/401/403 non-retryable INVALID_CONFIGURATION).
   - Implemented zero unannounced/silent fallback invariant: if Gemini fails, the error is returned directly as `LlmProviderException`.
   - Implemented multi-user BYOK settings storage (`user_ai_settings`) with PostgreSQL RLS isolation (`user_ai_settings_owner_isolation`) and encrypted-at-rest API keys (`'encrypted'` cast; hidden from serialization).
   - Extended `ConfiguredLlmProvider` and `ModelPolicyResolver` to dynamically route to the user-selected provider or default provider.
   - Created `AiSettingsController` exposing `GET /api/v1/ai/settings`, `PATCH /api/v1/ai/settings`, and non-mutating rate-limited `POST /api/v1/ai/test-connection`.
   - Updated `OpenAiResponsesProvider` with BYOK dynamic key support and `testConnection`.

3. **Frontend Settings Workspace (`apps/frontend/`)**:
   - Created `apps/frontend/src/app/settings/page.tsx` and `apps/frontend/src/app/ai-provider-settings.tsx`.
   - Implemented provider selector (Gemini vs OpenAI), masked API key display, key deletion/replacement, default model selector, live connection test with latency/status reporting, and settings persistence.
   - Integrated `/settings` route into `access-shell.tsx`, `workspace-shell.tsx`, and `application/app-shell.tsx`.
   - Added accessible responsive styles to `apps/frontend/src/app/globals.css`.

4. **Verification**:
   - Backend PHPUnit: 19/19 tests PASS (123 assertions) across `GeminiResponsesProviderTest`, `OpenAiResponsesProviderTest`, and `AiSettingsTest` (including Truth Guard PENDING invariant preservation).
   - Backend Pint: 0 style issues across all modified files.
   - Backend PHPStan: 0 errors (`[OK] No errors`).
   - Frontend Vitest: 11/11 test files (139/139 tests) PASS, including `ai-provider-settings.test.tsx` (6/6 PASS).
   - Frontend TypeScript: `tsc --noEmit` PASS (0 errors).
   - Frontend ESLint: 0 errors, 0 warnings.

---
## Playwright E2E UI Coverage & Quality Automation — 2026-10-24

Mode: IMPLEMENT; branch: `chore/playwright-e2e-coverage`; task: `.agents/tasks/playwright-e2e-coverage.md`. One agent / sequential execution.

### Completed Outcomes
1. **Playwright Automation Infrastructure (`apps/frontend/`)**:
   - Configured `playwright.config.ts` targeting local Docker Compose stack (`http://127.0.0.1:8080`) with Desktop Chromium (1440×900) and Mobile Chrome (Pixel 7 emulation, 393×851).
   - Added npm test scripts: `test:e2e`, `test:e2e:smoke`, `test:e2e:critical`, `test:e2e:ui`, `test:e2e:report`.
   - Built reusable test harness and fixtures (`apps/frontend/e2e/fixtures/test-fixtures.ts`) with synthetic run-scoped auth (`qa-*@test.cvortex`), automated WCAG 2.1 AA audits via `@axe-core/playwright`, and role-based helpers.
   - Configured CI job `e2e-tests` in `.github/workflows/quality.yml` with host dependency installation, Chromium headless test execution, and Playwright report artifact uploading.

2. **Test Suites & Verification Results (64 tests across 8 suites, 100% PASS)**:
   - `e2e/auth/login.spec.ts`: 10/10 PASS (Desktop + Mobile) — form rendering, valid auth, invalid password safe failure, nonexistent user handling, HTML5 boundary validation, session persistence, logout flow.
   - `e2e/auth/register.spec.ts`: 6/6 PASS — invite-only gate enforcement, successful registration with valid token, invalid token rejection, password mismatch validation.
   - `e2e/auth/access-control.spec.ts`: 8/8 PASS — role-based route access, admin-only Error Center diagnostics, regular user 403 denial, unauthenticated visitor login redirect.
   - `e2e/career/career-facts.spec.ts`: 8/8 PASS — career workspace panels, manual fact creation, fact review/confirmation, extraction provider error handling with safe localized messaging.
   - `e2e/vacancies/vacancies.spec.ts`: 8/8 PASS — vacancy workspace rendering, vacancy creation via paste, client-side validation on empty input, Truth Guard invariant enforcing human approval separation before AI draft approval.
   - `e2e/security/cross-user-isolation.spec.ts`: 6/6 PASS — multi-user career facts isolation, vacancy isolation, prompt injection resistance without privilege escalation.
   - `e2e/diagnostics/diagnostics.spec.ts`: 6/6 PASS — admin incidents log, detailed sanitized technical context, status update to RESOLVED.
   - `e2e/responsive/responsive-navigation.spec.ts`: 6/6 PASS — mobile navigation drawer, 390px viewport without horizontal overflow, 320px compact layout preservation.
   - `e2e/accessibility/a11y-audit.spec.ts`: 6/6 PASS — automated axe-core WCAG 2.1 AA scans on auth and career pages, modal focus trap, Escape key dismissal.
   - Vitest component/unit tests: 10/10 files (133/133 tests) PASS without regressions.
   - TypeScript (`tsc --noEmit`) and ESLint (`eslint`): 0 errors.

3. **Documentation Delivered**:
   - `docs/09-QA/playwright-coverage-matrix.md`: 30+ scenarios across 8 areas mapped to priority (P0–P2), type, route, and coverage status.
   - `docs/09-QA/playwright-architecture.md`: layout, fixtures, browser targets, configuration, and invariants.
   - `docs/09-QA/playwright-test-strategy.md`: Truth-first, multi-user isolation, deterministic AI boundaries.
   - `docs/09-QA/playwright-local-setup.md`: prerequisites, local test running instructions, environment overrides.
   - `docs/09-QA/playwright-ci.md`: GitHub Actions workflow, artifact retention, timeout policies.
   - `docs/09-QA/playwright-known-gaps.md`: honest audit of unimplemented UI features (file uploads, URL scraping, Career Tracks UI, Settings BYOK, Interview prep, Document export).

---

## Post-Merge Targeted Regression — stage SHA 8dce209b7c1c86747753a97083dcf3eb0360db3f, 2026-10-24

Mode: VERIFY ONLY / DOCUMENTATION ONLY; one agent/sequential. Проверенный HEAD ветки `stage`:
`8dce209b7c1c86747753a97083dcf3eb0360db3f` (синхронизирован с `origin/stage`).
Все четыре PR #47 (QA-001), #50 (QA-004), #49 (QA-003) и #48 (QA-002) объединены в `stage`.
GitHub Actions CI Quality (`m0-quality`) и Governance checks (`PR contract`, `Roadmap metadata`)
для данного SHA завершились успешно (PASS).
Фактическая независимая проверка на интегрированном дереве установила, что объединение в stage
не устранило открытые дефекты автоматически:

- **QA-001 (B01 OFL integrity): PASS.**
  Канонический checker `node docs/07-Design/reference/build-reference.cjs --check` PASS (exit 0);
  10/10 тестов Node (`build-reference.test.cjs`, включая QA001-01…08) PASS; 8/8 Python evidence
  regressions PASS. Нормализованный `OFL.txt` (4401 байт, 0 CRLF, 1 хвостовой пробел удалён)
  соответствует утверждённой политике `lf-trim-trailing-horizontal-whitespace`. Stored SHA-256
  (`18a4de52...`) отделён от upstream SHA-256 (`564ce565...`). Исходный hash mismatch устранён.
  Сохраняются открытыми non-blocking review findings: `REVIEW-QA001-01` (покрытие guard-веток
  постоянными параметризованными тестами) и `REVIEW-QA001-02` (документационное разграничение
  snapshot integrity и upstream authenticity в README). Локальная snapshot integrity не доказывает
  подлинность upstream.

- **QA-002 (Migration harness boundary & isolation): BLOCKED.**
  Селективный выбор миграций до границы `000008` реализован, 7/7 unit-тестов harness PASS.
  Unit-тесты не доказывают безопасность полного lifecycle. Критические замечания независимого
  ревью остаются открытыми:
  - `R1 MAJOR`: инвентаризация `inspect_owned` использует `docker container ls -q` без `-a`,
    пропуская остановленные контейнеры, что блокирует cleanup или пропускает неверный token;
  - `R2 MAJOR`: assertions в `reconciled.sql` проверяют только скалярные counts/статусы,
    но не проверяют полные FK-связи `vacancy → snapshots → analyses → LLM runs` и не содержат
    negative control для предотвращения ошибочной перепривязки к другому агрегату того же владельца;
  - `R3 MINOR`: verifier `scripts/qa_evidence.py` не проверяет QA-002 evidence package.
  PostgreSQL lifecycle execution заблокирован (`BLOCKED`) до устранения проблем безопасности
  harness и подтверждения disposable isolation (на хосте активен `cvortex-postgres-1` с `cvortex_test`).

- **QA-003 (Deterministic timestamp ordering): BLOCKED.**
  Разделение объекта `LlmProviderException` на два экземпляра устранило сбой WeakMap deduplication
  в фикстуре `DiagnosticsPostgresTest::test_runtime_role_uses_id_to_break_latest_occurrence_timestamp_ties`.
  Однако регрессионный тест пока не гарантирует обнаружение удаления обязательного `id DESC`:
  SQL-контроль показал, что при одинаковых timestamps физический порядок строк в PostgreSQL
  совпадает с порядком ID, создавая риск ложного PASS; строгий negative control отсутствует.
  Timestamp-tie для `latest_operation` не проверяется (LOW). В CI используется SQLite,
  поэтому PostgreSQL-специфичный тест пропускается (`SKIP`). Успешный CI не подтверждает
  прохождение этого теста на PostgreSQL. Выполнение на изолированном PostgreSQL не подтверждено
  (`SKIP` не является `PASS`).

- **QA-004 (Career Claim resolution UI & Truth Guard): FAIL.**
  Компонент эксплицитного выбора подтверждающего факта добавлен (`CareerClaim.tsx`, 25/25 component
  tests PASS). Backend Truth Guard downstream enforcement работает: unresolved Claim блокирует
  генерацию на уровне API (`422 Unprocessable` / `stale` / `BLOCK`, 12/12 `ClaimResolutionUiTest`,
  19/19 `ApplicationDraftTest` PASS [MOCKED]).
  Однако в `career-workspace.tsx` обнаружен открытый блокирующий дефект состояний гонки (RACE-01–RACE-08):
  функция `refresh()` выполняет несинхронизированный GET и затирает стейт (`setOverview`).
  Устаревший GET после PATCH resolution возвращает UI к `USER_RESOLUTION_REQUIRED` (RACE-01);
  устаревший GET с PASS перезаписывает более новый BLOCK (RACE-02); фоновый polling перекрывает
  PATCH (RACE-06); размонтирование производит недопустимый commit (RACE-07).
  Коммит `b09a081` добавил лишь обработку `RESOURCE_NOT_FOUND` и memoize, но саму проблему гонок не устранил.
  Статус UI-компонента и интеграции — FAIL.

- **Cross-task integration: FAIL.**
  Сквозной пользовательский сценарий `AMBIGUOUS CLAIM → USER_RESOLUTION_REQUIRED → DOWNSTREAM BLOCK → EXPLICIT RESOLUTION → TRUTH GUARD REVALIDATION → GENERATION → HUMAN APPROVAL → REFRESH → PERSISTENCE`
  на backend boundary работает с synthetic/mock данными (`PASS [MOCKED]`), но на уровне UI
  подвержен регрессии при устаревшем ответе GET/polling (`FAIL`). Полный end-to-end сценарий не прошёл.

- **Release Gates и ограничения:**
  - Production Accessibility Release Gate (ADR-0025, K1/Z1/A1): `OPEN / BLOCKED`.
  - Preview 0.1 → M2 Gate: `BLOCKED` (реальный пользовательский сценарий не подтверждён).
  - Реальные интеграции с OpenAI API и ChatGPT OAuth не подтверждены (`NOT_VERIFIED / BLOCKED`).
  - Все выполненные тесты AI провайдеров маркированы как MOCKED и не доказывают работу реального LLM.

## QA-003 — diagnostics timestamp-tie fixture repair, 2026-10-12

Explicit bounded owner IMPLEMENT override of
`NEXT=b01-current-manifest-integrity-reconciliation`; native write preflight PASS.
Base/target `stage`, branch `fix/qa-003-diagnostics-fixture`, one agent/sequential.
NEXT and BLOCKERS are unchanged; no other QA repair or full audit.

Baseline `5cfe18d401c056eefb179f722d875c3b1acf7522` reproduced expected 2/actual 1.
Validated code SHA `10d9b575b63cd44ad183af8287c322d4ff804191`: two distinct
RATE_LIMITED exceptions create two occurrences in one fingerprint; equal timestamps
select greater ID with latest provider/request/attempt/retry metadata. Dedicated
PostgreSQL same-object regression preserves one occurrence/count. Production
recorder/controller/migrations and WeakMap behavior are unchanged.

Owned PostgreSQL 18.6/tmpfs/internal network; runtime role NOSUPERUSER/NOBYPASSRLS.
Exact test 1/17 PASS; runtime Diagnostics suite 5/50 PASS; targeted Diagnostics
on PostgreSQL as isolated admin 57/575 PASS; zero skips, exit 0. USER list/detail/update
403, audit rollback and redaction PASS. Pint, PHPStan, diff checks PASS. After each
suite users/incidents/occurrences/audit return to zero; owned cleanup verified.
Setup and LOG_LEVEL invocation failures remain in new evidence alongside the
successful rerun; historical failure logs/accounting were not rewritten.
[SHA/commands/environment/results](../../docs/09-QA/evidence/qa003-2026-10-12/README.md).
Locally verified; PR delivery/CI is recorded in the final report. No merge. STOP.

## QA-004 — Career Claim resolution UI, 2026-10-12

Bounded IMPLEMENT, explicit owner override of NEXT. Base `origin/stage` = `5cfe18d`;
separate branch `fix/qa-004-career-claim-resolution-ui` and managed worktree.
Career now exposes explicit supporting-fact selection, existing PATCH, controlled
errors/retry and backend-authoritative result. No production backend/schema changes.

Local validation: frontend 133 tests, lint/typecheck/production build PASS;
Career/Claim API 52 tests / 417 assertions and Application 19 / 253 PASS
(SQLite in-memory), targeted Pint/PHPStan PASS. Browser: isolated PostgreSQL
`cvortex_qa004_synthetic`, runtime role `qa004_app` without superuser/BYPASSRLS,
Redis/Horizon + deterministic local provider; 15 scenarios PASS, including actual
keyboard resolution, persistence, reanalysis → generation → explicit acceptance/
approval, CSRF419 and cross-owner404. Responsive 320/390/768/1440 smoke PASS.
[Task](../tasks/qa-004-career-claim-resolution-ui.md),
[evidence/limits](../../docs/09-QA/evidence/qa004-2026-10-12/README.md).

QA-004 FIXED / locally verified, awaiting PR review/integration. NEXT/BLOCKERS unchanged;
QA-001–003, live-provider readiness, Preview 0.1 and manual accessibility gates
remain outside scope. No development DB writes, automatic approval/submission or merge.
STOP at QA-004; the next intended task remains `b01-manual-accessibility-release-gate`.

## QA-002 — deterministic migration harness, 2026-10-12

Explicit owner IMPLEMENT override of `NEXT=b01-current-manifest-integrity-reconciliation`;
base/target `stage`, branch `fix/qa-002-migration-harness`, one agent/sequential.
Native Git write preflight with task + `--user-override` PASS. NEXT remains unchanged.

The test selects paths through `000008`, checks actual pre-000009 schema, seeds
legacy duplicate history, applies `000009`–`000012`, checks data/status/owner/schema
invariants, and rolls back/reapplies only those named repairs before later migrations.
Every invocation owns a fresh Compose project, PostgreSQL container, database,
ephemeral credentials, volumes and internal network. External targets are blocked;
cleanup checks ownership and cannot silently PASS on failure.

Local PostgreSQL current-set run PASS (28/28 Ran); separate future-migration and
wrong-schema controls PASS. Seven harness unit tests, shell/Python syntax and
scoped diff checks PASS. Counts `1/8/10/5/3 → 1/5/10/5/3`; history retained,
owned cleanup verified. Read-only schema/count/row digests for existing `cvortex`,
`cvortex_test`, `cvortex_dev2` unchanged. Product migrations, QA-001/003/004 and
historical QA logs/accounting are preserved. [Evidence](../../docs/09-QA/evidence/qa002-2026-10-12/README.md).
Task is locally verified; governed PR delivery/CI is recorded in the final report.
Existing B01/Preview/provider/M2 blockers are unchanged. No merge. STOP.

## QA-001 B01 integrity — local implementation verified, 2026-10-12

Owner explicitly authorized IMPLEMENT, one agent/sequential, scoped commit/push/PR;
base stage `5cfe18d401c056eefb179f722d875c3b1acf7522`, branch
`fix/qa-001-b01-manifest-integrity`, isolated managed worktree. NEXT matched;
no task spec existed at launch, owner prompt used as bounded authority and recorded
in [new task spec](../tasks/b01-current-manifest-integrity-reconciliation.md).

Current canonical `--check` PASS and 10/10 Node tests PASS (QA001-01…08).
Pinned upstream fetched/rehashed: 4495 bytes/93 CRLF; tracked/worktree OFL:
4401 bytes/LF, unchanged. Git normalizes CRLF but does not remove the separately
observed trailing space. Manifest mixed upstream and stored-byte hashes.
Policy A retains LF/trimmed storage and separate upstream metadata/fixture;
check/write verify provenance before manifest generation. Only four generated
asset hashes change, with no token/source/rendering or unrelated asset changes.

[Current defect status and evidence](../../docs/09-QA/QA-001-RECONCILIATION.md).
Historical QA-001 FAIL, register and evidence manifest stay byte-for-byte intact;
new status supersedes their dated outcome. QA-002–QA-004 are not addressed.
First remote Quality run failed because the Python test sandbox omitted the new
linked task; its fixture helper now copies that task. Final-tree Python evidence
regressions 8/8 PASS; original CI failure retained. Independent PR review pending;
technical result is not independent review PASS.
B01 owner acceptance remains; full manual matrix/K1/Z1/A1 **NOT TESTED / OPEN**,
production release blocked. NEXT names the existing manual gate after QA-001
review/integration; this task stops without executing it.

## PR #46 evidence remediation candidate — 2026-10-12

Explicit owner override, primary mode IMPLEMENT, существующая PR branch `docs/full-functional-qa-2026-10-11`. Исправлены механизм manifest LF export, historical false-PASS accounting, mapping восьми реализованных workflows, export provenance и Documentation Map. Career verification helpers теперь проверяют реального победителя confirm/reject, owner/Claim/evidence и требуют успешной финальной проверки; exceptions/incomplete output не становятся PASS. Product code/ADRs/schema, QA-001–QA-004 и RCV-01 не изменены.

Executed validation: isolated PostgreSQL race PASS (confirm winner); targeted backend 51 tests / 358 assertions PASS, включая 11 новых invariant datasets; Python evidence regression 8 tests PASS, wrapper regression 2 tests PASS; targeted Pint, PHPStan и Bash syntax PASS. Raw logs/source hashes/commands — [remediation-runs](../../docs/09-QA/evidence/full-2026-10-11/remediation-runs.json). Manifest/JSON/XML/accounting verification выполняется canonical `python3 scripts/qa_evidence.py`; подробности — [PR46 remediation](../../docs/09-QA/PR46-REMEDIATION.md). Независимый review и Git delivery относятся к последующему frozen candidate gate; отчёт хранится вне дерева, не подменён self-review.

Исторические 432 records / 539 weighted units сохраняют 525 PASS / 5 FAIL / 7 BLOCKED / 2 NOT_RUN. Новый isolated run не стирает historical verifier failure. `NEXT` и `BLOCKERS` не менялись; production accessibility, real provider/Preview и M2 gates остаются открыты.

## PR #46 QA audit integration — 2026-10-11

PR #45 MERGED в `stage`; интегрированный Recovery HEAD — `ecf65f7f20224544beabbd6df7aadb69d7cc352a`. Сохранённый functional QA относится к исходному baseline `f06a6dba058589823bf3d3c8b693cb625170bc4b`; Recovery update был документационным и не менял product code. Отчёты и evidence сохранены без повторного полного QA Audit.

QA ledger содержит **432 records / 539 weighted units**: **525 PASS, 5 FAIL, 7 BLOCKED, 2 NOT_RUN**. Bounded inventory: 67 функций/контролей, 59 проверены; 54 PASS, 4 FAIL, 7 BLOCKED, 2 NOT_RUN. Вердикт аудита — **CHANGES REQUIRED**. QA-001–QA-004 остаются открыты. Пятый historical FAIL — Career concurrency final verifier; его verification helpers исправлены отдельным bounded remediation ниже, historical log не переписан. Состав результатов и ограничения — в [audit report](../../docs/09-QA/FULL-FUNCTIONAL-AUDIT-2026-10-11.md), детализация — в [coverage matrix](../../docs/09-QA/FUNCTIONAL-COVERAGE-MATRIX.md), проверяемые хэши — в evidence manifest.

Это отдельный результат от RCV-01 Recovery: Recovery closeout сохранён как **DONE_VERIFIED в локальном scope**, а функциональный QA — **CHANGES REQUIRED**. Текущий `NEXT` остаётся `b01-current-manifest-integrity-reconciliation`; `BLOCKERS.md` и Recovery gate не изменялись этой QA-интеграцией. Product code, migrations и QA-001–QA-004 не менялись; remediation затрагивает test/verification helpers.

## Recovery snapshot — 2026-10-11

Следующие checkout/Git/runtime формулировки — датированный Recovery snapshot, не live PR #46 head.
Последняя проверка Recovery: **2026-10-11, Europe/Moscow**.
Основной audited checkout: `stage`, HEAD
`f06a6dba058589823bf3d3c8b693cb625170bc4b`, совпадает с live remote stage.
Последний блок: **IMPLEMENT / RCV-01, DONE_VERIFIED в локальном scope**.
Предыдущий recovery audit: DOCS; его dated evidence сохранено без переписывания.
[RCV-01 Execution Report](LOCAL_RUNTIME_ALIGNMENT_REPORT.md) — текущие runtime checks.

[Recovery Report](PROJECT_RECOVERY_REPORT.md) — подробные evidence, матрицы и
ограничения. [Git snapshot](recovery-2026-10-11/git-snapshot.json),
[validation](recovery-2026-10-11/validation.json).

## Текущая фаза

**M1 First Value → незакрытый Preview 0.1.** M1.4:
**IMPLEMENTED_UNVERIFIED по полной пользовательской приёмке**.
Сохранённые recommendations/short+standard cover drafts, Truth Guard,
edit/revalidation и approval integrated; real-user E2E/feedback не зафиксированы.
M2 planning/implementation и MVP 0.2 не начаты и остаются за Preview gate.
Никаких процентов готовности: acceptance checkpoints ещё не закрыты.

## Завершённые результаты и границы

- Foundation 00–07: исторически завершённые reviewed contracts/research/design;
  PR/merge и deliverables перечислены в Recovery Report. Не равны runtime
  реализации всего conceptual ERD/AI design.
- M0: integrated runnable stack, свежие Compose/health/Horizon PASS.
- M1.1/M1.2/M1.3: integrated slice scopes, historical acceptance/PG proof и
  свежие targeted SQLite tests. Реальный authenticated user flow здесь NOT_RUN.
- M1.4: PR #31 merged, preparation/draft lifecycle тестируется;
  полного Preview PASS нет.
- Observability и ChatGPT plan/MCP extension integrated через #36/#37/#42;
  local schema mismatch закрыт RCV-01; provider/account readiness не проверена.

## Проверки предыдущего recovery audit

- Frontend: **108 tests / 9 files PASS**, ESLint PASS (0 warnings), TypeScript PASS.
- Backend canonical targeted invocation: **210 tests / 1396 assertions**,
  **1 PostgreSQL-only skip**. SQLite in-memory; не PostgreSQL RLS/race proof.
- Первичный backend run с принудительным APP_ENV=testing: FAIL на CSRF419/201;
  canonical environment recheck и rerun PASS. Ошибка invocation сохранена.
- Compose example config PASS; public live/ready/root 200, unauthenticated me401;
  Horizon running. **Readiness не подтверждает полноту schema.**
- 27 migrations Ran, 1 Pending; `chatgpt_connections.oauth_generation` отсутствует.
  `ConnectionService` использует поле. Миграции здесь не выполнялись.
- Reference transformer 2/2 PASS; React derivation 260 checks PASS.
  **Canonical manifest FAIL**: один OFL.txt hash mismatch, текущие tokens/source
  hashes не менялись. Причина drift ещё не доказана.
- Exact stage SHA remote Quality SUCCESS
  ([run](https://github.com/filatelist-hitech/CVortex/actions/runs/38153409400)).
  Это remote CI, не новое local build/PG/manual/user evidence.

## RCV-01 — выполнено 2026-10-11

- Явно разрешён `cvortex_test` как текущая локальная development DB: owner override
  запрета test DB в исходном task. Docker Desktop Unix socket, project `cvortex`,
  APP_ENV=local, фактические roles `cvortex_app` / `cvortex` проверены.
- Private backup вне Git: 226093 bytes, modes 0700/0600; полное archive decoding,
  TOC и SHA-256 recheck PASS. Restore в БД не выполнялся.
- Только existing migration `2026_10_07_000001_add_oauth_generation_to_chatgpt_connections`
  применена с точным --path через migration role: **28/28 Ran**, batch 5;
  `oauth_generation bigint NOT NULL DEFAULT 0`, existing connections 2/2 generation=0.
- 35 data tables / 302 existing rows: counts и row fingerprints совпали;
  schema-only diff — одно additive поле. RLS/policies/ACL/старые constraints сохранены.
  Initial metadata verifier FAIL учёл новый NOT NULL constraint как drift;
  исправленная проверка и независимый schema diff PASS.
- ChatGptPlanTest: **16 tests / 92 assertions PASS**, SQLite :memory:, mocked HTTP,
  stray requests запрещены. Public live/ready/root 200, unauthenticated me401,
  Horizon running. Это не live ChatGPT usability/Preview acceptance.
- Production DB, product code, infrastructure/config и другие БД не изменялись;
  live AI/OAuth, commit/push/PR не выполнялись.

## Активные workstreams

| Workstream | Состояние | Открытый gate |
| --- | --- | --- |
| Core M1 / Preview | Code integrated, full acceptance unverified | Real-user E2E/feedback; provider readiness |
| B01/B02 design | B01 owner ACCEPTED; B02 + React port integrated #44 | QA-001 local PASS, independent review pending; mandatory manual production accessibility |
| ChatGPT/MCP | #42 merged; mocked tests PASS | Local schema aligned RCV-01; no fresh external account/provider proof |
| Recovery control | Report/state/dashboard/spec prepared locally | Отдельная authorized documentation delivery, если нужна integration |

B01 принят по **ADR-0025**; owner visual acceptance сохраняется.
Manual full matrix, K1 keyboard, Z1 native200% zoom, A1 audible screen reader
**NOT TESTED / OPEN**. Production release **BLOCKED** до закрытия существующей
`b01-manual-accessibility-release-gate`. Synthetic demo и 239 historical visual
comparisons не закрывают этот gate. Отдельный approved Universe stream не найден;
внешние материалы/Figma NOT_VERIFIED. B03 не разрешён.

## Git

PR #44 **MERGED** 2026-10-11; #42 MERGED, 59/59 threads resolved;
#43 CLOSED/superseded, не merged отдельно. Единственный OPEN #34 конфликтует,
governance FAIL; его старые env/egress изменения преимущественно есть в stage.
`main` не promoted к текущему продукту. Ни cleanup, ни новая Git delivery здесь
не выполнялись. Исходные 802 untracked files и dirty соседний
`CVortex-chatgpt-plan-chat` worktree сохранены.

Документационные изменения этого блока находятся **только** в
`/Users/filatelist/Documents/it/CVortex-project-recovery`, branch
`docs/project-recovery-2026-10-11`, от audited HEAD. Исходный checkout остаётся
на stage без tracked изменений. Нет commit/push/PR; это локальный recovery result.

## Одна главная следующая задача

**`b01-manual-accessibility-release-gate`** — следующая intended task после
независимого review/integration QA-001. Current integrity проверен локально;
manual state/viewport, keyboard, native200% zoom и human screen reader остаются
OPEN/NOT TESTED. Нужен named human evidence; owner visual acceptance не заменяет
его. Preview/provider/M2 gates остаются отдельными. STOP без перехода к gate.

## История

Прежний STATUS сохранён byte-for-byte в
[STATUS-HISTORY-2026-10-11](STATUS-HISTORY-2026-10-11.md).
Это dated evidence, **не канонический текущий статус или NEXT**.
Следующая сессия читает этот STATUS, NEXT, BLOCKERS и task, как уже предписано AGENTS.

**STOP:** QA-001 проверен локально и передаётся на независимый review; следующая task не выполнялась.
