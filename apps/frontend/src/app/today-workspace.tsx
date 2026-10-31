"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { api } from "./access-shell";
import type { AiSettingsData, ConnectionTestResult, ModelOption } from "./ai-provider-settings";
import type { VacancySummary } from "./vacancy-workspace";
import type { Fact } from "./career-claim";

type CareerOverview = {
  facts: Fact[];
  claims: unknown[];
  sources: unknown[];
};

function getModelId(model: ModelOption): string {
  return typeof model === "string" ? model : model.id;
}

function getModelLabel(model: ModelOption): string {
  return typeof model === "string" ? model : (model.name || model.id);
}

const recommendationLabels: Record<string, string> = {
  STRONGLY_APPLY: "Рекомендуется (Высокий приоритет)",
  APPLY: "Рекомендуется откликнуться",
  MAYBE: "Средний приоритет",
  LOW_PRIORITY: "Низкий приоритет",
  SKIP: "Пропустить",
};

export default function TodayWorkspace({ email, role }: { email: string; role: string }) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  // AI settings state
  const [aiSettings, setAiSettings] = useState<AiSettingsData | null>(null);
  const [provider, setProvider] = useState<"gemini" | "openai">("gemini");
  const [apiKeyInput, setApiKeyInput] = useState("");
  const [showKeyInput, setShowKeyInput] = useState(false);
  const [testingAi, setTestingAi] = useState(false);
  const [testResult, setTestResult] = useState<ConnectionTestResult | null>(null);
  const [savingSettings, setSavingSettings] = useState(false);

  // Career state
  const [confirmedFactsCount, setConfirmedFactsCount] = useState<number | null>(null);

  // Vacancies state
  const [vacancies, setVacancies] = useState<VacancySummary[]>([]);

  const isMountedRef = useRef(true);
  const activeAbortControllerRef = useRef<AbortController | null>(null);
  const actionAbortControllerRef = useRef<AbortController | null>(null);

  const loadData = useCallback(async () => {
    if (activeAbortControllerRef.current) {
      activeAbortControllerRef.current.abort();
    }
    const controller = new AbortController();
    activeAbortControllerRef.current = controller;

    try {
      setLoading(true);
      setError("");

      const [aiRes, careerRes, vacanciesRes] = await Promise.allSettled([
        api("/api/v1/ai/settings", { signal: controller.signal }),
        api("/api/v1/career", { signal: controller.signal }),
        api("/api/v1/vacancies", { signal: controller.signal }),
      ]);

      if (controller.signal.aborted || !isMountedRef.current) return;

      if (aiRes.status === "fulfilled" && aiRes.value?.data) {
        const data: AiSettingsData = aiRes.value.data;
        if (data.providers?.gemini?.available_models) {
          data.gemini.available_models = data.providers.gemini.available_models;
        }
        if (data.providers?.openai?.available_models) {
          data.openai.available_models = data.providers.openai.available_models;
        }
        setAiSettings(data);
        setProvider(data.default_provider || "gemini");
      }

      if (careerRes.status === "fulfilled" && careerRes.value?.data) {
        const careerData = careerRes.value.data as CareerOverview;
        const confirmed = careerData.facts.filter((f) => f.status === "CONFIRMED").length;
        setConfirmedFactsCount(confirmed);
      } else {
        setConfirmedFactsCount(0);
      }

      if (vacanciesRes.status === "fulfilled" && vacanciesRes.value?.data) {
        setVacancies(vacanciesRes.value.data as VacancySummary[]);
      }
    } catch (err) {
      if (err instanceof Error && err.name === "AbortError") {
        return;
      }
      if (!controller.signal.aborted && isMountedRef.current) {
        setError(err instanceof Error ? err.message : "Не удалось загрузить данные дашборда.");
      }
    } finally {
      if (activeAbortControllerRef.current === controller) {
        activeAbortControllerRef.current = null;
        if (isMountedRef.current) {
          setLoading(false);
        }
      }
    }
  }, []);

  useEffect(() => {
    isMountedRef.current = true;
    // Owner-scoped API hydration is the external synchronization performed by this effect.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void loadData();
    return () => {
      isMountedRef.current = false;
      if (activeAbortControllerRef.current) {
        activeAbortControllerRef.current.abort();
        activeAbortControllerRef.current = null;
      }
      if (actionAbortControllerRef.current) {
        actionAbortControllerRef.current.abort();
        actionAbortControllerRef.current = null;
      }
    };
  }, [loadData]);

  // Switch default provider in 1 click
  async function handleSwitchProvider(targetProvider: "gemini" | "openai") {
    if (provider === targetProvider || savingSettings) return;
    setProvider(targetProvider);
    setTestResult(null);
    setShowKeyInput(false);
    setApiKeyInput("");
    setSavingSettings(true);
    try {
      await api("/api/v1/ai/settings", {
        method: "PATCH",
        body: JSON.stringify({ default_provider: targetProvider }),
      });
      if (aiSettings) {
        setAiSettings({ ...aiSettings, default_provider: targetProvider });
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : "Не удалось переключить AI-провайдера.");
      // Rollback on error
      if (aiSettings) setProvider(aiSettings.default_provider || "gemini");
    } finally {
      setSavingSettings(false);
    }
  }

  // Switch model for active provider
  async function handleModelChange(modelId: string) {
    if (!aiSettings || savingSettings) return;
    setSavingSettings(true);
    try {
      await api("/api/v1/ai/settings", {
        method: "PATCH",
        body: JSON.stringify({
          [provider]: {
            default_model: modelId,
          },
        }),
      });
      setAiSettings({
        ...aiSettings,
        [provider]: {
          ...aiSettings[provider],
          default_model: modelId,
        },
      });
    } catch (err) {
      setError(err instanceof Error ? err.message : "Не удалось изменить модель.");
    } finally {
      setSavingSettings(false);
    }
  }

  // Save new BYOK API key
  async function handleSaveKey() {
    if (!apiKeyInput.trim() || savingSettings) return;
    setSavingSettings(true);
    try {
      await api("/api/v1/ai/settings", {
        method: "PATCH",
        body: JSON.stringify({
          [provider]: {
            api_key: apiKeyInput.trim(),
          },
        }),
      });
      setApiKeyInput("");
      setShowKeyInput(false);
      // Reload settings to refresh masked key
      const res = await api("/api/v1/ai/settings");
      if (res?.data && isMountedRef.current) {
        setAiSettings(res.data);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : "Не удалось сохранить API-ключ.");
    } finally {
      setSavingSettings(false);
    }
  }

  // Test active connection with ping latency
  async function handleTestConnection() {
    if (testingAi || !aiSettings) return;
    if (actionAbortControllerRef.current) {
      actionAbortControllerRef.current.abort();
    }
    const controller = new AbortController();
    actionAbortControllerRef.current = controller;

    setTestingAi(true);
    setTestResult(null);

    const activeConfig = aiSettings[provider];
    const model = activeConfig.default_model;

    try {
      const payload: { provider: string; model?: string; api_key?: string } = {
        provider,
        model,
      };
      if (apiKeyInput.trim()) {
        payload.api_key = apiKeyInput.trim();
      }

      const res = await api("/api/v1/ai/test-connection", {
        method: "POST",
        body: JSON.stringify(payload),
        signal: controller.signal,
      });

      if (controller.signal.aborted || !isMountedRef.current) return;

      if (res?.data) {
        const result: ConnectionTestResult = res.data;
        setTestResult(result);

        // If returned available models, update catalog
        if (result.status === "healthy" && (res.data.available_models as ModelOption[] | undefined)) {
          setAiSettings((prev) => {
            if (!prev) return prev;
            return {
              ...prev,
              [provider]: {
                ...prev[provider],
                available_models: res.data.available_models as ModelOption[],
              },
            };
          });
        }
      }
    } catch (err) {
      if (err instanceof Error && err.name === "AbortError") {
        return;
      }
      if (!controller.signal.aborted && isMountedRef.current) {
        setTestResult({
          status: "failed",
          provider,
          model,
          message: err instanceof Error ? err.message : "Ошибка проверки подключения к AI.",
        });
      }
    } finally {
      if (actionAbortControllerRef.current === controller) {
        actionAbortControllerRef.current = null;
        if (isMountedRef.current) {
          setTestingAi(false);
        }
      }
    }
  }

  const currentConfig = aiSettings ? aiSettings[provider] : null;
  const availableModels = currentConfig?.available_models ?? [];
  const activeModel = currentConfig?.default_model ?? (provider === "gemini" ? "gemini-2.0-flash" : "gpt-4o");

  return (
    <section className="today-workspace" aria-labelledby="today-heading">
      <div className="section-title">
        <div>
          <p className="eyebrow">Командный центр CVortex</p>
          <h1 id="today-heading">Сегодня</h1>
        </div>
        <span className="user-role-badge badge neutral">{email} · {role === "admin" ? "Администратор" : "Кандидат"}</span>
      </div>

      <p className="tagline">
        Персональная операционная система поиска работы: проверенные факты карьеры, точный разбор вакансий и генерация откликов Truth-first.
      </p>

      {error && (
        <div className="alert error" role="alert">
          <strong>Ошибка дашборда</strong>
          <span>{error}</span>
          <button type="button" className="secondary" onClick={() => void loadData()}>
            Обновить данные
          </button>
        </div>
      )}

      {/* Primary CTAs */}
      <div className="today-primary-actions">
        <Link href="/vacancies/import" className="cta-button primary-pill">
          <svg className="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M12 5v14m-7-7h14" />
          </svg>
          <span>+ Импортировать вакансию</span>
        </Link>
        <Link href="/career" className="cta-button secondary-pill">
          <svg className="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <circle cx="12" cy="7" r="4" />
            <path d="M5 21v-3a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v3" />
          </svg>
          <span>Загрузить резюме в Карьеру</span>
        </Link>
      </div>

      {/* Smart Warning: 0 Confirmed Career Facts */}
      {confirmedFactsCount === 0 && !loading && (
        <div className="guard-card warning" role="region" aria-label="Предупреждение о фактах карьеры">
          <div className="guard-header">
            <span className="guard-icon" aria-hidden="true">⚠️</span>
            <h3>База карьерного опыта пуста</h3>
          </div>
          <p>
            У вас пока <strong>0 подтверждённых фактов карьеры</strong>. Чтобы алгоритмы Truth-first могли честно сопоставлять требования вакансий по 7 измерениям и готовить сопроводительные письма без галлюцинаций, добавьте ваш опыт в Карьеру.
          </p>
          <div className="guard-action">
            <Link href="/career" className="btn-action">
              Добавить опыт и подтвердить факты →
            </Link>
          </div>
        </div>
      )}

      {/* Grid: AI Widget + Readiness Summary */}
      <div className="today-grid">
        {/* Interactive AI Widget */}
        <div className="today-card ai-control-card">
          <div className="card-heading">
            <div className="heading-with-badge">
              <h3>Активный AI-провайдер</h3>
              <span className={`status-pill ${currentConfig?.has_key || currentConfig?.env_key_configured ? "ready" : "unconfigured"}`}>
                <span className="pulse-dot" aria-hidden="true" />
                {currentConfig?.has_key || currentConfig?.env_key_configured ? "Подключен" : "Требуется ключ"}
              </span>
            </div>
            <p className="card-subtitle">
              Управление мультипровайдерным ядром интеллекта (Google Gemini & OpenAI)
            </p>
          </div>

          {/* 1-Click Provider Switcher */}
          <div className="provider-tabs" role="tablist" aria-label="Выбор провайдера AI">
            <button
              type="button"
              role="tab"
              aria-selected={provider === "gemini"}
              className={`provider-tab ${provider === "gemini" ? "active" : ""}`}
              onClick={() => void handleSwitchProvider("gemini")}
              disabled={savingSettings}
            >
              <span className="provider-icon">⚡</span>
              <span className="provider-name">Google Gemini</span>
            </button>
            <button
              type="button"
              role="tab"
              aria-selected={provider === "openai"}
              className={`provider-tab ${provider === "openai" ? "active" : ""}`}
              onClick={() => void handleSwitchProvider("openai")}
              disabled={savingSettings}
            >
              <span className="provider-icon">🤖</span>
              <span className="provider-name">OpenAI</span>
            </button>
          </div>

          {/* Active Model Selector */}
          <div className="ai-field-group">
            <label htmlFor="ai-model-select">Модель для анализа и откликов:</label>
            <select
              id="ai-model-select"
              value={activeModel}
              onChange={(e) => void handleModelChange(e.target.value)}
              disabled={savingSettings || loading}
              className="select-field"
            >
              {availableModels.length > 0 ? (
                availableModels.map((m) => {
                  const id = getModelId(m);
                  return (
                    <option key={id} value={id}>
                      {getModelLabel(m)}
                    </option>
                  );
                })
              ) : (
                <option value={activeModel}>{activeModel}</option>
              )}
            </select>
          </div>

          {/* BYOK Key Status & Input */}
          <div className="ai-field-group key-group">
            <div className="key-header">
              <span>BYOK API-ключ:</span>
              {currentConfig?.has_key && !showKeyInput && (
                <button
                  type="button"
                  className="link-button"
                  onClick={() => setShowKeyInput(true)}
                >
                  Заменить ключ
                </button>
              )}
            </div>

            {currentConfig?.has_key && !showKeyInput ? (
              <div className="key-masked-display">
                <code>{currentConfig.masked_key || "••••••••••••••••"}</code>
                <span className="badge small neutral">Сохранён в RLS</span>
              </div>
            ) : (
              <div className="key-input-row">
                <input
                  type="password"
                  placeholder={provider === "gemini" ? "AIzaSy..." : "sk-..."}
                  value={apiKeyInput}
                  onChange={(e) => setApiKeyInput(e.target.value)}
                  className="input-field"
                  autoComplete="off"
                />
                <button
                  type="button"
                  onClick={() => void handleSaveKey()}
                  disabled={!apiKeyInput.trim() || savingSettings}
                  className="btn-save-key"
                >
                  {savingSettings ? "…" : "Сохранить"}
                </button>
                {currentConfig?.has_key && (
                  <button
                    type="button"
                    onClick={() => setShowKeyInput(false)}
                    className="btn-cancel"
                  >
                    Отмена
                  </button>
                )}
              </div>
            )}
          </div>

          {/* Test Connection Button & Latency Output */}
          <div className="connection-test-row">
            <button
              type="button"
              onClick={() => void handleTestConnection()}
              disabled={testingAi || loading}
              className="btn-test-connection"
            >
              {testingAi ? "Проверка связи…" : "⚡ Проверить подключение"}
            </button>

            {testResult && (
              <div
                className={`ping-result ${testResult.status === "healthy" ? "ping-healthy" : "ping-failed"}`}
                role="status"
              >
                {testResult.status === "healthy" ? (
                  <>
                    <span className="ping-dot" aria-hidden="true" />
                    <strong>{testResult.latency_ms !== undefined ? `${testResult.latency_ms} мс` : "OK"}</strong>
                    <span>· Подключение активно</span>
                  </>
                ) : (
                  <span>Ошибка: {testResult.message}</span>
                )}
              </div>
            )}
          </div>

          {/* Feature Badges */}
          <div className="ai-features-badges">
            <span className="feature-badge">✦ Извлечение фактов из резюме</span>
            <span className="feature-badge">✦ Разбор требований вакансий</span>
            <span className="feature-badge">✦ Генерация откликов Truth Guard</span>
          </div>
        </div>

        {/* Readiness Metrics Card */}
        <div className="today-card readiness-card">
          <div className="card-heading">
            <h3>Сводка готовности кандидата</h3>
            <p className="card-subtitle">Текущий статус профиля и воронки вакансий</p>
          </div>

          <div className="metrics-row">
            <div className="metric-box">
              <span className="metric-number">{confirmedFactsCount !== null ? confirmedFactsCount : "…"}</span>
              <span className="metric-label">Подтверждённых фактов карьеры</span>
              <Link href="/career" className="metric-link">
                Управление опытом →
              </Link>
            </div>

            <div className="metric-box">
              <span className="metric-number">{vacancies.length}</span>
              <span className="metric-label">Отслеживаемых вакансий</span>
              <Link href="/vacancies" className="metric-link">
                Список вакансий →
              </Link>
            </div>
          </div>

          {/* Quick Recent Vacancies */}
          <div className="recent-vacancies-block">
            <h4>Последние вакансии</h4>
            {vacancies.length === 0 ? (
              <p className="subtle">Вакансий пока нет. Нажмите «+ Импортировать вакансию» выше.</p>
            ) : (
              <ul className="recent-vacancies-list">
                {vacancies.slice(0, 4).map((v) => (
                  <li key={v.id} className="recent-vacancy-item">
                    <Link href={`/vacancies?opportunity=${v.id}&level=work#overview`} className="vacancy-quick-link">
                      <div className="item-main">
                        <strong className="item-title">{v.title || "Вакансия без названия"}</strong>
                        <span className="item-company">{v.company || "Компания не указана"}</span>
                      </div>
                      <div className="item-meta">
                        {v.recommendation && (
                          <span className={`badge small ${v.recommendation.toLowerCase()}`}>
                            {recommendationLabels[v.recommendation] ?? v.recommendation}
                          </span>
                        )}
                        <span className="status-label">{v.analysis_status}</span>
                      </div>
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      </div>
    </section>
  );
}
