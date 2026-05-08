# Mission Spec: Giiken domain modeling

**Mission ID**: `01KR2HKT7J73P2TK4XP9J0D9S0` (mid8: `01KR2HKT`)  
**Mission Slug**: `giiken-domain-modeling-01KR2HKT`  
**Mission Type**: `software-dev`  
**Target Branch**: `main`  
**Created**: 2026-05-08  
**Status**: In progress (specify phase)

---

## Overview

Giiken’s **community-scoped knowledge** domain is already implemented as three Waaseyaa content entities plus value objects and policies, but knowledge of that domain is fragmented across providers, migrations, and docs. This mission **canonizes** the domain model and boundaries so ingestion, compilation, UI, and governance features can extend without silent drift between schema, entities, and access rules.

**User intent (verbatim seed):** Start Giiken’s domain modeling mission — the natural successor to the foundation Symfony fallback work — because the framework is stable enough to support serious domain work.

**Not a bulk edit:** No cross-repo identifier rename; no `occurrence_map.yaml` requirement.

---

## User Scenarios & Testing

### Primary user story

**US-1: Maintainer extending Giiken safely**

As a maintainer adding ingestion or governance behavior, I want a **single authoritative description** of communities, knowledge items, lint reports, and their relationships so I know which columns exist, how tenancy is expressed, and where RBAC applies—without re-reading every migration and provider.

**Acceptance:** I can open the mission’s `data-model.md` and `research.md` (or downstream `docs/architecture/` mirrors produced in plan) and trace any field I persist to either a named column or an explicit `_data` contract.

### Acceptance scenarios

| ID | Scenario | Expected outcome |
| --- | --- | --- |
| AS-1 | A developer reads `data-model.md` before adding a `knowledge_item` field | They find whether similar data already lives in `_data` or needs a migration column, and how `community_id` ties to the tenant. |
| AS-2 | A developer implements a feature touching `AccessTier` | They cross-reference `research.md` decisions with `KnowledgeItemAccessPolicy` tests; no contradiction with documented tier semantics. |
| AS-3 | Planning / tasks phase runs | Work packages cite FR/NFR IDs from this spec; traceability preserved in commits or mission artifacts. |

### Edge cases

- **SQLite column limits:** New list fields need JSON/text columns; document array vs scalar storage expectations.
- **UUID vs numeric id:** `community_id` is `VARCHAR(128)`; document accepted formats for new code paths.
- **Wiki lint findings:** JSON shape may evolve; versioning or backward compatibility called out in plan if code changes.

---

## Requirements

### Functional requirements

| ID | Requirement | Status |
| --- | --- | --- |
| FR-001 | The mission SHALL maintain `kitty-specs/<slug>/data-model.md` listing all three entity type IDs, PHP classes, primary keys, label keys, and **entity-to-entity relationships** (including `community_id` edges). | Agreed |
| FR-002 | The mission SHALL maintain `kitty-specs/<slug>/research.md` capturing aggregate boundaries (Community, KnowledgeItem, WikiLintReport), tenancy rules, storage split (columns vs `_data`), and open questions for later phases. | Agreed |
| FR-003 | Domain documentation SHALL stay consistent with `migrations/` as applied to fresh installs (field lists match additive migrations through the mission’s merge commit). | Proposed |
| FR-004 | Any **code change** that alters entity persistence, HTTP lifecycle, or provider boot order SHALL update `docs/architecture/lifecycle.md` in the same change set per Giiken repo policy. | Proposed |
| FR-005 | Downstream plan/tasks SHALL enumerate concrete work (e.g. optional `docs/architecture/domain-model.md` mirror, audit scripts, or typed domain services) without contradicting FR-001–FR-003. | Proposed |

### Non-functional requirements

| ID | Requirement | Threshold | Status |
| --- | --- | --- | --- |
| NFR-001 | Traceability | Every normative claim in `research.md` that affects implementation SHALL cite an internal path (`src/…`, `migrations/…`, or `docs/…`) or be listed as an explicit assumption. | Agreed |
| NFR-002 | Reviewability | `checklists/requirements.md` SHALL be updated in the same PR or WP batch as substantive `spec.md` edits so reviewers can gate quality. | Agreed |
| NFR-003 | Test stability | Missions that only add/update documentation under `kitty-specs/` and `docs/` SHALL keep `composer test` and `npm run test:js` green unless a WP explicitly changes runtime code. | Proposed |

### Constraints

| ID | Constraint | Status |
| --- | --- | --- |
| C-001 | Work merges to `main` on the Giiken repository; Waaseyaa/framework changes are out of scope unless filed as a separate mission. | Required |
| C-002 | RBAC semantics (`CommunityRole` × `AccessTier` × `KnowledgeItemAccessPolicy`) SHALL NOT change in this mission unless a dedicated WP cites stakeholder approval and expands tests. | Required |
| C-003 | No new persisted entity type SHALL be introduced without a migration and `EntitiesProvider` registration following existing patterns. | Required |

---

## Success criteria

| ID | Outcome | How verified |
| --- | --- | --- |
| SC-1 | Canonical domain description exists for the three entities. | `data-model.md` reviewed against `KnowledgeItem`, `Community`, `WikiLintReport`, and migrations; no orphan columns. |
| SC-2 | Intent and boundaries are explicit for future features. | `research.md` lists aggregates, tenancy, and open questions; plan references them. |
| SC-3 | No accidental policy drift. | If PHP changes land, `tests/Unit/Access/KnowledgeItemAccessPolicyTest.php` (or successor) remains green. |

---

## Key entities

| Concept | Role |
| --- | --- |
| Community | Tenant root; wiki schema and sovereignty metadata |
| KnowledgeItem | Governed content; compilation and search subject |
| WikiLintReport | Derived per-community diagnostics |

(See `data-model.md` for column-level detail.)

---

## Assumptions

1. Waaseyaa `ContentEntityBase` + `EntityType::fromClass()` remain the persistence model for this program increment.
2. Giiken continues to use SQLite for dev/test; column-add patterns follow existing migrations.

---

## Dependencies

- Waaseyaa entity stack and migrations tooling (already in use).
- Optional: framework bump missions (e.g. alpha.174+) remain independent; this spec does not pin a composer version.
