---
title: Local Development
status: active
owner: project
created: 2026-09-12
updated: 2026-10-03
tags: [operations, local, docker, setup]
related:
  - "[[../00-Home/User-Guide|User Guide]]"
  - "[[../02-Architecture/M0-Runtime|M0 Runtime]]"
  - "[[M0-Runbook]]"
  - "[[Logging-and-Diagnostics|Logging and Diagnostics]]"
---

# Локальная установка и эксплуатация

Это инструкция для локального Preview CVortex через Docker Compose. Поддерживаемая граница здесь — рабочая копия репозитория на компьютере; публичное production-размещение этим runbook не настраивается.

## Требования

На компьютере нужны Git, Make, Docker с поддержкой Compose и запущенный Docker Engine/Desktop. PHP, Composer, Node.js, npm, PostgreSQL и Redis на хосте не нужны: они работают в контейнерах. Для дополнительной команды `make logs-pretty` нужен Python 3.

## Установка с нуля

```sh
git clone https://github.com/filatelist-hitech/CVortex.git
cd CVortex
make init
docker compose --env-file .env config --quiet
make up
make migrate
```

Что происходит:

- `make init` копирует `.env.example` в игнорируемый `.env`, если файла ещё нет; генерирует `APP_KEY`, пароль PostgreSQL и отдельный пароль роли приложения; собирает образы и устанавливает зафиксированные зависимости в Docker volumes.
- `docker compose ... config --quiet` проверяет, что Compose может собрать конфигурацию. При ошибке не запускайте стек, пока не исправите значения.
- `make up` запускает сервисы в фоне.
- `make migrate` настраивает ограниченную роль приложения `cvortex_app`, затем применяет миграции Laravel через отдельный сервис миграций.

Проверьте readiness и откройте приложение только после успешного ответа:

```sh
docker compose ps
health_authority="$(docker compose port nginx 80)"
curl -fsS "http://${health_authority}/api/v1/health/ready"
```

При успехе endpoint возвращает HTTP 200 и `{"status":"ready"}`. Затем откройте значение `APP_URL` из `.env` — по умолчанию `http://localhost:8080`. Создание администратора и приглашения описано в [Access and first sign-in](M1-Access-Core.md); новой установке не выдаётся стандартная учётная запись.

## Environment

`.env.example` содержит безопасные локальные значения и пустые места для внешних секретов. Не вставляйте в Git реальные пароли, API keys, приглашения или персональные карьерные данные. `make init` генерирует локальные секреты в `.env`; не заменяйте этот файл примером из другой рабочей копии.

| Переменная | Назначение |
|---|---|
| `CVORTEX_PORT` | Порт loopback-публикации Nginx; по умолчанию `8080` |
| `APP_URL` | Адрес, который открывает браузер; должен соответствовать порту и host |
| `POSTGRES_DB` | Имя базы внутри существующего PostgreSQL volume |
| `POSTGRES_USER`, `POSTGRES_PASSWORD` | Административное подключение PostgreSQL и миграций; значения должны соответствовать уже инициализированному volume |
| `POSTGRES_RUNTIME_USER`, `POSTGRES_RUNTIME_PASSWORD` | Отдельная ограниченная роль backend/Horizon; пароль генерирует `make init` и он должен отличаться от административного |
| `AI_PROVIDER` | По умолчанию `none`; для текущего OpenAI adapter задаётся `openai` |
| `OPENAI_API_KEY` | Секрет провайдера; нужен только при `AI_PROVIDER=openai` |
| `OPENAI_BASE_URL` | Адрес API провайдера; по умолчанию официальный OpenAI API URL |
| `OPENAI_CAREER_EXTRACTION_MODEL` | Модель для извлечения Career Facts и требований вакансии |
| `OPENAI_APPLICATION_DRAFT_MODEL` | Модель для подготовки/проверки черновиков; переменная поддерживается backend, но отсутствует в `.env.example` |
| `MCP_ENABLED` | По умолчанию `false`; расширенная read-only MCP интеграция, не нужна обычному Preview |

Для смены стандартного порта измените обе строки в `.env`, затем переподнимите сервисы:

```dotenv
CVORTEX_PORT=18080
APP_URL=http://localhost:18080
```

```sh
make up
```

AI-провайдер в чистой установке отключён. Без него доступны ручное добавление Career Facts, проверка/подтверждение фактов, просмотр сохранённых данных и детерминированная часть сопоставления. Извлечение текста, анализ вакансии и генерация/семантическая проверка черновиков требуют настроенного провайдера и выбранных имён моделей. Точные переменные — в `apps/backend/config/ai.php`; конкретные модели и их стоимость выбирает и проверяет оператор. MCP, OAuth и внешний tunnel — отдельная расширенная настройка, см. [MCP Gateway validation](MCP-Gateway-Validation.md).

## Остановить, запустить и обновить

| Действие | Команда | Результат |
|---|---|---|
| Запустить остановленный стек | `make up` | Поднимает контейнеры в фоне |
| Остановить стек | `make down` | Удаляет контейнеры/сеть, сохраняет PostgreSQL и private-storage volumes |
| Пересоздать сервисы | `make restart` | Выполняет `down`, затем `up`; volumes сохраняются |
| Применить миграции | `make migrate` | Обновляет схему локальной базы |

Для обновления исходников в рабочей копии с настроенной upstream-веткой:

```sh
git status --short
git pull --ff-only
make init
docker compose --env-file .env config --quiet
make up
make migrate
```

Сначала разрешите локальные Git-изменения; `git pull --ff-only` не объединяет расходящиеся ветки. `make init` пересобирает образы и зависимости, сохраняя заданные значения `.env`; если `POSTGRES_RUNTIME_PASSWORD` пуст или совпадает с `POSTGRES_PASSWORD`, команда создаёт для runtime-роли отдельный пароль. Миграции затрагивают постоянную базу. В репозитории нет пользовательской команды или проверенной процедуры backup/restore базы и приватных файлов: не удаляйте volumes и не считайте Git копией пользовательских данных.

## Диагностика запуска

### Страница открывается, но readiness возвращает 503

`/api/v1/health/live` проверяет путь HTTP → Nginx → Laravel. Только `/api/v1/health/ready` проверяет ещё PostgreSQL и Redis. Если readiness не готов, посмотрите состояния и журналы:

```sh
docker compose ps
make logs SERVICE=backend
make logs SERVICE=postgres
make logs SERVICE=redis
```

Зависимость PostgreSQL может быть healthy как контейнер, но Laravel всё ещё не сможет подключиться, если база из `POSTGRES_DB` отсутствует в существующем volume.

### В сообщении указано `database "…" does not exist`

Обычно `.env` содержит имя базы, отличное от имени при первой инициализации volume. Сначала остановите контейнеры и только прочитайте список баз:

```sh
make down
make up
docker compose exec -T postgres sh -lc \
  'psql -U "$POSTGRES_USER" -d postgres -Atc "SELECT datname FROM pg_database ORDER BY datname;"'
```

Если нужные данные находятся в базе с другим именем, задайте это существующее имя в `POSTGRES_DB` в `.env`. Не меняйте имена пользователей/пароли вслепую: Compose volume мог быть инициализирован прежними значениями. Затем проверьте конфигурацию и повторите миграции:

```sh
docker compose --env-file .env config --quiet
make up
make migrate
```

Если ожидаемой базы нет, остановитесь и выясните, какой Compose project/volume содержит данные. **Не запускайте `docker compose down --volumes` как способ исправления**: это удалит персистентную базу и private storage.

### Порт уже занят

Задайте свободные согласованные `CVORTEX_PORT` и `APP_URL` в `.env`, затем выполните `make up`. Не останавливайте неизвестный процесс только ради освобождения порта.

### Сервис нездоров или операция падает

Проверьте `docker compose ps`, затем журналы конкретного сервиса через `make logs SERVICE=backend|postgres|redis|frontend|horizon`. Для пользовательской ошибки сохраните код и Reference ID и передайте администратору; см. [Error Center user guide](../00-Home/Error-Center-User-Guide.md) и [Logging and Diagnostics](Logging-and-Diagnostics.md).

## Хранение и границы сети

Compose хранит PostgreSQL и `storage/app/private` в именованных Docker volumes; Redis намеренно без постоянного диска. Только Nginx публикуется на host, причём на `127.0.0.1`. Внутренние сервисы доступны по сети Compose. Не открывайте публичный порт роутера как shortcut для удалённого доступа.

Career, vacancy и draft данные содержат чувствительную информацию и переживают `make down`, `make restart` и `make up`. Команда `docker compose down --volumes` удаляет как минимум базу и private storage. Локальные Docker-логи ограничены ротацией; это не backup и не архив.

## Полезные команды разработчика/оператора

| Команда | Назначение |
|---|---|
| `make test` | Backend/frontend tests и проверка переключения MCP routes |
| `make lint` | Pint, PHPStan/Larastan, ESLint и TypeScript |
| `make logs SERVICE=backend` | Последние 200 строк журнала сервиса |
| `make logs-pretty SERVICE=backend` | Форматирует JSON-журнал; требует Python 3 на хосте |
| `make failed-jobs` | Список финальных ошибок очереди через диагностику |
| `make diagnostics-prune` | Немедленно удаляет записи диагностики за пределами retention |
| `make shell SERVICE=backend` | Открывает shell в работающем контейнере |
| `make shell SERVICE=backend COMMAND='php -v'` | Выполняет команду внутри контейнера |

Для полного операторского чеклиста и health probes см. [M0 Runbook](M0-Runbook.md). Для детальной схемы сервисов см. [M0 Runtime](../02-Architecture/M0-Runtime.md).

## Расширенная настройка MCP

Входящий MCP Gateway выключен по умолчанию и предоставляет только два read-only инструмента: `vacancy_get` и `application_context_get`. Он не принимает изменения черновиков, фактов или статусов. Для локального Inspector, OAuth и Secure MCP Tunnel нужны отдельные ключи, OAuth resource/issuer и подтверждённые доступы; обычное использование веб-интерфейса ничего из этого не требует.

Следуйте [MCP Gateway architecture](../02-Architecture/MCP-Gateway.md), [MCP Gateway validation](MCP-Gateway-Validation.md) и принятому [ADR-0021](../03-ADR/ADR-0021-inbound-mcp-read-only.md). Не включайте gateway и не раскрывайте локальный endpoint вовне без отдельной настройки и проверки.
