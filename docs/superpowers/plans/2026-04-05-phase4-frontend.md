# Phase 4: Frontend (Discovery + Management) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Inertia.js frontend so Giiken is usable in a browser: search, Q&A, knowledge browsing, and staff management panel.

**Architecture:** Vue 3 + Inertia.js rendered by PHP controllers registered in `GiikenServiceProvider::routes()`. The Waaseyaa framework provides `Inertia::render()`, `InertiaMiddleware`, `ControllerDispatcher`, and `ViteAssetManager`. Controllers are closures returning `InertiaResponse`. Two surfaces in one app: Discovery (all users) and Management (staff+).

**Tech Stack:** Vue 3, TypeScript, Inertia.js (via `waaseyaa/inertia`), Vite, Tailwind CSS v4, PHP 8.4 controllers

---

## File Structure

### Frontend (new files)

```
package.json
vite.config.ts
tsconfig.json
tailwind.config.ts
resources/
├── css/
│   └── app.css                          # Tailwind imports + design tokens
├── js/
│   ├── app.ts                           # Inertia app bootstrap
│   ├── types.ts                         # Shared TypeScript types
│   ├── ssr.ts                           # SSR entry (optional, stubbed)
│   ├── Layouts/
│   │   ├── DiscoveryLayout.vue          # Top nav + slot
│   │   └── ManagementLayout.vue         # Sidebar + slot
│   ├── Components/
│   │   ├── KnowledgeCard.vue            # Item card (type dot, title, excerpt, date)
│   │   ├── SearchInput.vue              # Combined search/Q&A input
│   │   ├── QaAnswer.vue                 # Answer block with citations
│   │   ├── TypeFilter.vue               # Knowledge type filter pills
│   │   ├── Pagination.vue               # Page nav
│   │   └── ReportCard.vue               # Report type card (management)
│   └── Pages/
│       ├── Discovery/
│       │   ├── Index.vue                # Home: hero + search + browse
│       │   ├── Search.vue               # Search results page
│       │   ├── Ask.vue                  # Q&A answer page
│       │   └── Show.vue                 # Single knowledge item detail
│       └── Management/
│           ├── Dashboard.vue            # Overview stats
│           ├── Reports.vue              # Report generation + history
│           ├── Users.vue                # User/role management
│           ├── Ingestion.vue            # Pipeline queue status
│           └── Export.vue               # Export/import page
```

### Backend (new + modified files)

```
src/
├── Http/
│   ├── Controller/
│   │   ├── DiscoveryController.php      # Search, Q&A, item detail, browse
│   │   └── ManagementController.php     # Dashboard, reports, users, ingestion, export
│   └── Middleware/
│       └── RequireStaffRole.php         # Guards management routes
├── GiikenServiceProvider.php            # MODIFY: add routes
```

### Tests

```
tests/
├── Unit/Http/
│   ├── Controller/
│   │   ├── DiscoveryControllerTest.php
│   │   └── ManagementControllerTest.php
│   └── Middleware/
│       └── RequireStaffRoleTest.php
```

---

## Task 1: Scaffold Frontend Tooling

**Files:**
- Create: `package.json`
- Create: `vite.config.ts`
- Create: `tsconfig.json`
- Create: `tailwind.config.ts`
- Create: `resources/css/app.css`
- Create: `resources/js/app.ts`
- Create: `resources/js/types.ts`

- [ ] **Step 1: Create package.json**

```json
{
  "private": true,
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vue-tsc --noEmit && vite build",
    "lint": "vue-tsc --noEmit"
  },
  "dependencies": {
    "@inertiajs/vue3": "^2.0",
    "vue": "^3.5"
  },
  "devDependencies": {
    "@tailwindcss/vite": "^4.0",
    "@vitejs/plugin-vue": "^5.0",
    "tailwindcss": "^4.0",
    "typescript": "^5.7",
    "vite": "^6.0",
    "vue-tsc": "^2.0"
  }
}
```

- [ ] **Step 2: Create vite.config.ts**

```ts
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: { '@': '/resources/js' },
  },
  build: {
    manifest: true,
    outDir: 'public/build',
    rollupOptions: {
      input: 'resources/js/app.ts',
    },
  },
})
```

- [ ] **Step 3: Create tsconfig.json**

```json
{
  "compilerOptions": {
    "target": "ESNext",
    "module": "ESNext",
    "moduleResolution": "bundler",
    "strict": true,
    "jsx": "preserve",
    "noEmit": true,
    "esModuleInterop": true,
    "paths": { "@/*": ["./resources/js/*"] },
    "types": ["vite/client"]
  },
  "include": ["resources/js/**/*.ts", "resources/js/**/*.vue"]
}
```

- [ ] **Step 4: Create tailwind.config.ts**

```ts
import type { Config } from 'tailwindcss'

export default {
  content: ['./resources/js/**/*.{vue,ts}'],
  theme: {
    extend: {
      colors: {
        indigo: {
          DEFAULT: '#3d35c8',
          dark: '#1a1a2e',
          light: '#f0f0ff',
          mid: '#6e66ff',
        },
        muted: '#9090b0',
        border: '#e8eaf0',
        bg: '#f8f8fd',
      },
    },
  },
} satisfies Config
```

- [ ] **Step 5: Create resources/css/app.css**

```css
@import "tailwindcss";
```

- [ ] **Step 6: Create resources/js/types.ts**

```ts
export interface KnowledgeItem {
  id: string
  title: string
  content: string
  knowledgeType: KnowledgeType | null
  accessTier: AccessTier
  communityId: string
  compiledAt: string
  createdAt: string
  updatedAt: string
}

export type KnowledgeType = 'cultural' | 'governance' | 'land' | 'relationship' | 'event'
export type AccessTier = 'public' | 'members' | 'staff' | 'restricted'
export type CommunityRole = 'admin' | 'knowledge_keeper' | 'staff' | 'member' | 'public'

export interface Community {
  id: string
  name: string
  slug: string
  locale: string
  contactEmail: string
}

export interface SearchResult {
  id: string
  title: string
  summary: string
  knowledgeType: KnowledgeType | null
  score: number
}

export interface SearchResultSet {
  items: SearchResult[]
  totalHits: number
  totalPages: number
}

export interface QaResponse {
  answer: string
  citedItemIds: string[]
  citedItems: SearchResult[]
  noRelevantItems: boolean
}

export const KNOWLEDGE_TYPE_CONFIG: Record<KnowledgeType, { label: string; bg: string; text: string }> = {
  cultural: { label: 'Cultural', bg: '#f0f0ff', text: '#3d35c8' },
  governance: { label: 'Governance', bg: '#e8f5f0', text: '#1e8a6e' },
  land: { label: 'Land', bg: '#edf2e8', text: '#4a7a3a' },
  relationship: { label: 'Relationship', bg: '#fff0f5', text: '#c83568' },
  event: { label: 'Event', bg: '#fff8e8', text: '#c89a35' },
}
```

- [ ] **Step 7: Create resources/js/app.ts**

```ts
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import '../css/app.css'

createInertiaApp({
  resolve: (name: string) => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
    return pages[`./Pages/${name}.vue`]
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el)
  },
})
```

- [ ] **Step 8: Install dependencies**

Run: `cd /home/jones/dev/giiken && npm install`
Expected: `node_modules/` created, lock file generated

- [ ] **Step 9: Verify Vite starts**

Run: `cd /home/jones/dev/giiken && npx vite build --mode development 2>&1 | head -20`
Expected: Build succeeds (may warn about missing pages, that's fine)

- [ ] **Step 10: Commit**

```bash
git add package.json vite.config.ts tsconfig.json tailwind.config.ts resources/css/app.css resources/js/app.ts resources/js/types.ts
git commit -m "feat: scaffold frontend tooling (Vue 3, Vite, Tailwind, Inertia)"
```

---

## Task 2: Discovery Controllers + Routes

**Files:**
- Create: `src/Http/Controller/DiscoveryController.php`
- Create: `tests/Unit/Http/Controller/DiscoveryControllerTest.php`
- Modify: `src/GiikenServiceProvider.php`

- [ ] **Step 1: Write the failing test for DiscoveryController**

Create `tests/Unit/Http/Controller/DiscoveryControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Http\Controller;

use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Giiken\Http\Controller\DiscoveryController;
use Giiken\Query\QaResponse;
use Giiken\Query\QaService;
use Giiken\Query\SearchQuery;
use Giiken\Query\SearchResultItem;
use Giiken\Query\SearchResultSet;
use Giiken\Query\SearchService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Inertia\InertiaResponse;

#[CoversClass(DiscoveryController::class)]
final class DiscoveryControllerTest extends TestCase
{
    private DiscoveryController $controller;
    private SearchService $searchService;
    private QaService $qaService;
    private CommunityRepositoryInterface $communityRepo;
    private KnowledgeItemRepositoryInterface $itemRepo;

    protected function setUp(): void
    {
        $this->searchService = $this->createMock(SearchService::class);
        $this->qaService = $this->createMock(QaService::class);
        $this->communityRepo = $this->createMock(CommunityRepositoryInterface::class);
        $this->itemRepo = $this->createMock(KnowledgeItemRepositoryInterface::class);

        $this->controller = new DiscoveryController(
            $this->searchService,
            $this->qaService,
            $this->communityRepo,
            $this->itemRepo,
        );
    }

    #[Test]
    public function index_returns_inertia_response_with_community(): void
    {
        $community = $this->makeCommunity();
        $this->communityRepo->method('findBySlug')->willReturn($community);

        $this->searchService->method('search')->willReturn(SearchResultSet::empty());

        $response = $this->controller->index('test-community', null);

        self::assertInstanceOf(InertiaResponse::class, $response);
    }

    #[Test]
    public function search_returns_results(): void
    {
        $community = $this->makeCommunity();
        $this->communityRepo->method('findBySlug')->willReturn($community);

        $resultSet = new SearchResultSet(
            items: [new SearchResultItem('1', 'Title', 'Summary', KnowledgeType::Cultural, 0.9)],
            totalHits: 1,
            totalPages: 1,
        );
        $this->searchService->method('search')->willReturn($resultSet);

        $response = $this->controller->search('test-community', 'test query', 1, null);

        self::assertInstanceOf(InertiaResponse::class, $response);
    }

    #[Test]
    public function ask_returns_qa_response(): void
    {
        $community = $this->makeCommunity();
        $this->communityRepo->method('findBySlug')->willReturn($community);

        $qaResponse = new QaResponse(answer: 'The answer', citedItemIds: ['1'], noRelevantItems: false);
        $this->qaService->method('ask')->willReturn($qaResponse);

        $this->searchService->method('search')->willReturn(SearchResultSet::empty());

        $response = $this->controller->ask('test-community', 'What is this?', null);

        self::assertInstanceOf(InertiaResponse::class, $response);
    }

    #[Test]
    public function show_returns_knowledge_item(): void
    {
        $community = $this->makeCommunity();
        $this->communityRepo->method('findBySlug')->willReturn($community);

        $item = new KnowledgeItem([
            'id' => '1',
            'community_id' => 'comm-1',
            'title' => 'Test Item',
            'content' => 'Content here',
            'knowledge_type' => 'cultural',
            'access_tier' => 'public',
        ]);
        $this->itemRepo->method('find')->willReturn($item);

        $response = $this->controller->show('test-community', '1', null);

        self::assertInstanceOf(InertiaResponse::class, $response);
    }

    private function makeCommunity(): Community
    {
        return new Community([
            'id' => 'comm-1',
            'name' => 'Test Community',
            'slug' => 'test-community',
            'sovereignty_profile' => 'local',
            'locale' => 'en',
            'contact_email' => 'test@example.com',
            'wiki_schema' => [],
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Http/Controller/DiscoveryControllerTest.php`
Expected: FAIL — `DiscoveryController` class not found

- [ ] **Step 3: Create DiscoveryController**

Create `src/Http/Controller/DiscoveryController.php`:

```php
<?php

declare(strict_types=1);

namespace Giiken\Http\Controller;

use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Query\QaService;
use Giiken\Query\SearchQuery;
use Giiken\Query\SearchService;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Inertia\Inertia;
use Waaseyaa\Inertia\InertiaResponse;

final class DiscoveryController
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly QaService $qaService,
        private readonly CommunityRepositoryInterface $communityRepo,
        private readonly KnowledgeItemRepositoryInterface $itemRepo,
    ) {}

    public function index(string $communitySlug, ?AccountInterface $account): InertiaResponse
    {
        $community = $this->communityRepo->findBySlug($communitySlug);

        $recent = $this->searchService->search(
            new SearchQuery(query: '', communityId: (string) $community->get('id')),
            $account,
        );

        return Inertia::render('Discovery/Index', [
            'community' => $this->serializeCommunity($community),
            'recentItems' => $this->serializeResultSet($recent),
        ]);
    }

    public function search(
        string $communitySlug,
        string $query,
        int $page,
        ?AccountInterface $account,
    ): InertiaResponse {
        $community = $this->communityRepo->findBySlug($communitySlug);

        $results = $this->searchService->search(
            new SearchQuery(
                query: $query,
                communityId: (string) $community->get('id'),
                page: $page,
            ),
            $account,
        );

        return Inertia::render('Discovery/Search', [
            'community' => $this->serializeCommunity($community),
            'query' => $query,
            'results' => $this->serializeResultSet($results),
            'page' => $page,
        ]);
    }

    public function ask(string $communitySlug, string $question, ?AccountInterface $account): InertiaResponse
    {
        $community = $this->communityRepo->findBySlug($communitySlug);
        $communityId = (string) $community->get('id');

        $qaResponse = $this->qaService->ask($question, $communityId, $account);

        $related = $this->searchService->search(
            new SearchQuery(query: $question, communityId: $communityId, pageSize: 5),
            $account,
        );

        return Inertia::render('Discovery/Ask', [
            'community' => $this->serializeCommunity($community),
            'question' => $question,
            'answer' => $qaResponse->answer,
            'citedItemIds' => $qaResponse->citedItemIds,
            'noRelevantItems' => $qaResponse->noRelevantItems,
            'relatedItems' => $this->serializeResultSet($related),
        ]);
    }

    public function show(string $communitySlug, string $itemId, ?AccountInterface $account): InertiaResponse
    {
        $community = $this->communityRepo->findBySlug($communitySlug);
        $item = $this->itemRepo->find($itemId);

        return Inertia::render('Discovery/Show', [
            'community' => $this->serializeCommunity($community),
            'item' => [
                'id' => $item->get('id'),
                'title' => $item->getTitle(),
                'content' => $item->getContent(),
                'knowledgeType' => $item->getKnowledgeType()?->value,
                'accessTier' => $item->getAccessTier()->value,
                'compiledAt' => $item->getCompiledAt(),
                'createdAt' => $item->getCreatedAt(),
                'updatedAt' => $item->getUpdatedAt(),
            ],
        ]);
    }

    private function serializeCommunity(\Giiken\Entity\Community\Community $community): array
    {
        return [
            'id' => $community->get('id'),
            'name' => $community->getName(),
            'slug' => $community->getSlug(),
            'locale' => $community->getLocale(),
        ];
    }

    private function serializeResultSet(\Giiken\Query\SearchResultSet $resultSet): array
    {
        return [
            'items' => array_map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'summary' => $item->summary,
                'knowledgeType' => $item->knowledgeType?->value,
                'score' => $item->score,
            ], $resultSet->items),
            'totalHits' => $resultSet->totalHits,
            'totalPages' => $resultSet->totalPages,
        ];
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/Http/Controller/DiscoveryControllerTest.php`
Expected: 4 tests, 4 assertions, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Http/Controller/DiscoveryController.php tests/Unit/Http/Controller/DiscoveryControllerTest.php
git commit -m "feat: add DiscoveryController with search, Q&A, and item detail"
```

---

## Task 3: Staff Middleware + Management Controller

**Files:**
- Create: `src/Http/Middleware/RequireStaffRole.php`
- Create: `tests/Unit/Http/Middleware/RequireStaffRoleTest.php`
- Create: `src/Http/Controller/ManagementController.php`
- Create: `tests/Unit/Http/Controller/ManagementControllerTest.php`

- [ ] **Step 1: Write the failing test for RequireStaffRole**

Create `tests/Unit/Http/Middleware/RequireStaffRoleTest.php`:

```php
<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Http\Middleware;

use Giiken\Http\Middleware\RequireStaffRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;

#[CoversClass(RequireStaffRole::class)]
final class RequireStaffRoleTest extends TestCase
{
    #[Test]
    public function allows_admin(): void
    {
        $account = $this->makeAccount(['giiken.community.comm-1.admin']);
        $middleware = new RequireStaffRole();

        self::assertTrue($middleware->check($account, 'comm-1'));
    }

    #[Test]
    public function allows_staff(): void
    {
        $account = $this->makeAccount(['giiken.community.comm-1.staff']);
        $middleware = new RequireStaffRole();

        self::assertTrue($middleware->check($account, 'comm-1'));
    }

    #[Test]
    public function allows_knowledge_keeper(): void
    {
        $account = $this->makeAccount(['giiken.community.comm-1.knowledge_keeper']);
        $middleware = new RequireStaffRole();

        self::assertTrue($middleware->check($account, 'comm-1'));
    }

    #[Test]
    public function denies_member(): void
    {
        $account = $this->makeAccount(['giiken.community.comm-1.member']);
        $middleware = new RequireStaffRole();

        self::assertFalse($middleware->check($account, 'comm-1'));
    }

    #[Test]
    public function denies_unauthenticated(): void
    {
        $middleware = new RequireStaffRole();

        self::assertFalse($middleware->check(null, 'comm-1'));
    }

    private function makeAccount(array $roles): AccountInterface
    {
        return new class($roles) implements AccountInterface {
            public function __construct(private readonly array $roles) {}
            public function id(): int|string { return '1'; }
            public function getRoles(): array { return $this->roles; }
            public function isAuthenticated(): bool { return true; }
            public function hasPermission(string $permission): bool { return false; }
        };
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Http/Middleware/RequireStaffRoleTest.php`
Expected: FAIL — `RequireStaffRole` class not found

- [ ] **Step 3: Create RequireStaffRole**

Create `src/Http/Middleware/RequireStaffRole.php`:

```php
<?php

declare(strict_types=1);

namespace Giiken\Http\Middleware;

use Giiken\Access\CommunityRole;
use Waaseyaa\Access\AccountInterface;

final class RequireStaffRole
{
    private const MINIMUM_RANK = 3; // Staff

    public function check(?AccountInterface $account, string $communityId): bool
    {
        if ($account === null) {
            return false;
        }

        $prefix = "giiken.community.{$communityId}.";

        foreach ($account->getRoles() as $role) {
            if (!str_starts_with($role, $prefix)) {
                continue;
            }

            $roleSlug = substr($role, strlen($prefix));
            $communityRole = CommunityRole::tryFrom($roleSlug);

            if ($communityRole !== null && $communityRole->rank() >= self::MINIMUM_RANK) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 4: Run middleware tests**

Run: `./vendor/bin/phpunit tests/Unit/Http/Middleware/RequireStaffRoleTest.php`
Expected: 5 tests, 5 assertions, all PASS

- [ ] **Step 5: Write the failing test for ManagementController**

Create `tests/Unit/Http/Controller/ManagementControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Http\Controller;

use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Http\Controller\ManagementController;
use Giiken\Query\Export\ExportService;
use Giiken\Query\Export\ImportService;
use Giiken\Query\Report\ReportService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Inertia\InertiaResponse;

#[CoversClass(ManagementController::class)]
final class ManagementControllerTest extends TestCase
{
    private ManagementController $controller;
    private CommunityRepositoryInterface $communityRepo;
    private ReportService $reportService;
    private ExportService $exportService;
    private ImportService $importService;

    protected function setUp(): void
    {
        $this->communityRepo = $this->createMock(CommunityRepositoryInterface::class);
        $this->reportService = $this->createMock(ReportService::class);
        $this->exportService = $this->createMock(ExportService::class);
        $this->importService = $this->createMock(ImportService::class);

        $this->controller = new ManagementController(
            $this->communityRepo,
            $this->reportService,
            $this->exportService,
            $this->importService,
        );
    }

    #[Test]
    public function dashboard_returns_inertia_response(): void
    {
        $this->communityRepo->method('findBySlug')->willReturn($this->makeCommunity());

        $response = $this->controller->dashboard('test-community');

        self::assertInstanceOf(InertiaResponse::class, $response);
    }

    #[Test]
    public function reports_returns_inertia_response(): void
    {
        $this->communityRepo->method('findBySlug')->willReturn($this->makeCommunity());

        $response = $this->controller->reports('test-community');

        self::assertInstanceOf(InertiaResponse::class, $response);
    }

    #[Test]
    public function export_page_returns_inertia_response(): void
    {
        $this->communityRepo->method('findBySlug')->willReturn($this->makeCommunity());

        $response = $this->controller->exportPage('test-community');

        self::assertInstanceOf(InertiaResponse::class, $response);
    }

    private function makeCommunity(): Community
    {
        return new Community([
            'id' => 'comm-1',
            'name' => 'Test Community',
            'slug' => 'test-community',
            'sovereignty_profile' => 'local',
            'locale' => 'en',
            'contact_email' => 'test@example.com',
            'wiki_schema' => [],
        ]);
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Http/Controller/ManagementControllerTest.php`
Expected: FAIL — `ManagementController` class not found

- [ ] **Step 7: Create ManagementController**

Create `src/Http/Controller/ManagementController.php`:

```php
<?php

declare(strict_types=1);

namespace Giiken\Http\Controller;

use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Query\Export\ExportService;
use Giiken\Query\Export\ImportService;
use Giiken\Query\Report\ReportService;
use Waaseyaa\Inertia\Inertia;
use Waaseyaa\Inertia\InertiaResponse;

final class ManagementController
{
    public function __construct(
        private readonly CommunityRepositoryInterface $communityRepo,
        private readonly ReportService $reportService,
        private readonly ExportService $exportService,
        private readonly ImportService $importService,
    ) {}

    public function dashboard(string $communitySlug): InertiaResponse
    {
        $community = $this->communityRepo->findBySlug($communitySlug);

        return Inertia::render('Management/Dashboard', [
            'community' => $this->serializeCommunity($community),
        ]);
    }

    public function reports(string $communitySlug): InertiaResponse
    {
        $community = $this->communityRepo->findBySlug($communitySlug);

        return Inertia::render('Management/Reports', [
            'community' => $this->serializeCommunity($community),
            'reportTypes' => ['governance_summary', 'language_report', 'land_brief'],
        ]);
    }

    public function users(string $communitySlug): InertiaResponse
    {
        $community = $this->communityRepo->findBySlug($communitySlug);

        return Inertia::render('Management/Users', [
            'community' => $this->serializeCommunity($community),
        ]);
    }

    public function ingestion(string $communitySlug): InertiaResponse
    {
        $community = $this->communityRepo->findBySlug($communitySlug);

        return Inertia::render('Management/Ingestion', [
            'community' => $this->serializeCommunity($community),
        ]);
    }

    public function exportPage(string $communitySlug): InertiaResponse
    {
        $community = $this->communityRepo->findBySlug($communitySlug);

        return Inertia::render('Management/Export', [
            'community' => $this->serializeCommunity($community),
        ]);
    }

    private function serializeCommunity(Community $community): array
    {
        return [
            'id' => $community->get('id'),
            'name' => $community->getName(),
            'slug' => $community->getSlug(),
            'locale' => $community->getLocale(),
        ];
    }
}
```

- [ ] **Step 8: Run tests**

Run: `./vendor/bin/phpunit tests/Unit/Http/Controller/ManagementControllerTest.php`
Expected: 3 tests, 3 assertions, all PASS

- [ ] **Step 9: Commit**

```bash
git add src/Http/Middleware/RequireStaffRole.php tests/Unit/Http/Middleware/RequireStaffRoleTest.php src/Http/Controller/ManagementController.php tests/Unit/Http/Controller/ManagementControllerTest.php
git commit -m "feat: add RequireStaffRole middleware and ManagementController"
```

---

## Task 4: Route Registration

**Files:**
- Modify: `src/GiikenServiceProvider.php`

- [ ] **Step 1: Add routes to GiikenServiceProvider**

Add the following use statements and route definitions inside `routes()`:

```php
use Symfony\Component\Routing\Route;
```

Replace the empty `routes()` method body with:

```php
public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
{
    // Discovery routes
    $router->addRoute('giiken.discovery.index', new Route(
        '/{communitySlug}',
        ['_controller' => [DiscoveryController::class, 'index']],
        ['communitySlug' => '[a-z0-9-]+'],
    ));

    $router->addRoute('giiken.discovery.search', new Route(
        '/{communitySlug}/search',
        ['_controller' => [DiscoveryController::class, 'search']],
        ['communitySlug' => '[a-z0-9-]+'],
    ));

    $router->addRoute('giiken.discovery.ask', new Route(
        '/{communitySlug}/ask',
        ['_controller' => [DiscoveryController::class, 'ask']],
        ['communitySlug' => '[a-z0-9-]+'],
    ));

    $router->addRoute('giiken.discovery.show', new Route(
        '/{communitySlug}/item/{itemId}',
        ['_controller' => [DiscoveryController::class, 'show']],
        ['communitySlug' => '[a-z0-9-]+', 'itemId' => '.+'],
    ));

    // Management routes
    $router->addRoute('giiken.management.dashboard', new Route(
        '/{communitySlug}/manage',
        ['_controller' => [ManagementController::class, 'dashboard']],
        ['communitySlug' => '[a-z0-9-]+'],
    ));

    $router->addRoute('giiken.management.reports', new Route(
        '/{communitySlug}/manage/reports',
        ['_controller' => [ManagementController::class, 'reports']],
        ['communitySlug' => '[a-z0-9-]+'],
    ));

    $router->addRoute('giiken.management.users', new Route(
        '/{communitySlug}/manage/users',
        ['_controller' => [ManagementController::class, 'users']],
        ['communitySlug' => '[a-z0-9-]+'],
    ));

    $router->addRoute('giiken.management.ingestion', new Route(
        '/{communitySlug}/manage/ingestion',
        ['_controller' => [ManagementController::class, 'ingestion']],
        ['communitySlug' => '[a-z0-9-]+'],
    ));

    $router->addRoute('giiken.management.export', new Route(
        '/{communitySlug}/manage/export',
        ['_controller' => [ManagementController::class, 'export']],
        ['communitySlug' => '[a-z0-9-]+'],
    ));
}
```

- [ ] **Step 2: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass (existing + new)

- [ ] **Step 3: Run static analysis**

Run: `./vendor/bin/phpstan analyse src/`
Expected: No errors

- [ ] **Step 4: Commit**

```bash
git add src/GiikenServiceProvider.php
git commit -m "feat: register discovery and management routes"
```

---

## Task 5: Discovery Layout + Components

**Files:**
- Create: `resources/js/Layouts/DiscoveryLayout.vue`
- Create: `resources/js/Components/SearchInput.vue`
- Create: `resources/js/Components/KnowledgeCard.vue`
- Create: `resources/js/Components/TypeFilter.vue`
- Create: `resources/js/Components/Pagination.vue`

- [ ] **Step 1: Create DiscoveryLayout.vue**

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import type { Community } from '@/types'

const props = defineProps<{
  community: Community
  showManagement?: boolean
}>()
</script>

<template>
  <div class="min-h-screen bg-bg">
    <nav class="bg-indigo-dark text-white px-6 py-3 flex items-center justify-between">
      <div class="flex items-center gap-6">
        <span class="font-bold text-lg">{{ community.name }}</span>
        <div class="flex gap-4 text-sm">
          <Link :href="`/${community.slug}`" class="hover:text-indigo-light">Discover</Link>
          <Link :href="`/${community.slug}/manage`" class="hover:text-indigo-light" v-if="showManagement">⚙ Management</Link>
        </div>
      </div>
    </nav>
    <main>
      <slot />
    </main>
  </div>
</template>
```

- [ ] **Step 2: Create SearchInput.vue**

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps<{
  communitySlug: string
  initialQuery?: string
}>()

const query = ref(props.initialQuery ?? '')

function submit() {
  const q = query.value.trim()
  if (!q) return

  const isQuestion = q.includes('?') || q.split(/\s+/).length > 5
  const route = isQuestion
    ? `/${props.communitySlug}/ask`
    : `/${props.communitySlug}/search`

  router.get(route, { q, page: 1 })
}
</script>

<template>
  <form @submit.prevent="submit" class="flex gap-2 w-full max-w-2xl">
    <input
      v-model="query"
      type="text"
      placeholder="Search or ask a question..."
      class="flex-1 px-4 py-3 rounded-lg border border-border focus:outline-none focus:ring-2 focus:ring-indigo text-base"
    />
    <button type="submit" class="px-6 py-3 bg-indigo text-white rounded-lg hover:bg-indigo-mid font-medium">
      Ask →
    </button>
  </form>
</template>
```

- [ ] **Step 3: Create KnowledgeCard.vue**

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { KNOWLEDGE_TYPE_CONFIG } from '@/types'
import type { KnowledgeType } from '@/types'

const props = defineProps<{
  id: string
  title: string
  summary: string
  knowledgeType: KnowledgeType | null
  communitySlug: string
}>()

const typeConfig = props.knowledgeType ? KNOWLEDGE_TYPE_CONFIG[props.knowledgeType] : null
</script>

<template>
  <Link :href="`/${communitySlug}/item/${id}`" class="block p-4 bg-white rounded-lg border border-border hover:shadow-md transition-shadow">
    <div class="flex items-start gap-3">
      <div
        v-if="typeConfig"
        class="w-3 h-3 rounded-full mt-1.5 shrink-0"
        :style="{ backgroundColor: typeConfig.text }"
      />
      <div class="min-w-0">
        <h3 class="font-semibold text-indigo-dark truncate">{{ title }}</h3>
        <p class="text-sm text-muted mt-1 line-clamp-2">{{ summary }}</p>
        <span
          v-if="typeConfig"
          class="inline-block text-xs px-2 py-0.5 rounded-full mt-2"
          :style="{ backgroundColor: typeConfig.bg, color: typeConfig.text }"
        >
          {{ typeConfig.label }}
        </span>
      </div>
    </div>
  </Link>
</template>
```

- [ ] **Step 4: Create TypeFilter.vue**

```vue
<script setup lang="ts">
import { KNOWLEDGE_TYPE_CONFIG } from '@/types'
import type { KnowledgeType } from '@/types'

defineProps<{ active: KnowledgeType | null }>()
const emit = defineEmits<{ select: [type: KnowledgeType | null] }>()

const types = Object.entries(KNOWLEDGE_TYPE_CONFIG) as [KnowledgeType, typeof KNOWLEDGE_TYPE_CONFIG[KnowledgeType]][]
</script>

<template>
  <div class="flex gap-2 flex-wrap">
    <button
      class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors"
      :class="active === null ? 'bg-indigo text-white' : 'bg-indigo-light text-indigo'"
      @click="emit('select', null)"
    >
      All
    </button>
    <button
      v-for="[type, config] in types"
      :key="type"
      class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors"
      :style="active === type
        ? { backgroundColor: config.text, color: 'white' }
        : { backgroundColor: config.bg, color: config.text }"
      @click="emit('select', type)"
    >
      {{ config.label }}
    </button>
  </div>
</template>
```

- [ ] **Step 5: Create Pagination.vue**

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

const props = defineProps<{
  currentPage: number
  totalPages: number
  baseUrl: string
  query?: string
}>()

function pageUrl(page: number): string {
  const params = new URLSearchParams()
  if (props.query) params.set('q', props.query)
  params.set('page', String(page))
  return `${props.baseUrl}?${params.toString()}`
}
</script>

<template>
  <nav v-if="totalPages > 1" class="flex gap-2 justify-center mt-6">
    <Link
      v-for="page in totalPages"
      :key="page"
      :href="pageUrl(page)"
      class="px-3 py-1.5 rounded text-sm"
      :class="page === currentPage ? 'bg-indigo text-white' : 'bg-indigo-light text-indigo hover:bg-indigo hover:text-white'"
    >
      {{ page }}
    </Link>
  </nav>
</template>
```

- [ ] **Step 6: Verify build**

Run: `cd /home/jones/dev/giiken && npx vite build --mode development 2>&1 | tail -5`
Expected: Build succeeds

- [ ] **Step 7: Commit**

```bash
git add resources/js/Layouts/DiscoveryLayout.vue resources/js/Components/SearchInput.vue resources/js/Components/KnowledgeCard.vue resources/js/Components/TypeFilter.vue resources/js/Components/Pagination.vue
git commit -m "feat: add Discovery layout and shared components"
```

---

## Task 6: Discovery Pages

**Files:**
- Create: `resources/js/Pages/Discovery/Index.vue`
- Create: `resources/js/Pages/Discovery/Search.vue`
- Create: `resources/js/Pages/Discovery/Ask.vue`
- Create: `resources/js/Pages/Discovery/Show.vue`
- Create: `resources/js/Components/QaAnswer.vue`

- [ ] **Step 1: Create QaAnswer.vue**

```vue
<script setup lang="ts">
import type { SearchResult } from '@/types'

defineProps<{
  answer: string
  citedItems: SearchResult[]
  noRelevantItems: boolean
}>()
</script>

<template>
  <div class="bg-white rounded-lg border border-border p-6">
    <div class="flex items-center gap-2 mb-4">
      <span class="w-2.5 h-2.5 bg-indigo rounded-full" />
      <span class="font-semibold text-indigo-dark">Answer</span>
      <span class="text-xs text-muted">from your knowledge base</span>
    </div>

    <div v-if="noRelevantItems" class="text-muted italic">
      I could not find information about this in the community's knowledge base.
    </div>

    <div v-else>
      <div class="prose prose-sm max-w-none text-indigo-dark" v-html="answer" />

      <div v-if="citedItems.length > 0" class="mt-4 pt-4 border-t border-border">
        <p class="text-sm font-medium text-muted mb-2">Sources</p>
        <div class="space-y-2">
          <div
            v-for="(item, idx) in citedItems"
            :key="item.id"
            class="text-sm p-3 rounded bg-bg"
          >
            <span class="text-indigo font-medium">[{{ idx + 1 }}]</span>
            <span class="font-medium ml-1">{{ item.title }}</span>
            <span class="text-muted ml-2">{{ item.summary?.slice(0, 100) }}...</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Create Discovery/Index.vue**

```vue
<script setup lang="ts">
import DiscoveryLayout from '@/Layouts/DiscoveryLayout.vue'
import SearchInput from '@/Components/SearchInput.vue'
import KnowledgeCard from '@/Components/KnowledgeCard.vue'
import TypeFilter from '@/Components/TypeFilter.vue'
import type { Community, SearchResultSet, KnowledgeType } from '@/types'
import { ref, computed } from 'vue'

const props = defineProps<{
  community: Community
  recentItems: SearchResultSet
}>()

const activeType = ref<KnowledgeType | null>(null)

const filteredItems = computed(() => {
  if (activeType.value === null) return props.recentItems.items
  return props.recentItems.items.filter(i => i.knowledgeType === activeType.value)
})
</script>

<template>
  <DiscoveryLayout :community="community">
    <div class="bg-gradient-to-br from-indigo to-indigo-mid text-white py-16 px-6 text-center">
      <h1 class="text-3xl font-bold mb-2">{{ community.name }} Knowledge Base</h1>
      <p class="text-indigo-light mb-8">Search or ask anything</p>
      <div class="flex justify-center">
        <SearchInput :community-slug="community.slug" />
      </div>
    </div>

    <div class="max-w-5xl mx-auto px-6 py-8">
      <TypeFilter :active="activeType" @select="activeType = $event" />

      <div class="grid gap-4 mt-6 sm:grid-cols-2 lg:grid-cols-3">
        <KnowledgeCard
          v-for="item in filteredItems"
          :key="item.id"
          :id="item.id"
          :title="item.title"
          :summary="item.summary"
          :knowledge-type="item.knowledgeType"
          :community-slug="community.slug"
        />
      </div>

      <p v-if="filteredItems.length === 0" class="text-muted text-center mt-12">
        No knowledge items yet. Upload documents to get started.
      </p>
    </div>
  </DiscoveryLayout>
</template>
```

- [ ] **Step 3: Create Discovery/Search.vue**

```vue
<script setup lang="ts">
import DiscoveryLayout from '@/Layouts/DiscoveryLayout.vue'
import SearchInput from '@/Components/SearchInput.vue'
import KnowledgeCard from '@/Components/KnowledgeCard.vue'
import Pagination from '@/Components/Pagination.vue'
import type { Community, SearchResultSet } from '@/types'

const props = defineProps<{
  community: Community
  query: string
  results: SearchResultSet
  page: number
}>()
</script>

<template>
  <DiscoveryLayout :community="community">
    <div class="bg-gradient-to-br from-indigo to-indigo-mid text-white py-10 px-6 text-center">
      <div class="flex justify-center">
        <SearchInput :community-slug="community.slug" :initial-query="query" />
      </div>
    </div>

    <div class="max-w-5xl mx-auto px-6 py-8">
      <p class="text-sm text-muted mb-4">{{ results.totalHits }} results for "{{ query }}"</p>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <KnowledgeCard
          v-for="item in results.items"
          :key="item.id"
          :id="item.id"
          :title="item.title"
          :summary="item.summary"
          :knowledge-type="item.knowledgeType"
          :community-slug="community.slug"
        />
      </div>

      <Pagination
        :current-page="page"
        :total-pages="results.totalPages"
        :base-url="`/${community.slug}/search`"
        :query="query"
      />
    </div>
  </DiscoveryLayout>
</template>
```

- [ ] **Step 4: Create Discovery/Ask.vue**

```vue
<script setup lang="ts">
import DiscoveryLayout from '@/Layouts/DiscoveryLayout.vue'
import SearchInput from '@/Components/SearchInput.vue'
import QaAnswer from '@/Components/QaAnswer.vue'
import KnowledgeCard from '@/Components/KnowledgeCard.vue'
import type { Community, SearchResultSet, SearchResult } from '@/types'

const props = defineProps<{
  community: Community
  question: string
  answer: string
  citedItemIds: string[]
  noRelevantItems: boolean
  relatedItems: SearchResultSet
}>()

const citedItems: SearchResult[] = props.relatedItems.items.filter(i =>
  props.citedItemIds.includes(i.id)
)
</script>

<template>
  <DiscoveryLayout :community="community">
    <div class="bg-gradient-to-br from-indigo to-indigo-mid text-white py-10 px-6 text-center">
      <div class="flex justify-center">
        <SearchInput :community-slug="community.slug" :initial-query="question" />
      </div>
    </div>

    <div class="max-w-3xl mx-auto px-6 py-8">
      <QaAnswer
        :answer="answer"
        :cited-items="citedItems"
        :no-relevant-items="noRelevantItems"
      />

      <div v-if="relatedItems.items.length > 0" class="mt-8">
        <h2 class="text-lg font-semibold text-indigo-dark mb-4">Related knowledge</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <KnowledgeCard
            v-for="item in relatedItems.items"
            :key="item.id"
            :id="item.id"
            :title="item.title"
            :summary="item.summary"
            :knowledge-type="item.knowledgeType"
            :community-slug="community.slug"
          />
        </div>
      </div>
    </div>
  </DiscoveryLayout>
</template>
```

- [ ] **Step 5: Create Discovery/Show.vue**

```vue
<script setup lang="ts">
import DiscoveryLayout from '@/Layouts/DiscoveryLayout.vue'
import { Link } from '@inertiajs/vue3'
import { KNOWLEDGE_TYPE_CONFIG } from '@/types'
import type { Community, KnowledgeItem } from '@/types'

const props = defineProps<{
  community: Community
  item: KnowledgeItem
}>()

const typeConfig = props.item.knowledgeType ? KNOWLEDGE_TYPE_CONFIG[props.item.knowledgeType] : null
</script>

<template>
  <DiscoveryLayout :community="community">
    <div class="max-w-3xl mx-auto px-6 py-8">
      <Link :href="`/${community.slug}`" class="text-sm text-indigo hover:underline mb-4 inline-block">
        ← Back to browse
      </Link>

      <article>
        <div class="flex items-center gap-3 mb-4">
          <span
            v-if="typeConfig"
            class="inline-block text-xs px-2 py-0.5 rounded-full"
            :style="{ backgroundColor: typeConfig.bg, color: typeConfig.text }"
          >
            {{ typeConfig.label }}
          </span>
          <span class="text-xs text-muted">{{ item.createdAt }}</span>
        </div>

        <h1 class="text-2xl font-bold text-indigo-dark mb-6">{{ item.title }}</h1>

        <div class="prose prose-sm max-w-none text-indigo-dark" v-html="item.content" />
      </article>
    </div>
  </DiscoveryLayout>
</template>
```

- [ ] **Step 6: Verify build**

Run: `cd /home/jones/dev/giiken && npx vite build --mode development 2>&1 | tail -5`
Expected: Build succeeds

- [ ] **Step 7: Commit**

```bash
git add resources/js/Components/QaAnswer.vue resources/js/Pages/Discovery/
git commit -m "feat: add Discovery pages (Index, Search, Ask, Show)"
```

---

## Task 7: Management Layout + Pages

**Files:**
- Create: `resources/js/Layouts/ManagementLayout.vue`
- Create: `resources/js/Components/ReportCard.vue`
- Create: `resources/js/Pages/Management/Dashboard.vue`
- Create: `resources/js/Pages/Management/Reports.vue`
- Create: `resources/js/Pages/Management/Users.vue`
- Create: `resources/js/Pages/Management/Ingestion.vue`
- Create: `resources/js/Pages/Management/Export.vue`

- [ ] **Step 1: Create ManagementLayout.vue**

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import type { Community } from '@/types'

defineProps<{ community: Community }>()
</script>

<template>
  <div class="min-h-screen bg-bg flex">
    <aside class="w-56 bg-indigo-dark text-white flex flex-col shrink-0">
      <div class="px-4 py-5 border-b border-white/10">
        <span class="font-bold text-sm">{{ community.name }}</span>
      </div>

      <nav class="flex-1 px-2 py-4 space-y-1">
        <p class="px-3 text-xs text-white/50 uppercase tracking-wider mb-2">Management</p>
        <Link :href="`/${community.slug}/manage`" class="block px-3 py-2 rounded text-sm hover:bg-white/10">📊 Dashboard</Link>
        <Link :href="`/${community.slug}/manage/reports`" class="block px-3 py-2 rounded text-sm hover:bg-white/10">📋 Reports</Link>
        <Link :href="`/${community.slug}/manage/users`" class="block px-3 py-2 rounded text-sm hover:bg-white/10">👥 Users</Link>
        <Link :href="`/${community.slug}/manage/ingestion`" class="block px-3 py-2 rounded text-sm hover:bg-white/10">📥 Ingestion Queue</Link>

        <p class="px-3 text-xs text-white/50 uppercase tracking-wider mt-6 mb-2">System</p>
        <Link :href="`/${community.slug}/manage/export`" class="block px-3 py-2 rounded text-sm hover:bg-white/10">📦 Export</Link>
      </nav>

      <div class="px-2 py-4 border-t border-white/10">
        <Link :href="`/${community.slug}`" class="block px-3 py-2 rounded text-sm hover:bg-white/10">
          ← Back to Discover
        </Link>
      </div>
    </aside>

    <main class="flex-1 p-8">
      <slot />
    </main>
  </div>
</template>
```

- [ ] **Step 2: Create ReportCard.vue**

```vue
<script setup lang="ts">
defineProps<{
  title: string
  description: string
  type: string
}>()

const emit = defineEmits<{ generate: [type: string] }>()
</script>

<template>
  <button
    @click="emit('generate', type)"
    class="p-5 bg-white rounded-lg border border-border hover:shadow-md transition-shadow text-left w-full"
  >
    <h3 class="font-semibold text-indigo-dark">{{ title }}</h3>
    <p class="text-sm text-muted mt-1">{{ description }}</p>
  </button>
</template>
```

- [ ] **Step 3: Create Management/Dashboard.vue**

```vue
<script setup lang="ts">
import ManagementLayout from '@/Layouts/ManagementLayout.vue'
import type { Community } from '@/types'

defineProps<{ community: Community }>()
</script>

<template>
  <ManagementLayout :community="community">
    <h1 class="text-2xl font-bold text-indigo-dark mb-6">Dashboard</h1>
    <p class="text-muted">Management dashboard coming soon. Use the sidebar to navigate.</p>
  </ManagementLayout>
</template>
```

- [ ] **Step 4: Create Management/Reports.vue**

```vue
<script setup lang="ts">
import ManagementLayout from '@/Layouts/ManagementLayout.vue'
import ReportCard from '@/Components/ReportCard.vue'
import type { Community } from '@/types'

defineProps<{
  community: Community
  reportTypes: string[]
}>()

const reports = [
  { type: 'governance_summary', title: 'Governance Summary', description: 'Overview of governance knowledge items with status and gaps.' },
  { type: 'language_report', title: 'Language Report', description: 'Language preservation items, coverage by dialect and domain.' },
  { type: 'land_brief', title: 'Land Brief', description: 'Land-related knowledge items with geographic and treaty context.' },
]

function generateReport(type: string) {
  // TODO: POST to report generation endpoint, download result
  alert(`Report generation for ${type} will be wired in the next iteration.`)
}
</script>

<template>
  <ManagementLayout :community="community">
    <h1 class="text-2xl font-bold text-indigo-dark mb-6">Reports</h1>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-8">
      <ReportCard
        v-for="report in reports"
        :key="report.type"
        :title="report.title"
        :description="report.description"
        :type="report.type"
        @generate="generateReport"
      />
    </div>
  </ManagementLayout>
</template>
```

- [ ] **Step 5: Create Management/Users.vue**

```vue
<script setup lang="ts">
import ManagementLayout from '@/Layouts/ManagementLayout.vue'
import type { Community } from '@/types'

defineProps<{ community: Community }>()
</script>

<template>
  <ManagementLayout :community="community">
    <h1 class="text-2xl font-bold text-indigo-dark mb-6">Users</h1>
    <p class="text-muted">User management will be wired when the Waaseyaa user service is integrated.</p>
  </ManagementLayout>
</template>
```

- [ ] **Step 6: Create Management/Ingestion.vue**

```vue
<script setup lang="ts">
import ManagementLayout from '@/Layouts/ManagementLayout.vue'
import type { Community } from '@/types'

defineProps<{ community: Community }>()
</script>

<template>
  <ManagementLayout :community="community">
    <h1 class="text-2xl font-bold text-indigo-dark mb-6">Ingestion Queue</h1>
    <p class="text-muted">Pipeline status display will be wired when the queue service is integrated.</p>
  </ManagementLayout>
</template>
```

- [ ] **Step 7: Create Management/Export.vue**

```vue
<script setup lang="ts">
import ManagementLayout from '@/Layouts/ManagementLayout.vue'
import type { Community } from '@/types'

defineProps<{ community: Community }>()

function requestExport() {
  // TODO: POST to export endpoint
  alert('Export will be wired in the next iteration.')
}
</script>

<template>
  <ManagementLayout :community="community">
    <h1 class="text-2xl font-bold text-indigo-dark mb-6">Export</h1>

    <div class="bg-white rounded-lg border border-border p-6 max-w-lg">
      <h2 class="font-semibold text-indigo-dark mb-2">Export all community data</h2>
      <p class="text-sm text-muted mb-4">
        Downloads a ZIP archive containing all knowledge items (Markdown), original media files,
        embeddings, and community configuration. Open formats only, no vendor lock-in.
      </p>
      <p class="text-xs text-indigo bg-indigo-light rounded p-3 mb-4">
        <strong>Community Sovereignty Guarantee:</strong> Your data is always exportable in open formats.
        You can move to any infrastructure at any time.
      </p>
      <button
        @click="requestExport"
        class="px-6 py-2 bg-indigo text-white rounded-lg hover:bg-indigo-mid font-medium"
      >
        Export Community Data
      </button>
    </div>
  </ManagementLayout>
</template>
```

- [ ] **Step 8: Verify build**

Run: `cd /home/jones/dev/giiken && npx vite build --mode development 2>&1 | tail -5`
Expected: Build succeeds

- [ ] **Step 9: Commit**

```bash
git add resources/js/Layouts/ManagementLayout.vue resources/js/Components/ReportCard.vue resources/js/Pages/Management/
git commit -m "feat: add Management layout and pages (Dashboard, Reports, Users, Ingestion, Export)"
```

---

## Task 8: Full Build Verification + PHPStan

- [ ] **Step 1: Run full PHP test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 2: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/`
Expected: No errors

- [ ] **Step 3: Run Vite production build**

Run: `cd /home/jones/dev/giiken && npx vite build`
Expected: Build succeeds, output in `public/build/`

- [ ] **Step 4: Verify manifest exists**

Run: `ls -la public/build/.vite/manifest.json`
Expected: File exists

- [ ] **Step 5: Final commit if any fixes were needed**

```bash
git add -A
git commit -m "chore: build verification and fixes"
```
