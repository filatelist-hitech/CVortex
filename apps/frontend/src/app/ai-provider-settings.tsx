"use client";

import { useEffect, useState, useId } from "react";
import { api } from "./access-shell";

export type ModelOption = string | { id: string; name: string };

export type ProviderConfig = {
  has_key: boolean;
  masked_key: string | null;
  default_model: string;
  available_models: ModelOption[];
  env_key_configured: boolean;
};

export type AiSettingsData = {
  default_provider: "gemini" | "openai";
  gemini: ProviderConfig;
  openai: ProviderConfig;
  providers?: {
    gemini?: {
      available_models?: ModelOption[];
      [key: string]: unknown;
    };
    openai?: {
      available_models?: ModelOption[];
      [key: string]: unknown;
    };
  };
};

function getModelId(model: ModelOption): string {
  return typeof model === "string" ? model : model.id;
}

function getModelLabel(model: ModelOption): string {
  return typeof model === "string" ? model : (model.name || model.id);
}

export type ConnectionTestResult = {
  status: "healthy" | "failed";
  provider: string;
  model: string;
  latency_ms?: number;
  message: string;
  recommendation?: string;
};

export default function AiProviderSettings() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [feedback, setFeedback] = useState<{ type: "success" | "error"; text: string } | null>(null);

  const [settings, setSettings] = useState<AiSettingsData | null>(null);
  const [selectedProvider, setSelectedProvider] = useState<"gemini" | "openai">("gemini");

  // Gemini state
  const [geminiKeyInput, setGeminiKeyInput] = useState("");
  const [geminiModel, setGeminiModel] = useState("");
  const [geminiKeyRemoved, setGeminiKeyRemoved] = useState(false);
  const [geminiTesting, setGeminiTesting] = useState(false);
  const [geminiTestResult, setGeminiTestResult] = useState<ConnectionTestResult | null>(null);
  const [refreshingGeminiModels, setRefreshingGeminiModels] = useState(false);

  // OpenAI state
  const [openaiKeyInput, setOpenaiKeyInput] = useState("");
  const [openaiModel, setOpenaiModel] = useState("");
  const [openaiKeyRemoved, setOpenaiKeyRemoved] = useState(false);
  const [openaiTesting, setOpenaiTesting] = useState(false);
  const [openaiTestResult, setOpenaiTestResult] = useState<ConnectionTestResult | null>(null);

  const geminiKeyId = useId();
  const geminiModelId = useId();
  const openaiKeyId = useId();
  const openaiModelId = useId();

  useEffect(() => {
    async function load() {
      try {
        setLoading(true);
        const res = await api("/api/v1/ai/settings");
        const data: AiSettingsData = res.data;
        if (data.providers?.gemini?.available_models) {
          data.gemini.available_models = data.providers.gemini.available_models;
        }
        if (data.providers?.openai?.available_models) {
          data.openai.available_models = data.providers.openai.available_models;
        }
        setSettings(data);
        setSelectedProvider(data.default_provider || "gemini");
        setGeminiModel(data.gemini.default_model);
        setOpenaiModel(data.openai.default_model);
      } catch (err) {
        setFeedback({
          type: "error",
          text: err instanceof Error ? err.message : "Не удалось загрузить настройки AI-провайдеров.",
        });
      } finally {
        setLoading(false);
      }
    }
    void load();
  }, []);

  async function handleTestConnection(provider: "gemini" | "openai") {
    const isGemini = provider === "gemini";
    const setTesting = isGemini ? setGeminiTesting : setOpenaiTesting;
    const setTestResult = isGemini ? setGeminiTestResult : setOpenaiTestResult;
    const keyInput = isGemini ? geminiKeyInput.trim() : openaiKeyInput.trim();
    const model = isGemini ? geminiModel : openaiModel;

    setTesting(true);
    setTestResult(null);
    try {
      const payload: { provider: string; api_key?: string; model?: string } = {
        provider,
        model,
      };
      if (keyInput) {
        payload.api_key = keyInput;
      }

      const res = await api("/api/v1/ai/test-connection", {
        method: "POST",
        body: JSON.stringify(payload),
      });

      if (isGemini && Array.isArray(res.available_models) && res.available_models.length > 0) {
        setSettings((prev) => {
          if (!prev) return prev;
          return {
            ...prev,
            gemini: {
              ...prev.gemini,
              available_models: res.available_models,
            },
          };
        });
      }

      if (res.model && typeof res.model === "string") {
        if (isGemini) {
          setGeminiModel(res.model);
        } else {
          setOpenaiModel(res.model);
        }
      }

      if (res.success === false || res.status === "failed") {
        setTestResult({
          status: "failed",
          provider,
          model: res.model || model,
          message: res.message || res.error_code || "Проверка подключения не удалась.",
          recommendation: res.recommendation,
        });
      } else {
        setTestResult({
          status: "healthy",
          provider,
          model: res.model,
          latency_ms: res.latency_ms,
          message: res.message || "Подключение успешно проверено.",
        });
      }
    } catch (err) {
      const rawMsg = err instanceof Error ? err.message : "Проверка подключения не удалась.";
      const isTimeout = rawMsg.includes("504") || rawMsg.toLowerCase().includes("gateway") || rawMsg.toLowerCase().includes("timeout");
      setTestResult({
        status: "failed",
        provider,
        model,
        message: isTimeout
          ? "Таймаут подключения (504): провайдер отвечает слишком долго. Попробуйте выбрать другую модель или повторите попытку."
          : rawMsg,
        recommendation: isTimeout
          ? "Модель может находиться в состоянии перегрузки в Google AI Studio или испытывать сетевые задержки. Рекомендуется выбрать gemini-1.5-flash или gemini-2.0-flash."
          : undefined,
      });
    } finally {
      setTesting(false);
    }
  }

  async function handleRefreshGeminiModels() {
    setRefreshingGeminiModels(true);
    setFeedback(null);
    try {
      const payload: { provider: string; api_key?: string } = { provider: "gemini" };
      const key = geminiKeyInput.trim();
      if (key) {
        payload.api_key = key;
      }
      const res = await api("/api/v1/ai/models", {
        method: "POST",
        body: JSON.stringify(payload),
      });
      const models = res.data?.models;
      if (Array.isArray(models) && models.length > 0) {
        setSettings((prev) => {
          if (!prev) return prev;
          return {
            ...prev,
            gemini: {
              ...prev.gemini,
              available_models: models,
            },
          };
        });
        const currentMatches = models.some((m: ModelOption) => getModelId(m) === geminiModel);
        if (!currentMatches) {
          setGeminiModel(getModelId(models[0]));
        }
        setFeedback({
          type: "success",
          text: `Список моделей Gemini обновлен: доступно ${models.length} моделей по ключу.`,
        });
      } else {
        setFeedback({
          type: "error",
          text: "По указанному ключу не обнаружено доступных моделей генерации контента.",
        });
      }
    } catch (err) {
      setFeedback({
        type: "error",
        text: err instanceof Error ? err.message : "Не удалось обновить список моделей Gemini.",
      });
    } finally {
      setRefreshingGeminiModels(false);
    }
  }

  async function handleSave(event: React.FormEvent) {
    event.preventDefault();
    if (!settings) return;

    setSaving(true);
    setFeedback(null);

    const payload: {
      default_provider: "gemini" | "openai";
      gemini_api_key?: string | null;
      gemini_model?: string;
      openai_api_key?: string | null;
      openai_model?: string;
    } = {
      default_provider: selectedProvider,
      gemini_model: geminiModel,
      openai_model: openaiModel,
    };

    if (geminiKeyRemoved) {
      payload.gemini_api_key = null;
    } else if (geminiKeyInput.trim()) {
      payload.gemini_api_key = geminiKeyInput.trim();
    }

    if (openaiKeyRemoved) {
      payload.openai_api_key = null;
    } else if (openaiKeyInput.trim()) {
      payload.openai_api_key = openaiKeyInput.trim();
    }

    try {
      const res = await api("/api/v1/ai/settings", {
        method: "PATCH",
        body: JSON.stringify(payload),
      });

      const updated: AiSettingsData = res.data;
      setSettings(updated);
      setSelectedProvider(updated.default_provider);
      setGeminiKeyInput("");
      setGeminiKeyRemoved(false);
      setOpenaiKeyInput("");
      setOpenaiKeyRemoved(false);
      setFeedback({
        type: "success",
        text: "Настройки AI-провайдеров успешно сохранены.",
      });
    } catch (err) {
      setFeedback({
        type: "error",
        text: err instanceof Error ? err.message : "Ошибка при сохранении настроек.",
      });
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return (
      <div className="settings-shell">
        <p className="loading">Загрузка конфигурации AI-провайдеров…</p>
      </div>
    );
  }

  if (!settings) {
    return (
      <div className="settings-shell">
        {feedback && <p className="alert" role="alert">{feedback.text}</p>}
      </div>
    );
  }

  const geminiConfig = settings.gemini;
  const openaiConfig = settings.openai;

  return (
    <div className="settings-shell">
      <header className="settings-header">
        <div>
          <h1>Настройки AI-провайдеров</h1>
          <p className="subtle">
            Управление LLM-провайдерами (Google Gemini, OpenAI), ключами API (BYOK) и маршрутизацией запросов.
          </p>
        </div>
      </header>

      {feedback && (
        <div
          className={feedback.type === "success" ? "settings-alert success" : "settings-alert error"}
          role={feedback.type === "error" ? "alert" : "status"}
        >
          <span>{feedback.text}</span>
          <button type="button" onClick={() => setFeedback(null)} aria-label="Закрыть уведомление">
            ×
          </button>
        </div>
      )}

      <form onSubmit={handleSave} className="settings-form">
        {/* Выбор активного провайдера */}
        <section className="settings-section" aria-labelledby="default-provider-heading">
          <h2 id="default-provider-heading">Активный провайдер по умолчанию</h2>
          <p className="subtle">
            Используется для извлечения фактов, разбора требований вакансий и генерации материалов.
          </p>

          <div className="provider-selector" role="radiogroup" aria-labelledby="default-provider-heading">
            <label className={`provider-choice ${selectedProvider === "gemini" ? "selected" : ""}`}>
              <input
                type="radio"
                name="default_provider"
                value="gemini"
                checked={selectedProvider === "gemini"}
                onChange={() => setSelectedProvider("gemini")}
              />
              <span className="choice-title">
                <strong>Google Gemini</strong>
                <span className="choice-badge">Рекомендуется</span>
              </span>
              <small>Быстрый, экономичный контекст (Gemini 1.5 / 2.0 Flash & Pro) с нативным OpenAPI structured output.</small>
            </label>

            <label className={`provider-choice ${selectedProvider === "openai" ? "selected" : ""}`}>
              <input
                type="radio"
                name="default_provider"
                value="openai"
                checked={selectedProvider === "openai"}
                onChange={() => setSelectedProvider("openai")}
              />
              <span className="choice-title">
                <strong>OpenAI</strong>
              </span>
              <small>Модели GPT-4o и GPT-4o-mini через официальный Responses API.</small>
            </label>
          </div>
        </section>

        {/* Сетка настроек провайдеров */}
        <div className="providers-grid">
          {/* Карточка Google Gemini */}
          <section
            className={`provider-card ${selectedProvider === "gemini" ? "active-provider" : ""}`}
            aria-labelledby="gemini-card-heading"
          >
            <div className="provider-card-header">
              <div>
                <h3 id="gemini-card-heading">Google Gemini</h3>
                <span className="subtle">API v1beta · REST via Laravel HTTP</span>
              </div>
              {selectedProvider === "gemini" && (
                <span className="badge confirmed-badge" aria-label="Активный провайдер">
                  Активен
                </span>
              )}
            </div>

            <div className="provider-status-row">
              <span className="status-label">Системный ключ (.env):</span>
              <span className={geminiConfig.env_key_configured ? "badge confirmed-badge" : "badge blocked-badge"}>
                {geminiConfig.env_key_configured ? "Настроен" : "Не настроен"}
              </span>
            </div>

            {/* Персональный API ключ (BYOK) */}
            <div className="form-group">
              <label htmlFor={geminiKeyId}>
                Персональный API-ключ Gemini
                <small className="help-text">
                  Шифруется при сохранении (AES-256-CBC) и никогда не передаётся в браузер в открытом виде.
                </small>
              </label>

              {geminiConfig.has_key && !geminiKeyRemoved ? (
                <div className="existing-key-row">
                  <span className="key-display">
                    <code>{geminiConfig.masked_key}</code>
                    <span className="badge confirmed-badge">Сохранён в БД</span>
                  </span>
                  <button
                    type="button"
                    className="secondary danger-text"
                    onClick={() => {
                      setGeminiKeyRemoved(true);
                      setGeminiKeyInput("");
                    }}
                  >
                    Удалить ключ
                  </button>
                </div>
              ) : null}

              {geminiKeyRemoved && (
                <p className="removed-key-notice">
                  Ключ будет удален после сохранения настроек.
                  <button
                    type="button"
                    className="link-button"
                    onClick={() => setGeminiKeyRemoved(false)}
                  >
                    Отменить
                  </button>
                </p>
              )}

              {(!geminiConfig.has_key || geminiKeyRemoved) && (
                <input
                  id={geminiKeyId}
                  type="password"
                  placeholder="AIzaSy..."
                  value={geminiKeyInput}
                  autoComplete="off"
                  onChange={(e) => setGeminiKeyInput(e.target.value)}
                />
              )}

              {geminiConfig.has_key && !geminiKeyRemoved && (
                <input
                  id={geminiKeyId}
                  type="password"
                  placeholder="Задать новый ключ Gemini (для замены существующего)"
                  value={geminiKeyInput}
                  autoComplete="off"
                  onChange={(e) => setGeminiKeyInput(e.target.value)}
                />
              )}
            </div>

            {/* Выбор модели Gemini */}
            <div className="form-group">
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                <label htmlFor={geminiModelId}>Модель Gemini по умолчанию</label>
                <button
                  type="button"
                  className="link-button"
                  style={{ fontSize: "var(--font-size-xs)" }}
                  disabled={refreshingGeminiModels}
                  onClick={() => void handleRefreshGeminiModels()}
                >
                  {refreshingGeminiModels ? "Обновление моделей…" : "Обновить список по ключу"}
                </button>
              </div>
              <select
                id={geminiModelId}
                value={geminiModel}
                onChange={(e) => setGeminiModel(e.target.value)}
              >
                {geminiConfig.available_models.map((m) => {
                  const id = getModelId(m);
                  const label = getModelLabel(m);
                  return (
                    <option key={id} value={id}>
                      {label}
                    </option>
                  );
                })}
              </select>
              {geminiConfig.available_models.length > 0 && (
                <small className="help-text">
                  Доступно моделей по ключу: {geminiConfig.available_models.length}. Все полученные модели готовы к работе.
                </small>
              )}
            </div>

            {/* Тестирование подключения Gemini */}
            <div className="test-connection-block">
              <button
                type="button"
                className="secondary"
                disabled={geminiTesting}
                onClick={() => handleTestConnection("gemini")}
              >
                {geminiTesting ? "Проверка подключения…" : "Проверить подключение Gemini"}
              </button>

              {geminiTestResult && (
                <div
                  className={`test-result-box ${geminiTestResult.status === "healthy" ? "healthy" : "failed"}`}
                  role="status"
                >
                  <strong>{geminiTestResult.status === "healthy" ? "✓ Успешно" : "✗ Ошибка"}</strong>:{" "}
                  {geminiTestResult.message}
                  {geminiTestResult.latency_ms !== undefined && (
                    <small> ({geminiTestResult.latency_ms} мс · {geminiTestResult.model})</small>
                  )}
                  {geminiTestResult.recommendation && (
                    <div className="test-recommendation">{geminiTestResult.recommendation}</div>
                  )}
                </div>
              )}
            </div>
          </section>

          {/* Карточка OpenAI */}
          <section
            className={`provider-card ${selectedProvider === "openai" ? "active-provider" : ""}`}
            aria-labelledby="openai-card-heading"
          >
            <div className="provider-card-header">
              <div>
                <h3 id="openai-card-heading">OpenAI</h3>
                <span className="subtle">API v1 · Responses API</span>
              </div>
              {selectedProvider === "openai" && (
                <span className="badge confirmed-badge" aria-label="Активный провайдер">
                  Активен
                </span>
              )}
            </div>

            <div className="provider-status-row">
              <span className="status-label">Системный ключ (.env):</span>
              <span className={openaiConfig.env_key_configured ? "badge confirmed-badge" : "badge blocked-badge"}>
                {openaiConfig.env_key_configured ? "Настроен" : "Не настроен"}
              </span>
            </div>

            {/* Персональный API ключ (BYOK) OpenAI */}
            <div className="form-group">
              <label htmlFor={openaiKeyId}>
                Персональный API-ключ OpenAI
                <small className="help-text">
                  Шифруется при сохранении (AES-256-CBC) и никогда не передаётся в браузер в открытом виде.
                </small>
              </label>

              {openaiConfig.has_key && !openaiKeyRemoved ? (
                <div className="existing-key-row">
                  <span className="key-display">
                    <code>{openaiConfig.masked_key}</code>
                    <span className="badge confirmed-badge">Сохранён в БД</span>
                  </span>
                  <button
                    type="button"
                    className="secondary danger-text"
                    onClick={() => {
                      setOpenaiKeyRemoved(true);
                      setOpenaiKeyInput("");
                    }}
                  >
                    Удалить ключ
                  </button>
                </div>
              ) : null}

              {openaiKeyRemoved && (
                <p className="removed-key-notice">
                  Ключ будет удален после сохранения настроек.
                  <button
                    type="button"
                    className="link-button"
                    onClick={() => setOpenaiKeyRemoved(false)}
                  >
                    Отменить
                  </button>
                </p>
              )}

              {(!openaiConfig.has_key || openaiKeyRemoved) && (
                <input
                  id={openaiKeyId}
                  type="password"
                  placeholder="sk-proj-..."
                  value={openaiKeyInput}
                  autoComplete="off"
                  onChange={(e) => setOpenaiKeyInput(e.target.value)}
                />
              )}

              {openaiConfig.has_key && !openaiKeyRemoved && (
                <input
                  id={openaiKeyId}
                  type="password"
                  placeholder="Задать новый ключ OpenAI (для замены существующего)"
                  value={openaiKeyInput}
                  autoComplete="off"
                  onChange={(e) => setOpenaiKeyInput(e.target.value)}
                />
              )}
            </div>

            {/* Выбор модели OpenAI */}
            <div className="form-group">
              <label htmlFor={openaiModelId}>Модель OpenAI по умолчанию</label>
              <select
                id={openaiModelId}
                value={openaiModel}
                onChange={(e) => setOpenaiModel(e.target.value)}
              >
                {openaiConfig.available_models.map((m) => {
                  const id = getModelId(m);
                  const label = getModelLabel(m);
                  return (
                    <option key={id} value={id}>
                      {label}
                    </option>
                  );
                })}
              </select>
            </div>

            {/* Тестирование подключения OpenAI */}
            <div className="test-connection-block">
              <button
                type="button"
                className="secondary"
                disabled={openaiTesting}
                onClick={() => handleTestConnection("openai")}
              >
                {openaiTesting ? "Проверка подключения…" : "Проверить подключение OpenAI"}
              </button>

              {openaiTestResult && (
                <div
                  className={`test-result-box ${openaiTestResult.status === "healthy" ? "healthy" : "failed"}`}
                  role="status"
                >
                  <strong>{openaiTestResult.status === "healthy" ? "✓ Успешно" : "✗ Ошибка"}</strong>:{" "}
                  {openaiTestResult.message}
                  {openaiTestResult.latency_ms !== undefined && (
                    <small> ({openaiTestResult.latency_ms} мс · {openaiTestResult.model})</small>
                  )}
                  {openaiTestResult.recommendation && (
                    <div className="test-recommendation">{openaiTestResult.recommendation}</div>
                  )}
                </div>
              )}
            </div>
          </section>
        </div>

        {/* Кнопка сохранения */}
        <div className="settings-actions">
          <button type="submit" disabled={saving}>
            {saving ? "Сохранение настроек…" : "Сохранить настройки"}
          </button>
        </div>
      </form>
    </div>
  );
}
