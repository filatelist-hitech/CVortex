/* B01 synthetic interaction reference. No network, storage or production data. */
(() => {
  const $ = id => document.getElementById(id);
  const motion = window.CVOrbitMotion;
  const scenarios = new Set(['normal', 'loading', 'empty', 'failed', 'stale', 'blocked', 'pending', 'approval']);
  const sections = new Set(['overview', 'requirements', 'materials', 'employer', 'history']);
  const opportunities = {
    northstar: { company: 'Northstar Labs', role: 'Старший инженер по тестированию', initial: 'N', status: 'Отклик не отправлен', salary: { min: 240000, max: 290000 }, metadata: 'Удалённо, UTC+3' },
    cedar: { company: 'Cedar Systems', role: 'Старший тестировщик', initial: 'C', status: 'Отклик не отправлен', salary: { min: 250000, max: 300000 }, metadata: 'Гибрид, Москва' },
  };
  const messages = {
    loading: ['Анализ вакансии выполняется', 'Показан пример загрузки. Источник и черновик доступны. Запрос к ИИ не отправляется.', 'Дождитесь завершения анализа. Процент готовности неизвестен.'],
    empty: ['Вакансия ещё не проанализирована', 'Анализа и подтверждений пока нет. Соответствие требованиям не оценено.', 'Проверьте исходный текст, прежде чем запускать анализ в приложении.'],
    failed: ['Не удалось проанализировать вакансию', 'Анализ завершился с ошибкой. Причина неизвестна. Сохранённый текст вакансии доступен.', 'В приложении повторите только этот анализ, когда сервис станет доступен и вы подтвердите отправку данных.'],
    stale: ['Подтверждения устарели', 'Текст вакансии изменился после создания предложения. Прежняя формулировка сохранена в истории; её нужно проверить заново.', 'Проверьте актуальные версии источника и фактов, прежде чем принимать правку или одобрять текст.'],
    blocked: ['Нет подтверждений для текста', 'Утверждение CL-08 не связано с подтверждённым фактом из вашего профиля.', 'Добавьте подтверждение или уберите неподтверждённую формулировку. Пока текст нельзя одобрить.'],
    pending: ['Факт ещё не подтверждён', 'Факт F-31 получен при импорте и ещё не проверен человеком. Он не может подтверждать предложенный текст.', 'Проверьте факт в разделе «Карьера», затем проверьте предложение заново. До этого текст нельзя одобрить.'],
    approval: ['Правка принята, текст ещё не одобрен', 'Правка добавлена в черновик. Текст ещё не одобрен, отклик не отправлен.', 'Проверьте формулировку и подтверждения. Затем отдельно решите, одобрять ли этот текст.'],
  };
  const defaultProposal = 'Писал регрессионные тесты API на Python и pytest; проверял границы транзакций PostgreSQL.';
  const supportedProposals = new Set([defaultProposal, 'Писал регрессионные тесты API на Python и pytest.']);
  let selected = new URLSearchParams(location.search).get('opportunity') === 'cedar' ? 'cedar' : 'northstar';
  let section = sections.has(location.hash.slice(1)) ? location.hash.slice(1) : 'materials';
  let scenario = new URLSearchParams(location.search).get('scenario') || 'normal';
  if (!scenarios.has(scenario)) scenario = 'normal';
  let accepted = scenario === 'approval' && selected === 'northstar';
  let approved = false;
  let rejected = false;
  let proposal = defaultProposal;
  let trigger = null;
  let pinned = false;
  let level = new URLSearchParams(location.search).get('level') === 'list' || (scenario === 'normal' && !new URLSearchParams(location.search).has('opportunity')) ? 'list' : 'work';

  const tabletQuery = matchMedia('(min-width: 768px) and (max-width: 1023px)');
  const drawerQuery = matchMedia('(max-width: 1439px)');
  let listOpen = false;
  let modalPanel = null;
  function syncPanels() {
    document.body.dataset.modalOpen = String($('account-dialog').open || (listOpen && tabletQuery.matches) || (!$('inspector').hidden && !isCompact() && drawerQuery.matches));
    modalPanel = listOpen && tabletQuery.matches ? $('opportunity-list')
      : !$('inspector').hidden && !isCompact() && drawerQuery.matches ? $('inspector') : null;
    for (const panel of [$('opportunity-list'), $('inspector')]) {
      if (panel === modalPanel) { panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-modal', 'true'); }
      else { panel.removeAttribute('role'); panel.removeAttribute('aria-modal'); }
    }
    for (const item of document.querySelectorAll('.site-header, .global-menu, .tablet-bar, .mobile-bar, .reference-footer, .workspace, .opportunity-list, .inspector')) {
      item.inert = Boolean(modalPanel && item !== modalPanel);
    }
  }
  function closeList(focus = true) {
    motion.stop($('opportunity-list'));
    listOpen = false; document.body.dataset.listOpen = 'false';
    $('open-list').setAttribute('aria-expanded', 'false'); syncPanels();
    if (focus && tabletQuery.matches) $('open-list').focus();
  }
  function editing() { return !$('edit-box').hidden; }
  function allowSelection() {
    if (!editing()) return true;
    $('action-status').textContent = 'Есть несохранённая правка. Сохраните текст с подтверждениями или отмените правку, прежде чем менять вакансию или состояние макета.';
    $('proposal-edit').focus(); announce('Текст остался в поле ввода. Сохраните или отмените правку перед переходом.');
    return false;
  }
  function remember(push = true) {
    const salary = new URLSearchParams(location.search).get('salary') === 'missing' ? '&salary=missing' : '';
    const url = `${location.pathname}?scenario=${scenario}&opportunity=${selected}&level=${level}${salary}#${section}`;
    history[push ? 'pushState' : 'replaceState']({ selected, scenario, section, level }, '', url);
  }
  function announce(message) { $('live-status').textContent = message; }
  function isCompact() { return matchMedia('(max-width: 767px)').matches; }
  function effectiveState() { return selected === 'cedar' && (scenario === 'normal' || scenario === 'approval') ? 'blocked' : scenario; }
  // One page heading follows the visible mobile level. Visual roles remain independent.
  function pageHeading(id, tag, role) {
    const current = $(id);
    if (current.localName !== tag) {
      const focused = document.activeElement === current;
      const replacement = document.createElement(tag);
      for (const attr of current.attributes) replacement.setAttribute(attr.name, attr.value);
      replacement.append(...current.childNodes); current.replaceWith(replacement);
      if (focused) replacement.focus();
    }
    $(id).className = role;
  }
  function setLevel(next, focus = true) {
    level = next;
    document.body.dataset.level = next;
    $('back-level').hidden = next === 'list';
    $('level-title').textContent = next === 'list' ? 'Вакансии' : next === 'work' ? opportunities[selected].company : 'Подтверждения';
    const compact = isCompact();
    pageHeading('workspace-title', compact && next !== 'work' ? 'h2' : 'h1', 'type-page');
    pageHeading('list-title', compact && next === 'list' ? 'h1' : 'p', compact && next === 'list' ? 'type-page' : 'type-section');
    pageHeading('inspector-title', compact && next === 'context' ? 'h1' : 'h2', compact && next === 'context' ? 'type-page' : 'type-section');
    pageHeading('evidence-title', compact && next === 'context' ? 'h2' : 'h3', 'type-subsection');
    if (focus && isCompact()) {
      (next === 'list' ? (document.querySelector(`[data-opportunity="${selected}"]:not([hidden])`) || $('list-title')) : next === 'work' ? $('workspace-title') : $('inspector-title')).focus();
    }
  }
  function closeInspector(returnFocus = true) {
    motion.stop($('inspector'));
    $('inspector').hidden = true;
    document.querySelectorAll('[data-open-evidence]').forEach(button => button.setAttribute('aria-expanded', 'false'));
    if (isCompact()) setLevel('work', false);
    syncPanels();
    if (returnFocus && trigger && trigger.isConnected) trigger.focus();
    trigger = null;
    syncPanels();
    announce('Панель подтверждений закрыта.');
  }
  function renderInspector() {
    const state = effectiveState();
    const unavailable = ['loading', 'empty', 'failed'].includes(state);
    $('inspector-scope').textContent = `${opportunities[selected].company} · предложение R-07${pinned ? ' · закреплено' : ''}`;
    const badge = $('inspector-status');
    badge.className = `badge ${state === 'blocked' ? 'danger' : state === 'stale' || state === 'pending' ? 'warning' : unavailable ? 'neutral' : 'success'}`;
    badge.dataset.level = state === 'blocked' ? 'error' : state === 'stale' || state === 'pending' ? 'warning' : unavailable ? 'neutral' : 'success';
    badge.textContent = unavailable ? 'Нет подтверждений · нет актуального анализа' : state === 'blocked' ? 'Проверка остановлена · нет подтверждений или есть противоречие' : state === 'stale' ? 'Данные устарели · проверьте источник заново' : state === 'pending' ? 'Факт ждёт подтверждения · использовать нельзя' : 'Подтверждения есть (вымышленный пример)';
    const trace = unavailable ? ['Вымышленный текст вакансии: API, PostgreSQL и браузерные тесты.', 'В этом примере нет актуального проверенного утверждения и подтверждённых фактов для него.', 'Проверьте источник и повторите анализ этой вакансии.'] : selected === 'cedar'
      ? ['Предложенный текст о гибридной работе / R-07', 'В примере подтверждено предпочтение только удалённой работы. Оно противоречит предложению.', 'Связь с работодателем не подтверждена; противоречие не устранено', 'Сначала устраните противоречие, затем повторите проверку']
      : state === 'pending'
        ? ['Предложенный текст / версия примера R-07', 'Утверждение CL-08 ссылается на неподтверждённый факт F-31', 'F-31 · получен при импорте, ещё не проверен и не подтверждён', 'Одобрение доступно только после проверки факта и повторной проверки текста']
        : state === 'blocked'
          ? ['Предложенный текст / версия примера R-07', 'Утверждение CL-08 не связано с подтверждённым фактом', 'Нет достоверных сведений о подтверждении источника', 'Добавьте подтверждение или уберите формулировку']
          : ['Предложенный текст / версия примера R-07', 'Утверждение CL-08 · в примере проверена именно эта формулировка', 'F-12 · вымышленный подтверждённый опыт тестов API / 15 сентября 2026', 'F-18 · вымышленный подтверждённый опыт тестов PostgreSQL / 15 сентября 2026', 'Вымышленная выдержка: «Писал тесты API на Python и pytest; проверял транзакции PostgreSQL».', 'Подтвердил: участник учебного примера / 15 сентября 2026 · вымышленные сведения'];
    $('evidence-trace').replaceChildren(...trace.map(text => { const li = document.createElement('li'); li.textContent = text; return li; }));
    $('pin-inspector').setAttribute('aria-pressed', String(pinned));
    $('pin-inspector').textContent = pinned ? 'Открепить' : 'Закрепить';
  }
  function openInspector(button, push = true) {
    closeList(false);
    trigger = button;
    renderInspector();
    $('inspector').hidden = false;
    document.querySelectorAll('[data-open-evidence]').forEach(item => item.setAttribute('aria-expanded', 'true'));
    if (isCompact()) setLevel('context', false);
    syncPanels();
    if (isCompact() && push) remember();
    $('inspector-title').focus();
    motion.to($('inspector'), 1, { fresh: true, x: isCompact() ? 0 : 12, y: 0 });
    announce('Открыты подтверждения для выбранной вымышленной вакансии.');
  }
  function renderReview() {
    const state = effectiveState();
    const eligible = selected === 'northstar' && (state === 'normal' || state === 'approval');
    const safe = !editing() && eligible;
    $('review-card').hidden = ['loading', 'empty', 'failed'].includes(state);
    $('scenario-panel').hidden = state === 'normal';
    if (state !== 'normal') {
      const message = selected === 'cedar' && state === 'blocked'
        ? ['Нужно устранить противоречие в формате работы', 'В примере подтверждено предпочтение только удалённой работы. Правка предлагает гибрид. Название компании не подтверждает связь с работодателем.', 'Сравните обе формулировки, устраните противоречие и повторите проверку. До этого нельзя принять правку или одобрить текст.']
        : messages[state];
      $('scenario-heading').textContent = message[0];
      $('scenario-description').textContent = message[1];
      $('scenario-recovery').textContent = message[2];
      $('scenario-panel').className = `state-panel ${state === 'failed' || state === 'blocked' ? 'danger' : ['loading', 'empty', 'approval'].includes(state) ? 'informational' : ''}`;
      $('scenario-panel').dataset.level = state === 'failed' || state === 'blocked' ? 'error' : ['loading', 'empty', 'approval'].includes(state) ? 'informational' : 'warning';
    }
    $('review-evidence').textContent = eligible ? 'Факты F-12 и F-18 подтверждают утверждение CL-08 в этом примере.' : state === 'pending' ? 'Утверждение CL-08 ссылается на факт F-31, который ещё не подтверждён.' : state === 'stale' ? 'Здесь прежние подтверждения. Актуальную версию источника нужно проверить заново.' : 'Утверждение CL-08 не подтверждено или противоречит фактам.';
    $('requirement-support').textContent = eligible ? 'Подтверждено фактами F-12 и F-18 об опыте тестирования API (вымышленный пример).' : 'Актуальных подтверждений нет. Проверьте источник и указанную причину.';
    $('summary-evidence').textContent = $('requirement-support').textContent;
    $('proposal-copy').textContent = proposal;
    $('review-card').dataset.reviewState = approved ? 'approved' : rejected ? 'rejected' : accepted ? 'accepted' : 'proposed';
    $('review-state').className = `badge ${approved ? 'success' : rejected ? 'neutral' : state === 'blocked' ? 'danger' : state === 'stale' || state === 'pending' || accepted ? 'warning' : 'informational'}`;
    $('review-state').dataset.level = approved ? 'success' : rejected ? 'neutral' : state === 'blocked' ? 'error' : state === 'stale' || state === 'pending' || accepted ? 'warning' : 'informational';
    $('review-state').textContent = approved ? 'Текст одобрен, отклик не отправлен' : rejected ? 'Правка отклонена' : accepted ? 'Правка принята, текст не одобрен' : state === 'stale' ? 'Источник устарел' : state === 'pending' ? 'Факт не подтверждён' : state === 'blocked' ? 'Одобрение заблокировано' : 'Ожидает проверки';
    $('accept').disabled = !safe || accepted || rejected || approved;
    $('edit').disabled = !safe || accepted || rejected || approved;
    $('reject').disabled = editing() || rejected || approved || !['normal', 'approval', 'stale', 'blocked', 'pending'].includes(state);
    $('approve').disabled = !safe || !accepted || approved || rejected;
    $('approval-copy').textContent = approved ? 'Одобрение сохранено только в макете. Отклик не отправлен.' : rejected ? 'Сохранён прежний текст. Отклонённую правку нельзя одобрить.' : accepted ? 'Проверьте получившийся текст и отдельно решите, одобрять ли его. Отклик не отправляется.' : safe ? 'Сначала примите подходящую правку. Затем отдельно одобрите точный текст. Это не отправляет отклик.' : 'Одобрение недоступно до проверки фактов и текста. Отклик не отправлен.';
    renderNextAction();
  }
  function renderNextAction() {
    const state = effectiveState();
    $('next-action').className = `notice ${state === 'blocked' ? 'blocked' : state === 'stale' || state === 'pending' ? 'warning' : ''}`;
    const next = selected === 'cedar' && state === 'blocked'
      ? ['Устраните противоречие в формате работы', 'Предпочтение удалённой работы противоречит предложению гибрида. Принятие и одобрение недоступны.']
      : state === 'blocked' ? ['Добавьте подтверждения', 'Свяжите текст с подтверждёнными фактами или уберите неподтверждённую формулировку.']
      : state === 'pending' ? ['Проверьте карьерный факт', 'Факт F-31 ещё не подтверждён человеком. Использовать его для одобрения нельзя.']
      : state === 'stale' ? ['Обновите проверку источника', 'Источник изменился. Принять правку и одобрить текст можно только после новой проверки.']
      : state === 'loading' ? ['Дождитесь анализа', 'Показано состояние загрузки. Запрос к ИИ не отправляется.']
      : state === 'empty' ? ['Проверьте исходный текст вакансии', 'Анализа пока нет. Соответствие требованиям не оценено.']
      : state === 'failed' ? ['Анализ нужно повторить', 'Повторный запрос в приложении потребует вашего согласия на отправку данных.']
      : approved ? ['Текст одобрен', 'Одобрение действует только в открытом макете. Отклик не отправлен.']
      : rejected ? ['Правка отклонена', 'Прежний текст сохранён. Отклик не отправлен.']
      : accepted ? ['Проверьте текст перед одобрением', 'Правка уже в черновике. Одобрение точной формулировки остаётся отдельным решением.']
      : ['Проверьте предложенную правку', 'Сравните исходный и предложенный текст, затем проверьте факты.'];
    $('next-action-title').textContent = next[0]; $('next-action-copy').textContent = next[1];
  }
  function render() {
    document.querySelector('.global-menu').open = !isCompact();
    const item = opportunities[selected];
    $('crumb-company').textContent = item.company;
    $('domain-status').textContent = item.status;
    $('workspace-title').textContent = item.role;
    $('company-name').textContent = item.company;
    $('identity-initial').textContent = item.initial;
    $('vacancy-metadata').textContent = item.metadata;
    const salaryMissing = new URLSearchParams(location.search).get('salary') === 'missing';
    const salary = $('vacancy-salary');
    if (salaryMissing) {
      const label = document.createElement('span'); label.className = 'type-subsection'; label.textContent = 'Не указана';
      salary.replaceChildren(label); salary.removeAttribute('aria-label'); delete salary.dataset.min; delete salary.dataset.max;
    } else {
      const range = document.createElement('span'); range.className = 'type-number';
      range.textContent = `${item.salary.min / 1000}–${item.salary.max / 1000}`;
      const unit = document.createElement('span'); unit.className = 'salary-unit type-secondary'; unit.textContent = 'тыс. ';
      const currency = document.createElement('span'); currency.className = 'salary-currency'; currency.textContent = '₽'; unit.append(currency);
      salary.replaceChildren(range, document.createTextNode(' '), unit);
      const number = new Intl.NumberFormat('ru-RU');
      salary.setAttribute('aria-label', `${number.format(item.salary.min)}–${number.format(item.salary.max)} ₽ в месяц, до налогов`);
      salary.dataset.min = item.salary.min; salary.dataset.max = item.salary.max;
    }
    document.querySelector('.salary-basis').textContent = salaryMissing ? 'В этом примере данных о зарплате нет' : 'В месяц, до налогов';
    const state = effectiveState();
    document.querySelectorAll('[data-opportunity]').forEach(button => button.setAttribute('aria-current', String(button.dataset.opportunity === selected)));
    document.querySelectorAll('[data-section]').forEach(link => { if (link.dataset.section === section) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current'); });
    sections.forEach(name => $(name).hidden = name !== section);
    $('section-select').value = section;
    $('scenario-select').value = scenario;
    renderReview();
    $('materials').setAttribute('aria-busy', String(state === 'loading'));
    if (!$('inspector').hidden) renderInspector();
    setLevel(level, false);
  }
  function selectOpportunity(id) {
    if (!opportunities[id] || !allowSelection()) return;
    closeList(false);
    if (!$('inspector').hidden) closeInspector(false);
    pinned = false; selected = id; accepted = false; approved = false; rejected = false; proposal = selected === 'cedar' ? 'Готов к гибридной работе в Москве.' : defaultProposal;
    $('edit-box').hidden = true; $('action-status').textContent = '';
    render(); setLevel('work'); remember();
    announce(`Выбрана компания ${opportunities[id].company}. Предыдущие подтверждения закрыты, закрепление сброшено.`);
  }
  function setSection(name) {
    if (!sections.has(name)) return;
    section = name; render();
    remember();
    announce(`Раздел «${$('section-select').selectedOptions[0].textContent}», компания ${opportunities[selected].company}.`);
  }
  document.querySelectorAll('[data-opportunity]').forEach(button => button.addEventListener('click', () => selectOpportunity(button.dataset.opportunity)));
  document.querySelectorAll('[data-section]').forEach(link => link.addEventListener('click', event => { event.preventDefault(); setSection(link.dataset.section); }));
  $('section-select').addEventListener('change', event => setSection(event.target.value));
  $('scenario-select').addEventListener('change', event => {
    if (!allowSelection()) { $('scenario-select').value = scenario; return; }
    closeInspector(false); closeList(false);
    scenario = event.target.value; accepted = scenario === 'approval' && selected === 'northstar'; approved = false; rejected = false; proposal = selected === 'cedar' ? 'Готов к гибридной работе в Москве.' : defaultProposal;
    $('edit-box').hidden = true; $('action-status').textContent = '';

    level = 'work'; render(); setLevel('work'); remember();
    announce(`Состояние макета: ${$('scenario-select').selectedOptions[0].textContent}. Запросы к API и ИИ не отправляются.`);
  });
  $('opportunity-filter').addEventListener('input', event => {
    const query = event.target.value.toLocaleLowerCase(); let count = 0;
    document.querySelectorAll('[data-opportunity]').forEach(button => {
      button.hidden = !button.textContent.toLocaleLowerCase().includes(query);
      if (!button.hidden) count++;
    });
    $('list-no-results').hidden = count > 0;
  });
  document.querySelectorAll('[data-open-evidence]').forEach(button => button.addEventListener('click', () => openInspector(button)));
  $('close-inspector').addEventListener('click', () => closeInspector());
  $('pin-inspector').addEventListener('click', () => { pinned = !pinned; renderInspector(); announce(pinned ? 'Подтверждение закреплено для этой вакансии.' : 'Подтверждение откреплено.'); });
  $('back-level').addEventListener('click', () => {
    if (level === 'context') { closeInspector(); remember(); }
    else if (level === 'work' && allowSelection()) { setLevel('list'); remember(); announce('Открыт список вакансий.'); }
  });
  document.addEventListener('keydown', event => {
    if ($('account-dialog').open) {
      if (event.key === 'Tab') {
        const items = [...$('account-dialog').querySelectorAll('button:not(:disabled), select')].filter(el => el.getClientRects().length);
        const first = items[0], last = items.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
      }
      return;
    }
    if (event.key === 'Tab' && modalPanel) {
      const items = [...modalPanel.querySelectorAll('button:not(:disabled), input, select, a[href], [tabindex="0"]')].filter(el => el.getClientRects().length);
      const first = items[0], last = items.at(-1);
      if (event.shiftKey && (document.activeElement === first || !items.includes(document.activeElement))) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && (document.activeElement === last || !items.includes(document.activeElement))) { event.preventDefault(); first.focus(); }
    }
    if (event.key === 'Escape' && isCompact() && document.querySelector('.global-menu').open) { document.querySelector('.global-menu').open = false; document.querySelector('.global-menu > summary').focus(); }
    else if (event.key === 'Escape' && listOpen) { event.preventDefault(); closeList(); }
    else if (event.key === 'Escape' && !$('inspector').hidden) { event.preventDefault(); closeInspector(); if (isCompact()) remember(); }
  });
  $('accept').addEventListener('click', () => { accepted = true; renderReview(); $('approve').focus(); announce('Правка добавлена в вымышленный черновик. Текст нужно одобрить отдельно.'); });
  $('edit').addEventListener('click', () => { $('edit-box').hidden = false; $('proposal-edit').value = proposal; renderReview(); $('proposal-edit').focus(); });
  $('cancel-edit').addEventListener('click', () => { $('edit-box').hidden = true; renderReview(); $('edit').focus(); });
  $('save-edit').addEventListener('click', () => {
    if (!supportedProposals.has($('proposal-edit').value)) {
      $('action-status').setAttribute('role', 'alert');
      $('action-status').textContent = 'Правка заблокирована: для этого текста в примере нет подтверждений. Верните подтверждённую формулировку.';
      announce('Для правки нет подтверждений. Её нельзя принять или одобрить.');
      return;
    }
    proposal = $('proposal-edit').value; accepted = false; $('edit-box').hidden = true;
    $('action-status').setAttribute('role', 'status'); $('action-status').textContent = 'Текст с подтверждениями сохранён для проверки. Он ещё не одобрен.';
    renderReview(); $('accept').focus();
  });
  $('reject').addEventListener('click', () => { rejected = true; accepted = false; renderReview(); $('action-status').setAttribute('role', 'status'); $('action-status').textContent = 'Правка отклонена. Прежний текст сохранён; отклик не отправлен.'; $('review-state').focus(); announce('Правка отклонена.'); });
  $('approve').addEventListener('click', () => { approved = true; renderReview(); $('action-status').setAttribute('role', 'status'); $('action-status').textContent = 'Этот вымышленный текст одобрен в макете. Отклик не отправлен.'; $('review-state').focus(); announce('Одобрение сохранено только в открытом макете. Отклик не отправлен.'); });
  // Synthetic session display only. No credentials, server roles or persistent state.
  let demoSession = 'user';
  function renderAccount() {
    const guest = demoSession === 'guest', admin = demoSession === 'admin';
    $('account-role-label').textContent = guest ? 'Демо · без входа' : admin ? 'Демо · администратор' : 'Демо · пользователь';
    $('demo-profile-name').textContent = guest ? 'Вы не вошли в демо-профиль' : admin ? 'Демо-администратор' : 'Демо-пользователь';
    $('demo-profile-detail').textContent = guest ? 'Для просмотра вакансий вход в макет не нужен' : 'Вымышленный профиль · данные не загружаются';
    $('demo-session').value = demoSession;
    $('admin-utilities').hidden = !admin;
    $('diagnostics-action').disabled = !admin;
    $('profile-action').disabled = guest;
    $('settings-action').disabled = guest;
    $('demo-login').hidden = !guest;
    $('demo-logout').hidden = guest;
  }
  function setDemoSession(value) {
    if (!['user', 'admin', 'guest'].includes(value)) return;
    demoSession = value; renderAccount();
    $('account-feedback').textContent = 'Режим изменён только в макете. Сессия и права в приложении не менялись.';
  }
  $('account-trigger').addEventListener('click', () => {
    closeList(false);
    if (!$('inspector').hidden) { closeInspector(false); if (isCompact()) remember(false); }
    renderAccount();
    $('account-feedback').textContent = 'Выберите пункт меню или посмотрите другой режим доступа.';
    const dialog = $('account-dialog'), wasOpen = dialog.open;
    dialog.style.setProperty('--account-top', `${Math.round($('account-trigger').getBoundingClientRect().bottom + 8)}px`);
    delete dialog.dataset.closing;
    if (!wasOpen) dialog.showModal();
    syncPanels();
    motion.to(dialog, 1, { fresh: !wasOpen, y: isCompact() ? 16 : -8, scale: .01 });
    $('account-trigger').setAttribute('aria-expanded', 'true');
    $('close-account').focus(); $('account-dialog').scrollTop = 0;
  });
  function closeAccount() {
    const dialog = $('account-dialog');
    if (dialog.dataset.closing) { motion.stop(dialog); dialog.close(); return; }
    dialog.dataset.closing = 'true';
    motion.to(dialog, 0, { y: isCompact() ? 16 : -8, scale: .01, complete: () => dialog.close() });
  }
  $('close-account').addEventListener('click', closeAccount);
  $('account-dialog').addEventListener('cancel', event => { event.preventDefault(); closeAccount(); });
  // New input redirects an exit from its actual presentation value, without locking controls.
  for (const name of ['pointerdown', 'keydown']) $('account-dialog').addEventListener(name, event => {
    if (!$('account-dialog').dataset.closing || event.target.closest('#close-account') || event.key === 'Escape') return;
    delete $('account-dialog').dataset.closing;
    motion.to($('account-dialog'), 1, { y: isCompact() ? 16 : -8, scale: .01 });
  }, true);
  let backdropPress = false;
  $('account-dialog').addEventListener('pointerdown', event => {
    const r = $('account-dialog').getBoundingClientRect();
    backdropPress = event.target === $('account-dialog') && (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom);
  });
  $('account-dialog').addEventListener('click', event => {
    const r = $('account-dialog').getBoundingClientRect();
    if (event.target === $('account-dialog') && backdropPress && (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom)) closeAccount();
    backdropPress = false;
  });
  $('account-dialog').addEventListener('close', () => {
    if ($('account-dialog').open) return;
    motion.stop($('account-dialog')); delete $('account-dialog').dataset.closing;
    $('account-trigger').setAttribute('aria-expanded', 'false'); syncPanels(); $('account-trigger').focus();
  });
  $('demo-session').addEventListener('change', event => setDemoSession(event.target.value));
  $('demo-login').addEventListener('click', () => { setDemoSession('user'); $('demo-session').focus(); });
  $('demo-logout').addEventListener('click', () => { setDemoSession('guest'); $('demo-login').focus(); });
  $('profile-action').addEventListener('click', () => { $('account-feedback').textContent = 'Редактирование профиля в этом макете пока недоступно. Карьерные факты не меняются.'; });
  $('settings-action').addEventListener('click', () => { $('account-feedback').textContent = 'Экран настроек в этот макет не входит. Настройки приложения не меняются.'; });
  $('diagnostics-action').addEventListener('click', () => { $('account-feedback').textContent = 'В приложении диагностику открывает администратор. Здесь показан только пункт меню, запросов и реальных инцидентов нет.'; });
  $('open-list').addEventListener('click', () => {
    closeInspector(false); listOpen = true; document.body.dataset.listOpen = 'true';
    $('open-list').setAttribute('aria-expanded', 'true'); syncPanels(); $('list-title').focus();
    motion.to($('opportunity-list'), 1, { fresh: true, x: -12, y: 0 });
  });
  $('close-list').addEventListener('click', () => closeList());
  document.querySelector('.skip-link').addEventListener('click', event => { event.preventDefault(); setLevel('work', false); remember(); $('workspace-title').focus(); });
  window.addEventListener('hashchange', () => {
    const name = location.hash.slice(1);
    if (sections.has(name)) { section = name; render(); announce(`Открыт раздел «${$('section-select').selectedOptions[0].textContent}».`); }
  });
  window.addEventListener('popstate', event => {
    if (editing()) { remember(); allowSelection(); return; }
    const saved = event.state;
    if (!saved || !opportunities[saved.selected] || !scenarios.has(saved.scenario) || !sections.has(saved.section)) return;
    const changed = selected !== saved.selected || scenario !== saved.scenario;
    closeInspector(false); closeList(false);
    selected = saved.selected; scenario = saved.scenario; section = saved.section; level = saved.level;
    if (changed) { pinned = false; accepted = scenario === 'approval' && selected === 'northstar'; approved = false; rejected = false; proposal = selected === 'cedar' ? 'Готов к гибридной работе в Москве.' : defaultProposal; }
    render();
    if (level === 'context') { level = 'work'; openInspector(document.querySelector('#materials [data-open-evidence]'), false); }
    else setLevel(level);
  });
  window.addEventListener('resize', () => { if (!tabletQuery.matches) closeList(false); document.querySelector('.global-menu').open = !isCompact(); setLevel(level, false); syncPanels(); });
  if (selected === 'cedar') proposal = 'Готов к гибридной работе в Москве.';
  $('token-revision').textContent = getComputedStyle(document.documentElement).getPropertyValue('--cv-token-source-sha256').trim().replaceAll('"', '');
  render(); remember(false);
})();
