# Discovery Surface Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add config coverage tests (#86), SearchHero + BrowseStrip components (#18), and DOM snapshot tests (#84) in a single PR.

**Architecture:** Three commits in sequence. First adds a `KNOWLEDGE_TYPES` array to `types.ts` and a test that validates TS-PHP enum sync. Second builds `SearchHero.vue` and `BrowseStrip.vue`, wires them into `Discover.vue`. Third adds DOM snapshots for all Vue components including the new ones.

**Tech Stack:** Vue 3, TypeScript, Vitest, Vue Test Utils, Inertia.js, Tailwind CSS v4

---

## File Map

### New files
| File | Responsibility |
|------|---------------|
| `tests/js/KnowledgeTypeConfig.test.ts` | Config coverage + PHP-TS sync invariants |
| `resources/js/Components/SearchHero.vue` | Root-level gradient hero with search input |
| `resources/js/Components/BrowseStrip.vue` | Knowledge-type navigation chip strip |
| `tests/js/snapshots.test.ts` | DOM snapshot tests for all Vue components |

### Modified files
| File | Change |
|------|--------|
| `resources/js/types.ts` | Add `KNOWLEDGE_TYPES` array export |
| `resources/js/Pages/Discover.vue` | Replace `<header>` with SearchHero + BrowseStrip |

---

## Task 1: Add KNOWLEDGE_TYPES array to types.ts

**Files:**
- Modify: `resources/js/types.ts`

- [ ] **Step 1: Add KNOWLEDGE_TYPES export**

In `resources/js/types.ts`, after the `KnowledgeType` type definition (line 16), add:

```ts
export const KNOWLEDGE_TYPES = [
  'cultural', 'governance', 'land', 'relationship', 'event',
] as const satisfies readonly KnowledgeType[]
```

This goes between the `KnowledgeType` type (line 16) and the `AccessTier` type (line 17).

- [ ] **Step 2: Verify no type errors**

Run: `npx vue-tsc --noEmit`
Expected: Clean exit, no errors.

---

## Task 2: Write config coverage test (invariant 1 — TS-side)

**Files:**
- Create: `tests/js/KnowledgeTypeConfig.test.ts`

- [ ] **Step 1: Write the TS completeness test**

Create `tests/js/KnowledgeTypeConfig.test.ts`:

```ts
import { describe, it, expect } from 'vitest'
import { KNOWLEDGE_TYPES, KNOWLEDGE_TYPE_CONFIG } from '@/types'

describe('KNOWLEDGE_TYPE_CONFIG', () => {
  it('has an entry for every member of KNOWLEDGE_TYPES', () => {
    const configKeys = Object.keys(KNOWLEDGE_TYPE_CONFIG).sort()
    const typesList = [...KNOWLEDGE_TYPES].sort()
    expect(configKeys).toEqual(typesList)
  })

  it('every config entry has required style properties', () => {
    for (const type of KNOWLEDGE_TYPES) {
      const entry = KNOWLEDGE_TYPE_CONFIG[type]
      expect(entry).toHaveProperty('label')
      expect(entry).toHaveProperty('chip')
      expect(entry).toHaveProperty('activeChip')
      expect(entry).toHaveProperty('dot')
    }
  })
})
```

- [ ] **Step 2: Run the test**

Run: `npm run test:js -- --run tests/js/KnowledgeTypeConfig.test.ts`
Expected: 2 tests PASS.

---

## Task 3: Write PHP-TS sync test (invariant 2)

**Files:**
- Modify: `tests/js/KnowledgeTypeConfig.test.ts`

- [ ] **Step 1: Add the PHP-TS sync invariant**

Append to `tests/js/KnowledgeTypeConfig.test.ts`, inside the existing `describe` block, after the last `it()`:

```ts
  const EXPECTED_FUTURE = new Set(['synthesis'])

  it('every PHP KnowledgeType case is in KNOWLEDGE_TYPES or EXPECTED_FUTURE', async () => {
    const fs = await import('node:fs/promises')
    const path = await import('node:path')
    const phpPath = path.resolve(__dirname, '../../src/Entity/KnowledgeItem/KnowledgeType.php')
    const phpSource = await fs.readFile(phpPath, 'utf-8')
    const caseRegex = /case\s+\w+\s*=\s*'(\w+)'/g
    const phpCases: string[] = []
    let match: RegExpExecArray | null
    while ((match = caseRegex.exec(phpSource)) !== null) {
      phpCases.push(match[1])
    }

    expect(phpCases.length).toBeGreaterThan(0)

    const tsSet = new Set<string>(KNOWLEDGE_TYPES)
    for (const phpCase of phpCases) {
      const inTs = tsSet.has(phpCase)
      const inFuture = EXPECTED_FUTURE.has(phpCase)
      expect(
        inTs || inFuture,
        `PHP case '${phpCase}' is not in KNOWLEDGE_TYPES or EXPECTED_FUTURE`,
      ).toBe(true)
    }
  })

  it('EXPECTED_FUTURE has no members already in KNOWLEDGE_TYPES', () => {
    const tsSet = new Set<string>(KNOWLEDGE_TYPES)
    for (const futureType of EXPECTED_FUTURE) {
      expect(
        tsSet.has(futureType),
        `'${futureType}' is in both EXPECTED_FUTURE and KNOWLEDGE_TYPES — remove it from EXPECTED_FUTURE`,
      ).toBe(false)
    }
  })
```

- [ ] **Step 2: Run all config tests**

Run: `npm run test:js -- --run tests/js/KnowledgeTypeConfig.test.ts`
Expected: 4 tests PASS.

- [ ] **Step 3: Run full test suite**

Run: `npm run test:js`
Expected: All tests pass (existing + new).

- [ ] **Step 4: Commit**

```bash
git add resources/js/types.ts tests/js/KnowledgeTypeConfig.test.ts
git commit -m "feat(#86): add KNOWLEDGE_TYPES array and config coverage test

Two runtime invariants:
1. KNOWLEDGE_TYPE_CONFIG keys match KNOWLEDGE_TYPES array
2. Every PHP KnowledgeType enum case exists in TS or EXPECTED_FUTURE

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Create SearchHero component

**Files:**
- Create: `resources/js/Components/SearchHero.vue`

- [ ] **Step 1: Create SearchHero.vue**

Create `resources/js/Components/SearchHero.vue`:

```vue
<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

interface CommunitySummary {
  id: number | string
  name: string
  slug: string
  locale: string
}

const props = defineProps<{
  communities: CommunitySummary[]
}>()

const query = ref('')
const selectedSlug = computed(() => {
  if (props.communities.length === 1) return props.communities[0].slug
  return manualSlug.value
})
const manualSlug = ref(props.communities[0]?.slug ?? '')

function submit() {
  const q = query.value.trim()
  if (!q || !selectedSlug.value) return

  const isQuestion = q.includes('?') || q.split(/\s+/).length > 5
  const route = isQuestion
    ? `/${selectedSlug.value}/ask`
    : `/${selectedSlug.value}/search`

  router.get(route, { q, page: 1 })
}
</script>

<template>
  <header class="bg-gradient-to-br from-primary to-primary-hover text-on-primary py-20 px-6 text-center">
    <h1 class="text-4xl font-bold mb-3">Sovereign Indigenous Knowledge</h1>
    <p class="text-primary-subtle text-lg max-w-2xl mx-auto mb-10">
      Browse community knowledge bases. Each community governs its own content under its own protocols.
    </p>

    <form
      v-if="communities.length > 0"
      class="flex items-center justify-center gap-2 max-w-2xl mx-auto"
      @submit.prevent="submit"
    >
      <select
        v-if="communities.length > 1"
        v-model="manualSlug"
        class="px-3 py-3 rounded-lg border border-border text-ink text-base"
      >
        <option v-for="c in communities" :key="c.id" :value="c.slug">
          {{ c.name }}
        </option>
      </select>
      <input
        v-model="query"
        type="text"
        placeholder="Search or ask a question..."
        class="flex-1 px-4 py-3 rounded-lg border border-border focus:outline-none focus:ring-2 focus:ring-primary text-ink text-base"
      />
      <button
        type="submit"
        class="px-6 py-3 bg-surface text-primary rounded-lg hover:bg-surface-raised font-medium"
      >
        Ask →
      </button>
    </form>
  </header>
</template>
```

- [ ] **Step 2: Verify no type errors**

Run: `npx vue-tsc --noEmit`
Expected: Clean exit.

---

## Task 5: Create BrowseStrip component

**Files:**
- Create: `resources/js/Components/BrowseStrip.vue`

- [ ] **Step 1: Create BrowseStrip.vue**

Create `resources/js/Components/BrowseStrip.vue`:

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { KNOWLEDGE_TYPE_CONFIG } from '@/types'
import type { KnowledgeType } from '@/types'

interface CommunitySummary {
  id: number | string
  name: string
  slug: string
  locale: string
}

const props = defineProps<{
  communities: CommunitySummary[]
}>()

const types = Object.entries(KNOWLEDGE_TYPE_CONFIG) as [KnowledgeType, typeof KNOWLEDGE_TYPE_CONFIG[KnowledgeType]][]
const targetSlug = props.communities[0]?.slug
</script>

<template>
  <section v-if="communities.length > 0" class="max-w-5xl mx-auto px-6 pt-8">
    <div class="flex gap-2 overflow-x-auto flex-nowrap justify-center">
      <Link
        v-for="[type, config] in types"
        :key="type"
        :href="`/${targetSlug}?type=${type}`"
        class="px-3 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors"
        :class="config.chip"
      >
        {{ config.label }}
      </Link>
    </div>
  </section>
</template>
```

- [ ] **Step 2: Verify no type errors**

Run: `npx vue-tsc --noEmit`
Expected: Clean exit.

---

## Task 6: Wire SearchHero and BrowseStrip into Discover.vue

**Files:**
- Modify: `resources/js/Pages/Discover.vue`

- [ ] **Step 1: Update Discover.vue**

Replace the entire contents of `resources/js/Pages/Discover.vue` with:

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import SearchHero from '@/Components/SearchHero.vue'
import BrowseStrip from '@/Components/BrowseStrip.vue'

interface CommunitySummary {
  id: number | string
  name: string
  slug: string
  locale: string
}

defineProps<{
  communities: CommunitySummary[]
}>()
</script>

<template>
  <div class="min-h-screen bg-surface">
    <nav class="bg-surface-inverse text-on-inverse px-6 py-3">
      <span class="font-bold text-lg">Giiken</span>
    </nav>

    <SearchHero :communities="communities" />
    <BrowseStrip :communities="communities" />

    <main class="max-w-5xl mx-auto px-6 py-12">
      <h2 class="text-xl font-semibold text-ink mb-6">Communities</h2>

      <div v-if="communities.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Link
          v-for="community in communities"
          :key="community.id"
          :href="`/${community.slug}`"
          class="block p-5 bg-surface-raised rounded-lg border border-border hover:shadow-md hover:border-primary transition"
        >
          <h3 class="font-semibold text-ink text-lg">{{ community.name }}</h3>
          <p class="text-sm text-ink-muted mt-1">/{{ community.slug }}</p>
          <p class="text-xs text-ink-muted mt-3 uppercase tracking-wide">{{ community.locale }}</p>
        </Link>
      </div>

      <div v-else class="bg-surface-raised border border-border rounded-lg p-8 text-center">
        <p class="text-ink-muted">
          No communities yet. Run
          <code class="text-primary bg-primary-subtle px-2 py-0.5 rounded">./bin/waaseyaa giiken:seed:test-community</code>
          to seed a demo community.
        </p>
      </div>
    </main>
  </div>
</template>
```

- [ ] **Step 2: Verify no type errors**

Run: `npx vue-tsc --noEmit`
Expected: Clean exit.

- [ ] **Step 3: Visual smoke test**

Start the dev server: `composer run dev`
Open `http://127.0.0.1:8080/` in a browser.

Verify:
- Hero section renders with gradient, headline, and search input
- If one seeded community exists, no `<select>` dropdown appears
- BrowseStrip renders 5 knowledge-type chips below the hero
- Clicking a chip navigates to `/{communitySlug}?type={type}`
- Community grid still renders below the browse strip
- Submitting a search query navigates to `/{slug}/search?q=...`
- Submitting a question (contains `?` or >5 words) navigates to `/{slug}/ask?q=...`

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/SearchHero.vue resources/js/Components/BrowseStrip.vue resources/js/Pages/Discover.vue
git commit -m "feat(#18): add SearchHero and BrowseStrip, rework Discover.vue

SearchHero: gradient hero with embedded search input. Auto-selects
community when only one exists, shows a <select> for multiple.

BrowseStrip: knowledge-type chip strip using KNOWLEDGE_TYPE_CONFIG.
Chips are navigation links into the community discovery surface.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: Write DOM snapshot tests

**Files:**
- Create: `tests/js/snapshots.test.ts`

- [ ] **Step 1: Create snapshot test file**

Create `tests/js/snapshots.test.ts`:

```ts
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import KnowledgeCard from '@/Components/KnowledgeCard.vue'
import CitationCard from '@/Components/CitationCard.vue'
import AnswerPanel from '@/Components/AnswerPanel.vue'
import SearchInput from '@/Components/SearchInput.vue'
import TypeFilter from '@/Components/TypeFilter.vue'
import SearchHero from '@/Components/SearchHero.vue'
import BrowseStrip from '@/Components/BrowseStrip.vue'
import { KNOWLEDGE_TYPES } from '@/types'
import type { KnowledgeType, Citation } from '@/types'

const LinkStub = { template: '<a :href="href"><slot /></a>', props: ['href'] }
const globalStubs = { global: { stubs: { Link: LinkStub } } }

const singleCommunity = [{ id: '1', name: 'Test Nation', slug: 'test-nation', locale: 'en' }]
const multiCommunity = [
  { id: '1', name: 'Test Nation', slug: 'test-nation', locale: 'en' },
  { id: '2', name: 'Second Nation', slug: 'second-nation', locale: 'fr' },
]

describe('KnowledgeCard snapshots', () => {
  for (const type of KNOWLEDGE_TYPES) {
    it(`renders ${type} variant`, () => {
      const wrapper = mount(KnowledgeCard, {
        props: {
          id: '1',
          title: `${type} item`,
          summary: `A ${type} knowledge item.`,
          knowledgeType: type as KnowledgeType,
          communitySlug: 'test-community',
        },
        ...globalStubs,
      })
      expect(wrapper.html()).toMatchSnapshot()
    })
  }
})

describe('CitationCard snapshot', () => {
  it('renders a citation', () => {
    const citation: Citation = {
      itemId: '10',
      title: 'Governance doc',
      excerpt: 'Relevant excerpt from the document.',
      knowledgeType: 'governance',
    }
    const wrapper = mount(CitationCard, {
      props: { index: 1, citation, communitySlug: 'test-community' },
      ...globalStubs,
    })
    expect(wrapper.html()).toMatchSnapshot()
  })
})

describe('AnswerPanel snapshot', () => {
  it('renders an answer with citations', () => {
    const citations: Citation[] = [
      { itemId: '10', title: 'Doc A', excerpt: 'Excerpt A.', knowledgeType: 'cultural' },
      { itemId: '11', title: 'Doc B', excerpt: 'Excerpt B.', knowledgeType: 'land' },
    ]
    const wrapper = mount(AnswerPanel, {
      props: {
        answer: 'This is the answer [1] with sources [2].',
        citations,
        communitySlug: 'test-community',
      },
      ...globalStubs,
    })
    expect(wrapper.html()).toMatchSnapshot()
  })
})

describe('SearchInput snapshot', () => {
  it('renders default state', () => {
    const wrapper = mount(SearchInput, {
      props: { communitySlug: 'test-community' },
    })
    expect(wrapper.html()).toMatchSnapshot()
  })
})

describe('TypeFilter snapshots', () => {
  it('renders with no active filter', () => {
    const wrapper = mount(TypeFilter, {
      props: { active: null },
    })
    expect(wrapper.html()).toMatchSnapshot()
  })

  it('renders with cultural active', () => {
    const wrapper = mount(TypeFilter, {
      props: { active: 'cultural' as KnowledgeType },
    })
    expect(wrapper.html()).toMatchSnapshot()
  })
})

describe('SearchHero snapshots', () => {
  it('renders with single community (no select)', () => {
    const wrapper = mount(SearchHero, {
      props: { communities: singleCommunity },
    })
    expect(wrapper.html()).toMatchSnapshot()
  })

  it('renders with multiple communities (shows select)', () => {
    const wrapper = mount(SearchHero, {
      props: { communities: multiCommunity },
    })
    expect(wrapper.html()).toMatchSnapshot()
  })
})

describe('BrowseStrip snapshot', () => {
  it('renders with single community', () => {
    const wrapper = mount(BrowseStrip, {
      props: { communities: singleCommunity },
      ...globalStubs,
    })
    expect(wrapper.html()).toMatchSnapshot()
  })
})
```

- [ ] **Step 2: Run snapshots to generate baselines**

Run: `npm run test:js -- --run tests/js/snapshots.test.ts`
Expected: 13 tests PASS. Vitest creates snapshot file at `tests/js/__snapshots__/snapshots.test.ts.snap`.

- [ ] **Step 3: Run full test suite**

Run: `npm run test:js`
Expected: All tests pass (config tests + behavioral tests + snapshots).

- [ ] **Step 4: Commit**

```bash
git add tests/js/snapshots.test.ts tests/js/__snapshots__/
git commit -m "test(#84): add DOM snapshot tests for all Vue components

13 snapshots covering KnowledgeCard (5 type variants), CitationCard,
AnswerPanel, SearchInput, TypeFilter (2 states), SearchHero (single +
multi community), and BrowseStrip.

Phase D.1 safeguard — lightweight DOM snapshots. Playwright screenshot
diffs deferred to Phase D.3 when the design system stabilizes.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Final verification

- [ ] **Step 1: Run full JS test suite**

Run: `npm run test:js`
Expected: All tests pass.

- [ ] **Step 2: Run PHP test suite**

Run: `./vendor/bin/phpunit`
Expected: 198/198 pass (no PHP changes, just confirming no regressions).

- [ ] **Step 3: Type check**

Run: `npx vue-tsc --noEmit`
Expected: Clean exit.

- [ ] **Step 4: Static analysis**

Run: `./vendor/bin/phpstan analyse src/`
Expected: Clean exit.
