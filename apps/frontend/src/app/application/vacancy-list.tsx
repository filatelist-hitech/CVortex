import { useWorkspace } from "./use-workspace";
import type { WorkspaceVacancy } from "./model";

export function VacancyListItem({ item, hidden }: { item: WorkspaceVacancy; hidden: boolean }) {
  const w = useWorkspace();
  return <button className="opportunity-item" type="button" hidden={hidden} data-opportunity={item.id} aria-current={w.location.selected === item.id ? "true" : "false"} onClick={() => w.select(item.id)}><strong className="company-row type-secondary"><span className="company-mark" aria-hidden="true">{item.initial}</span>{item.company}</strong>{item.salary && <span className="list-salary type-secondary">{item.salary.min / 1000}–{item.salary.max / 1000} тыс. <span className="salary-currency">₽</span> / месяц</span>}<span className="list-role type-card">{item.role}</span><small>{item.note}</small></button>;
}

export function VacancyList() {
  const w = useWorkspace();
  const Heading = w.size === "mobile" && w.location.level === "list" ? "h1" : "p";
  const matches = (item: WorkspaceVacancy) => `${item.company} ${item.role} ${item.note} ${item.salary ? `${item.salary.min / 1000}–${item.salary.max / 1000}` : ""}`.toLocaleLowerCase().includes(w.query.toLocaleLowerCase());
  return <aside className="opportunity-list" id="opportunity-list" aria-labelledby="list-title">
    <div className="pane-heading"><button id="close-list" type="button" onClick={() => w.closeList()}>Закрыть список</button><Heading className={Heading === "h1" ? "type-page" : "type-section"} id="list-title" tabIndex={-1}>Вакансии</Heading><span className="badge neutral">{w.demo ? "2 примера" : `${w.vacancies.length} сохранено`}</span></div>
    <label className="field">Найти вакансию <input id="opportunity-filter" type="search" autoComplete="off" placeholder="Должность или компания" value={w.query} onChange={event => w.setQuery(event.target.value)} /></label>
    <div className="opportunity-items" id="opportunity-items">{w.vacancies.map(item => <VacancyListItem key={item.id} item={item} hidden={!matches(item)} />)}</div>
    <p id="list-no-results" className="subtle" hidden={w.loading || w.vacancies.some(matches)} role="status" aria-live="polite">{!w.vacancies.length && !w.demo ? w.error || "Сохранённых вакансий пока нет. Добавьте вакансию в обзоре." : "Ничего не найдено. Попробуйте другой запрос или очистите поиск."}</p>
    {w.loading && !w.demo && <p role="status">Загрузка вакансий…</p>}
    <p className="list-note type-caption">{w.demo ? "Вымышленные вакансии. Выбор сохраняется, пока макет открыт." : "Сохранённые вакансии из вашего профиля."}</p>
    {!w.demo && <button type="button" onClick={() => { w.setSection("overview"); w.setLevel("work"); }}>Добавить вакансию</button>}
  </aside>;
}
