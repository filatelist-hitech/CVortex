import { useWorkspace } from "./use-workspace";
import { Icon } from "./icons";
import type { DraftItem } from "../vacancy-workspace";
import { useEffect } from "react";

export function supportCopy(eligible: boolean) { return eligible ? "Подтверждено фактами F-12 и F-18 об опыте тестирования API (вымышленный пример)." : "Актуальных подтверждений нет. Проверьте источник и указанную причину."; }

export function NextAction() {
  const w = useWorkspace();
  const state = w.effective;
  const next = !w.demo ? w.loading ? ["Дождитесь загрузки", "Загружаются сохранённая вакансия и её анализ."] : w.error ? ["Не удалось открыть вакансию", w.error] : !w.item ? ["Добавьте вакансию", "Сохранённые вакансии пока отсутствуют. Импорт доступен в обзоре."] : state === "stale" ? ["Обновите проверку источника", "Источник изменился. Прежние подтверждения нужно проверить заново."] : !w.detail?.analysis ? ["Проверьте исходный текст вакансии", "Анализа пока нет. Соответствие требованиям не оценено."] : ["Проверьте требования и подтверждения", "Откройте требования и материалы. Одобрение точного текста остаётся отдельным решением."]
    : w.location.selected === "cedar" && state === "blocked" ? ["Устраните противоречие в формате работы", "Предпочтение удалённой работы противоречит предложению гибрида. Принятие и одобрение недоступны."]
    : state === "blocked" ? ["Добавьте подтверждения", "Свяжите текст с подтверждёнными фактами или уберите неподтверждённую формулировку."]
    : state === "pending" ? ["Проверьте карьерный факт", "Факт F-31 ещё не подтверждён человеком. Использовать его для одобрения нельзя."]
    : state === "stale" ? ["Обновите проверку источника", "Источник изменился. Принять правку и одобрить текст можно только после новой проверки."]
    : state === "loading" ? ["Дождитесь анализа", "Показано состояние загрузки. Запрос к ИИ не отправляется."]
    : state === "empty" ? ["Проверьте исходный текст вакансии", "Анализа пока нет. Соответствие требованиям не оценено."]
    : state === "failed" ? ["Анализ нужно повторить", "Повторный запрос в приложении потребует вашего согласия на отправку данных."]
    : w.approved ? ["Текст одобрен", "Одобрение действует только в открытом макете. Отклик не отправлен."]
    : w.rejected ? ["Правка отклонена", "Прежний текст сохранён. Отклик не отправлен."]
    : w.accepted ? ["Проверьте текст перед одобрением", "Правка уже в черновике. Одобрение точной формулировки остаётся отдельным решением."]
    : ["Проверьте предложенную правку", "Сравните исходный и предложенный текст, затем проверьте факты."];
  return <div className={`notice ${state === "blocked" ? "blocked" : ["stale", "pending"].includes(state) ? "warning" : ""}`} id="next-action"><p className="next-action-title type-subsection" id="next-action-title">{next[0]}</p><p className="type-secondary" id="next-action-copy">{next[1]}</p></div>;
}

export function ApprovalControls() {
  const w = useWorkspace();
  const copy = w.approved ? "Одобрение сохранено только в макете. Отклик не отправлен." : w.rejected ? "Сохранён прежний текст. Отклонённую правку нельзя одобрить." : w.accepted ? "Проверьте получившийся текст и отдельно решите, одобрять ли его. Отклик не отправляется." : w.safe ? "Сначала примите подходящую правку. Затем отдельно одобрите точный текст. Это не отправляет отклик." : "Одобрение недоступно до проверки фактов и текста. Отклик не отправлен.";
  return <div className="approval"><div><h4 className="type-subsection">Одобрение текста</h4><p className="type-support" id="approval-copy">{copy}</p></div><button id="approve" type="button" disabled={!w.safe || !w.accepted || w.approved || w.rejected} onClick={w.approve}>Одобрить этот текст</button></div>;
}

export function RecommendationCard() {
  const w = useWorkspace(), state = w.effective;
  const evidence = w.eligible ? "Факты F-12 и F-18 подтверждают утверждение CL-08 в этом примере." : state === "pending" ? "Утверждение CL-08 ссылается на факт F-31, который ещё не подтверждён." : state === "stale" ? "Здесь прежние подтверждения. Актуальную версию источника нужно проверить заново." : "Утверждение CL-08 не подтверждено или противоречит фактам.";
  const badge = w.approved ? "success" : w.rejected ? "neutral" : state === "blocked" ? "danger" : ["stale", "pending"].includes(state) || w.accepted ? "warning" : "informational";
  const review = w.approved ? "Текст одобрен, отклик не отправлен" : w.rejected ? "Правка отклонена" : w.accepted ? "Правка принята, текст не одобрен" : state === "stale" ? "Источник устарел" : state === "pending" ? "Факт не подтверждён" : state === "blocked" ? "Одобрение заблокировано" : "Ожидает проверки";
  return <article className="review-card" id="review-card" aria-labelledby="recommendation-title" hidden={w.unavailable} data-review-state={w.approved ? "approved" : w.rejected ? "rejected" : w.accepted ? "accepted" : "proposed"}>
    <div className="review-head"><span className="badge recommended">Предложение R-07</span><span className={`badge ${badge}`} id="review-state" tabIndex={-1}>{review}</span></div>
    <h3 className="type-card" id="recommendation-title">Уточнить опыт тестирования API</h3><p className="subtle">Пример резюме. Создание и экспорт файла здесь недоступны.</p>
    <div className="review-body">
      <div className="diff"><div><h4 className="type-label">Сейчас</h4><p className="type-body">Тестировал серверные сервисы.</p></div><div><h4 className="type-label">Предлагаемая правка</h4><p className="type-body" id="proposal-copy">{w.proposal}</p></div></div>
      <aside className="reason-grid" aria-label="Обоснование и риски правки"><div><h4 className="type-label">Почему эта правка</h4><p className="type-secondary">Правка связывает подтверждённый опыт тестирования API и баз данных с требованиями вакансии.</p></div><div><h4 className="type-label">Подтверждения</h4><p className="type-secondary"><span id="review-evidence">{evidence}</span></p><button type="button" data-open-evidence aria-expanded={w.inspector} onClick={event => w.openEvidence(event.currentTarget)}>Факты и источник</button></div><div><h4 className="type-label">Что нельзя добавлять</h4><p className="type-secondary">Опыт руководства и работы с Playwright в реальных проектах не подтверждён. Не добавляйте его в текст.</p></div></aside>
      <div id="edit-box" className="edit-box" hidden={!w.editing}><label className="field">Изменить предложенный текст <textarea id="proposal-edit" rows={3} value={w.editText} onChange={event => w.setEditText(event.target.value)} /></label><p className="type-support">Макет проверяет только две заранее подготовленные формулировки с подтверждениями.</p><button id="save-edit" type="button" onClick={w.saveEdit}>Сохранить и проверить</button><button id="cancel-edit" type="button" onClick={w.cancelEdit}>Отменить правку</button></div>
      <div className="actions"><button className="primary" id="accept" type="button" disabled={!w.safe || w.accepted || w.rejected || w.approved} onClick={w.accept}>Принять правку</button><button id="edit" type="button" disabled={!w.safe || w.accepted || w.rejected || w.approved} onClick={w.edit}>Изменить</button><button id="reject" type="button" disabled={w.editing || w.rejected || w.approved || w.unavailable} onClick={w.reject}>Отклонить</button></div>
    </div>
    <ApprovalControls />
    <p className="action-status type-secondary" id="action-status" role={w.alert ? "alert" : "status"} aria-live="polite">{w.status}</p>
  </article>;
}

export function MaterialsHeading() { return <div className="section-heading"><h2 className="type-section" id="materials-title">Правка резюме</h2><span className="badge recommended"><Icon name="sparkles" />Предложение ИИ</span></div>; }

// Same B01 composition for persisted API drafts; all operations remain in ApplicationDraftPanel.
export function SavedRecommendationCard({ item, content, busy, stale, providerPaused, onChange, onAction }: {
  item: DraftItem; content: string; busy: boolean; stale: boolean; providerPaused: boolean;
  onChange: (value: string) => void; onAction: (action: "accept" | "edit" | "reject" | "approve") => void;
}) {
  const w = useWorkspace();
  const changed = content !== item.content;
  const setDraftDirty = w.setDraftDirty;
  useEffect(() => { setDraftDirty(item.id, changed); return () => setDraftDirty(item.id, false); }, [setDraftDirty, item.id, changed]);
  const terminal = ["APPROVED", "REJECTED"].includes(item.status);
  const safe = !busy && !stale && !providerPaused && item.validation_result === "PASS";
  const status = item.status === "APPROVED" ? "Текст одобрен, отклик не отправлен" : item.status === "ACCEPTED" ? "Правка принята, текст не одобрен" : item.status === "REJECTED" ? "Правка отклонена" : item.validation_result !== "PASS" ? "Одобрение заблокировано" : "Ожидает проверки";
  return <article className="review-card" data-draft-id={item.id}>
    <div className="review-head"><span className="badge recommended">{item.kind === "COVER_DRAFT" ? "Черновик письма" : "Предложение"} · версия {item.revision_number}</span><span className={`badge ${item.status === "APPROVED" ? "success" : item.validation_result !== "PASS" ? "danger" : "warning"}`}>{status}</span></div>
    <h3 className="type-card">{item.kind === "COVER_DRAFT" ? "Сопроводительное письмо" : item.section ?? "Правка резюме"}</h3><p className="subtle">Одобрение не отправляет отклик. Создание и экспорт файла пока недоступны.</p>
    <div className="review-body"><div className="diff"><div><h4 className="type-label">Сейчас</h4><p className="type-body">{item.before ?? "Исходный текст не предоставлен"}</p></div><div><h4 className="type-label">Предлагаемая правка</h4><p className="type-body">{item.content}</p></div></div>
      <aside className="reason-grid" aria-label="Обоснование и риски правки"><div><h4 className="type-label">Почему эта правка</h4><p className="type-secondary">{item.reason ?? "Обоснование не предоставлено."}</p></div><div><h4 className="type-label">Подтверждения</h4><p className="type-secondary">Truth Guard: {item.validation_result}</p><details><summary className="type-label">Факты и источник</summary>{item.claim_usages.length ? item.claim_usages.map((usage, i) => <div key={i}><p>{usage.assertion}</p>{usage.supporting_claims.map(claim => <div key={claim.id}><p>Утверждение · {claim.truth_status}: {claim.statement}</p>{claim.career_facts.map(fact => <p key={fact.id}>Факт · {fact.status}: {fact.statement}<br />{fact.provenance_type}: {fact.source_excerpt}</p>)}</div>)}</div>) : <p>Подтверждения в ответе не предоставлены.</p>}</details><button type="button" onClick={event => w.openEvidence(event.currentTarget)}>Подтверждения анализа вакансии</button></div><div><h4 className="type-label">Что нельзя добавлять</h4><p className="type-secondary">{item.risk ?? "Не добавляйте опыт без подтверждённых фактов."}</p></div></aside>
      <div className="edit-box"><label className="field">Изменить предложенный текст<textarea data-draft-edit value={content} maxLength={6000} rows={3} disabled={busy || stale || terminal} onChange={event => onChange(event.target.value)} /></label><p className="type-support">Изменение точного текста требует повторной серверной проверки.</p><button type="button" disabled={busy || stale || providerPaused || terminal || !changed} onClick={() => onAction("edit")}>Сохранить и проверить</button></div>
      <div className="actions"><button className="primary" type="button" disabled={!safe || terminal || changed || item.status === "ACCEPTED"} onClick={() => onAction("accept")}>Принять правку</button><button type="button" disabled={busy || stale || terminal || changed} onClick={() => onAction("reject")}>Отклонить</button></div>
    </div>
    <div className="approval"><div><h4 className="type-subsection">Одобрение текста</h4><p className="type-support">{item.status === "APPROVED" ? "Этот текст одобрен. Отклик не отправлен." : "Сначала примите правку. Затем отдельно одобрите точную формулировку."}</p></div><button type="button" disabled={!safe || changed || item.status !== "ACCEPTED"} onClick={() => onAction("approve")}>Одобрить этот текст</button></div>
    <details className="draft-history"><summary className="type-label">История редакций и одобрений</summary>{item.revisions.map(revision => <div key={revision.revision_number}><p>Версия {revision.revision_number} · {revision.action} · {revision.validation_result}</p><p>{revision.content}</p></div>)}{item.approvals.map((approval, i) => <p key={i}>{approval.action} · версия {approval.revision_number} · {approval.validation_result}</p>)}</details>
  </article>;
}
