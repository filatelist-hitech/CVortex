# Реальные blockers

Проверено 2026-10-11; scope/evidence в [Recovery Report](PROJECT_RECOVERY_REPORT.md).
Недостающие M2–M6 функции — planned deliverables, а не blockers текущей recovery task.

## Закрыто в RCV-01 — local schema mismatch

2026-10-11 existing migration применена только к `cvortex_test`, явно разрешённой
владельцем как текущая local development DB вопреки test-DB запрету исходной spec.
Private backup verified; 28/28 Ran; column/default present; данные и isolation
metadata сохранены; targeted mocked tests и health PASS.
[Execution report](LOCAL_RUNTIME_ALIGNMENT_REPORT.md). Live ChatGPT/account/provider
readiness этим не доказана. Production mutations и другие DB не выполнялись.

## 1. Production accessibility release gate

- **Причина:** mandatory reference/production state/viewport interactions,
  K1 keyboard/focus, Z1 native200% zoom/reflow и A1 audible human screen reader
  остаются NOT TESTED/OPEN.
- **Влияние:** production release BLOCKED по ADR-0025. B01 owner ACCEPTED и
  merged B02 не закрывают gate.
- **Разрешение:** existing
  [b01-manual-accessibility-release-gate](../tasks/b01-manual-accessibility-release-gate.md),
  named human evidence для всех mandatory rows, устранение blocking findings
  отдельными authorized tasks. Сначала current reference integrity.
- **Владелец:** нужен human reviewer/AT session; screenshots/DOM/AX недостаточны.

## 2. Current B01 reference integrity

- **Причина:** fresh `build-reference.cjs --check` FAIL:
  manifest/referenceAssetSha256 для assets/OFL.txt не соответствует tracked bytes.
  [Точное hash evidence](recovery-2026-10-11/manifest-drift.json).
- **Влияние:** current canonical integrity PASS и новые release-readiness claims
  не подтверждены; historical owner acceptance не отменяется.
- **Разрешение:** отдельная bounded current-manifest/license-provenance reconciliation,
  exact diff review и --check PASS, без переписывания historical reports/goldens.
- **Владелец:** новое design/ADR решение сейчас не требуется; разрешённый scope
  будущей correction task нужен, здесь manifest не менялся.

## 3. Preview 0.1 → M2 product gate

- **Причина:** repository не содержит полной real-user acceptance и feedback
  по m1-4 Preview completion checklist. Synthetic UI и unit tests этого не доказывают.
- **Влияние:** M1.4 full PASS/Preview accepted не заявлены; M2 planning/implementation
  ещё не executable. Missing package features не отдельные blockers Preview.
- **Разрешение:** provider readiness, authorized real user E2E со всеми
  PASS/FAIL criteria и feedback; только затем reassessment NEXT для M2.
- **Владелец:** real user review и разрешение на конкретную передачу user content
  провайдеру, если требуется новый live analysis/generation.

## Provider limitation — последнее известное evidence, не новый live диагноз

Исторический authorized retry 2026-10-03: все три попытки RATE_LIMITED/HTTP429,
final FAILED/PROVIDER_ERROR, успешного analysis/matching result не было.
Причина quota/billing/rate limit не установлена. Текущий статус провайдера
**NOT_VERIFIED**: этот audit не делал billing/account read или live запросы.
Оператору требуется проверить конкретный configured project перед разрешённым
повтором; API-key и ChatGPT plan usage — разные каналы. Это ограничение live Preview,
не blocker документационных/локальных mocked checks.

Development-mode frontend build issue и PR #34 governance failures — risks/follow-ups
в Recovery Report, не blockers production-mode Preview или всех DOCS работ.
