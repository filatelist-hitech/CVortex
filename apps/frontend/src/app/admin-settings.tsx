"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { api } from "./access-shell";

export type SettingOption = { value: string | number; label: string };

export type SettingItem = {
  key: string;
  group: string;
  subgroup: string | null;
  label: string;
  description: string;
  type: "string" | "integer" | "boolean" | "enum" | "secret" | "url";
  options: SettingOption[] | null;
  default: unknown;
  value: unknown;
  source: "database" | "environment" | "default" | "environment_locked";
  is_secret: boolean;
  is_configured: boolean;
  is_overridden: boolean;
  admin_editable: boolean;
  runtime_mutable: boolean;
  requires_restart: boolean;
  env_locked: boolean;
  env_fallback_key: string | null;
  dangerous: boolean;
  testable: boolean;
  sort_order: number;
  version: number;
  updated_at: string | null;
  updated_by: string | null;
};

export type EnvironmentDiagnostics = {
  app_name: string;
  app_env: string;
  app_debug: boolean;
  app_url: string;
  php_version: string;
  laravel_version: string;
  database: {
    default: string;
    status: string;
    runtime_role: string;
  };
  redis: {
    client: string;
    status: string;
  };
  cache: {
    default: string;
  };
  queue: {
    default: string;
  };
  session: {
    driver: string;
    secure: boolean;
  };
  storage: {
    default: string;
  };
  encryption: {
    configured: boolean;
    cipher: string;
  };
  logging: {
    channel: string;
    level: string;
  };
};

const CATEGORIES: { id: string; label: string; icon: string }[] = [
  { id: "general", label: "Общие", icon: "sliders" },
  { id: "auth", label: "Аутентификация", icon: "key" },
  { id: "ai_models", label: "Модели ИИ", icon: "cpu" },
  { id: "ai_providers", label: "Провайдеры ИИ", icon: "cloud" },
  { id: "email", label: "Почта и SMTP", icon: "mail" },
  { id: "storage", label: "Файлы и шаблоны", icon: "file-text" },
  { id: "limits", label: "Лимиты и хранение", icon: "activity" },
  { id: "environment", label: "Среда и диагностика", icon: "server" },
];

function SourceBadge({ source }: { source: string }) {
  if (source === "database") {
    return <span className="settings-badge badge-db" title="Значение переопределено в базе данных">Database override</span>;
  }
  if (source === "environment_locked") {
    return <span className="settings-badge badge-locked" title="Значение заблокировано конфигурацией окружения">Environment · Locked</span>;
  }
  if (source === "environment") {
    return <span className="settings-badge badge-env" title="Значение получено из переменных окружения">Environment</span>;
  }
  return <span className="settings-badge badge-default" title="Значение по умолчанию из кода приложения">Application default</span>;
}

export default function AdminSystemSettings() {
  const [settings, setSettings] = useState<SettingItem[]>([]);
  const [diagnostics, setDiagnostics] = useState<EnvironmentDiagnostics | null>(null);
  const [activeCategory, setActiveCategory] = useState<string>("general");
  const [searchQuery, setSearchQuery] = useState<string>("");
  const [loading, setLoading] = useState<boolean>(true);
  const [saving, setSaving] = useState<boolean>(false);
  const [feedback, setFeedback] = useState<{ text: string; type: "success" | "error" } | null>(null);

  // Staged edits
  const [stagedValues, setStagedValues] = useState<Record<string, unknown>>({});
  const [secretEdits, setSecretEdits] = useState<Record<string, string>>({});
  const [clearedSecrets, setClearedSecrets] = useState<Record<string, boolean>>({});

  // Integration test states: key -> { running, result, message, latency }
  const [testResults, setTestResults] = useState<Record<string, { success: boolean; message: string; latency_ms: number } | null>>({});
  const [runningTests, setRunningTests] = useState<Record<string, boolean>>({});

  const loadData = useCallback(async () => {
    setLoading(true);
    setFeedback(null);
    try {
      const [settingsRes, envRes] = await Promise.all([
        api("/api/v1/admin/settings"),
        api("/api/v1/admin/settings/environment"),
      ]);
      setSettings(settingsRes.data || []);
      setDiagnostics(envRes.data || null);
    } catch (err) {
      setFeedback({
        text: err instanceof Error ? err.message : "Не удалось загрузить системные настройки.",
        type: "error",
      });
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadData();
  }, [loadData]);

  const overridesCount = useMemo(
    () => settings.filter((s) => s.is_overridden).length,
    [settings]
  );
  const lockedCount = useMemo(
    () => settings.filter((s) => s.env_locked).length,
    [settings]
  );

  const unsavedCount = useMemo(() => {
    const valueKeys = Object.keys(stagedValues);
    const secretKeys = Object.keys(secretEdits).filter((k) => secretEdits[k].trim().length > 0);
    const clearedKeys = Object.keys(clearedSecrets).filter((k) => clearedSecrets[k]);
    const all = new Set([...valueKeys, ...secretKeys, ...clearedKeys]);
    return all.size;
  }, [stagedValues, secretEdits, clearedSecrets]);

  const filteredSettings = useMemo(() => {
    let list = settings;
    if (activeCategory !== "environment") {
      list = list.filter((s) => s.group === activeCategory);
    }
    if (searchQuery.trim().length > 0) {
      const q = searchQuery.toLowerCase().trim();
      list = settings.filter(
        (s) =>
          s.label.toLowerCase().includes(q) ||
          s.description.toLowerCase().includes(q) ||
          s.key.toLowerCase().includes(q)
      );
    }
    return list;
  }, [settings, activeCategory, searchQuery]);

  function handleStageChange(key: string, value: unknown) {
    setStagedValues((prev) => ({
      ...prev,
      [key]: value,
    }));
  }

  function handleDiscard() {
    setStagedValues({});
    setSecretEdits({});
    setClearedSecrets({});
    setFeedback(null);
  }

  async function handleSave() {
    if (unsavedCount === 0) return;
    setSaving(true);
    setFeedback(null);

    const payload: {
      key: string;
      value?: unknown;
      expected_version?: number;
      clear_secret?: boolean;
    }[] = [];

    // Collect standard changes
    for (const [key, val] of Object.entries(stagedValues)) {
      const orig = settings.find((s) => s.key === key);
      payload.push({
        key,
        value: val,
        expected_version: orig?.version,
      });
    }

    // Collect secret updates
    for (const [key, secretVal] of Object.entries(secretEdits)) {
      if (secretVal.trim().length > 0) {
        const orig = settings.find((s) => s.key === key);
        payload.push({
          key,
          value: secretVal,
          expected_version: orig?.version,
        });
      }
    }

    // Collect cleared secrets
    for (const [key, cleared] of Object.entries(clearedSecrets)) {
      if (cleared && !secretEdits[key]) {
        const orig = settings.find((s) => s.key === key);
        payload.push({
          key,
          clear_secret: true,
          expected_version: orig?.version,
        });
      }
    }

    try {
      await api("/api/v1/admin/settings", {
        method: "PATCH",
        body: JSON.stringify({ settings: payload }),
      });
      setStagedValues({});
      setSecretEdits({});
      setClearedSecrets({});
      await loadData();
      setFeedback({ text: "Настройки успешно сохранены.", type: "success" });
    } catch (err) {
      setFeedback({
        text: err instanceof Error ? err.message : "Ошибка при сохранении настроек.",
        type: "error",
      });
    } finally {
      setSaving(false);
    }
  }

  async function handleReset(key: string) {
    setSaving(true);
    setFeedback(null);
    try {
      await api("/api/v1/admin/settings/reset", {
        method: "POST",
        body: JSON.stringify({ key }),
      });
      // clear any staged values for this key
      setStagedValues((prev) => {
        const next = { ...prev };
        delete next[key];
        return next;
      });
      setSecretEdits((prev) => {
        const next = { ...prev };
        delete next[key];
        return next;
      });
      setClearedSecrets((prev) => {
        const next = { ...prev };
        delete next[key];
        return next;
      });
      setFeedback({ text: "Настройка сброшена к унаследованному значению.", type: "success" });
      await loadData();
    } catch (err) {
      setFeedback({
        text: err instanceof Error ? err.message : "Не удалось сбросить настройку.",
        type: "error",
      });
    } finally {
      setSaving(false);
    }
  }

  async function handleTest(target: "openai" | "smtp" | "storage") {
    setRunningTests((prev) => ({ ...prev, [target]: true }));
    setTestResults((prev) => ({ ...prev, [target]: null }));
    try {
      const res = await api("/api/v1/admin/settings/test", {
        method: "POST",
        body: JSON.stringify({ target }),
      });
      setTestResults((prev) => ({ ...prev, [target]: res.data }));
    } catch (err) {
      setTestResults((prev) => ({
        ...prev,
        [target]: {
          success: false,
          message: err instanceof Error ? err.message : "Сбой проверки соединения.",
          latency_ms: 0,
        },
      }));
    } finally {
      setRunningTests((prev) => ({ ...prev, [target]: false }));
    }
  }

  return (
    <div className="admin-settings-container">
      {/* Top Administration Navigation */}
      <nav className="admin-nav-breadcrumbs" aria-label="Раздел администрирования">
        <span className="admin-nav-title">Администрирование</span>
        <span className="admin-nav-separator">/</span>
        <Link href="/diagnostics" className="admin-nav-link">
          Журнал инцидентов
        </Link>
        <span className="admin-nav-separator">/</span>
        <span className="admin-nav-link disabled" title="Управление пользователями появится в M2">
          Пользователи <small className="badge-soon">M2</small>
        </span>
        <span className="admin-nav-separator">/</span>
        <span className="admin-nav-link disabled" title="Управление приглашениями появится в M2">
          Приглашения <small className="badge-soon">M2</small>
        </span>
        <span className="admin-nav-separator">/</span>
        <strong className="admin-nav-current" aria-current="page">
          Настройки системы
        </strong>
      </nav>

      {/* Control plane header */}
      <header className="settings-header">
        <div className="settings-header-left">
          <h1 className="settings-title">Системные настройки</h1>
          <p className="settings-subtitle">
            Управление runtime-конфигурацией CVortex ·{" "}
            <strong>{overridesCount}</strong> переопределений в БД ·{" "}
            <strong>{lockedCount}</strong> заблокировано средой
          </p>
        </div>
        <div className="settings-header-right">
          <div className="settings-search-wrapper">
            <input
              type="search"
              aria-label="Поиск настроек"
              placeholder="Поиск по названию или ключу..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="settings-search-input"
            />
            {searchQuery && (
              <button
                type="button"
                className="settings-search-clear"
                onClick={() => setSearchQuery("")}
                aria-label="Очистить поиск"
              >
                ✕
              </button>
            )}
          </div>
        </div>
      </header>

      {/* Global feedback message */}
      {feedback && (
        <div
          className={`settings-feedback-banner ${feedback.type}`}
          role={feedback.type === "error" ? "alert" : "status"}
        >
          <span>{feedback.text}</span>
          <button type="button" onClick={() => setFeedback(null)} aria-label="Закрыть">
            ✕
          </button>
        </div>
      )}

      {/* Main layout */}
      <div className="settings-layout">
        {/* Category Sidebar */}
        <aside className="settings-sidebar" aria-label="Категории настроек">
          <ul className="settings-category-list">
            {CATEGORIES.map((cat) => {
              const count =
                cat.id === "environment"
                  ? 0
                  : settings.filter((s) => s.group === cat.id && s.is_overridden).length;
              const isActive = activeCategory === cat.id && !searchQuery;
              return (
                <li key={cat.id}>
                  <button
                    type="button"
                    className={`settings-category-button ${isActive ? "active" : ""}`}
                    onClick={() => {
                      setActiveCategory(cat.id);
                      setSearchQuery("");
                    }}
                    aria-current={isActive ? "true" : undefined}
                  >
                    <span className="category-label">{cat.label}</span>
                    {count > 0 && <span className="category-counter">{count}</span>}
                  </button>
                </li>
              );
            })}
          </ul>
        </aside>

        {/* Settings Content Area */}
        <main className="settings-content" id="settings-content-main">
          {loading ? (
            <div className="settings-loading-card">
              <span className="loading-spinner" aria-hidden="true" />
              <p>Загрузка конфигурации системы...</p>
            </div>
          ) : activeCategory === "environment" && !searchQuery ? (
            /* Read-only Environment Diagnostics Section */
            <div className="settings-group-panel">
              <div className="group-heading">
                <h2>Среда и диагностика развертывания</h2>
                <p className="group-desc">
                  Диагностическая информация об инфраструктурном окружении, подключении к базам
                  данных, кэшу и криптографическим ключам. Данные настройки зафиксированы на уровне
                  развертывания (Bootstrap/ENV_ONLY).
                </p>
              </div>

              {diagnostics ? (
                <div className="diagnostics-grid">
                  <div className="diag-card">
                    <span className="diag-label">Окружение (APP_ENV)</span>
                    <strong className="diag-value">
                      {diagnostics.app_env}
                      <span className={`status-pill ${diagnostics.app_debug ? "warning" : "ok"}`}>
                        Debug: {diagnostics.app_debug ? "On" : "Off"}
                      </span>
                    </strong>
                    <small className="diag-sub">URL: {diagnostics.app_url}</small>
                  </div>

                  <div className="diag-card">
                    <span className="diag-label">Платформа и фреймворк</span>
                    <strong className="diag-value">PHP {diagnostics.php_version}</strong>
                    <small className="diag-sub">Laravel {diagnostics.laravel_version}</small>
                  </div>

                  <div className="diag-card">
                    <span className="diag-label">База данных</span>
                    <strong className="diag-value">
                      {diagnostics.database.default}
                      <span className={`status-pill ${diagnostics.database.status === "connected" ? "ok" : "danger"}`}>
                        {diagnostics.database.status}
                      </span>
                    </strong>
                    <small className="diag-sub">Роль: {diagnostics.database.runtime_role}</small>
                  </div>

                  <div className="diag-card">
                    <span className="diag-label">Redis</span>
                    <strong className="diag-value">
                      {diagnostics.redis.client}
                      <span className={`status-pill ${diagnostics.redis.status === "connected" ? "ok" : "danger"}`}>
                        {diagnostics.redis.status}
                      </span>
                    </strong>
                    <small className="diag-sub">Cache & Queues</small>
                  </div>

                  <div className="diag-card">
                    <span className="diag-label">Файловое хранилище</span>
                    <strong className="diag-value">{diagnostics.storage.default}</strong>
                    <small className="diag-sub">Сессия: {diagnostics.session.driver}</small>
                  </div>

                  <div className="diag-card">
                    <span className="diag-label">Мастер-ключ шифрования</span>
                    <strong className="diag-value">
                      <span className={`status-pill ${diagnostics.encryption.configured ? "ok" : "danger"}`}>
                        {diagnostics.encryption.configured ? "Сконфигурирован" : "Отсутствует"}
                      </span>
                    </strong>
                    <small className="diag-sub">Шифр: {diagnostics.encryption.cipher} (APP_KEY никогда не передаётся)</small>
                  </div>
                </div>
              ) : (
                <p>Данные среды недоступны.</p>
              )}
            </div>
          ) : (
            /* Configurable Settings List */
            <div className="settings-group-panel">
              <div className="group-heading">
                <h2>
                  {searchQuery
                    ? `Результаты поиска (${filteredSettings.length})`
                    : CATEGORIES.find((c) => c.id === activeCategory)?.label || "Настройки"}
                </h2>
                <p className="group-desc">
                  {searchQuery
                    ? `Найдено ${filteredSettings.length} настроек по запросу "${searchQuery}"`
                    : "Изменения применяются сразу после сохранения без перезапуска контейнеров."}
                </p>
              </div>

              {filteredSettings.length === 0 ? (
                <div className="settings-empty-state">
                  <p>Настройки не найдены.</p>
                </div>
              ) : (
                <div className="settings-items-list">
                  {filteredSettings.map((item) => {
                    const isStaged = stagedValues[item.key] !== undefined;
                    const currentValue = isStaged ? stagedValues[item.key] : item.value;
                    const isSecretUpdated = secretEdits[item.key] !== undefined && secretEdits[item.key].length > 0;
                    const isSecretCleared = clearedSecrets[item.key] === true;

                    return (
                      <div
                        key={item.key}
                        className={`setting-row ${item.dangerous ? "dangerous" : ""} ${isStaged || isSecretUpdated || isSecretCleared ? "dirty" : ""}`}
                      >
                        <div className="setting-info">
                          <div className="setting-title-line">
                            <label htmlFor={`input-${item.key}`} className="setting-label">
                              {item.label}
                            </label>
                            <SourceBadge source={item.source} />
                            {item.requires_restart && (
                              <span className="badge-warning" title="Требует перезапуска для полного применения">
                                Requires restart
                              </span>
                            )}
                            {item.dangerous && (
                              <span className="badge-danger" title="Влияет на сетевые эндпоинты">
                                High risk
                              </span>
                            )}
                          </div>
                          <p className="setting-description">{item.description}</p>
                          <details className="setting-advanced-details">
                            <summary>Техническая информация</summary>
                            <div className="advanced-props">
                              <span>Ключ: <code>{item.key}</code></span>
                              {item.env_fallback_key && <span>ENV fallback: <code>{item.env_fallback_key}</code></span>}
                              {item.updated_at && <span>Изменено: {new Date(item.updated_at).toLocaleString("ru")} ({item.updated_by || "admin"})</span>}
                            </div>
                          </details>
                        </div>

                        <div className="setting-control-area">
                          {/* Secret Type Field */}
                          {item.is_secret ? (
                            <div className="secret-field-wrapper">
                              <div className="secret-status-line">
                                <span className={`secret-indicator ${item.is_configured && !isSecretCleared ? "configured" : "empty"}`}>
                                  {isSecretCleared
                                    ? "Будет удалён"
                                    : isSecretUpdated
                                    ? "Новое значение готово к сохранению"
                                    : item.is_configured
                                    ? "Сконфигурирован (зашифрован)"
                                    : "Не настроен"}
                                </span>
                                {item.is_configured && !isSecretCleared && (
                                  <button
                                    type="button"
                                    className="button-link danger"
                                    onClick={() => {
                                      setClearedSecrets((prev) => ({ ...prev, [item.key]: true }));
                                      setSecretEdits((prev) => {
                                        const next = { ...prev };
                                        delete next[item.key];
                                        return next;
                                      });
                                    }}
                                  >
                                    Очистить
                                  </button>
                                )}
                              </div>
                              <input
                                id={`input-${item.key}`}
                                type="password"
                                autoComplete="new-password"
                                placeholder={item.is_configured ? "•••••••••••• (введите новый для замены)" : "Введите секретный ключ"}
                                value={secretEdits[item.key] || ""}
                                onChange={(e) => {
                                  setSecretEdits((prev) => ({ ...prev, [item.key]: e.target.value }));
                                  setClearedSecrets((prev) => ({ ...prev, [item.key]: false }));
                                }}
                                disabled={!item.admin_editable || item.env_locked}
                                className="setting-input-field"
                              />
                            </div>
                          ) : item.type === "boolean" ? (
                            /* Boolean Checkbox / Toggle */
                            <label className="toggle-switch-label">
                              <input
                                id={`input-${item.key}`}
                                type="checkbox"
                                checked={Boolean(currentValue)}
                                onChange={(e) => handleStageChange(item.key, e.target.checked)}
                                disabled={!item.admin_editable || item.env_locked}
                              />
                              <span className="toggle-slider" />
                              <span className="toggle-text">{currentValue ? "Включено" : "Выключено"}</span>
                            </label>
                          ) : item.type === "enum" ? (
                            /* Enum Select */
                            <select
                              id={`input-${item.key}`}
                              value={String(currentValue ?? item.default ?? "")}
                              onChange={(e) => handleStageChange(item.key, e.target.value)}
                              disabled={!item.admin_editable || item.env_locked}
                              className="setting-select-field"
                            >
                              {(item.options || []).map((opt) => (
                                <option key={opt.value} value={opt.value}>
                                  {opt.label}
                                </option>
                              ))}
                            </select>
                          ) : (
                            /* String / Integer / URL Input */
                            <input
                              id={`input-${item.key}`}
                              type={item.type === "integer" ? "number" : item.type === "url" ? "url" : "text"}
                              value={currentValue !== undefined && currentValue !== null ? String(currentValue) : ""}
                              onChange={(e) =>
                                handleStageChange(
                                  item.key,
                                  item.type === "integer" ? (e.target.value === "" ? null : Number(e.target.value)) : e.target.value
                                )
                              }
                              disabled={!item.admin_editable || item.env_locked}
                              className="setting-input-field"
                            />
                          )}

                          {/* Action Buttons: Reset override & Test connection */}
                          <div className="setting-actions-row">
                            {item.is_overridden && (
                              <button
                                type="button"
                                className="button-reset-override"
                                onClick={() => void handleReset(item.key)}
                                disabled={saving}
                                title="Удалить переопределение из БД и вернуться к значению по умолчанию"
                              >
                                Сбросить переопределение
                              </button>
                            )}

                            {item.testable && (
                              <button
                                type="button"
                                className="button-test-connection"
                                onClick={() => {
                                  if (item.key.includes("openai")) void handleTest("openai");
                                  else if (item.key.includes("mail")) void handleTest("smtp");
                                  else if (item.key.includes("storage")) void handleTest("storage");
                                }}
                                disabled={runningTests[item.key.includes("openai") ? "openai" : "smtp"] || saving}
                              >
                                {runningTests[item.key.includes("openai") ? "openai" : "smtp"]
                                  ? "Проверка..."
                                  : "Проверить соединение"}
                              </button>
                            )}
                          </div>

                          {/* Inline test result display */}
                          {item.testable && testResults[item.key.includes("openai") ? "openai" : "smtp"] && (
                            <div
                              className={`test-result-box ${testResults[item.key.includes("openai") ? "openai" : "smtp"]?.success ? "success" : "error"}`}
                            >
                              <span>{testResults[item.key.includes("openai") ? "openai" : "smtp"]?.message}</span>
                              {testResults[item.key.includes("openai") ? "openai" : "smtp"]?.latency_ms ? (
                                <small>({testResults[item.key.includes("openai") ? "openai" : "smtp"]?.latency_ms} ms)</small>
                              ) : null}
                            </div>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          )}
        </main>
      </div>

      {/* Sticky Save / Discard Bar */}
      {unsavedCount > 0 && (
        <aside className="settings-sticky-action-bar" aria-label="Панель сохранения изменений">
          <div className="sticky-bar-content">
            <span className="unsaved-badge">
              <strong>{unsavedCount}</strong> {unsavedCount === 1 ? "несохранённое изменение" : "несохранённых изменений"}
            </span>
            <div className="sticky-buttons">
              <button
                type="button"
                className="button-secondary"
                onClick={handleDiscard}
                disabled={saving}
              >
                Отменить
              </button>
              <button
                type="button"
                className="button-primary"
                onClick={() => void handleSave()}
                disabled={saving}
              >
                {saving ? "Сохранение..." : "Сохранить изменения"}
              </button>
            </div>
          </div>
        </aside>
      )}
    </div>
  );
}
