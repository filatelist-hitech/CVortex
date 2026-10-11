---
title: RCV-01 — Local runtime alignment execution report
status: completed-local-verified
owner: project
created: 2026-10-11
updated: 2026-10-11
repository_commit: f06a6dba058589823bf3d3c8b693cb625170bc4b
execution_mode: implementation
tags: [recovery, runtime, local, evidence]
---

# RCV-01 — проверенный локальный результат

**DONE_VERIFIED в scope RCV-01.** Применена одна существующая миграция
`2026_10_07_000001_add_oauth_generation_to_chatgpt_connections` к текущей локальной
development DB `cvortex_test`. 28/28 migrations Ran; новое поле
`oauth_generation bigint NOT NULL DEFAULT '0'::bigint`, batch 5.
Данные сохранены; mocked tests и технические health checks PASS.
Live ChatGPT usability, PostgreSQL races и Preview acceptance этим не доказаны.

## Authorization и target

- Владелец разрешил запуск task, только существующую local development migration
  после private backup, один агент, без live AI/commit/push/PR; после результата STOP.
- Preflight выявил `cvortex_test`: исходная spec запрещает test DB, а прежние записи
  описывают её как отдельную тестовую БД. До решения владельца mutation не выполнялась.
- Владелец явно ответил: «Разрешаю RCV-01 для cvortex_test как текущей локальной
  development DB после private backup». Этот target-specific override снимает
  исходное test-DB ограничение только для данной базы и данной миграции.
- Docker context `desktop-linux`, endpoint — локальный Docker Desktop Unix socket.
  Compose project `cvortex`; source checkout `/Users/filatelist/Documents/it/CVortex`.
  Существующие контейнеры backend/Horizon/scheduler имеют APP_ENV=local,
  DB_HOST=postgres, DB_DATABASE=cvortex_test, runtime role=cvortex_app.
  Фактическое Laravel PDO подключение подтверждено SQL, не только env.
- PostgreSQL container `cvortex-postgres-1`, persistent volume `cvortex_postgres_data`,
  PostgreSQL 18.6; DB port не опубликован. Nginx — только 127.0.0.1:8080.
  Полные container IDs записаны в [validation](runtime-alignment-2026-10-11/validation.json).
- Migration service подключается к той же DB через role `cvortex`;
  runtime role `cvortex_app` остаётся NOSUPERUSER/NOBYPASSRLS/NOCREATEDB.
  `make migrate` целиком не запускался: повторное provisioning ролей не требовалось.
- Recovery checkout: branch `docs/project-recovery-2026-10-11`, HEAD совпал с
  live remote `stage` и local `stage`: `f06a6dba058589823bf3d3c8b693cb625170bc4b`.
  User-selected existing branch сохранена вместе с dirty recovery documents.
  Preflight implementation --write PASS, без NEXT override при запуске.
- Runtime source и recovery source migration/ConnectionService/ChatGptPlanTest/
  compose.yaml byte-identical. Migration SHA-256:
  `e7a243efc7f701fa0b27787251a0fff898f3fd8055105b5cece067dd847f1570`.
  Identity и hash повторно проверены из migration container до применения.

## Private backup и сохранность

Backup создан до mutation, вне Git, в private owner-owned directory.
Safe identifier `rcv-01-20261011-runtime-alignment`; custom archive 226093 bytes;
directory 0700, file 0600. Полное decoding через pg_restore в `/dev/null` и TOC
с expected tables PASS; SHA-256 после migration совпал. Физическое восстановление
в БД **NOT_RUN**: проверена читаемость архива, не restore drill.
Точный private path и содержимое dump не включены в документы.
[Safe backup metadata](runtime-alignment-2026-10-11/backup-metadata.json).

До/после migration: **35 data tables / 302 rows**, count и sorted row fingerprints
совпали во всех таблицах. Исключены только таблица migrations и новое поле
chatgpt_connections.oauth_generation. Raw rows/token values не выводились.
Контрольные counts: users=2, career_facts=3, vacancies=8,
application_preparations=2, chatgpt_connections=2; обе connections generation=0,
NULL=0. Migration history выросла ровно 27→28; другие pending migrations отсутствуют.

Первичный metadata verifier **FAIL**: он исключал новое поле, но учитывал новый
NOT NULL constraint PostgreSQL 18. Это ошибка области сравнения verifier.
Schema-only diff показал **ровно одну добавленную строку**. После исключения только
нового column-specific NOT NULL constraint контроль прежних columns, constraints,
policies, table owners/ACL/RLS совпал с before digest. Ничего не откатывалось.
[Schema diff](runtime-alignment-2026-10-11/schema-diff.patch),
[preservation checks](runtime-alignment-2026-10-11/preservation-result.json),
[initial read-only control SQL](runtime-alignment-2026-10-11/data-control.sql).

## Фактические команды и результаты

Literal commands, SQL inputs, safe identity и статусы по слоям:
[validation.json](runtime-alignment-2026-10-11/validation.json).
У subprocess backup output перенаправлен непосредственно в private file;
restore verifier получает этот file через stdin. Полный dump не печатался.

Применение **PASS**, единственная migration через существующий privileged service:

```sh
docker compose --project-directory /Users/filatelist/Documents/it/CVortex --env-file /Users/filatelist/Documents/it/CVortex/.env --profile tools run --rm --no-deps migration php artisan migrate --force --path=database/migrations/2026_10_07_000001_add_oauth_generation_to_chatgpt_connections.php --no-ansi
```

Targeted validation **PASS — 16 tests / 92 assertions**, SQLite :memory: и mocked HTTP:

```sh
docker exec -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= -e CACHE_STORE=array -e QUEUE_CONNECTION=sync -e SESSION_DRIVER=array -e MAIL_MAILER=array -e AI_PROVIDER=none -e OPENAI_API_KEY= cvortex-backend-1 php vendor/bin/phpunit --filter ChatGptPlanTest --do-not-cache-result
```

APP_ENV принудительно не менялся; использована существующая Makefile convention
для DB/cache/queue/session/mail. ChatGptPlanTest вызывает Http::preventStrayRequests.
RefreshDatabase действует только на изолированную in-memory SQLite; data-bearing
PostgreSQL этим тестом не очищается. Real connections не disconnect/revoke/refresh.

Public checks **PASS**: `/api/v1/health/live` 200, `/api/v1/health/ready` 200,
unauthenticated `/api/v1/me` 401, `/` 200; Horizon running.
Документные ссылки, preservation/scope check и `git diff --check` **PASS**.

**NOT_RUN**: full suites, Pint/Larastan/frontend lint/build (product source не менялся),
disposable PostgreSQL/RLS/race harness, live OAuth/inference/provider quota,
authenticated user E2E, manual keyboard/native zoom/audible screen reader,
следующая B01 integrity correction. Эти omissions не помечены PASS.

## Документы, ограничения и STOP

Изменены STATUS/NEXT/BLOCKERS, task completion и task index, current dashboard;
добавлены этот report и безопасное evidence. Historical recovery report,
history/validation/goldens, unrelated dirty files сохранены byte-for-byte.
Product code, dependencies, Compose/env, roles, другие БД и production не менялись.
Существующий one-off migration workflow не пересоздавал runtime services.
Один агент; commit/push/PR/live AI не выполнялись.

Остаются current B01 integrity, manual production accessibility gate и
real provider/Preview acceptance. NEXT intent:
**`b01-current-manifest-integrity-reconciliation`**. Отдельной executable spec ещё нет;
нужен отдельный owner launch. NEXT update отражает outcome, не отменяет STOP.
M2/B03/release не разрешены. **STOP.**
