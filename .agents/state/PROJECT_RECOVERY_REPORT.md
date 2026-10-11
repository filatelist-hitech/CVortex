---
title: CVortex — Project Recovery Report
status: audit-report
owner: project
created: 2026-10-11
updated: 2026-10-11
audit_timezone: Europe/Moscow
repository_commit: f06a6dba058589823bf3d3c8b693cb625170bc4b
execution_mode: docs
tags: [recovery, evidence, roadmap, mvp]
---

# CVortex — Project Recovery Report

## A. Executive Summary

CVortex — локальный Laravel/Next.js Job Search OS с работающим техническим стеком.
Основная продуктовая дорожка: M1 First Value, незакрытый checkpoint Preview 0.1.
M0, Access, Career и Vacancy интегрированы; есть сохранённые resume/cover drafts.
M1.4 имеет код и тесты, но реальная пользовательская приёмка не доказана.
MVP 0.2 ещё не достигнут: файлы резюме, application package и Employer Memory отсутствуют.
B01 «Орбита решения» принят владельцем; B02/React port merged в stage через PR #44.
Дизайн не меняет продуктовый milestone и не закрывает Preview.
Frontend: свежие 108 tests PASS; backend: 210 tests / 1396 assertions, один skip.
Локальная readiness = 200, однако одна миграция ChatGPT Pending, столбца нет.
B01 manifest check FAIL: сохранённый hash OFL.txt не совпадает с tracked файлом.
Production release запрещён до обязательного manual/accessibility gate ADR-0025.
Единственный открытый PR #34 устарел, конфликтует и не проходит governance.
Главная проблема управления — перемешанные текущие статусы, история и разные слои evidence.
Каноническое состояние восстановлено в .agents/state; прежние записи сохранены как история.
Следующий bounded шаг — локальное согласование схемы с интегрированным кодом.
Никакие миграции, live AI retries, commits, push, merge или новая функция здесь не выполнялись.

## B. Repository Snapshot

- Дата: **2026-10-11, Europe/Moscow**. SHA всех текущих выводов: `f06a6dba058589823bf3d3c8b693cb625170bc4b`.
- Исходный checkout: `/Users/filatelist/Documents/it/CVortex`; branch `stage`, upstream `origin/stage`.
- Staged/unstaged tracked changes на старте: **0/0**. Untracked: **802 файла**: архивные captures 53, personal-preview 10, application-port evidence 739. Не интерпретированы как новая продуктовая реализация, не изменены.
- Remote `stage` SHA проверен через `git ls-remote`; совпадает с HEAD. Development branch — **stage**: policy, реальные base PR и интеграционная история согласованы. GitHub default branch **main** — это release/default, не текущая разработка.
- `main...stage`: stage имеет 85 commits вне main, main — один merge commit вне stage. Это topology count, не число незавершённых задач; release promotion не подтверждён.
- Remote facts проверены через gh API: 43 PR, 36 MERGED, 6 CLOSED без merge, 1 OPEN. [Снимок](recovery-2026-10-11/git-snapshot.json).
- Для документационных правок создан отдельный checkout `/Users/filatelist/Documents/it/CVortex-project-recovery`, branch `docs/project-recovery-2026-10-11`, от этого HEAD. Исходный checkout не переключён. Это административное разделение разрешённых DOCS writes и read-only Git audit; commits/refs существующих веток не изменены. Base/будущий target — stage; PR здесь не создавался.

## Source of Truth и смысл статусов

Нормативный продукт: [PROJECT](../../PROJECT.md), [Roadmap](../../docs/01-Product/Roadmap.md), [PRD/FR/NFR](../../docs/01-Product/Phase-06-Product-Design.md), [Scope](../../docs/01-Product/Scope.md).
Архитектура: [ADR index](../../docs/03-ADR/INDEX.md), действующие accepted ADR; research — evidence для решений, не решение.
Реализация: исходники/migrations/tests + Git/PR + результаты ниже. [Conceptual ERD](../../docs/04-Data/Phase-06-Data-Design.md) прямо не является physical schema.
Исполнение: STATUS + NEXT + BLOCKERS + task specification. Dashboard — представление, а не второй authority.
Дизайн: ADR-0024/0025, canonical reference и Git-held tokens; Figma вспомогательна.

`DONE_VERIFIED` ниже относится только к указанному scope и слою доказательств. Историческая документальная фаза не обещает рабочий продукт. `IMPLEMENTED_UNVERIFIED` означает незакрытые acceptance criteria, даже если unit tests PASS. `BLOCKED` — конкретный gate/зависимость, а не любая будущая функция. Все live/manual/remote ограничения перечислены отдельно.

## C. Phase Status Matrix

| Phase | Goal | Status | Evidence | Missing | Blocker |
| --- | --- | --- | --- | --- | --- |
| 00 AI System Bootstrap | Agent contracts/policies/workflows | DONE_VERIFIED в историческом governance scope | PR #1 MERGED `dd992b8`; AGENTS, policies/templates, свежий DOCS preflight PASS | Не подтверждает product AI agents | — |
| 01 Project Knowledge | Индексируемое знание проекта | DONE_VERIFIED в историческом docs scope | PR #2 MERGED `d507aee`; PROJECT, Documentation-Map, продуктовые документы | Текущая актуальность требует recovery, выполнено здесь | — |
| 02 Research Plan | Ограниченный research inventory | DONE_VERIFIED как план | PR #4 MERGED `ceba59e`; research/RESEARCH-PLAN.md и RESEARCH-INDEX.md | План не доказывает выполнение research | — |
| 03 Technical Research | Синтез технических вопросов | DONE_VERIFIED как reviewed historical research | PR #6 MERGED `dc69116`; research/technical/README.md, SOURCES, DECISION-CANDIDATES; index REVIEWED | Изменчивые vendor facts не перепроверялись | Для новых связанных решений нужен freshness check |
| 04 Product/Integrations/Hiring | Reviewed research synthesis | DONE_VERIFIED как historical research | PR #5 MERGED `2eff1a4`; PHASE-04-SUMMARY.md, RESEARCH-INDEX.md | Не доказывает работающие adapters | — |
| 05 Architecture Freeze | Принятые границы/решения | DONE_VERIFIED как decision baseline | PR #7 MERGED `e3c023b`; Architecture-Baseline, ADR index, явные amendments 0019–0025 | Не равно реализации всех ADR | — |
| 06 Product/Data/AI/Security | Design contracts и FR/NFR | DONE_VERIFIED как reviewed design baseline | PR #13 MERGED `e74d5a7`; phase-06 task, PRD, conceptual ERD, AI/System Design, Threat-Model | Большие части целевой модели ещё planned | — |
| 07 Design Foundation | Tokens/design contracts | DONE_VERIFIED в исходном foundation scope | PR #14/#15 MERGED `8cfe9a7`/`0eb829c`; Design-Foundation, brand/tokens; поздние ADR-0024/0025 | Не означает принятую accessibility текущего React port | См. B01/B02 |
| M0 Runnable Core | Реально запущенный стек | DONE_VERIFIED для исходного M0; текущий runtime имеет drift | PR #21 MERGED `c194b10`; Compose PASS, HTTP live/ready 200, Horizon running | Полная актуальная схема поверх позднего расширения | Pending ChatGPT migration |
| M1.1 Access Core | Invite/register/login/isolation | DONE_VERIFIED в slice scope, не live user acceptance | PR #23 MERGED `c7c5d97`; AccessCoreTest в свежем PASS, CI SHA PASS | Live authenticated flow здесь NOT_RUN | Preview |
| M1.2 Career Core | PENDING → manual confirm → facts/Claims | DONE_VERIFIED в slice scope | PR #26 MERGED `7755e71`; CareerCoreTest в свежем PASS; [historical evidence](STATUS-HISTORY-2026-10-11.md) | Files/full CareerTrack намеренно M3+ | — |
| M1.3 Vacancy Core | Paste/snapshot/extraction/explainable match | DONE_VERIFIED в slice/test scope | PR #27/#28 MERGED `6a61925`/`baf9834`; VacancyCoreTest PASS, исторические изолированные PostgreSQL проверки в STATUS history | Новый real-provider successful workflow не подтверждён | Исторический RATE_LIMITED; актуальность не проверена |
| M1.4 Application Draft | Save/edit/revalidate/approve truthful drafts | IMPLEMENTED_UNVERIFIED по полной приёмке | PR #31/#32 MERGED `8e08f45`/`98f656b`; ApplicationDraftTest PASS; task Preview criteria | Real-user E2E + feedback | Preview gate |
| Preview 0.1 | Обязательный user-value checkpoint | BLOCKED | m1-4 task, раздел Preview completion criteria; PROJECT | Результаты всех real-user criteria, наблюдения/feedback | Нет приёмки; runtime/schema и provider readiness требуют согласования |
| M2 / MVP 0.2 | Downloadable traceable package + employer consistency | BLOCKED / implementation PLANNED | Roadmap; m2-application-package-planning.md | Company/Application lifecycle, resume/cover versions, documents, Employer Memory, credentials work | Preview не закрыт |
| M3 | Safe imports/source integrations | PLANNED | Roadmap, legacy mapping | File import/adapters/SSRF runtime | После доказанного package flow |
| M4 | Employer Journey | PLANNED | Roadmap | Conversations/interviews/deeper memory | После реальных applications |
| M5 | Outcome analytics | PLANNED | Roadmap | Recorded outcomes/analytics | M4 и реальные данные |
| M6 | Distribution/hardening/PWA | PLANNED | Roadmap | Backup/restore proof, deployment, retention, PWA | Устойчивое локальное использование |
| B01 reference | Approved «Орбита решения» visual baseline | Owner ACCEPTED; текущая integrity IMPLEMENTED_UNVERIFIED | ADR-0025, reference/REVIEW.md, MANUAL-ACCEPTANCE.md; fresh transformer 2/2 PASS | Manifest current hash drift; manual outcomes | Mandatory release gate OPEN |
| B02 + exact React port | Shell/navigation/canonical port | IMPLEMENTED_UNVERIFIED для release; integrated | PR #44 MERGED `f06a6db`; 108 frontend tests PASS; saved 239 synthetic comparisons | Manual production accessibility и authenticated E2E | ADR-0025 release gate |

Все указанные MERGED и merge SHA сверены с GitHub; [полный список](recovery-2026-10-11/git-snapshot.json). Для исторических фаз оценён результат их договорённого документального scope, не заново вся исследовательская корректность или все historical reviews. Phase 08–12 **superseded execution sequence**, материалы в tasks/legacy остаются requirement inventory. Они не назначают текущую фазу.

## D. Implementation Matrix

`Designed` указывает наличие нормативного источника; `Implemented` использует NOT_DESIGNED / DESIGNED / PARTIALLY_IMPLEMENTED / IMPLEMENTED / VERIFIED / BLOCKED. `Verified` всегда сообщает конкретный слой и пробелы.

| Component | Designed | Implemented | Verified | Evidence |
| --- | --- | --- | --- | --- |
| Compose/Nginx/local setup | Да, ADR-0005, M0 | VERIFIED в техническом scope | config PASS, HTTP 200 | compose.yaml; infra/nginx; Makefile; M0-Runtime.md |
| Laravel/PostgreSQL | Да, ADR-0001/0002 | IMPLEMENTED; late schema BLOCKED | backend tests SQLite PASS; ready PASS; одна Pending migration | apps/backend; database/migrations; validation results |
| Redis/Horizon | Да, ADR-0003 | IMPLEMENTED | Horizon running; readiness PASS; работа job с provider здесь NOT_RUN | compose.yaml; app/Jobs; config/horizon.php |
| Environment configuration | Да, runbook/AI config | PARTIALLY_IMPLEMENTED | example config PASS; live NODE_ENV=development, demo flag=1 | .env.example; compose.yaml; frontend demoAllowed; frontend-build-environment-follow-up task |
| Auth/authorization | Да, ADR-0007/0008/0019 | IMPLEMENTED | AccessCoreTest PASS; me unauthenticated 401; live login NOT_RUN | routes/api.php; AuthController, active-user; AccessCoreTest |
| Domain modules/API | Да, M1 slice data contracts | IMPLEMENTED в M1 scope | targeted backend tests PASS | Career/Vacancy/ApplicationPreparation services/controllers/models; versioned routes |
| Career Fact Base | Да, M1.2 data design | IMPLEMENTED | CareerCoreTest PASS; historical PostgreSQL proof | CareerFactService, CareerOwnerChain, TrustedCareerQuery; migrations 000003–000007 |
| FACT → CLAIM → GENERATED CONTENT | Да, ADR-0009 | IMPLEMENTED для drafts | ApplicationDraftTest/CareerCoreTest PASS | Claim/ClaimEvidence/ApplicationDraftItem; ApplicationTruthGuard |
| Truth Guard/Human Approval | Да, ADR-0009/PROJECT | IMPLEMENTED | fail-closed/edit/approval tests PASS; human flow NOT_RUN | TruthGuard, ApplicationTruthGuard, ApplicationPreparationService; approval events |
| Vacancies/snapshots | Да, M1.3 | IMPLEMENTED для paste | VacancyCoreTest PASS | VacancyIngestionService, VacancySnapshot, VacancyController |
| Requirement extraction | Да, M1.3/AI design | IMPLEMENTED | mocked structured-output tests PASS; fresh provider NOT_RUN | VacancyAnalysisService, VacancyRequirementValidator; runtime-ai/skills/vacancy-requirement-extraction |
| Matching | Да, seven explainable dimensions | IMPLEMENTED | VacancyCoreTest PASS; historical successful runtime отдельно | VacancyMatchingService, TrustedCareerQuery; live failure не подменяет test PASS |
| Applications | Да, M1.4/M2 | PARTIALLY_IMPLEMENTED | ApplicationDraftTest PASS для preparation, не application tracking | ApplicationPreparation; нет полноценной Application/StatusHistory |
| Resume Generation | Да, ADR-0016/M2 | PARTIALLY_IMPLEMENTED | Recommendations тестируются; file generation NOT_RUN/нет кода | ApplicationDraftItem; нет ResumeVersion/DOCX pipeline |
| Cover Letters | Да, M1.4/M2 | PARTIALLY_IMPLEMENTED | short/standard text draft tests PASS | application-draft-generation; нет document/version export |
| Employer Memory/Consistency | Да, M2/M4 | DESIGNED | NOT_RUN, runtime сервисов не найдено | Roadmap; conceptual ERD; нет EmployerMemory/EmployerConsistencyCheck |
| Next.js/pages/navigation/API | Да, B02/ADR-0004/0024 | IMPLEMENTED в текущем scope | 108 tests + lint/typecheck PASS | src/app/page.tsx, career/page.tsx, register/page.tsx, diagnostics/page.tsx; workspace-shell; application/use-workspace.tsx |
| Responsive/PWA | Да, foundation/M6 | PARTIALLY_IMPLEMENTED | synthetic responsive historical proof; manual NOT_RUN | application-port comparison-summary; installability/service worker не найдены в app tree |
| Provider abstraction | Да, ADR-0010 | IMPLEMENTED | mocked provider/ChatGPT tests; no live egress | AI/Contracts/LlmProvider, StreamingProvider; OpenAiResponsesProvider, OpenAiChatGptPlanProvider |
| ModelPolicy | Да, ADR-0011 | IMPLEMENTED | config resolver tests в CI; account availability fresh NOT_RUN | AI/Data/ModelPolicy, ModelPolicyResolver; config/ai.php |
| Model Router | Да, AI design | PARTIALLY_IMPLEMENTED | deterministic resolver; generic risk/complexity routing не найден | ModelPolicyResolver — не полный обещанный ModelRouter |
| Skills | Да, ADR-0018 | IMPLEMENTED | registry/schemas/fixture tests в backend suite | runtime-ai/README, skills/*/v*/manifest + prompts/schemas; RuntimeSkillRegistry |
| Product Agents | Да, AI design | DESIGNED | общего agent runtime нет | Agent в целевой архитектуре; .agents — development tooling |
| Workflows | Да, AI design | PARTIALLY_IMPLEMENTED | service/job state machines tested; generic engine отсутствует | CareerExtractionService, VacancyAnalysisService, ApplicationPreparationService |
| Prompt Registry | Да, AI design | PARTIALLY_IMPLEMENTED | versioned Skill prompt assets; отдельного центрального registry нет | RuntimeSkillRegistry + runtime-ai/skills; не выдавать assets за полный registry |
| Context Builder | Да, AI design | IMPLEMENTED для draft/chat | targeted tests + exact-SHA CI | ApplicationContextBuilder, VacancyChatContextBuilder; VacancyPlanChatTest исторически/CI |
| Structured outputs | Да, schemas/validators | IMPLEMENTED | adversarial fixture tests PASS | LlmProvider; VacancyRequirementValidator, CareerSemanticValidator; runtime output schemas |
| LLM accounting | Да, AI design | PARTIALLY_IMPLEMENTED | safe run metadata/tests; cost analytics не реализована | LlmRun/VacancyLlmRun/ApplicationLlmRun; policy estimated costs; OAuth cost nullable |
| MCP/ChatGPT integration | Да, ADR-0021 + 0023 | IMPLEMENTED; live schema BLOCKED | PR #42 merged; ChatGptPlanTest PASS; missing oauth_generation | MCP two reads + controlled analysis-draft save; ChatGptConnection/ConnectionService |
| Observability | Да, ADR-0022 | IMPLEMENTED | 53 frontend diagnostics tests + exact-SHA CI; authenticated live NOT_RUN | app/Diagnostics; DiagnosticsController; correlated safe logs |
| Storage/documents/imports | Да, ADR-0015/0016/M2/M3 | PARTIALLY_IMPLEMENTED | private storage foundation; rendering/import NOT_RUN | private_storage; нет generated document pipeline |

API inventory: health; auth/register/login/logout/me; career sources/extractions/facts/claims; vacancies/analysis/preparations/draft approval; diagnostics; ChatGPT connections/chat/analysis drafts. Source: routes/api.php. Машиночитаемые OpenAPI найдены для diagnostics и chatgpt-plan; полный aggregate OpenAPI для всех M1 endpoints отсутствует. Наличие YAML не доказывает contract test для каждого endpoint.

## E. Git / PR Status

### Активные/сомнительные остатки

| Branch / head | Назначение, task | Divergence behind/ahead stage | PR/integration | Остаток и действие |
| --- | --- | --- | --- | --- |
| stage `f06a6db` | Integration, B02 + core | 0/0 | #44 merge SHA; live remote совпадает | База аудита; не release |
| main `11f7ccf` | Stable/default | 85/1 | Нет текущего release promotion | Очень отстал от продукта; не автоматически merge |
| feature/b02-global-shell-navigation `b977180` | B02 + port, reference docs | 1/10 | #44 MERGED; три PR checks SUCCESS | Ahead от squash не является незавершённой работой; acceptance gates открыты |
| docs/ux-redesign-concepts `936e95b` | Design reconciliation | 1/1 | #43 CLOSED, **не merged**; ancestor head #44 | Superseded delivery; включён через #44, не отдельный merge #43 |
| chore/application-workspace-reference-foundation `89f30e3` | B01 historical research/reference | 1/2 | PR нет; ancestry в consolidated head #44 | Исторический checkpoint; не current port authority |
| feature/chatgpt-local-mcp-integration `6e4d53a` | chatgpt-plan-chat | 2/53 | #42 MERGED `a62ff08`, 59/59 threads resolved | Snapshot показывает squash topology; поздняя миграция locally Pending |
| chore/m1.4-preview-env `9e8f259` | Старый Preview egress/config proposal | 10/1 | #34 OPEN, CONFLICTING; no assignees/labels/milestone; governance FAIL | Три env keys и egress уже есть в stage другим путём; старый network name/provider docs и 1444-line task отличаются. Decision review перед отдельным закрытием, не merge |
| docs/chatgpt-plan-chat `eb56551` | Старый tunnel/setup checkpoint | 2/9 | Нет PR; отдельный worktree | Сравнение tree с stage показывает более старую backend реализацию. Dirty NEXT/STATUS + 2 untracked: **сохранить**, не считать текущим планом |
| fix/chatgpt-plugin-layout `bff5f13` | Local plugin packaging | 2/6 | Нет PR | В diff metadata/skills против stage; disposition UNKNOWN, не объявлять нужной integration по имени |
| docs/project-recovery-2026-10-11 `f06a6db` | Этот DOCS recovery | Новая ветка без commit delta | PR нет | Только разрешённые локальные docs changes; требует отдельной Git delivery authorization |

Остальные локальные ветки и 24 remote heads перечислены в [Git snapshot](recovery-2026-10-11/git-snapshot.json): purpose из commit/PR title, SHA, behind/ahead, PR mapping, unique logs, merge-base diff. M0/M1/auth/career/vacancy/application/error-center/MCP/docs branches преимущественно historical squash-delivery remnants с MERGED PR; их unique commits нельзя повторно интегрировать по ahead count. Backup branch `backup/stage-before-phase-03` — историческая страховка, не roadmap. Remote-only phase-08/09 refs соответствуют CLOSED #16/#17 и сохранённым legacy specs.

### Что не потеряно

- 802 исходных untracked files сохранены; исторические diagnostic FAIL JSON не переписаны в PASS.
- Соседний docs/chatgpt-plan-chat worktree с tracked NEXT/STATUS edits не менялся.
- Staged product changes не было. Доступные прочие worktrees на старте чистые по tracked status.
- Проверены open #34 reviews: 0 threads; #44: 0 threads/reviews; #42: 59 resolved. Это не утверждение о ручном review каждой реализации и не аудит всех historical review conversations.
- Live ruleset активен для main/stage: no deletion/force push, PR + thread resolution; approving review count 0; required checks **Roadmap metadata/PR contract**, Quality не включён в required_status_checks. Зелёный Quality — отдельное evidence, не обещание enforced gate.

## F. Architecture Consistency

| Severity | Location / conflicting statements | Authoritative source и consequence | Recommended action / disposition |
| --- | --- | --- | --- |
| HIGH | STATUS: #44 OPEN/pending CI; GitHub: MERGED и SUCCESS | live PR #44 + stage SHA; ошибка способна вызвать повторную delivery | Исправлено текущим STATUS; история сохранена |
| HIGH | readiness 200 vs Pending migration и отсутствующий oauth_generation, использованный ConnectionService | database schema + integrated migration/service; health не проверяет полноту schema | Bounded local schema alignment — NEXT; production/DB changes в этом аудите запрещены |
| HIGH | B01 delivery-validation исторический canonical check PASS vs fresh manifest FAIL | текущие байты/manifest: один OFL.txt hash mismatch | Отдельное integrity reconciliation; не переписывать старые reports/goldens |
| MEDIUM | m1-3 task remediation-awaiting-review и review task ready vs #27/#28 merged/reconciled historical PASS | PR/implementation/historical review + fresh tests; документальный lifecycle устарел | Обновлены frontmatter/примечания; historical checkboxes не выданы за новые результаты |
| MEDIUM | m1-4 task ready vs #31 merged, Preview missing | task full acceptance + PROJECT; integrated code ≠ full PASS | status implemented-preview-unverified |
| MEDIUM | tasks README: B02 awaiting owner/no delivery vs owner acceptance/#44 | ADR-0025, acceptance README, PR44 | Исправлен индекс; manual gate сохранён |
| MEDIUM | Dated CURRENT audit: DB readiness 503, PRD не найден, один OpenAPI vs текущие ready200, Phase-06 PRD, два YAML | свежие probes + реально существующие файлы | Старый аудит сохранён как dated report; existing entry page стала dashboard |
| MEDIUM | Target ERD/AI design упоминает Company/Application/ModelRouter/registry как компоненты vs отсутствующий/частичный runtime | Accepted design для норматива; migrations/code для факта | Матрица отделяет planned; ADR не отменять |
| MEDIUM | scoped backend/frontend AGENTS ограничивают M0, хотя root + текущие explicit tasks разрешили M1/B02 | Root instruction priority и task scope; это historical bootstrap wording, не доказательство запрета integrated features | Отдельный bounded scoped-contract sync; здесь изменение этих файлов не разрешено |
| LOW | Product Design MVP direction включает interviews/outcomes, Roadmap MVP 0.2 уже заканчивается package/consistency | Roadmap авторитетен для execution checkpoint; PRD для общего направления | Dashboard явно определяет MVP 0.2, без нового бизнес-решения |
| LOW | NEXT requested prose vs check-agent-contract.sh читает весь файл как ID через tr -d whitespace | Existing machine contract; product-code/scripts здесь не менять | NEXT сохранён как один ID; цель/deps/AC/spec и 2 ориентира доступны в STATUS/task/dashboard. Расширение формата — отдельная задача, не скрытый regression |

ADR-0013 superseded ADR-0024; ADR-0020 superseded ADR-0021, который amended ADR-0023 для одного controlled draft save. ADR-0025 amends B01 closure timing, не отменяет accessibility. Факт наличия MCP write после ADR-0023 не является нарушением старого read-only ADR. Независимых новых конфликтов accepted ADR, требующих отмены решений, не подтверждено.

Ссылки новых/изменённых документов проверяются targeted validation ниже. Полный recursive link audit historical docs не выполнялся; broken historical links/localhost previews не объявлены массово исправленными.

## G. Design / Universe Status

Утверждённое направление: Concept B/Application Workspace, canonical **«Орбита решения»**, [ADR-0024](../../docs/03-ADR/ADR-0024-git-design-reference-workflow.md) + [ADR-0025](../../docs/03-ADR/ADR-0025-b01-reference-acceptance-release-gate.md). Machine authority: `brand/tokens/cvortex.tokens.json`. Active reference: `docs/07-Design/reference`; owner decision в REVIEW/MANUAL-ACCEPTANCE. B02/global shell и canonical React port реально находятся в apps/frontend и integrated PR44.

Исторические conceptual candidates/research и personal-preview — не backend/domain features. Personal-preview excluded из public delivery; его локальные файлы не использованы как career facts и не копировались в report. Figma-Handoff — historical/superseded, live Figma **NOT_VERIFIED**. Ни Figma, ни бренд не изменялись.

Сохранённая port validation: 97 state/viewport cases + 142 scroll pairs, 239 comparisons, 260 derivation checks; ephemeral synthetic API. Свежая derivation React check: 260 PASS. Но отдельный canonical manifest FAIL. Owner visual acceptance не заменяет full manual state/viewport, keyboard K1, native 200% zoom Z1, audible screen-reader A1 и real Laravel E2E.

Целевой поиск Universe/style bible/storyboard/scene/camera timeline в индексах, текущих design docs и task specs **не выявил отдельного утверждённого Universe task/acceptance stream**. Найден design workstream B01/B02; физическая анимация reference/port не доказывает утверждённую Universe-систему. Если такие материалы существуют вне Git/внешнем Figma, состояние UNKNOWN. Core milestone остаётся M1/Preview.

## H. Validation Results

Таблица содержит только реально выполненные команды этой сессии. Финальные document/scope checks: [final-document-checks.json](recovery-2026-10-11/final-document-checks.json). Проверены 13 текущих Markdown-файлов, 86 локальных ссылок, структура отчёта/spec, byte identity двух архивов и SHA-256 всех 802 исходных untracked files. Полные безопасные summaries: [validation.json](recovery-2026-10-11/validation.json). `exec` команды выполнялись на исходном checkout с bind-mounted тем же source SHA; DOCS checks — на recovery worktree. Зависимости не устанавливались.

| Command | Execution status | Result / relevant error | Affected component |
| --- | --- | --- | --- |
| git status/diff/log/for-each-ref/worktree list; git ls-remote --heads origin | PASS | tracked clean; 802 untracked; remote stage = HEAD; branches recorded | Git |
| gh pr list/view; API reviews/ruleset; gh run view 38153409400 | PASS | 36 merged/6 closed/1 open; exact stage Quality SUCCESS | Remote audit |
| bash scripts/check-agent-contract.sh docs --write --user-override | PASS | recovery branch from stage, DOCS override | Write preflight |
| docker compose --env-file .env.example config --quiet | PASS | exit 0; конфигурация не выводилась | Infrastructure |
| docker compose ps; php artisan horizon:status | PASS | 7 running services, 5 с healthy; Horizon running | Runtime metadata |
| curl /api/v1/health/live, /ready, /, /api/v1/me | PASS | 200/200/200/401; ready body status=ready | Public health/auth boundary |
| docker compose exec -T backend php artisan migrate:status --no-ansi | PASS как inspection | 27 Ran + 1 Pending oauth_generation; schema alignment **FAIL** | Local DB |
| php artisan tinker --execute='dump(Schema::hasColumn("chatgpt_connections", "oauth_generation"));' через fully-qualified facade | PASS как inspection | false; никаких table data/credentials не читалось | Local schema |
| docker compose exec -T frontend npm test | PASS | 108 tests / 9 files | Frontend synthetic/component |
| docker compose exec -T frontend npm run lint | PASS | exit 0, warnings 0 | Frontend |
| docker compose exec -T frontend npm run typecheck | PASS | exit 0 | Frontend |
| node --test docs/07-Design/reference/build-reference.test.cjs | PASS | 2 tests | Token transformer |
| node apps/frontend/scripts/check-application-reference.cjs | PASS | 260 checks / 252 rules | React derivation |
| node docs/07-Design/reference/build-reference.cjs --check | FAIL | manifest source/hash drift; только assets/OFL.txt hash отличается; tokens и другие source/asset hashes совпали | Canonical B01 integrity |
| targeted backend PHPUnit, SQLite :memory:, принудительный APP_ENV=testing | FAIL | 210 tests/1394 assertions/1 failure/1 skip; expected CSRF419, actual201 | Test invocation; Laravel testing CSRF bypass |
| тот же CSRF test без APP_ENV override | PASS | 1 test / 3 assertions | Narrow diagnostic recheck |
| тот же targeted backend filter без APP_ENV override, как Makefile environment | PASS | 210 tests/1396 assertions/1 PostgreSQL-only skip | Access/Career/Vacancy/Application/ChatGPT; **SQLite**, не PG proof |
| git diff --check | PASS | exit 0 на исходном checkout; финальный recovery diff также проверяется | Whitespace |

Backend filter: `AccessCoreTest|CareerCoreTest|ApplicationDraftTest|VacancyCoreTest|ChatGptPlanTest`. DB_CONNECTION=sqlite, DB_DATABASE=:memory:, DB_URL empty; cache=array, queue=sync, session=array, mail=array; --do-not-cache-result. Runtime database не очищалась. Полный literal command — в validation.json. Первичный FAIL не приписан product regression: canonical rerun с правильным environment прошёл. Synthetic provider fixtures исключают live transmission.

Not executed (не выдаются за PASS): свежий production build, full local backend suite/Pint/Larastan, PostgreSQL RLS/race harnesses, real authenticated browser flow, manual state/viewport/zoom/VoiceOver, external Figma, live provider quota/inference. Exact-SHA remote Quality выполняет make lint/test/build; это **remote CI** evidence. Существующие PostgreSQL harnesses создают/мигрируют/удаляют disposable DB; по scope этой сессии их не запускали. Backend phpunit.xml/Makefile используют SQLite. CI не доказывает PostgreSQL RLS/races. Quality job есть, но только governance checks required live ruleset.

Сохранённые delivery-validation/closure reports исторические: не выдаются за новые browser runs. Test/config/migration inventories сверены по files, Makefile, phpunit.xml, workflows. Свежесть старых PG/live/manual результатов ограничена SHA/датой report; runtime/schema drift требует новой целевой проверки.

## I. Risks

**CRITICAL:** подтверждённого текущего critical exploit/data loss не найдено в bounded inspection. Это не полный penetration audit.

| Priority | Risk / evidence | Consequence | Bounded action |
| --- | --- | --- | --- |
| HIGH | Local oauth_generation отсутствует, ConnectionService increment/save его использует | Нельзя считать ChatGPT connect/disconnect lifecycle runtime-ready; возможен SQL failure и неполный revoke concurrency boundary | RCV-01 local alignment; нет live retries до проверки |
| HIGH | Manual release gate OPEN, MANUAL-ACCEPTANCE NOT TESTED | Production accessibility не принята | Existing b01-manual-accessibility-release-gate |
| HIGH | Нет реального Preview acceptance; historical provider RATE_LIMITED | M1.4/M2 readiness не доказана | Operator quota decision + authorized real Preview verification |
| MEDIUM | Canonical OFL.txt manifest hash mismatch | Текущий integrity PASS недостоверен | RCV-02 manifest reconciliation; нельзя автоматически переписать baseline здесь |
| MEDIUM | Career ownership сервисный/FK boundary vs Vacancy/Application FORCE RLS | Разная глубина defense-in-depth, direct DB access хуже защищён | Отдельный read-only DB isolation review; exploit не доказан |
| MEDIUM | SQLite/CI не проверяет PG-only boundaries | Locks/RLS runtime assumptions остаются historical/unverified на текущем SHA | Disposable PG verification только отдельным разрешённым scope |
| MEDIUM | #34 conflict/governance FAIL; dirty соседний worktree | Риск повторной integration/потери локальных материалов | Read-only disposition, owner-authorized Git cleanup отдельно |
| MEDIUM | Development build /_global-error historical failure | production-mode build PASS не доказывает dev-mode build | Existing frontend-build-environment-follow-up; не blocker production-mode Preview |
| LOW | Scoped AGENTS historical M0; conceptual planned systems выглядят implemented | Будущая сессия может неверно определить authority | Contract/index sync; текущий report даёт явное разделение |

Проверенные invariants: trusted query допускает только CONFIRMED и TruthGuard PASS; CareerOwnerChain проверяет owner/source excerpt/reviewer; draft approval требует provenance; route guards auth:sanctum/active-user, db-owner-context для Vacancy/Application/chat; domain services используют LlmProvider/StreamingProvider, не SDK; demoAllowed ограничен loopback + env + demo=b01, component tests проверяют отсутствие writes. Автоподтверждение фактов и автоматическая отправка откликов в осмотренных flows не найдены. Prompt-injection regression fixtures выполнялись. Secret exposure или доказанный cross-user exploit не подтверждены; exhaustive secret/history scan и новые PG attack tests не выполнялись. Credentials/raw user payload не выводились и не включены в evidence.

## J. Missing Deliverables

1. Real-user Preview 0.1 E2E/feedback по существующей m1-4 task checklist.
2. Полное человеческое evidence manual state/viewport/K1/Z1/A1 reference **и** applicable production scope.
3. Применённая уже существующая migration oauth_generation в текущей local DB и post-upgrade validation. Это runtime missing deliverable, новый migration file не требуется.
4. Актуальный подтверждённый canonical manifest hash; файл существует, integrity результата не хватает.
5. M2 package implementation/design derived from Preview: Company/Application/status history, ResumeVersion/CoverLetter versions, ClaimUsage, employer consistency, DOCX/PDF/private download/READY_TO_APPLY.
6. M3–M6 runtime deliverables по Roadmap, без преждевременной детализации.
7. Full M1 aggregate OpenAPI и общий PG CI/eval execution — future quality gap, не автоматический milestone blocker.

**Не отсутствуют:** PRD/FR/NFR (Phase-06-Product-Design.md), ERD (conceptual), research index, design tokens, accepted ADR и task index. Новые дубли этих материалов не нужны.

## K. Recommended Recovery Actions

Порядок: RECOVER → STABILIZE → COMPLETE M1 → VERIFY → REVIEW → INTEGRATE → CONTINUE. Никаких сроков или процентов: release, Preview и MVP имеют разные completion criteria.

| Order / ID | Title / goal | Priority | Dependencies | Scope | Acceptance criteria |
| --- | --- | --- | --- | --- | --- |
| 0 RCV-00 | Восстановить project control | P0 | Проверенный HEAD/PR/state | Этот DOCS block | Report/state/dashboard/одна next spec; checks и limitations сохранены; product untouched |
| 1 RCV-01 | Согласовать local schema с merged кодом | P0 | Явный запуск [next task](../tasks/recovery-local-runtime-alignment.md), сохранённая data-bearing DB | Только existing pending migration, protected backup, targeted post-check; no AI | Точный DB/runtime identity; backup verified privately; oauth_generation present/Ran; narrow tests PASS; health PASS; данные сохранены |
| 2 RCV-02 | Восстановить canonical reference integrity | P1 | Tracked source/license review; RCV-01 не меняет design | Только current manifest/license provenance, отдельный bounded docs/asset task | Причина hash drift установлена; current --check PASS; historical reports сохранены; tokens/visual/product unchanged |
| 3 existing B01 gate | Закрыть accessibility release gate | P1 | RCV-02 current hashes; человек/AT/browser | Existing b01-manual-accessibility-release-gate; reference + shipped UI | Все mandatory rows actual PASS с именем reviewer/OS/AT/zoom; FAIL не замаскированы; owner visual acceptance не заменяет evidence |
| 4 RCV-03 | Диспозиция старого #34/локальных веток | P0 control housekeeping, после runtime | Git read-only audit; явное разрешение внешних mutations | Review уже integrated env/egress и уникальной history; no remerge | Decision sheet для owner; пользовательские правки сохранены; close/delete только отдельно authorized |
| 5 RCV-04 | Operator provider readiness | P1 | RCV-01; доступ владельца к billing/quota | No user-content transmission для read-only diagnosis | Причина historical 429 подтверждена либо UNKNOWN; явное разрешение конкретного payload/retry перед inference |
| 6 M1.4 Preview verification | Завершить current product milestone | P1 | Local/schema/provider readiness; existing m1-4 criteria | Real user full draft flow + feedback; no M2 features | Каждый критерий PASS/FAIL; approval/provenance/isolation; feedback; no autosend |
| 7 M2 planning | Один bounded package plan | P2 | Preview accepted; authorization reassessed | Existing m2-application-package-planning.md | Один реалистичный next implementation spec из feedback; no premature features |
| 8 M2 package / MVP0.2 | Traceable downloadable package | P2 | M2 plan + tests/review/integration decision | Roadmap M2 only | Download/manual use/consistency/approval acceptance; independent QA |
| 9 contract/quality follow-ups | Scoped instruction sync, PG verification, dev build | P2 | Separate authorized scopes | Existing env task; bounded reviews, no mass rewrite | No contradictions; reproducible PG evidence; actual env outcome |
| 10 M3–M6 | Imports → Journey → Analytics → Distribution | P3 | Prior checkpoints и real-use evidence | Roadmap only | Milestone-specific criteria, just-in-time specs |

**Одна NEXT задача: RCV-01 / recovery-local-runtime-alignment.** Это исправляет наблюдаемый runtime/schema mismatch в уже integrated capability до пользовательского verification; не выбирается по номеру и не начинает M2. Manual gate не отменён и остаётся release blocker. RCV-02 и existing manual gate — два следующих ориентира. Недостающие package features сами по себе не blockers этой recovery task.

NEXT.md сохранён в машинном формате одного task ID: существующий check-agent-contract читает весь файл через `tr -d '[:space:]'`. Поэтому цель, dependencies, acceptance criteria, spec link и два ориентира представлены в каноническом STATUS/task specification и dashboard. Добавление prose в NEXT без изменения script сломало бы execution preflight; product/scripts changes запрещены этим scope. Это явное ограничение формата, а не второй next pointer.

## L. Evidence / Uncertainty

- [Git/PR snapshot](recovery-2026-10-11/git-snapshot.json): all available local refs, remote heads, 43 PR, divergent counts, checks and selected review threads. Remote facts live на дату, не будущая гарантия.
- [Validation](recovery-2026-10-11/validation.json): literal commands, result/status/errors, layers. [Manifest drift](recovery-2026-10-11/manifest-drift.json): only OFL.txt mismatch, SHA-256 pairs.
- [Pre-recovery STATUS history](STATUS-HISTORY-2026-10-11.md): неизменённая хронология прежних runs/owner decisions. Не текущий execution state.
- [Предыдущий датированный аудит](../../docs/00-Home/PROJECT-STATE-AUDIT-2026-10-03.md): сохранён byte-for-byte; прежняя readiness503/phase claims не подменяют live snapshot.
- Live authenticated Laravel E2E, новые PG RLS/races, новые provider calls/quota, personal user acceptance и live Figma: **NOT_VERIFIED**, потому что требуются другой scope/consent/human evidence. Private raw content не извлекался.
- Причина OFL.txt bytes mismatch не установлена; нормализация line endings не доказана. No auto --write performed.
- Новая миграция не запускалась. Health PASS не означает что connect/disconnect уже исправны.
- Для внешних Universe материалов, local plugin branch business intent и всех historical reviews доказательств недостаточно: UNKNOWN/NOT_VERIFIED.
- Repository continuity уже задана root AGENTS: PROJECT/STATUS/NEXT/BLOCKERS/task + relevant ADR. AGENTS не требует изменения.
- Recovery documents пока локальные в отдельном worktree; основной stage остался без правок. Next session следует открыть recovery checkout либо отдельно интегрировать docs после разрешённой Git delivery. Автоматического commit/PR нет.

**STOP:** next task не выполнена; no runtime migrations, code/schema/infrastructure edits, dependency installs, commits, PR creation/closure, merge, push, branch deletion, stash/reset/clean, Figma edits, employer action или AI payload transmission.

## M. Closeout after RCV-01 — 2026-10-11

Sections A–L above record the initial Project Recovery audit, before RCV-01 ran.
Their statements that the migration was pending, local alignment was NEXT, and no
migration had run describe that earlier audit window. They are retained as dated
evidence and do not describe the current runtime state.

RCV-01 is now **DONE_VERIFIED in its local scope**. The owner-authorized existing
migration was applied to `cvortex_test` after private backup verification; 28/28
migrations are Ran, the additive column/default is present, and 35 data tables /
302 rows were preserved. Targeted mocked tests and technical health checks passed.
See the [RCV-01 execution report](LOCAL_RUNTIME_ALIGNMENT_REPORT.md) and its
[validation record](runtime-alignment-2026-10-11/validation.json). No production
database, product code, credentials, provider call, or external delivery changed.

Current execution authority is `.agents/state/STATUS.md`, `NEXT.md`,
`BLOCKERS.md`, and the task named by NEXT. NEXT now points to
`b01-current-manifest-integrity-reconciliation`; its bounded specification and
owner launch are still required. The manual accessibility gate and Preview 0.1
acceptance remain open. The original audit's RCV-01 recommendation is superseded
by this closeout; all other historical findings retain their original scope/date.

Recovery documents and evidence remain local on
`docs/project-recovery-2026-10-11`, based on the then-current `stage` SHA
`f06a6dba058589823bf3d3c8b693cb625170bc4b`. This closeout prepares review only;
no commit, push, PR, merge, or worktree removal occurred.
