import { ApplicationDraftPanel } from "../vacancy-workspace";
import VacancyChat from "../vacancy-chat";
import { sections, sectionLabels, type Section } from "./model";
import { MaterialsHeading, RecommendationCard, supportCopy } from "./recommendation";
import { useWorkspace } from "./use-workspace";

export function WorkspaceTabs() {
  const w = useWorkspace();
  return <><nav className="section-nav" aria-label="Подготовка к выбранной вакансии">{sections.map((section, i) => <a key={section} className="type-nav" href={`#${section}`} data-section={section} aria-current={w.location.section === section ? "page" : undefined} onClick={event => { event.preventDefault(); w.setSection(section); }}>{sectionLabels[i]}</a>)}</nav><label className="mobile-section field">Раздел подготовки <select id="section-select" value={w.location.section} onChange={event => w.setSection(event.target.value as Section)}>{sections.map((section, i) => <option key={section} value={section}>{sectionLabels[i]}</option>)}</select></label></>;
}

function EvidenceButton({ children }: { children: string }) {
  const w = useWorkspace();
  return <button type="button" data-open-evidence aria-expanded={w.inspector} onClick={event => w.openEvidence(event.currentTarget)}>{children}</button>;
}

const recommendationRussian: Record<string, { label: string; badgeClass: string; desc: string }> = {
  STRONGLY_APPLY: {
    label: "Рекомендуется откликнуться (Высокий приоритет)",
    badgeClass: "badge-success",
    desc: "Сильное совпадение по ключевым техническим требованиям и карьерным фактам.",
  },
  APPLY: {
    label: "Рекомендуется откликнуться",
    badgeClass: "badge-success",
    desc: "Подтверждённые факты карьеры соответствуют большинству обязательных требований.",
  },
  MAYBE: {
    label: "Стоит рассмотреть (Средний приоритет)",
    badgeClass: "badge-warning",
    desc: "Есть смежный опыт или пробелы по отдельным требованиям, требующие внимания.",
  },
  LOW_PRIORITY: {
    label: "Низкий приоритет",
    badgeClass: "badge-neutral",
    desc: "Значительное количество пробелов в опыте или расхождение по ключевым условиям.",
  },
  SKIP: {
    label: "Пропустить",
    badgeClass: "badge-neutral",
    desc: "Обнаружены критические блокеры или полное несовпадение профиля с вакансией.",
  },
};

const dimensionRussianNames: Record<string, string> = {
  TECHNICAL: "Технические навыки",
  EXPERIENCE: "Опыт работы",
  DOMAIN: "Предметная область (Домен)",
  LANGUAGE: "Языки",
  LOCATION: "Локация",
  WORK_FORMAT: "Формат работы",
  SALARY: "Условия и зарплата",
};

const dimensionStatusRussian: Record<string, { label: string; badgeClass: string }> = {
  MATCH: { label: "Совпадение", badgeClass: "badge-match" },
  ADJACENT: { label: "Частично", badgeClass: "badge-adjacent" },
  GAP: { label: "Пробел / Гэп", badgeClass: "badge-gap" },
  UNKNOWN: { label: "Неизвестно", badgeClass: "badge-unknown" },
  BLOCKER: { label: "Блокер", badgeClass: "badge-blocker" },
  NOT_APPLICABLE: { label: "Не применимо", badgeClass: "badge-neutral" },
};

const requirementImportanceRussian: Record<string, string> = {
  MANDATORY: "Обязательно",
  PREFERRED: "Желательно",
  UNCERTAIN: "Требует уточнения",
};

export function OverviewPanel() {
  const w = useWorkspace();
  const recKey = w.detail?.analysis?.recommendation;
  const verdict = recKey ? recommendationRussian[recKey] : null;

  return <section className="content-section" id="overview" aria-labelledby="overview-title" hidden={w.location.section !== "overview"}>
    <h2 className="type-section" id="overview-title">Обзор вакансии</h2>
    {w.demo ? (
      <>
        <div className="analysis-summary">
          <article>
            <span className="badge neutral">Подтверждения</span>
            <h3 className="type-card">API и PostgreSQL</h3>
            <p className="type-body" id="summary-evidence">{supportCopy(w.eligible)}</p>
            <EvidenceButton>Посмотреть подтверждения</EvidenceButton>
          </article>
          <article>
            <span className="badge warning">Неизвестно</span>
            <h3 className="type-card">Playwright</h3>
            <p>Опыт в рабочих проектах не подтверждён. Соответствие этому требованию оценить нельзя.</p>
          </article>
        </div>
        <div className="content-card">
          <h3 className="type-card">Перед использованием текста</h3>
          <p>Проверьте факты и формулировку. Примите подходящую правку, затем отдельно одобрите точный текст. Это не отправляет отклик. История общения с работодателем в макете недоступна.</p>
          <a className="type-secondary action-link" href="#materials" data-section="materials" onClick={event => { event.preventDefault(); w.setSection("materials"); }}>Перейти к правке резюме</a>
        </div>
      </>
    ) : (
      <>
        {/* Recommendation Verdict Card */}
        <div className="content-card verdict-card">
          <div className="verdict-header">
            <span className="eyebrow">Оценка соответствия Truth-first</span>
            {verdict ? (
              <div className="verdict-title-row">
                <span className={`badge ${verdict.badgeClass}`}>{verdict.label}</span>
                <p className="verdict-desc">{verdict.desc}</p>
              </div>
            ) : (
              <p className="type-card">{w.detail?.analysis_status === "PENDING" || w.detail?.analysis_status === "RUNNING" ? "Анализ выполняется…" : "Актуального анализа пока нет."}</p>
            )}
            <p className="type-caption subtle">Класс приоритета сформирован детерминированным анализом на основе подтверждённых фактов карьеры. Не является автоматической оценкой ATS или гарантией ответа работодателя.</p>
          </div>

          {/* Key Reasons */}
          {w.detail?.analysis?.key_reasons && w.detail.analysis.key_reasons.length > 0 && (
            <div className="verdict-factors">
              <h4 className="type-label">Ключевые факторы соответствия:</h4>
              <ul className="factors-list">
                {w.detail.analysis.key_reasons.map((reason, i) => (
                  <li key={i}>{reason}</li>
                ))}
              </ul>
            </div>
          )}

          {/* Material Gaps */}
          {w.detail?.analysis?.material_gaps && w.detail.analysis.material_gaps.length > 0 && (
            <div className="verdict-gaps">
              <h4 className="type-label">Существенные разрывы (gaps):</h4>
              <ul className="gaps-list">
                {w.detail.analysis.material_gaps.map((gap, i) => (
                  <li key={i}><strong>{gap.label}</strong> — {gap.category}</li>
                ))}
              </ul>
            </div>
          )}

          {/* Uncertainties */}
          {w.detail?.analysis?.uncertainties && w.detail.analysis.uncertainties.length > 0 && (
            <div className="verdict-uncertainties">
              <h4 className="type-label">Неопределённости и вопросы:</h4>
              <ul className="uncertainties-list">
                {w.detail.analysis.uncertainties.map((u, i) => (
                  <li key={i}><strong>{u.label}</strong> — {u.reason}</li>
                ))}
              </ul>
            </div>
          )}

          <div className="card-actions">
            <EvidenceButton>Посмотреть подтверждения</EvidenceButton>
            <a className="type-secondary action-link" href="#materials" data-section="materials" onClick={event => { event.preventDefault(); w.setSection("materials"); }}>
              Перейти к материалам отклика →
            </a>
          </div>
        </div>

        {/* 7 Dimensions Grid */}
        {w.detail?.analysis?.dimensions && w.detail.analysis.dimensions.length > 0 && (
          <div className="dimensions-section">
            <h3 className="type-section-sub">7 измерений соответствия вакансии</h3>
            <div className="dimensions-grid">
              {w.detail.analysis.dimensions.map((dim) => {
                const dimStatus = dimensionStatusRussian[dim.result] ?? { label: dim.result, badgeClass: "badge-neutral" };
                const dimName = dimensionRussianNames[dim.dimension] ?? dim.dimension;
                return (
                  <article className={`dimension-card ${dim.result.toLowerCase()}`} key={dim.dimension}>
                    <div className="dimension-header">
                      <h4 className="type-card">{dimName}</h4>
                      <span className={`badge small ${dimStatus.badgeClass}`}>{dimStatus.label}</span>
                    </div>
                    <p className="type-body">{dim.explanation}</p>
                    {dim.candidate_evidence && dim.candidate_evidence.length > 0 && (
                      <details className="evidence-details">
                        <summary className="type-caption">Подтверждающие факты ({dim.candidate_evidence.length})</summary>
                        <ul className="evidence-mini-list">
                          {dim.candidate_evidence.map((ev) => (
                            <li key={ev.id}>
                              <span>{ev.statement}</span>
                              <small className="badge tiny neutral">{ev.status}</small>
                            </li>
                          ))}
                        </ul>
                      </details>
                    )}
                  </article>
                );
              })}
            </div>
          </div>
        )}
      </>
    )}
  </section>;
}

export function RequirementsPanel() {
  const w = useWorkspace();
  return <section className="content-section" id="requirements" aria-labelledby="requirements-title" hidden={w.location.section !== "requirements"}>
    <h2 className="type-section" id="requirements-title">Требования и мой опыт</h2>
    <div className="content-card">
      {w.demo ? (
        <>
          <article className="requirement-line">
            <span className="requirement-marker" aria-hidden="true" />
            <div>
              <h3 className="type-card">Регрессионные тесты API на Python и pytest</h3>
              <p className="type-body" id="requirement-support">{supportCopy(w.eligible)}</p>
            </div>
          </article>
          <article className="requirement-line unknown">
            <span className="requirement-marker" aria-hidden="true" />
            <div>
              <h3 className="type-card">Playwright в рабочих проектах</h3>
              <p><span className="badge warning">Неизвестно</span> Опыт в рабочих проектах не подтверждён. Соответствие этому требованию оценить нельзя.</p>
            </div>
          </article>
          <EvidenceButton>Посмотреть источник и факты</EvidenceButton>
          <details>
            <summary className="type-label">Текст вакансии (вымышленный источник)</summary>
            <p>В вымышленной вакансии нужны API, PostgreSQL и автоматизация браузерных тестов. Требования работодателя сами по себе не подтверждают ваш опыт.</p>
          </details>
        </>
      ) : (
        <>
          {w.detail?.requirements.length ? (
            <div className="requirements-structured-list">
              {w.detail.requirements.map(requirement => {
                const importanceText = requirementImportanceRussian[requirement.importance] ?? requirement.importance;
                const dimName = dimensionRussianNames[requirement.dimension] ?? requirement.dimension;
                return (
                  <article className="requirement-line-modern" key={requirement.id}>
                    <div className="req-header-row">
                      <h3 className="type-card">{requirement.label}</h3>
                      <div className="req-badges">
                        <span className={`badge small ${requirement.importance === "MANDATORY" ? "badge-danger" : "badge-neutral"}`}>{importanceText}</span>
                        <span className="badge small neutral">{dimName}</span>
                      </div>
                    </div>
                    {requirement.source_excerpt && (
                      <blockquote className="req-source-quote">
                        «{requirement.source_excerpt}»
                      </blockquote>
                    )}
                  </article>
                );
              })}
            </div>
          ) : (
            <p className="subtle">Требования ещё не извлечены. Дождитесь завершения анализа.</p>
          )}

          <div className="requirements-footer-actions">
            <EvidenceButton>Посмотреть источник и факты</EvidenceButton>
            <details className="raw-source-details">
              <summary className="type-label">Исходный текст описания вакансии</summary>
              <pre className="source-copy-box">{w.detail?.snapshot.raw_text ?? "Исходный текст ещё не загружен."}</pre>
            </details>
          </div>
        </>
      )}
    </div>
  </section>;
}

export function MaterialsPanel() {
  const w = useWorkspace();
  const message = w.demo && w.effective !== "normal" ? w.location.selected === "cedar" && w.effective === "blocked"
    ? ["Нужно устранить противоречие в формате работы", "В примере подтверждено предпочтение только удалённой работы. Правка предлагает гибрид. Название компании не подтверждает связь с работодателем.", "Сравните обе формулировки, устраните противоречие и повторите проверку. До этого нельзя принять правку или одобрить текст."] : w.demo.scenarioMessages[w.effective] : null;

  return <section className="content-section" id="materials" aria-labelledby="materials-title" hidden={w.location.section !== "materials"} aria-busy={w.effective === "loading"}>
    <MaterialsHeading />
    {w.demo ? (
      <>
        <div id="scenario-panel" className={`state-panel ${["failed", "blocked"].includes(w.effective) ? "danger" : ["loading", "empty", "approval"].includes(w.effective) ? "informational" : ""}`} hidden={!message} role="status" aria-live="polite">
          <h3 className="type-subsection" id="scenario-heading">{message?.[0]}</h3>
          <p className="type-secondary" id="scenario-description">{message?.[1]}</p>
          <p className="type-secondary" id="scenario-recovery">{message?.[2]}</p>
        </div>
        <RecommendationCard />
      </>
    ) : w.detail && w.location.section === "materials" ? (
      <>
        <ApplicationDraftPanel canonical key={w.detail.id} vacancyId={w.detail.id} vacancyTitle={w.detail.title ?? "Вакансия"} />
        <div className="materials-chat-container">
          <div className="chat-section-header">
            <h3 className="type-section-sub">Чат по вакансии и подготовка к интервью</h3>
            <p className="type-caption subtle">Задавайте точечные вопросы по стеку, требованиям и формулировкам сопроводительного письма.</p>
          </div>
          <VacancyChat key={`chat-${w.detail.id}`} vacancyId={w.detail.id} />
        </div>
      </>
    ) : (
      <div className="content-card" role="status">
        <h3 className="type-card">Материалы пока недоступны</h3>
        <p>{w.error || (w.loading ? "Дождитесь загрузки вакансии." : "Выберите сохранённую вакансию. Создание и экспорт файлов пока недоступны.")}</p>
      </div>
    )}
  </section>;
}

export function EmployerPanel() {
  const w = useWorkspace();
  return <section className="content-section" id="employer" aria-labelledby="employer-title" hidden={w.location.section !== "employer"}>
    <h2 className="type-section" id="employer-title">Работодатель</h2>
    <div className="content-card">
      <h3 className="type-card">История общения пока недоступна</h3>
      <p>{w.demo ? "Чтобы открыть историю общения, подтвердите связь вакансии с работодателем в своём профиле. Название компании эту связь не подтверждает. Макет не создаёт историю общения." : "История общения требует подтверждённой связи с работодателем. Название компании эту связь не подтверждает. Текущий API не предоставляет эту историю."}</p>
      <button type="button" disabled>История общения недоступна</button>
    </div>
  </section>;
}

export function HistoryPanel() {
  const w = useWorkspace();
  return <section className="content-section" id="history" aria-labelledby="history-title" hidden={w.location.section !== "history"}>
    <h2 className="type-section" id="history-title">История</h2>
    <div className="content-card">
      <h3 className="type-card">{w.demo ? "События этого примера" : "История подготовки"}</h3>
      <p>{w.demo ? "Источник, предложение и решение о правке вымышлены. Переписка с работодателем и отправка отклика здесь не сохраняются." : "История редакций и одобрений доступна в материалах. История общения и отправки отклика не предоставляется текущим API."}</p>
    </div>
  </section>;
}
