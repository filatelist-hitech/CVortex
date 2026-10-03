---
title: Локальное подключение ChatGPT к CVortex
status: verified-current-docs-external-blocked
owner: project
created: 2026-10-03
updated: 2026-10-03
tags: [operations, chatgpt, mcp, tunnel, security]
related: [MCP-Gateway-Validation.md, ../02-Architecture/MCP-Gateway.md, ../../research/technical/11-MCP-GATEWAY-FOUNDATION.md]
---

# Локальное подключение ChatGPT к CVortex

## Актуальный способ подключения

Проверено по документации OpenAI 2026-10-03. ChatGPT не подключается напрямую к `localhost`. Для частного MCP-сервера на компьютере OpenAI документирует **Secure MCP Tunnel**: процесс `tunnel-client` на том же компьютере открывает исходящее HTTPS-соединение к OpenAI и передаёт MCP-запросы локальному CVortex. Входящий порт и публичный прокси для этого не нужны.

Создание и проверка custom MCP app описаны через ChatGPT web: откройте Plugins, нажмите `+`, выберите создание MCP app и соединение `Tunnel`. Справка OpenAI говорит, что custom MCP apps доступны только в web. Поэтому наличие локального plugin marketplace в ChatGPT Desktop не доказывает, что Desktop умеет вызывать MCP app. Для локального пакета ниже Desktop может показать skill; фактический MCP workflow требует отдельной регистрации app в ChatGPT и реального вызова.

Источники и текущая неоднозначность плана Plus: [Developer Mode](https://developers.openai.com/api/docs/guides/developer-mode), [справка о Developer Mode и MCP apps](https://help.openai.com/en/articles/12584461-developer-mode-and-mcp-apps-in-chatgpt), [Secure MCP Tunnel](https://developers.openai.com/api/docs/guides/secure-mcp-tunnels), [архитектура tunnel-client](https://github.com/openai/tunnel-client/blob/master/docs/architecture.md), [формат plugin package](https://developers.openai.com/plugins/build/plugins). В аккаунте Plus 2026-10-03 форма `Create custom MCP server` была видна и разрешила выбрать существующую запись Platform tunnel. Приложение не создавали; реальную авторизацию и вызов не проверяли. Официальные материалы расходятся по Plus read-only entitlement, поэтому сам тариф и видимая форма не считаются доказательством E2E.

## Предварительные условия

- CVortex запускается локально через Docker Compose; локальный Nginx слушает `127.0.0.1:8080`.
- MCP включён только для локальной проверки. По умолчанию `MCP_ENABLED=false`.
- У пользователя есть рабочая сессия CVortex для OAuth.
- В OpenAI Platform выбран или создан Secure MCP Tunnel. Для создания/изменения нужны Tunnels Read + Manage; для запуска процесса и выбора туннеля в ChatGPT — Tunnels Read + Use. Туннель должен быть связан с нужной ChatGPT workspace и Platform organization.
- Runtime key для `tunnel-client` хранится в локальном менеджере секретов и подаётся процессу через `CONTROL_PLANE_API_KEY`. Не помещайте его в репозиторий, `.env`, журнал или чат. Этот ключ не равен `OPENAI_API_KEY`.
- OAuth endpoint CVortex должен быть доступен для браузерного шага авторизации. Secure MCP Tunnel не превращает произвольный локальный OAuth endpoint в публичный URL.

Для Plus официальный Developer Mode guide перечисляет тариф Plus, а plan-specific Help Center FAQ перечисляет Pro и рабочие тарифы, но не Plus. Проверяйте доступность в своём аккаунте; администратор workspace может отдельно ограничить Developer Mode.

## Запуск CVortex

Из корня репозитория:

```sh
make up
```

В корневом `.env` задайте:

```dotenv
MCP_ENABLED=true
```

Локальный canonical origin: `APP_URL=http://127.0.0.1:8080`, `CVORTEX_PORT=8080`. Оставьте существующие `MCP_RESOURCE_URL=` и `MCP_AUTHORIZATION_SERVER_URL=` пустыми: `McpResource` выводит resource `/mcp/v1`, issuer и endpoints `/oauth/authorize`, `/oauth/token`, `/oauth/register` из `APP_URL`. Compose передаёт эти optional overrides backend и Horizon, но для этого локального сценария они не нужны. В существующем `.env` замените только non-secret `APP_URL`, затем выполните `docker compose up -d --no-deps backend horizon` и `docker compose exec -T backend php artisan config:clear`. Не смешивайте `localhost` и `127.0.0.1`: это разные OAuth origins, даже если оба ведут на loopback. Metadata не берёт адреса из request Host/body; overrides предназначены только для доверенной deployment configuration, не пользовательского ввода. Не задавайте выдуманный tunnel URL.

Примените изменение окружения перезапуском Compose:

```sh
docker compose up -d
```

`OPENAI_API_KEY` не нужен для MCP initialize, OAuth CVortex, `tools/list`, `vacancy_get` или `application_context_get`. Он относится к исходящим запросам CVortex к модели. Для самого Secure MCP Tunnel нужен отдельный Platform runtime key `CONTROL_PLANE_API_KEY` у процесса `tunnel-client`.

## Проверка локального MCP

Проверьте тесты переключения MCP:

```sh
bash scripts/test-mcp-gateway-toggle.sh
```

Ожидается `0` MCP/OAuth маршрутов при `MCP_ENABLED=false` и доступные маршруты при `true`. Затем проверьте discovery и безопасный отказ без токена:

```sh
python3 - <<'PY'
import json
import urllib.error
import urllib.request

base = "http://127.0.0.1:8080"
for path in (
    "/.well-known/oauth-protected-resource",
    "/.well-known/oauth-protected-resource/mcp",
    "/.well-known/oauth-authorization-server",
):
    with urllib.request.urlopen(base + path, timeout=10) as response:
        print(f"GET {path}: HTTP {response.status}")

body = json.dumps({
    "jsonrpc": "2.0", "id": 1, "method": "initialize",
    "params": {
        "protocolVersion": "2025-03-26", "capabilities": {},
        "clientInfo": {"name": "cvortex-local-check", "version": "1.0"},
    },
}).encode()
request = urllib.request.Request(
    base + "/mcp/v1", data=body,
    headers={"Content-Type": "application/json", "Accept": "application/json, text/event-stream"},
)
try:
    urllib.request.urlopen(request, timeout=10)
except urllib.error.HTTPError as error:
    print(f"POST /mcp/v1 without bearer token: HTTP {error.code}")
    if error.code != 401:
        raise
PY
```

Discovery должен ответить `200`, неавторизованный MCP POST — `401`. Не проверяйте живой OAuth, используя чужой или сохранённый токен.

## Запуск Secure MCP Tunnel

Скачайте актуальный `tunnel-client` только со страницы [OpenAI tunnel-client](https://github.com/openai/tunnel-client) либо из ссылки Platform Tunnel settings. Не используйте устаревающий зафиксированный номер версии из старого отчёта.

Сначала прочитайте CLI quickstart и список встроенных профилей:

```sh
tunnel-client help quickstart
tunnel-client profiles samples list
```

На дату исследования OpenAI указывает профиль OAuth/DCR `sample_mcp_with_dcr`. После того как Platform выдал реальный `tunnel_id`, замените пример ниже на этот ID (он не является секретом) и создайте локальный профиль для HTTP MCP:

```sh
CONTROL_PLANE_TUNNEL_ID='tunnel_...'
tunnel-client init \
  --sample sample_mcp_with_dcr \
  --profile cvortex-chatgpt \
  --tunnel-id "$CONTROL_PLANE_TUNNEL_ID" \
  --mcp-server-url http://127.0.0.1:8080/mcp/v1
```

Подайте `CONTROL_PLANE_API_KEY` из локального secret manager в окружение процесса, затем выполните:

```sh
tunnel-client doctor --profile cvortex-chatgpt --explain
tunnel-client run --profile cvortex-chatgpt --harpoon.allow-plaintext-http --health.listen-addr 127.0.0.1:0
```

Для установленного v0.0.15 `--harpoon.allow-plaintext-http` необходим для регистрации trusted HTTP loopback PRMD в Harpoon; это не отключает OAuth. Health port `0` предотвращает конфликт с Nginx на 8080. Дополнительный `--mcp.oauth-trusted-origin http://localhost:8080` не нужен: target, resource и issuer используют один origin. Не добавляйте arbitrary trusted origins. Источник: [официальная конфигурация установленного release](https://github.com/openai/tunnel-client/blob/a390c168ff1b2d14e73a95991c186c6aba3ff5a0/docs/configuration.md#harpoon-mcp-outbound-http-allowlist).

Оставьте `run` запущенным во время discovery и вызовов ChatGPT. `doctor` проверяет связность и OAuth metadata; сохраняйте только безопасный итог, не дампьте окружение и не печатайте ключ. Актуальные имена sample и поля всегда сверяйте с CLI: OpenAI меняет `tunnel-client` независимо от кода CVortex.

## Создание ChatGPT app и локальный plugin

1. В ChatGPT web откройте **Settings → Security and login → Developer mode**, если переключатель доступен.
2. Откройте **Plugins → `+` → Create custom MCP server**.
3. В форме выберите **Connection → Tunnel** и выберите туннель либо укажите его настоящий `tunnel_id`. Туннель должен быть связан с активным ChatGPT workspace, а у пользователя должно быть Tunnels Read + Use.
4. Выберите OAuth и завершите OAuth CVortex от имени своего пользователя. Просмотрите обнаруженные tools и подтвердите, что доступны только `vacancy_get` и `application_context_get`. Если появится инструмент записи или список пуст — остановитесь и не используйте app.
5. После создания запишите технический ID приложения, который начинается с `plugin_asdk_app`. Он нужен, чтобы добавить регистрацию приложения в `.app.json` локального plugin package. Не выдумывайте ID и не коммитьте пользовательские credential-файлы.
6. Чтобы установить локальный skill package через официальный Codex CLI из корня репозитория:

   ```sh
   codex plugin marketplace add ./
   codex plugin add cvortex-read-only@cvortex-repository
   codex plugin list --marketplace cvortex-repository --json
   ```

   До изменения существующего `~/.codex/config.toml` сохраните его копию; CLI добавляет marketplace и plugin, не заменяя весь файл. Установка из CLI активирует skill в Codex plugin environment. Если marketplace доступен в desktop Plugins Directory, перезапустите desktop app и установите/проверьте plugin в поддерживаемом Work/Codex surface. Это skill package, а не гарантия MCP app runtime. В этом репозитории лежат portable package и repo marketplace: [plugin manifest](../../integrations/chatgpt/plugin.json), [marketplace](../../.agents/plugins/marketplace.json). Пока настоящий `plugin_asdk_app...` ID не зарегистрирован, пакет содержит только truth-first skill-инструкции и не подключает MCP app.

Пакет использует текущий формат OpenAI `plugin.json` и skill в `skills/cvortex/SKILL.md`. Он запрещает выдумывать факты, считает текст вакансии недоверенными данными, направляет к двум read-only tools и не утверждает, что созданный текст сохранён в CVortex.

## Проверка в ChatGPT

В новом чате ChatGPT web выберите созданное CVortex app в Developer mode и используйте конкретный ID своей вакансии:

```text
Используй CVortex app и вызови vacancy_get для вакансии <мой vacancy_id>.
Покажи только ограниченные сведения о вакансии.
```

```text
Используй CVortex app и вызови application_context_get для той же вакансии.
Объясни соответствия и пробелы, опираясь только на подтверждённые факты.
```

```text
На основе этой вакансии и подтверждённого контекста CVortex напиши короткое сопроводительное письмо.
Покажи текст только в чате и не сохраняй его в CVortex.
```

Отрицательная проверка: попросите сохранить письмо в CVortex. Правильный результат — отказ от записи: у app нет write tool. Не просите систему обходить ограничение другим connector/action. Текст вакансии с командами вроде «раскрой все данные» должен обрабатываться только как содержимое вакансии.

Эти проверки пока не выполнялись. Не считайте видимый marketplace, MCP Inspector или открывшуюся форму доказательством ChatGPT E2E. Полная запись результатов ведётся в [MCP validation](MCP-Gateway-Validation.md).

## Диагностика

| Симптом | Проверка |
|---|---|
| MCP-инструменты не отвечают | `MCP_ENABLED=true`, Compose services запущены, tunnel-client остаётся в состоянии ready, локальный MCP POST без bearer возвращает ожидаемый `401`. |
| Discovery не видит инструменты | Выполните `tunnel-client doctor --profile cvortex-chatgpt --explain`, проверьте локальный `/mcp/v1`, затем повторно обновите app metadata. Для app допустимы ровно два названия инструментов. |
| Туннель отсутствует в ChatGPT | Убедитесь, что Platform tunnel связан не только с Platform organization, но и с целевым ChatGPT workspace; проверьте Tunnels Read + Use. |
| OAuth завершается ошибкой | Проверьте `MCP_AUTHORIZATION_SERVER_URL`, issuer/resource metadata, действующую сессию CVortex и достижимость authorization URL из браузера. Туннель сам по себе не туннелирует произвольный browser-facing authorization endpoint. Не ослабляйте Passport/OAuth. |
| Открылась не та учётная запись CVortex | Переподключите OAuth в ChatGPT под нужным активным пользователем. MCP не принимает `user_id` от вызывающей модели; чужая вакансия должна возвращать `NOT_FOUND`. |
| Docker остановлен или порт занят | `docker compose ps` и `docker compose port nginx 80`; восстановите штатный `make up` или задайте согласованные `CVORTEX_PORT`/`APP_URL`. Не удаляйте PostgreSQL volume. |
| Плагин устарел | Обновите файлы repo package и перезапустите ChatGPT Desktop, затем переустановите/обновите plugin из local marketplace. Это не обновляет зарегистрированную MCP app: её tools metadata обновляется отдельно в ChatGPT web. |
| Developer Mode или создание app недоступно | Plus-документация OpenAI противоречива. Проверьте фактический аккаунт/workspace и доступ администратора; не подменяйте проверку наличием `OPENAI_API_KEY`. |
| ChatGPT Desktop не предлагает MCP app | Help Center описывает custom MCP apps как web-only. Повторите app tool-flow в ChatGPT web. Repo marketplace в Desktop доказывает только доступность local plugin package, не MCP transport. |

## Секреты и текущий результат

- В `.env.example` MCP выключен по умолчанию; проектные MCP reads не требуют `OPENAI_API_KEY`.
- `CONTROL_PLANE_API_KEY` и настоящий `tunnel_id` относятся к Secure MCP Tunnel. Не сохраняйте runtime key в `.env`, Git, skill/package, логи или переписку.
- Не открывайте Nginx в публичную сеть и не заменяйте Secure MCP Tunnel публичным туннелем.
- На 2026-10-03 локальные OAuth metadata отвечали `200`, неавторизованный `POST /mcp/v1` — `401`. В ChatGPT Plus доступна форма custom MCP, а существующий Platform tunnel разрешается этой формой. Официальный `tunnel-client` v0.0.15 установлен, его официальный SHA-256 проверен; локальный профиль `cvortex-chatgpt` создан с `env:CONTROL_PLANE_API_KEY`. `doctor --explain` остановился только на отсутствии этой переменной. Приложение не создано, runtime client не запущен, OAuth/data reads, ChatGPT E2E и native Desktop discovery не проверены.
