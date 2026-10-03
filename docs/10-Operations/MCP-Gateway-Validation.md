---
title: Проверка MCP Gateway только для чтения
status: verified-local-external-blocked
owner: project
created: 2026-09-25
updated: 2026-10-03
tags: [operations, mcp, validation, read-only]
related: [../02-Architecture/MCP-Gateway.md, ../03-ADR/ADR-0021-inbound-mcp-read-only.md]
---

# Проверка MCP Gateway только для чтения

## Что разрешено

Входящий MCP по умолчанию выключен. Если оператор его включит, будут доступны ровно два инструмента и только для чтения:

1. `vacancy_get`: получить сведения о вакансии;
2. `application_context_get`: получить контекст подготовки отклика.

Пользователь определяется по проверенному OAuth bearer-токену. При чтении проверяется владелец записи и действуют правила изоляции PostgreSQL RLS. Возвращаемый объём данных ограничен; внешний сервис ИИ `LlmProvider` не вызывается. Подготовка отклика, Truth Guard и подтверждение пользователем остаются обычными сценариями CVortex.

## Результаты локальной проверки от 2026-09-27

| Проверка | Результат |
|---|---|
| `make test` | PASS: backend (215 тестов, 1419 проверок; 7 пропусков тестов, требующих PostgreSQL), frontend (16/16). Включена проверка переключения 0/20 маршрутов при выключенном и включённом MCP. |
| `make lint` | PASS: Pint проверил 162 файла; PHPStan проверил 102 файла без ошибок; ESLint и TypeScript прошли. |
| `bash scripts/test-application-postgres-boundary.sh` | PASS: проверены владелец записи и RLS в PostgreSQL, включая снимки данных до и после чтения через MCP; 3 теста и 55 проверок. Использовалась отдельная временная база. Основную базу Compose и её тома не сбрасывали и не удаляли. |
| `bash scripts/test-mcp-gateway-toggle.sh` | PASS: при `MCP_ENABLED=false` зарегистрировано 0 маршрутов MCP/OAuth; при `true` зарегистрированы и проверены ожидаемые адреса из 20 маршрутов MCP/OAuth. |
| Метаданные локального обнаружения | PASS: корневой адрес, `/mcp` и канонический `/mcp/v1`, а также метаданные сервера авторизации отвечали HTTP 200 при включённом MCP. |
| Неавторизованный `POST /mcp/v1` | PASS: HTTP 401 с безопасной JSON-ошибкой; стек не возвращается клиенту. |
| MCP Inspector CLI, актуальный протокол, сохранённая сессия OAuth | PASS: при обращении по Streamable HTTP вызов `tools/list` вернул ровно два указанных выше инструмента. Оба помечены как доступные только для чтения, без разрушительных действий и идемпотентные. |
| `vacancy_get` через Inspector для записи из Preview | PASS: получен тот же сохранённый идентификатор вакансии с проверкой владельца; статус анализа `FAILED`; возвращены ограниченные метаданные, исходный текст скрыт. |
| `application_context_get` через Inspector для этой вакансии | PASS: получен безопасный результат о незавершённом анализе; требований, утверждений и фрагмента исходного текста нет; ошибки не возвращено. |
| Снимок состояния PostgreSQL до и после обоих чтений | PASS: хэш сериализованного состояния совпал (`6714368272c9171dfe18ab171cfb1049b3a9bb75722b215b2d4e5426da98f271`). Автоматическая проверка также сравнивает таблицы продукта и проверяет изоляцию владельцев. |
| `docker compose --env-file .env.example config --quiet` | PASS. |
| `git diff --check` | PASS при итоговой проверке перед коммитом. |

## Повторная проверка discovery и безопасного отказа — 2026-10-03

В текущем локальном Compose-стеке все три OAuth discovery адреса (`/.well-known/oauth-protected-resource`, `/.well-known/oauth-protected-resource/mcp`, `/.well-known/oauth-authorization-server`) ответили HTTP 200. Неавторизованный Streamable HTTP `POST /mcp/v1` на запрос `initialize` ответил HTTP 401 с JSON-объектом безопасной ошибки. Тело ошибки не содержит стек. Это подтверждает локальные discovery/deny границы, но не выполняет `initialize` с bearer-токеном и не обновляет доказательство инструментов `tools/list`; для этого сохраняются результаты Inspector от 2026-09-27 выше.

Живые вызовы Inspector использовали существующую вакансию Preview `01m3fvhmxhp83kcz80ecrd0jwg`. Название вакансии, компанию и учётные данные OAuth не приводим. Для поиска и вызовов инструментов использовалась ранее выданная сессия OAuth. Полный новый сценарий DCR → вход → согласие → токен в эту проверку не вошёл: в браузере CVortex показывал страницу входа, а учётных данных пользователя не было. Не считайте вызов с сохранённой авторизацией доказательством нового входа и согласия.

## Автоматизированная регрессия — 2026-10-03

| Проверка | Результат |
|---|---|
| `make test` | PASS: backend — 299 тестов, 2261 assertion, 11 пропусков; frontend — 73 теста. `McpGatewayTest` повторно проверил точный `tools/list` (`vacancy_get`, `application_context_get`, count=2), отсутствие MCP-записи и отсутствие изменения строк владельца. |
| `make lint` | PASS: Pint — 187 файлов; PHPStan — 117 файлов без ошибок; ESLint и TypeScript прошли. |
| `bash scripts/test-application-postgres-boundary.sh` | PASS: PostgreSQL runtime-role/RLS suite выполнилась до и после точечного rollback/re-up — по 3 теста и 55 assertions; `vacancies`, Career Facts и users сохранились. Harness выделяет точные OAuth/Application migration records во временной БД и не меняет постоянную БД/volume. |
| Проверка текущих discovery routes | PASS: три OAuth metadata endpoint ответили HTTP 200; неавторизованный `POST /mcp/v1` — HTTP 401 с JSON error. |

Автоматизированный MCP набор был запущен повторно, но MCP Inspector с новым CVortex OAuth входом в этот прогон не запускался. Последний фактический Inspector `tools/list`/оба tool call со снимком продукта остаётся датирован 2026-09-27; текущий результат `make test` не является новым Inspector или ChatGPT E2E.

Обезличенный фрагмент живого ответа Inspector:

```json
{
  "tools": ["vacancy_get", "application_context_get"],
  "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true},
  "vacancy_get": {
    "isError": false,
    "id": "01m3fvhmxhp83kcz80ecrd0jwg",
    "analysis_status": "FAILED",
    "untrusted_data": true
  },
  "application_context_get": {
    "isError": false,
    "analysis_status": "FAILED",
    "requirements_count": 0,
    "confirmed_claims_count": 0,
    "untrusted_vacancy_data": true,
    "context_truncated": false
  }
}
```

Полная проверка границ PostgreSQL сравнивает строки продукта до и после чтения через MCP для владельца данных и второго пользователя. Она охватывает подготовки откликов и черновики, подтверждения, отклики, факты о карьере, утверждения, статусы вакансий и снимки исходного текста, а также сведения о работодателях. Запросы к чужой вакансии или её контексту возвращают `not-found`. Проверки чтения без изменений также входят в набор MCP на SQLite.

Регрессионные проверки URI перенаправления вызывают настоящий маршрут регистрации. Они принимают разрешённые адреса возврата ChatGPT и динамические порты локальных приложений, но отклоняют подмену узла, данные пользователя в URI, обход пути (включая закодированный), разделители запроса и фрагмента, а также некорректные адреса. Проверки ресурса и аудитории связывают разрешение и токены доступа с настроенными адресом MCP и издателем. Они требуют разрешение `mcp:use` и отклоняют просроченные или отозванные токены и отключённых пользователей.

OAuth-проверки также подтверждают, что заголовок `WWW-Authenticate` строит ссылку на метаданные защищённого ресурса по настроенному внешнему адресу и пути, а не по входящему заголовку `Host`.

По умолчанию задано безопасное значение `MCP_ENABLED=false`. Для локального чтения `OPENAI_API_KEY` не нужен; локальная OAuth-авторизация требует существующих ключей подписи Passport. Точная настройка приведена в инструкции [«Локальная установка»](Local-Development.md).

## Состояние внешней проверки

- **Обнаружение MCP и чтение через локальный MCP Inspector: PASS.**
- **Новый вход Inspector, согласие и получение токена: НЕ ВЫПОЛНЕНО в этой проверке.** В текущем браузере не было авторизованной сессии CVortex. Сохранённая OAuth-авторизация позволила выполнить реальные чтения, но не заменяет проверку нового сценария.
- **ChatGPT Plus UI, 2026-10-03: ФОРМА ВИДНА, ПОДКЛЮЧЕНИЕ НЕ ПРОВЕРЕНО.** В ChatGPT web была видна учётная запись Plus и Plugins → `+` → `Create custom MCP server`; форма предлагала `Server URL` и `Tunnel`, а существующая запись Platform tunnel разрешилась при выборе по ID. Форму не отправляли, разрешения не подтверждали, приложение не создавали. Официальные источники расходятся по Plus read-only entitlement: Developer Mode guide включает Plus, а Help Center FAQ описывает Pro и рабочие тарифы, но Plus не упоминает. См. датированное [исследование OpenAI/MCP](../../research/technical/11-MCP-GATEWAY-FOUNDATION.md).
- **Secure MCP Tunnel current boundary — 2026-10-03:** local metadata correction and checks are recorded below. Runtime key is absent from this agent process, so doctor is not rerun. The user reports successful Harpoon PRMD HTTP 200 before correction; post-fix ChatGPT discovery remains manual/unverified. Earlier key/daemon observations are historical and do not describe the user’s current shell.
- **ChatGPT Desktop: НЕ ПРОВЕРЕН.** Создание custom MCP app выполняется через документированный ChatGPT web flow; Help Center описывает MCP apps как web-only. Текущий repo marketplace flow относится к Work mode или Codex surface в ChatGPT desktop. Он устанавливает plugin/skill package, но сам по себе не создаёт MCP-подключение. Реального Desktop tool discovery или вызова не было.

Обнаружение OAuth через ChatGPT, передача ресурса через Secure MCP Tunnel, выбор инструментов, отказ на запросы изменения данных, prompt-injection сценарий и изоляция разных пользователей в ChatGPT не проверялись. Успех в Inspector нельзя называть сквозной проверкой ChatGPT. Ставить ChatGPT-совместимость PASS можно только после реального подключения и успешного вызова обоих инструментов чтения и генерации текста в ChatGPT без записи в CVortex.

## Секреты и журналы

Учётные данные процесса туннеля нельзя хранить в `.env` репозитория. Передавайте их через хранилище секретов оператора или переменные окружения процесса согласно актуальной инструкции OpenAI. Не выводите токены и секреты в тестовый журнал.

Ошибки авторизации MCP возвращают клиенту безопасные структурированные ответы. Ожидаемые ошибки bearer-авторизации Passport записываются только с безопасными метаданными запроса. Для production задайте `APP_DEBUG=false`. Не утверждайте, что серверный журнал содержит только метаданные, если проверка обработчика этого не подтверждает.

## Canonical local origin validation — 2026-10-03

Local correction **PASS**, ChatGPT E2E **BLOCKED_EXTERNAL / manual follow-up**. `APP_URL=http://127.0.0.1:8080` is the single existing base; both optional MCP overrides are empty. Root local defaults and active ignored .env were corrected; backend/Horizon recreated and backend config cache cleared without migration/data changes.

Executed `make test`: backend 300 tests / 2285 assertions / 11 skipped; frontend 73 passed. Includes existing OAuth code/refresh/PKCE/redirect/resource/issuer checks, exactly-two-read-tools and no-mutation tests, plus new structural local metadata regression independent of request Host. `make lint` passed Pint (187 files), PHPStan (117 files, no errors), ESLint and TypeScript. Compose example config validation and `git diff --check` passed.

Both live `curl -fsS` discovery requests returned HTTP 200 and valid JSON. Independent structured HTTP assertions verified:

| Field | Actual value |
| --- | --- |
| PRMD resource | `http://127.0.0.1:8080/mcp/v1` |
| PRMD authorization_servers[0] / AS issuer | `http://127.0.0.1:8080` |
| authorization_endpoint | `http://127.0.0.1:8080/oauth/authorize` |
| token_endpoint | `http://127.0.0.1:8080/oauth/token` |
| registration_endpoint | `http://127.0.0.1:8080/oauth/register` |

No `localhost` occurs in either live metadata response. S256, code, refresh_token, mcp:use, public-client none, DCR and issuer response support remain advertised and tested. No tool/domain/OAuth security implementation changed; no runtime secret was added.

`CONTROL_PLANE_API_KEY` is absent from this process; doctor was not run. Use the existing local profile with `tunnel-client run --profile cvortex-chatgpt --harpoon.allow-plaintext-http --health.listen-addr 127.0.0.1:0`; flag semantics were checked against installed v0.0.15 help and matching official release docs linked in the setup guide. No localhost trust exception is necessary.

User-provided pre-correction logs show successful `harpoon` dispatch and `oauth-prmd-source-0` status 200 while ChatGPT reports OAuth discovery failure. A fresh post-correction UI retry was not performed; these logs do not prove an upstream defect. Keep any remaining external discovery failure separate from this fixed local configuration issue; do not weaken OAuth or add undocumented workarounds.
