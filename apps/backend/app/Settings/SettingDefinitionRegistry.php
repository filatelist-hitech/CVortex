<?php

namespace App\Settings;

class SettingDefinitionRegistry
{
    /** @var array<string, SettingDefinition> */
    private array $definitions = [];

    public function __construct()
    {
        $this->registerAll();
    }

    /**
     * @return array<string, SettingDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    public function get(string $key): ?SettingDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    /**
     * @return list<SettingDefinition>
     */
    public function forGroup(string $group): array
    {
        return array_values(array_filter(
            $this->definitions,
            fn (SettingDefinition $def) => $def->group === $group
        ));
    }

    /**
     * @return list<string>
     */
    public function groups(): array
    {
        $groups = array_values(array_unique(array_map(fn (SettingDefinition $def) => $def->group, $this->definitions)));
        sort($groups);

        return $groups;
    }

    private function register(SettingDefinition $definition): void
    {
        $this->definitions[$definition->key] = $definition;
    }

    private function registerAll(): void
    {
        // ---------------------------------------------------------------------
        // General
        // ---------------------------------------------------------------------
        $this->register(new SettingDefinition(
            key: 'general.app_name',
            group: 'general',
            label: 'Название приложения',
            description: 'Имя системы, отображаемое в заголовках, письмах и метаданных.',
            type: 'string',
            default: 'CVortex',
            validationRules: ['string', 'min:2', 'max:64'],
            envFallbackKey: 'APP_NAME',
            sortOrder: 10,
        ));

        $this->register(new SettingDefinition(
            key: 'general.default_locale',
            group: 'general',
            label: 'Язык по умолчанию',
            description: 'Основной язык интерфейса и форматирования дат.',
            type: 'enum',
            default: 'ru',
            options: [
                ['value' => 'ru', 'label' => 'Русский (ru)'],
                ['value' => 'en', 'label' => 'English (en)'],
            ],
            validationRules: ['string', 'in:ru,en'],
            envFallbackKey: 'APP_LOCALE',
            sortOrder: 20,
        ));

        $this->register(new SettingDefinition(
            key: 'general.default_timezone',
            group: 'general',
            label: 'Часовой пояс по умолчанию',
            description: 'Базовый часовой пояс для системных логов, дат инцидентов и аудита.',
            type: 'enum',
            default: 'UTC',
            options: [
                ['value' => 'UTC', 'label' => 'UTC'],
                ['value' => 'Europe/Moscow', 'label' => 'Europe/Moscow (UTC+3)'],
                ['value' => 'Europe/Berlin', 'label' => 'Europe/Berlin (UTC+1/+2)'],
                ['value' => 'Europe/London', 'label' => 'Europe/London (UTC+0/+1)'],
                ['value' => 'America/New_York', 'label' => 'America/New_York (UTC-5/-4)'],
                ['value' => 'Asia/Dubai', 'label' => 'Asia/Dubai (UTC+4)'],
            ],
            validationRules: ['string', 'timezone'],
            envFallbackKey: 'APP_TIMEZONE',
            sortOrder: 30,
        ));

        // ---------------------------------------------------------------------
        // Authentication & Invitations
        // ---------------------------------------------------------------------
        $this->register(new SettingDefinition(
            key: 'auth.invitation_default_expires_days',
            group: 'auth',
            label: 'Срок действия приглашений (дней)',
            description: 'Количество дней, в течение которых действительно вновь созданное приглашение.',
            type: 'integer',
            default: 7,
            validationRules: ['integer', 'min:1', 'max:30'],
            sortOrder: 10,
        ));

        $this->register(new SettingDefinition(
            key: 'auth.login_rate_limit_attempts',
            group: 'auth',
            label: 'Лимит попыток входа',
            description: 'Максимальное количество неудачных попыток авторизации до временной блокировки.',
            type: 'integer',
            default: 5,
            validationRules: ['integer', 'min:1', 'max:100'],
            envFallbackKey: 'AUTH_LOGIN_RATE_LIMIT_ATTEMPTS',
            sortOrder: 20,
        ));

        $this->register(new SettingDefinition(
            key: 'auth.login_rate_limit_decay_seconds',
            group: 'auth',
            label: 'Период сброса лимита входа (секунды)',
            description: 'Время ожидания после превышения лимита попыток входа.',
            type: 'integer',
            default: 60,
            validationRules: ['integer', 'min:10', 'max:3600'],
            envFallbackKey: 'AUTH_LOGIN_RATE_LIMIT_DECAY_SECONDS',
            sortOrder: 30,
        ));

        // ---------------------------------------------------------------------
        // AI & Models
        // ---------------------------------------------------------------------
        $this->register(new SettingDefinition(
            key: 'ai.model_policies.low_cost.model',
            group: 'ai_models',
            label: 'Модель структурированного извлечения',
            description: 'LLM модель для извлечения карьерных фактов и первичных требований вакансий (низкая стоимость).',
            type: 'string',
            default: 'gpt-4o-mini',
            validationRules: ['string', 'min:2', 'max:64'],
            envFallbackKey: 'OPENAI_CAREER_EXTRACTION_MODEL',
            sortOrder: 10,
        ));

        $this->register(new SettingDefinition(
            key: 'ai.model_policies.standard.model',
            group: 'ai_models',
            label: 'Модель семантической генерации',
            description: 'LLM модель для генерации черновиков откликов, сопроводительных писем и глубокого сопоставления.',
            type: 'string',
            default: 'gpt-4o',
            validationRules: ['string', 'min:2', 'max:64'],
            envFallbackKey: 'OPENAI_APPLICATION_DRAFT_MODEL',
            sortOrder: 20,
        ));

        $this->register(new SettingDefinition(
            key: 'ai.career_extraction.max_source_characters',
            group: 'ai_models',
            label: 'Лимит символов текста карьеры',
            description: 'Максимальный объём сырого текста резюме или опыта для извлечения за один раз.',
            type: 'integer',
            default: 30000,
            validationRules: ['integer', 'min:1000', 'max:100000'],
            sortOrder: 30,
        ));

        $this->register(new SettingDefinition(
            key: 'ai.vacancy_extraction.max_source_characters',
            group: 'ai_models',
            label: 'Лимит символов текста вакансии',
            description: 'Максимальный объём текста вакансии для парсинга требований.',
            type: 'integer',
            default: 50000,
            validationRules: ['integer', 'min:1000', 'max:100000'],
            sortOrder: 40,
        ));

        $this->register(new SettingDefinition(
            key: 'ai.model_policies.low_cost.input_cost_micros',
            group: 'ai_models',
            label: 'Стоимость ввода: извлечение (micros/$ / 1M токенов)',
            description: 'Расчётная стоимость входных токенов в микродолларах для учёта затрат.',
            type: 'integer',
            default: null,
            validationRules: ['nullable', 'integer', 'min:0'],
            envFallbackKey: 'OPENAI_CAREER_INPUT_COST_MICROS_PER_MILLION_TOKENS',
            sortOrder: 50,
        ));

        $this->register(new SettingDefinition(
            key: 'ai.model_policies.low_cost.output_cost_micros',
            group: 'ai_models',
            label: 'Стоимость вывода: извлечение (micros/$ / 1M токенов)',
            description: 'Расчётная стоимость выходных токенов в микродолларах для учёта затрат.',
            type: 'integer',
            default: null,
            validationRules: ['nullable', 'integer', 'min:0'],
            envFallbackKey: 'OPENAI_CAREER_OUTPUT_COST_MICROS_PER_MILLION_TOKENS',
            sortOrder: 60,
        ));

        $this->register(new SettingDefinition(
            key: 'ai.model_policies.standard.input_cost_micros',
            group: 'ai_models',
            label: 'Стоимость ввода: генерация (micros/$ / 1M токенов)',
            description: 'Расчётная стоимость входных токенов генерации в микродолларах.',
            type: 'integer',
            default: null,
            validationRules: ['nullable', 'integer', 'min:0'],
            envFallbackKey: 'OPENAI_APPLICATION_INPUT_COST_MICROS_PER_MILLION_TOKENS',
            sortOrder: 70,
        ));

        $this->register(new SettingDefinition(
            key: 'ai.model_policies.standard.output_cost_micros',
            group: 'ai_models',
            label: 'Стоимость вывода: генерация (micros/$ / 1M токенов)',
            description: 'Расчётная стоимость выходных токенов генерации в микродолларах.',
            type: 'integer',
            default: null,
            validationRules: ['nullable', 'integer', 'min:0'],
            envFallbackKey: 'OPENAI_APPLICATION_OUTPUT_COST_MICROS_PER_MILLION_TOKENS',
            sortOrder: 80,
        ));

        // ---------------------------------------------------------------------
        // AI Providers
        // ---------------------------------------------------------------------
        $this->register(new SettingDefinition(
            key: 'ai.providers.openai.enabled',
            group: 'ai_providers',
            label: 'OpenAI API провайдер включён',
            description: 'Активирует прямое обращение к OpenAI API для фоновых задач.',
            type: 'boolean',
            default: true,
            validationRules: ['boolean'],
            sortOrder: 10,
        ));

        $this->register(new SettingDefinition(
            key: 'ai.providers.openai.api_key',
            group: 'ai_providers',
            label: 'OpenAI API Key',
            description: 'Системный API ключ OpenAI. Хранится в зашифрованном виде.',
            type: 'secret',
            default: null,
            validationRules: ['nullable', 'string', 'min:8', 'max:255'],
            isSecret: true,
            envFallbackKey: 'OPENAI_API_KEY',
            testable: true,
            sortOrder: 20,
        ));

        $this->register(new SettingDefinition(
            key: 'ai.providers.openai.base_url',
            group: 'ai_providers',
            label: 'OpenAI Base URL',
            description: 'Эндпоинт API (позволяет использовать прокси, LiteLLM или совместимые шлюзы).',
            type: 'url',
            default: 'https://api.openai.com/v1',
            validationRules: ['required', 'url'],
            envFallbackKey: 'OPENAI_BASE_URL',
            dangerous: true,
            sortOrder: 30,
        ));

        $this->register(new SettingDefinition(
            key: 'ai.providers.openai.timeout_seconds',
            group: 'ai_providers',
            label: 'Тайм-аут запросов OpenAI (секунды)',
            description: 'Максимальное время ожидания ответа от API OpenAI.',
            type: 'integer',
            default: 45,
            validationRules: ['integer', 'min:5', 'max:300'],
            envFallbackKey: 'OPENAI_TIMEOUT_SECONDS',
            sortOrder: 40,
        ));

        $this->register(new SettingDefinition(
            key: 'ai.features.chatgpt_plan_enabled',
            group: 'ai_providers',
            label: 'ChatGPT Plan подключение разрешено',
            description: 'Разрешает пользователям подключать персональную подписку ChatGPT Plus/Pro через OAuth.',
            type: 'boolean',
            default: false,
            validationRules: ['boolean'],
            envFallbackKey: 'CHATGPT_PLAN_ENABLED',
            sortOrder: 50,
        ));

        $this->register(new SettingDefinition(
            key: 'ai.features.mcp_enabled',
            group: 'ai_providers',
            label: 'Inbound MCP Gateway активен',
            description: 'Включает шлюз Model Context Protocol для локальных инструментов.',
            type: 'boolean',
            default: false,
            validationRules: ['boolean'],
            envFallbackKey: 'MCP_ENABLED',
            sortOrder: 60,
        ));

        // ---------------------------------------------------------------------
        // Email
        // ---------------------------------------------------------------------
        $this->register(new SettingDefinition(
            key: 'mail.default',
            group: 'email',
            label: 'Почтовый драйвер',
            description: 'Транспорт для отправки системных уведомлений и приглашений.',
            type: 'enum',
            default: 'log',
            options: [
                ['value' => 'log', 'label' => 'Log (запись в системный лог)'],
                ['value' => 'smtp', 'label' => 'SMTP (внешний сервер)'],
                ['value' => 'sendmail', 'label' => 'Sendmail (/usr/sbin/sendmail)'],
                ['value' => 'array', 'label' => 'Array (тестовый накопитель)'],
            ],
            validationRules: ['string', 'in:log,smtp,sendmail,array'],
            envFallbackKey: 'MAIL_MAILER',
            sortOrder: 10,
        ));

        $this->register(new SettingDefinition(
            key: 'mail.smtp.host',
            group: 'email',
            label: 'SMTP Host',
            description: 'Адрес SMTP-сервера.',
            type: 'string',
            default: '127.0.0.1',
            validationRules: ['nullable', 'string', 'max:128'],
            envFallbackKey: 'MAIL_HOST',
            testable: true,
            sortOrder: 20,
        ));

        $this->register(new SettingDefinition(
            key: 'mail.smtp.port',
            group: 'email',
            label: 'SMTP Port',
            description: 'Порт SMTP-сервера (обычно 25, 465, 587 или 2525).',
            type: 'integer',
            default: 2525,
            validationRules: ['nullable', 'integer', 'min:1', 'max:65535'],
            envFallbackKey: 'MAIL_PORT',
            sortOrder: 30,
        ));

        $this->register(new SettingDefinition(
            key: 'mail.smtp.username',
            group: 'email',
            label: 'SMTP Логин',
            description: 'Имя пользователя для аутентификации на SMTP-сервере.',
            type: 'string',
            default: null,
            validationRules: ['nullable', 'string', 'max:128'],
            envFallbackKey: 'MAIL_USERNAME',
            sortOrder: 40,
        ));

        $this->register(new SettingDefinition(
            key: 'mail.smtp.password',
            group: 'email',
            label: 'SMTP Пароль',
            description: 'Пароль SMTP-сервера. Хранится в зашифрованном виде.',
            type: 'secret',
            default: null,
            validationRules: ['nullable', 'string', 'max:255'],
            isSecret: true,
            envFallbackKey: 'MAIL_PASSWORD',
            sortOrder: 50,
        ));

        $this->register(new SettingDefinition(
            key: 'mail.from.address',
            group: 'email',
            label: 'Адрес отправителя (From)',
            description: 'Email, отображаемый получателям системных писем.',
            type: 'string',
            default: 'hello@example.com',
            validationRules: ['required', 'email'],
            envFallbackKey: 'MAIL_FROM_ADDRESS',
            sortOrder: 60,
        ));

        $this->register(new SettingDefinition(
            key: 'mail.from.name',
            group: 'email',
            label: 'Имя отправителя',
            description: 'Отображаемое имя отправителя (например, CVortex Security).',
            type: 'string',
            default: 'CVortex',
            validationRules: ['required', 'string', 'max:64'],
            envFallbackKey: 'MAIL_FROM_NAME',
            sortOrder: 70,
        ));

        // ---------------------------------------------------------------------
        // Files & Documents
        // ---------------------------------------------------------------------
        $this->register(new SettingDefinition(
            key: 'storage.default_disk',
            group: 'storage',
            label: 'Хранилище файлов',
            description: 'Основное дисковое хранилище. Изменение требует миграции файлов и перезапуска.',
            type: 'enum',
            default: 'local',
            options: [
                ['value' => 'local', 'label' => 'Локальный диск (Docker Volume)'],
            ],
            validationRules: ['string', 'in:local'],
            adminEditable: false,
            runtimeMutable: false,
            requiresRestart: true,
            envFallbackKey: 'FILESYSTEM_DISK',
            testable: true,
            sortOrder: 10,
        ));

        $this->register(new SettingDefinition(
            key: 'documents.default_template',
            group: 'storage',
            label: 'Шаблон резюме по умолчанию',
            description: 'Идентификатор базового типографического шаблона для экспорта DOCX/PDF.',
            type: 'enum',
            default: 'standard-ru',
            options: [
                ['value' => 'standard-ru', 'label' => 'Стандартный (RU) — строгий деловой'],
                ['value' => 'compact-ru', 'label' => 'Компактный (RU) — плотный technical'],
                ['value' => 'international-en', 'label' => 'International (EN) — standard Harvard'],
            ],
            validationRules: ['string', 'in:standard-ru,compact-ru,international-en'],
            sortOrder: 20,
        ));

        // ---------------------------------------------------------------------
        // Limits & Observability
        // ---------------------------------------------------------------------
        $this->register(new SettingDefinition(
            key: 'limits.max_upload_size_kb',
            group: 'limits',
            label: 'Лимит размера загружаемых файлов (КБ)',
            description: 'Максимальный размер документа при импорте резюме или материалов.',
            type: 'integer',
            default: 5120,
            validationRules: ['integer', 'min:512', 'max:20480'],
            sortOrder: 10,
        ));

        $this->register(new SettingDefinition(
            key: 'diagnostics.occurrence_retention_days',
            group: 'limits',
            label: 'Хранение единичных сбоев (дней)',
            description: 'Срок хранения подробных записей occurrences в журнале инцидентов.',
            type: 'integer',
            default: 30,
            validationRules: ['integer', 'min:1', 'max:365'],
            envFallbackKey: 'DIAGNOSTIC_OCCURRENCE_DAYS',
            sortOrder: 20,
        ));

        $this->register(new SettingDefinition(
            key: 'diagnostics.closed_incident_retention_days',
            group: 'limits',
            label: 'Хранение закрытых инцидентов (дней)',
            description: 'Срок хранения архивированных инцидентов со статусом RESOLVED или IGNORED.',
            type: 'integer',
            default: 90,
            validationRules: ['integer', 'min:1', 'max:1095'],
            envFallbackKey: 'DIAGNOSTIC_CLOSED_DAYS',
            sortOrder: 30,
        ));
    }
}
