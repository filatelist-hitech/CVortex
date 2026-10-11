# План concept exploration

Дата: 2026-10-07. Статус: proposal, awaiting user selection. Режим: RESEARCH, явный bounded user override; один агент, последовательная работа. Ветка `docs/ux-redesign-concepts`, base/будущий PR target `stage`; remote stage и HEAD совпадали: `a62ff0899ea9a2dd2266eb5a972d618f803c9952`. Push, PR, commit и delivery не входят в задачу.

Вопрос: какая модель позволяет быстро увидеть состояние поиска, связанный контекст и полезное действие без длинного последовательного просмотра? Свежесть: текущие официальные product/help pages, предпочтительно 2025–2026; фундаментальные HAX/PAIR допустимы с явной датировкой. STOP: четыре runnable концепта, browser evidence, сравнение и рекомендация; окончательный выбор остаётся пользователю.

## Инварианты и допущения

**Hard:** CVortex; Your career, in context.; dark-first navy, cyan/blue/violet, Space Grotesk; confirmed Facts → Claims → Content; human approval; consistency с подтверждённой Employer Memory; приватность; неизвестное не равно отсутствующему; отсутствие псевдоточных ATS/вероятностных оценок; accessibility AA, keyboard, 44px targets, reduced motion, reflow; critical employer actions только человеком.

**Redesignable:** секции/навигация, длинная страница, равноправные карточки, density, UI-размещение AI, масштаб и композиция типографики. Prototype-only CSS не становится новой token authority.

**Логотип:** отдельного approved logo asset в task-relevant repository inventory не найдено. Сохраняем CVortex wordmark и существующий CSS `brand-mark` (градиентная линия из `apps/frontend/src/app/globals.css:83`); не выдаём её за утверждённый полноценный логотип. Перед final design reconciliation нужен approved asset, если он хранится вне репозитория.

**DESIGN_SOURCE_OF_TRUTH_CONFLICT: present.** PROJECT.md, accepted ADR-0013/0014 и Design-Foundation закрепляют reviewed Figma visual/component authority плюс Git tokens. Текущее явное задание запрещает Figma и разрешает HTML exploration. Это временное разрешение для proposals, не superseding ADR. Existing docs, token JSON и production files остаются неизменными.

## Визуальный план до реализации

Цвета из существующей brand family: canvas `#070b18`, surface `#0b1224`, elevated `#111c35`, text `#f7f9fc`, cyan `#35d6e8`, blue `#669bff`, violet `#a78bfa`. Семантические состояния: green confirmed, amber unknown/pending, red blocked с обязательными текстовыми метками. Акценты не образуют декоративный HUD.

Тип: Space Grotesk для латинского интерфейса, 32px H1, 24px H2, 16px body, 14px compact, минимум 12px; tabular figures для сроков и зарплат. Локальный font asset с upstream OFL сохраняет воспроизводимость offline, только для research preview; production font workflow не меняется.

```
A: navigation | urgent action + state strip
              | pipeline table       | selected decision
              | employer timeline    | interview agenda
B: application list | vacancy + local sections | context inspector
                    | inline AI diff          | Fact / employer
C: career track header
   confirmed experience → capability/evidence → linked opportunities
   evidence story                             | gaps/review
D: Discover → Analyze → Prepare → Apply → Communicate → Interview → Outcome
   stage queue | active stage task + readiness | downstream context
```

План пересмотрен против brief: общий dashboard-kit исключён; каждый экран использует разную единицу организации. A организует **внимание**, B — **отклик**, C — **доказанный карьерный контекст**, D — **движение через этапы**. Одинаковые fixtures и brand family сохраняют сопоставимость. Alignment — left; чтение длинного evidence ограничено по ширине; вторичная metadata раскрывается.

Concept previews демонстрируют также будущие pipeline/interview/employer capabilities. Это vision exploration, не утверждение об их текущей доступности. Текущий stage подтверждает Career/Vacancy/draft core, Preview acceptance ещё не записана; M2 не разрешён.
