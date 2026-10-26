"use client";

import { FormEvent, useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { api } from "./access-shell";
import type { AiSettingsData } from "./ai-provider-settings";
import type { VacancyDetail } from "./vacancy-workspace";
import type { Fact } from "./career-claim";

type CareerOverview = {
  facts: Fact[];
  claims: unknown[];
  sources: unknown[];
};

type ImportStep = "idle" | "saving" | "extracting" | "matching" | "completed" | "failed";

export default function VacancyImportWorkspace() {
  const router = useRouter();

  const [sourceText, setSourceText] = useState("");
  const [sourceUrl, setSourceUrl] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");

  // AI info
  const [aiProvider, setAiProvider] = useState<string>("Google Gemini");
  const [aiModel, setAiModel] = useState<string>("gemini-2.0-flash");

  // Career facts guard
  const [confirmedFactsCount, setConfirmedFactsCount] = useState<number | null>(null);

  // Progress state
  const [currentStep, setCurrentStep] = useState<ImportStep>("idle");
  const [createdVacancyId, setCreatedVacancyId] = useState<string | null>(null);
  const [extractedRequirementsCount, setExtractedRequirementsCount] = useState<number>(0);

  const isMountedRef = useRef(true);
  const pollTimerRef = useRef<number | null>(null);

  useEffect(() => {
    isMountedRef.current = true;

    // Load active AI settings and career facts
    void Promise.allSettled([
      api("/api/v1/ai/settings"),
      api("/api/v1/career"),
    ]).then(([aiRes, careerRes]) => {
      if (!isMountedRef.current) return;

      if (aiRes.status === "fulfilled" && aiRes.value?.data) {
        const data: AiSettingsData = aiRes.value.data;
        const prov = data.default_provider || "gemini";
        const config = data[prov];
        setAiProvider(prov === "gemini" ? "Google Gemini" : "OpenAI");
        setAiModel(config?.default_model || (prov === "gemini" ? "gemini-2.0-flash" : "gpt-4o"));
      }

      if (careerRes.status === "fulfilled" && careerRes.value?.data) {
        const careerData = careerRes.value.data as CareerOverview;
        const count = careerData.facts?.filter((f) => f.status === "CONFIRMED").length ?? 0;
        setConfirmedFactsCount(count);
      } else {
        setConfirmedFactsCount(0);
      }
    });

    return () => {
      isMountedRef.current = false;
      if (pollTimerRef.current !== null) {
        window.clearInterval(pollTimerRef.current);
      }
    };
  }, []);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!sourceText.trim() || busy) return;

    setBusy(true);
    setError("");
    setCurrentStep("saving");

    try {
      // Step 1: Save snapshot
      const result = await api("/api/v1/vacancies", {
        method: "POST",
        body: JSON.stringify({
          source_text: sourceText.trim(),
          source_url: sourceUrl.trim() || null,
        }),
      });

      if (!isMountedRef.current) return;

      const vacancyId = result.data.id as string;
      setCreatedVacancyId(vacancyId);

      // Transition to Step 2: Extracting requirements
      setCurrentStep("extracting");

      // Poll until analysis completes
      const startTime = Date.now();
      const poll = async () => {
        try {
          const detailRes = await api(`/api/v1/vacancies/${vacancyId}`);
          if (!isMountedRef.current) return;

          const detail = detailRes.data as VacancyDetail;

          if (detail.requirements && detail.requirements.length > 0) {
            setExtractedRequirementsCount(detail.requirements.length);
          }

          if (detail.analysis_status === "COMPLETED") {
            setCurrentStep("matching");
            setTimeout(() => {
              if (!isMountedRef.current) return;
              setCurrentStep("completed");
              // Smooth transition to vacancy workspace
              setTimeout(() => {
                if (!isMountedRef.current) return;
                router.push(`/vacancies?opportunity=${vacancyId}&level=work#overview`);
              }, 1000);
            }, 600);
            if (pollTimerRef.current !== null) {
              window.clearInterval(pollTimerRef.current);
              pollTimerRef.current = null;
            }
          } else if (detail.analysis_status === "FAILED") {
            setCurrentStep("failed");
            setError(detail.error_code ? `Ошибка анализа: ${detail.error_code}` : "Не удалось извлечь требования из вакансии.");
            setBusy(false);
            if (pollTimerRef.current !== null) {
              window.clearInterval(pollTimerRef.current);
              pollTimerRef.current = null;
            }
          } else {
            // Still PENDING or RUNNING
            // If polling over 45s, show timeout warning but keep polling
            if (Date.now() - startTime > 45000) {
              setError("Анализ занимает больше времени, чем обычно. Вы можете перейти к вакансии позже.");
            }
          }
        } catch (pollErr) {
          // Non-fatal poll error, retry on next tick
          console.error("Poll check failed:", pollErr);
        }
      };

      // Initial check immediately, then poll every 1.5s
      await poll();
      pollTimerRef.current = window.setInterval(() => void poll(), 1500);
    } catch (err) {
      if (isMountedRef.current) {
        setError(err instanceof Error ? err.message : "Не удалось сохранить вакансию.");
        setCurrentStep("failed");
        setBusy(false);
      }
    }
  }

  const charCount = sourceText.length;
  const maxChars = 50000;

  return (
    <section className="vacancy-import-surface" aria-labelledby="import-heading">
      <div className="section-title">
        <div>
          <p className="eyebrow">Импорт вакансии</p>
          <h1 id="import-heading">Новая вакансия</h1>
        </div>
        <div className="ai-analyst-badge">
          <span className="badge-icon">⚡</span>
          <span>Анализ выполнит: <strong>{aiProvider}</strong> ({aiModel})</span>
        </div>
      </div>

      <p className="tagline">
        Вставьте текст описания вакансии. Исходный снимок сохранится неизменным, модель выделит требования, а система сопоставит их с вашей подтверждённой карьерой по 7 измерениям.
      </p>

      {/* Guard Notice if Career Facts == 0 */}
      {confirmedFactsCount === 0 && (
        <div className="guard-card informational" role="region" aria-label="Предупреждение о карьерных фактах">
          <div className="guard-header">
            <span className="guard-icon" aria-hidden="true">💡</span>
            <h3>Рекомендация перед анализом</h3>
          </div>
          <p>
            Требования вакансии будут успешно извлечены, но у вас пока <strong>нет подтверждённых фактов карьеры</strong>. Чтобы получить честную оценку соответствия (матчинг 7 измерений), рекомендуем также загрузить текст вашего резюме в раздел «Карьера».
          </p>
          <div className="guard-action">
            <Link href="/career" className="btn-action secondary-btn">
              Открыть Карьеру в новой вкладке ↗
            </Link>
          </div>
        </div>
      )}

      {error && (
        <div className="alert error" role="alert">
          <strong>Ошибка импорта</strong>
          <span>{error}</span>
          {createdVacancyId && (
            <Link href={`/vacancies?opportunity=${createdVacancyId}&level=work#overview`} className="btn-action">
              Перейти к вакансии →
            </Link>
          )}
        </div>
      )}

      {/* Progress Tracker (Visible when processing) */}
      {currentStep !== "idle" && (
        <div className="import-progress-card" role="region" aria-label="Прогресс обработки вакансии">
          <h3 className="progress-title">Обработка вакансии</h3>
          <ul className="progress-steps-list">
            <li className="step-item step-done">
              <span className="step-icon">✓</span>
              <div className="step-content">
                <strong>Шаг 1: Сохранение исходного снимка</strong>
                <small>Снимок вакансии зафиксирован в БД с RLS-изоляцией</small>
              </div>
            </li>

            <li className={`step-item ${currentStep === "extracting" ? "step-active" : ["matching", "completed"].includes(currentStep) ? "step-done" : "step-pending"}`}>
              <span className="step-icon">
                {currentStep === "extracting" ? <span className="spinner-dot" aria-hidden="true" /> : ["matching", "completed"].includes(currentStep) ? "✓" : "○"}
              </span>
              <div className="step-content">
                <strong>Шаг 2: Извлечение требований AI-моделью</strong>
                <small>
                  {currentStep === "extracting"
                    ? `${aiProvider} анализирует текст и нормализует требования…`
                    : extractedRequirementsCount > 0
                    ? `Извлечено требований: ${extractedRequirementsCount}`
                    : "Требования успешно извлечены"}
                </small>
              </div>
            </li>

            <li className={`step-item ${currentStep === "matching" ? "step-active" : currentStep === "completed" ? "step-done" : "step-pending"}`}>
              <span className="step-icon">
                {currentStep === "matching" ? <span className="spinner-dot" aria-hidden="true" /> : currentStep === "completed" ? "✓" : "○"}
              </span>
              <div className="step-content">
                <strong>Шаг 3: Сопоставление с карьерой (7 измерений)</strong>
                <small>Детерминированный матчинг по подтверждённым фактам</small>
              </div>
            </li>

            <li className={`step-item ${currentStep === "completed" ? "step-done" : "step-pending"}`}>
              <span className="step-icon">{currentStep === "completed" ? "✓" : "○"}</span>
              <div className="step-content">
                <strong>Шаг 4: Анализ завершён</strong>
                <small>Переход в рабочее пространство вакансии…</small>
              </div>
            </li>
          </ul>

          {currentStep === "completed" && createdVacancyId && (
            <div className="redirect-hint">
              <Link href={`/vacancies?opportunity=${createdVacancyId}&level=work#overview`} className="btn-action">
                Открыть карточку вакансии прямо сейчас →
              </Link>
            </div>
          )}
        </div>
      )}

      {/* Input Form */}
      {currentStep === "idle" && (
        <form onSubmit={(e) => void handleSubmit(e)} className="vacancy-import-form">
          <div className="form-group">
            <div className="label-row">
              <label htmlFor="source_text" className="form-label">
                Текст вакансии <span className="required">*</span>
              </label>
              <span className={`char-counter ${charCount > maxChars ? "char-overflow" : ""}`}>
                {charCount.toLocaleString("ru-RU")} / {maxChars.toLocaleString("ru-RU")} символов
              </span>
            </div>
            <textarea
              id="source_text"
              name="source_text"
              required
              rows={14}
              maxLength={maxChars}
              placeholder="Вставьте полное описание вакансии (требования, стек технологий, обязанности, условия, локация, формат работы)..."
              value={sourceText}
              onChange={(e) => setSourceText(e.target.value)}
              className="vacancy-textarea"
              disabled={busy}
            />
          </div>

          <div className="form-group">
            <label htmlFor="source_url" className="form-label">
              Ссылка на источник вакансии (опционально)
            </label>
            <input
              id="source_url"
              name="source_url"
              type="url"
              maxLength={2048}
              placeholder="https://hh.ru/vacancy/... или https://linkedin.com/jobs/..."
              value={sourceUrl}
              onChange={(e) => setSourceUrl(e.target.value)}
              className="vacancy-input"
              disabled={busy}
            />
            <small className="field-hint">
              Ссылка сохраняется как метаданные источника (source provenance). Автоматический скрейпинг внешних сайтов не производится в целях безопасности.
            </small>
          </div>

          <div className="form-actions">
            <button
              type="submit"
              disabled={busy || !sourceText.trim() || charCount > maxChars}
              className="submit-import-btn"
            >
              {busy ? "Сохранение…" : "Сохранить и проанализировать"}
            </button>
            <Link href="/vacancies" className="cancel-link">
              Отмена
            </Link>
          </div>
        </form>
      )}
    </section>
  );
}
