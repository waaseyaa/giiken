# Discovery Surface: Config Coverage, Snapshots, and Search Hero

**Issues:** #86, #84, #18
**Date:** 2026-04-12
**Approach:** Single combined spec, one PR with per-issue commits

---

## 1. Config Coverage Test (#86)

**File:** `tests/js/KnowledgeTypeConfig.test.ts`

### Changes to `types.ts`

Export a `KNOWLEDGE_TYPES` const array typed as `KnowledgeType[]`:

```ts
export const KNOWLEDGE_TYPES: KnowledgeType[] = [
  'cultural', 'governance', 'land', 'relationship', 'event',
] as const satisfies readonly KnowledgeType[]
```

This gives a runtime-iterable source of truth. The existing `KNOWLEDGE_TYPE_CONFIG: Record<KnowledgeType, ...>` already enforces compile-time completeness; the array enables runtime assertions.

### Invariant 1: TS-side completeness

Assert that every member of `KNOWLEDGE_TYPES` has a corresponding key in `KNOWLEDGE_TYPE_CONFIG`, and vice versa. Same length, same members, no extras.

### Invariant 2: PHP-TS sync

Read `src/Entity/KnowledgeItem/KnowledgeType.php` at test time, extract enum case values via regex (`case\s+\w+\s*=\s*'(\w+)'`), and assert every PHP case either:

- Exists in the TS `KNOWLEDGE_TYPES` array, or
- Is listed in an `EXPECTED_FUTURE` set (starts with `['synthesis']`)

Additional guard: if a value in `EXPECTED_FUTURE` also appears in `KNOWLEDGE_TYPES`, the test fails. This forces cleanup of the set when a future type gets wired into the frontend.

### Why both invariants

The TS compiler catches missing keys in `KNOWLEDGE_TYPE_CONFIG` at build time, but cannot detect PHP-side additions. The PHP-TS sync invariant catches the case where someone adds a new `KnowledgeType` enum case in PHP but forgets to add it to the TypeScript union and config. Together they form a closed loop: PHP enum -> TS union -> config record -> test.

---

## 2. DOM Snapshot Tests (#84)

**File:** `tests/js/snapshots.test.ts`
**Strategy:** Vitest inline snapshots via `mount().html()` from Vue Test Utils. No new dependencies.

### Phase D.1 coverage (this PR)

| Component | Fixtures | Snapshot count |
|-----------|----------|---------------|
| KnowledgeCard | One per knowledge type | 5 |
| CitationCard | One citation with type | 1 |
| AnswerPanel | Answer with 2 citations | 1 |
| SearchInput | Default state with community slug | 1 |
| TypeFilter | No active filter + one active | 2 |
| SearchHero (#18) | Single community + multi community | 2 |
| BrowseStrip (#18) | Single community | 1 |

**Total: ~13 snapshots**

### Structure

Single test file, grouped by `describe()` blocks per component. Snapshot tests are structural, not behavioral, so they live separately from the existing per-component behavioral tests (`KnowledgeCard.test.ts`, `CitationCard.test.ts`, `AnswerPanel.test.ts`).

### Stubs

`@inertiajs/vue3` `Link` stubbed as a plain `<a>` element (matches existing test pattern).

### Phase D.3 (later, not this PR)

- Introduce Playwright screenshot diffs
- Capture baselines for key flows
- Add CI gating

### Cleanup

Any Playwright-based tests found in `/tmp/giiken-puppeteer/` or elsewhere that are heavy/inappropriate for the current stage will be removed.

---

## 3. Discovery Surface (#18)

**Scope:** Rework `Discover.vue` (the `/` root landing page). `Discovery/Index.vue` (community-scoped) is untouched.

### New component: `SearchHero.vue`

Full-width gradient hero section for the root landing page.

**Props:**
```ts
defineProps<{
  communities: CommunitySummary[]
}>()
```

**Behavior:**
- Gradient background using existing `from-primary to-primary-hover` tokens
- Headline: "Sovereign Indigenous Knowledge"
- Subtext: governance-focused tagline
- Embedded `SearchInput` configured for root-level use
- When `communities.length === 1`: search input navigates directly to that community's search/ask route
- When `communities.length > 1`: a `<select>` element appears inline before the search input, listing community names. Selected community determines the search/ask navigation target.
- When `communities.length === 0`: search input is hidden (nothing to search)

**Styling:** Reuses existing design tokens. No new CSS custom properties needed.

### New component: `BrowseStrip.vue`

Horizontal knowledge-type chip strip below the hero.

**Props:**
```ts
defineProps<{
  communities: CommunitySummary[]
}>()
```

**Behavior:**
- Renders one chip per entry in `KNOWLEDGE_TYPE_CONFIG`
- Uses `KNOWLEDGE_TYPE_CONFIG` for labels and chip styling (same tokens as `TypeFilter`)
- Unlike `TypeFilter` (which filters an in-page list), `BrowseStrip` chips are navigation links
- Each chip navigates to `/{communitySlug}?type={type}`
- When multiple communities exist, chips link to the first community (MVP behavior)
- When no communities exist, strip is hidden

**Layout:** Horizontal scroll on mobile, centered row on desktop. `overflow-x-auto` with `flex-nowrap`.

### Updated `Discover.vue` structure

```html
<div class="min-h-screen bg-surface">
  <nav>  <!-- existing, unchanged -->
  <SearchHero :communities="communities" />
  <BrowseStrip :communities="communities" />
  <main>  <!-- existing community grid, unchanged -->
</div>
```

The hero replaces the current `<header>` block. The browse strip sits between hero and community grid as a new section. The community grid below remains as-is.

### No backend changes

The existing controller already passes `communities: CommunitySummary[]` to the Inertia page. All navigation targets (`/{slug}`, `/{slug}/search`, `/{slug}/ask`) already have routes and controllers.

### Props flow

- `Discover.vue` receives `communities: CommunitySummary[]` (unchanged)
- Passes `communities` down to `SearchHero` and `BrowseStrip`
- Both components derive navigation targets from community slugs

---

## File inventory

### New files
- `tests/js/KnowledgeTypeConfig.test.ts` — config coverage test
- `tests/js/snapshots.test.ts` — DOM snapshot tests
- `resources/js/Components/SearchHero.vue` — hero component
- `resources/js/Components/BrowseStrip.vue` — browse strip component

### Modified files
- `resources/js/types.ts` — add `KNOWLEDGE_TYPES` array export
- `resources/js/Pages/Discover.vue` — replace `<header>` with `SearchHero` + `BrowseStrip`

### Unchanged files
- `resources/js/Pages/Discovery/Index.vue`
- `resources/js/Components/SearchInput.vue`
- `resources/js/Components/TypeFilter.vue`
- `resources/js/Components/KnowledgeCard.vue`
- All PHP files

---

## Commit plan

1. `feat(#86): add KNOWLEDGE_TYPES array and config coverage test`
2. `feat(#18): add SearchHero and BrowseStrip components, rework Discover.vue`
3. `test(#84): add DOM snapshot tests for all Vue components`

Snapshots go last so they capture the final state of all components including the new #18 ones.
