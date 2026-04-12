# Phase 2 Completion: Ingestion Pipeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close out Phase 2 milestone by verifying already-implemented issues, completing the wiki schema layer (#25), and building the wiki lint job (#23).

**Architecture:** Five of eight Phase 2 issues (#5, #6, #8, #29, #30) are already fully implemented in code but not closed on GitHub. Two issues (#25, #23) need new code. Issue #27 (MVP acceptance scenario) spans Phase 2+3 and gets a partial setup here (community fixture + ingestion smoke test), with full validation deferred until Phase 3 query layer lands.

**Tech Stack:** PHP 8.4, PHPUnit 10.5, Waaseyaa framework packages (entity, access, ai-pipeline, queue), PHPStan

---

## File Structure

### Existing files (verify, no changes expected)
- `src/Ingestion/FileIngestionHandlerInterface.php` — #5
- `src/Ingestion/IngestionHandlerRegistry.php` — #5
- `src/Ingestion/RawDocument.php` — #5
- `src/Ingestion/IngestionException.php` — #5
- `src/Ingestion/Handler/DocumentIngestionHandler.php` — #6
- `src/Ingestion/Handler/CsvIngestionHandler.php` — #6
- `src/Ingestion/Handler/HtmlIngestionHandler.php` — #6
- `src/Ingestion/Handler/MarkdownIngestionHandler.php` — #6
- `src/Ingestion/Converter/MarkItDownConverter.php` — #29
- `src/Ingestion/Converter/FileConverterInterface.php` — #29
- `src/Pipeline/CompilationPipeline.php` — #8
- `src/Pipeline/Step/*.php` (5 steps) — #8
- `src/Entity/KnowledgeItem/KnowledgeItem.php` (toMarkdown method) — #30

### New files for #25 (Wiki Schema)
- `src/Entity/Community/WikiSchema.php` — Value object: language, knowledge types, LLM instructions
- `tests/Unit/Entity/Community/WikiSchemaTest.php` — Serialization, defaults, validation
- `tests/Unit/Entity/Community/CommunityWikiSchemaTest.php` — Community getter/setter integration

### New files for #23 (Wiki Lint)
- `src/Wiki/WikiLintJob.php` — Job class dispatched via QueueInterface
- `src/Wiki/WikiLintReport.php` — Entity storing lint results
- `src/Wiki/Check/OrphanPageCheck.php` — Detects pages with no inbound links
- `src/Wiki/Check/BrokenLinkCheck.php` — Detects internal links to nonexistent pages
- `src/Wiki/Check/LintCheckInterface.php` — Contract for lint checks
- `tests/Unit/Wiki/WikiLintJobTest.php` — Job dispatch and orchestration
- `tests/Unit/Wiki/Check/OrphanPageCheckTest.php` — Orphan detection logic
- `tests/Unit/Wiki/Check/BrokenLinkCheckTest.php` — Broken link detection logic

### New files for #27 (Partial: acceptance fixture)
- `tests/Integration/MasseySolarSmokeTest.php` — End-to-end ingestion smoke test

---

## Task 1: Verify and close implemented issues (#5, #6, #8, #29, #30)

**Files:** All existing test files under `tests/Unit/`

This task confirms the existing code matches issue specs and closes the issues on GitHub.

- [ ] **Step 1: Run full test suite**

```bash
cd /home/jones/dev/giiken && ./vendor/bin/phpunit --no-progress
```

Expected: 78 tests, 139 assertions, all passing.

- [ ] **Step 2: Run PHPStan**

```bash
cd /home/jones/dev/giiken && ./vendor/bin/phpstan analyse src/
```

Expected: No errors.

- [ ] **Step 3: Verify #5 spec coverage**

Check that these files exist and match the issue contract:
- `src/Ingestion/FileIngestionHandlerInterface.php` has `supports(string $mimeType): bool` and `handle(UploadedFile $file, Community $community): RawDocument`
- `src/Ingestion/IngestionHandlerRegistry.php` has `register()` and `getHandler()` methods
- `src/Ingestion/RawDocument.php` has `markdownContent`, `mimeType`, `originalFilename`, `fileSize` properties
- `src/Ingestion/IngestionException.php` exists
- `tests/Unit/Ingestion/IngestionHandlerRegistryTest.php` exists and passes

- [ ] **Step 4: Verify #6 spec coverage**

Check that these handlers exist and each:
- Implements `FileIngestionHandlerInterface`
- Has a corresponding test file
- `DocumentIngestionHandler` supports PDF/DOCX MIME types
- `CsvIngestionHandler` supports text/csv
- `HtmlIngestionHandler` supports text/html
- `MarkdownIngestionHandler` supports text/markdown

- [ ] **Step 5: Verify #29 spec coverage**

Check:
- `src/Ingestion/Converter/MarkItDownConverter.php` implements `FileConverterInterface`
- `src/Ingestion/Converter/FileConverterInterface.php` has `convert(string $filePath): string` and `supports(string $mimeType): bool`
- `bin/setup-markitdown.sh` exists and is executable
- `tests/Unit/Ingestion/Converter/MarkItDownConverterTest.php` passes

- [ ] **Step 6: Verify #8 spec coverage**

Check:
- `src/Pipeline/CompilationPipeline.php` orchestrates all 5 steps
- Each step in `src/Pipeline/Step/` implements `PipelineStepInterface`
- Steps: TranscribeStep, ClassifyStep, StructureStep, LinkStep, EmbedStep
- `src/Pipeline/CompilationPayload.php` carries data between steps
- `src/Pipeline/Provider/LlmProviderInterface.php` and `EmbeddingProviderInterface.php` exist
- All 6 pipeline tests pass

- [ ] **Step 7: Verify #30 spec coverage**

Check:
- `KnowledgeItem::toMarkdown()` returns a formatted markdown string with frontmatter (title, type, tier, tags) and body content
- `tests/Unit/Entity/KnowledgeItem/KnowledgeItemToMarkdownTest.php` passes

- [ ] **Step 8: Close verified issues on GitHub**

For each verified issue, close it with a comment referencing commit `a733428` (or the relevant merge commit from PR #32):

```bash
gh issue close 5 --repo waaseyaa/giiken --comment "Implemented in PR #32 (commit a733428). Verified: interface, registry, RawDocument, exception, and tests all match spec."
gh issue close 6 --repo waaseyaa/giiken --comment "Implemented in PR #32. All four handlers (Document, CSV, HTML, Markdown) with tests verified against spec."
gh issue close 8 --repo waaseyaa/giiken --comment "Implemented in PR #32. CompilationPipeline + 5 steps (Transcribe, Classify, Structure, Link, Embed) with tests verified."
gh issue close 29 --repo waaseyaa/giiken --comment "Implemented in PR #32. MarkItDownConverter, FileConverterInterface, setup script, and tests verified."
gh issue close 30 --repo waaseyaa/giiken --comment "Implemented in PR #32. KnowledgeItem::toMarkdown() with frontmatter rendering and test verified."
```

---

## Task 2: WikiSchema value object (#25)

**Files:**
- Create: `src/Entity/Community/WikiSchema.php`
- Create: `tests/Unit/Entity/Community/WikiSchemaTest.php`
- Test: `tests/Unit/Entity/Community/CommunityWikiSchemaTest.php`

Community already has `getWikiSchema(): array`. This task adds a proper value object that structures the schema with typed access, defaults, and serialization.

- [ ] **Step 1: Write the WikiSchema test**

Create `tests/Unit/Entity/Community/WikiSchemaTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Community;

use App\Entity\Community\WikiSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WikiSchema::class)]
final class WikiSchemaTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $schema = new WikiSchema();

        self::assertSame('en', $schema->defaultLanguage);
        self::assertSame([], $schema->knowledgeTypes);
        self::assertSame('', $schema->llmInstructions);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $schema = WikiSchema::fromArray([
            'default_language' => 'oji',
            'knowledge_types' => ['Governance', 'Cultural', 'Land'],
            'llm_instructions' => 'Preserve oral history phrasing. Do not paraphrase elders.',
        ]);

        self::assertSame('oji', $schema->defaultLanguage);
        self::assertSame(['Governance', 'Cultural', 'Land'], $schema->knowledgeTypes);
        self::assertSame('Preserve oral history phrasing. Do not paraphrase elders.', $schema->llmInstructions);
    }

    #[Test]
    public function it_serializes_to_array(): void
    {
        $schema = new WikiSchema(
            defaultLanguage: 'fr',
            knowledgeTypes: ['Event'],
            llmInstructions: 'Respond in French.',
        );

        $array = $schema->toArray();

        self::assertSame('fr', $array['default_language']);
        self::assertSame(['Event'], $array['knowledge_types']);
        self::assertSame('Respond in French.', $array['llm_instructions']);
    }

    #[Test]
    public function it_roundtrips_through_json(): void
    {
        $original = new WikiSchema(
            defaultLanguage: 'oji',
            knowledgeTypes: ['Governance', 'Cultural'],
            llmInstructions: 'Use community voice.',
        );

        $json = json_encode($original->toArray(), JSON_THROW_ON_ERROR);
        $restored = WikiSchema::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));

        self::assertSame($original->defaultLanguage, $restored->defaultLanguage);
        self::assertSame($original->knowledgeTypes, $restored->knowledgeTypes);
        self::assertSame($original->llmInstructions, $restored->llmInstructions);
    }

    #[Test]
    public function from_array_ignores_unknown_keys(): void
    {
        $schema = WikiSchema::fromArray([
            'default_language' => 'en',
            'unknown_key' => 'ignored',
        ]);

        self::assertSame('en', $schema->defaultLanguage);
    }

    #[Test]
    public function from_array_handles_empty_input(): void
    {
        $schema = WikiSchema::fromArray([]);

        self::assertSame('en', $schema->defaultLanguage);
        self::assertSame([], $schema->knowledgeTypes);
        self::assertSame('', $schema->llmInstructions);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Unit/Entity/Community/WikiSchemaTest.php
```

Expected: FAIL — class `WikiSchema` not found.

- [ ] **Step 3: Implement WikiSchema**

Create `src/Entity/Community/WikiSchema.php`:

```php
<?php

declare(strict_types=1);

namespace App\Entity\Community;

final class WikiSchema
{
    /**
     * @param list<string> $knowledgeTypes
     */
    public function __construct(
        public readonly string $defaultLanguage = 'en',
        public readonly array $knowledgeTypes = [],
        public readonly string $llmInstructions = '',
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            defaultLanguage: (string) ($data['default_language'] ?? 'en'),
            knowledgeTypes: isset($data['knowledge_types']) && is_array($data['knowledge_types'])
                ? array_values(array_map('strval', $data['knowledge_types']))
                : [],
            llmInstructions: (string) ($data['llm_instructions'] ?? ''),
        );
    }

    /**
     * @return array{default_language: string, knowledge_types: list<string>, llm_instructions: string}
     */
    public function toArray(): array
    {
        return [
            'default_language' => $this->defaultLanguage,
            'knowledge_types' => $this->knowledgeTypes,
            'llm_instructions' => $this->llmInstructions,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Unit/Entity/Community/WikiSchemaTest.php
```

Expected: 6 tests, all PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Entity/Community/WikiSchema.php tests/Unit/Entity/Community/WikiSchemaTest.php
git commit -m "feat(#25): add WikiSchema value object with serialization"
```

- [ ] **Step 6: Write Community WikiSchema integration test**

Create `tests/Unit/Entity/Community/CommunityWikiSchemaTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Community;

use App\Entity\Community\Community;
use App\Entity\Community\WikiSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Community::class)]
#[CoversClass(WikiSchema::class)]
final class CommunityWikiSchemaTest extends TestCase
{
    #[Test]
    public function get_wiki_schema_returns_typed_object(): void
    {
        $community = new Community([
            'name' => 'Test Community',
            'wiki_schema' => [
                'default_language' => 'oji',
                'knowledge_types' => ['Governance', 'Cultural'],
                'llm_instructions' => 'Preserve oral history phrasing.',
            ],
        ]);

        $schema = $community->getTypedWikiSchema();

        self::assertSame('oji', $schema->defaultLanguage);
        self::assertSame(['Governance', 'Cultural'], $schema->knowledgeTypes);
        self::assertSame('Preserve oral history phrasing.', $schema->llmInstructions);
    }

    #[Test]
    public function get_typed_wiki_schema_returns_defaults_when_empty(): void
    {
        $community = new Community(['name' => 'Empty Schema']);

        $schema = $community->getTypedWikiSchema();

        self::assertSame('en', $schema->defaultLanguage);
        self::assertSame([], $schema->knowledgeTypes);
        self::assertSame('', $schema->llmInstructions);
    }

    #[Test]
    public function get_typed_wiki_schema_handles_json_string(): void
    {
        $schemaJson = json_encode([
            'default_language' => 'fr',
            'knowledge_types' => ['Event'],
            'llm_instructions' => 'Respond in French.',
        ], JSON_THROW_ON_ERROR);

        $community = new Community([
            'name' => 'JSON Schema',
            'wiki_schema' => $schemaJson,
        ]);

        $schema = $community->getTypedWikiSchema();

        self::assertSame('fr', $schema->defaultLanguage);
        self::assertSame(['Event'], $schema->knowledgeTypes);
    }
}
```

- [ ] **Step 7: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Unit/Entity/Community/CommunityWikiSchemaTest.php
```

Expected: FAIL — method `getTypedWikiSchema` not found.

- [ ] **Step 8: Add getTypedWikiSchema to Community**

Add to `src/Entity/Community/Community.php`, after the existing `getWikiSchema()` method:

```php
public function getTypedWikiSchema(): WikiSchema
{
    return WikiSchema::fromArray($this->getWikiSchema());
}
```

Add the import at the top of the file:

```php
use App\Entity\Community\WikiSchema;
```

- [ ] **Step 9: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Unit/Entity/Community/CommunityWikiSchemaTest.php
```

Expected: 3 tests, all PASS.

- [ ] **Step 10: Run full suite to check for regressions**

```bash
./vendor/bin/phpunit --no-progress
```

Expected: 87 tests (78 + 9 new), all passing.

- [ ] **Step 11: Commit**

```bash
git add src/Entity/Community/Community.php tests/Unit/Entity/Community/CommunityWikiSchemaTest.php
git commit -m "feat(#25): add getTypedWikiSchema() to Community entity"
```

---

## Task 3: LintCheckInterface and OrphanPageCheck (#23)

**Files:**
- Create: `src/Wiki/Check/LintCheckInterface.php`
- Create: `src/Wiki/Check/OrphanPageCheck.php`
- Create: `tests/Unit/Wiki/Check/OrphanPageCheckTest.php`

- [ ] **Step 1: Write the OrphanPageCheck test**

Create `tests/Unit/Wiki/Check/OrphanPageCheckTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wiki\Check;

use App\Entity\KnowledgeItem\KnowledgeItem;
use App\Wiki\Check\LintCheckInterface;
use App\Wiki\Check\OrphanPageCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrphanPageCheck::class)]
final class OrphanPageCheckTest extends TestCase
{
    #[Test]
    public function it_implements_lint_check_interface(): void
    {
        $check = new OrphanPageCheck();
        self::assertInstanceOf(LintCheckInterface::class, $check);
    }

    #[Test]
    public function it_detects_orphan_pages(): void
    {
        $check = new OrphanPageCheck();

        // Page A links to B, but C has no inbound links
        $items = [
            $this->makeItem('page-a', 'Page A', 'See [[page-b]] for details.'),
            $this->makeItem('page-b', 'Page B', 'Referenced from A.'),
            $this->makeItem('page-c', 'Page C', 'Nobody links here.'),
        ];

        $findings = $check->run($items);

        self::assertCount(1, $findings);
        self::assertSame('page-c', $findings[0]['item_id']);
        self::assertSame('orphan_page', $findings[0]['type']);
    }

    #[Test]
    public function it_returns_empty_when_all_pages_linked(): void
    {
        $check = new OrphanPageCheck();

        $items = [
            $this->makeItem('page-a', 'Page A', 'See [[page-b]].'),
            $this->makeItem('page-b', 'Page B', 'See [[page-a]].'),
        ];

        $findings = $check->run($items);

        self::assertCount(0, $findings);
    }

    #[Test]
    public function it_treats_single_page_as_orphan(): void
    {
        $check = new OrphanPageCheck();

        $items = [
            $this->makeItem('lonely', 'Lonely Page', 'No links anywhere.'),
        ];

        $findings = $check->run($items);

        self::assertCount(1, $findings);
    }

    private function makeItem(string $id, string $title, string $body): KnowledgeItem
    {
        return new KnowledgeItem([
            'id' => $id,
            'title' => $title,
            'body' => $body,
            'community_id' => 'test-community',
            'knowledge_type' => 'general',
            'access_tier' => 'public',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Unit/Wiki/Check/OrphanPageCheckTest.php
```

Expected: FAIL — classes not found.

- [ ] **Step 3: Implement LintCheckInterface**

Create `src/Wiki/Check/LintCheckInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Wiki\Check;

use App\Entity\KnowledgeItem\KnowledgeItem;

interface LintCheckInterface
{
    /**
     * Run this check against a set of knowledge items.
     *
     * @param list<KnowledgeItem> $items
     * @return list<array{item_id: string, type: string, message: string}>
     */
    public function run(array $items): array;
}
```

- [ ] **Step 4: Implement OrphanPageCheck**

Create `src/Wiki/Check/OrphanPageCheck.php`:

```php
<?php

declare(strict_types=1);

namespace App\Wiki\Check;

use App\Entity\KnowledgeItem\KnowledgeItem;

final class OrphanPageCheck implements LintCheckInterface
{
    /**
     * @param list<KnowledgeItem> $items
     * @return list<array{item_id: string, type: string, message: string}>
     */
    public function run(array $items): array
    {
        $allIds = [];
        $linkedIds = [];

        foreach ($items as $item) {
            $id = (string) $item->get('id');
            $allIds[] = $id;

            // Extract [[wiki-link]] references from body
            $body = (string) ($item->get('body') ?? '');
            if (preg_match_all('/\[\[([^\]]+)\]\]/', $body, $matches)) {
                foreach ($matches[1] as $link) {
                    $linkedIds[] = $link;
                }
            }
        }

        $linkedIds = array_unique($linkedIds);
        $findings = [];

        foreach ($allIds as $id) {
            if (!in_array($id, $linkedIds, true)) {
                $findings[] = [
                    'item_id' => $id,
                    'type' => 'orphan_page',
                    'message' => "Page '{$id}' has no inbound links from other pages.",
                ];
            }
        }

        return $findings;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Unit/Wiki/Check/OrphanPageCheckTest.php
```

Expected: 4 tests, all PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Wiki/Check/LintCheckInterface.php src/Wiki/Check/OrphanPageCheck.php tests/Unit/Wiki/Check/OrphanPageCheckTest.php
git commit -m "feat(#23): add LintCheckInterface and OrphanPageCheck"
```

---

## Task 4: BrokenLinkCheck (#23)

**Files:**
- Create: `src/Wiki/Check/BrokenLinkCheck.php`
- Create: `tests/Unit/Wiki/Check/BrokenLinkCheckTest.php`

- [ ] **Step 1: Write the BrokenLinkCheck test**

Create `tests/Unit/Wiki/Check/BrokenLinkCheckTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wiki\Check;

use App\Entity\KnowledgeItem\KnowledgeItem;
use App\Wiki\Check\BrokenLinkCheck;
use App\Wiki\Check\LintCheckInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BrokenLinkCheck::class)]
final class BrokenLinkCheckTest extends TestCase
{
    #[Test]
    public function it_implements_lint_check_interface(): void
    {
        $check = new BrokenLinkCheck();
        self::assertInstanceOf(LintCheckInterface::class, $check);
    }

    #[Test]
    public function it_detects_broken_links(): void
    {
        $check = new BrokenLinkCheck();

        $items = [
            $this->makeItem('page-a', 'Page A', 'See [[page-b]] and [[page-missing]].'),
            $this->makeItem('page-b', 'Page B', 'Links to [[also-missing]].'),
        ];

        $findings = $check->run($items);

        self::assertCount(2, $findings);

        $itemIds = array_column($findings, 'item_id');
        self::assertContains('page-a', $itemIds);
        self::assertContains('page-b', $itemIds);

        $types = array_unique(array_column($findings, 'type'));
        self::assertSame(['broken_link'], $types);
    }

    #[Test]
    public function it_returns_empty_when_all_links_valid(): void
    {
        $check = new BrokenLinkCheck();

        $items = [
            $this->makeItem('page-a', 'Page A', 'See [[page-b]].'),
            $this->makeItem('page-b', 'Page B', 'See [[page-a]].'),
        ];

        $findings = $check->run($items);

        self::assertCount(0, $findings);
    }

    #[Test]
    public function it_handles_pages_with_no_links(): void
    {
        $check = new BrokenLinkCheck();

        $items = [
            $this->makeItem('page-a', 'Page A', 'No links here.'),
        ];

        $findings = $check->run($items);

        self::assertCount(0, $findings);
    }

    private function makeItem(string $id, string $title, string $body): KnowledgeItem
    {
        return new KnowledgeItem([
            'id' => $id,
            'title' => $title,
            'body' => $body,
            'community_id' => 'test-community',
            'knowledge_type' => 'general',
            'access_tier' => 'public',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Unit/Wiki/Check/BrokenLinkCheckTest.php
```

Expected: FAIL — class `BrokenLinkCheck` not found.

- [ ] **Step 3: Implement BrokenLinkCheck**

Create `src/Wiki/Check/BrokenLinkCheck.php`:

```php
<?php

declare(strict_types=1);

namespace App\Wiki\Check;

use App\Entity\KnowledgeItem\KnowledgeItem;

final class BrokenLinkCheck implements LintCheckInterface
{
    /**
     * @param list<KnowledgeItem> $items
     * @return list<array{item_id: string, type: string, message: string}>
     */
    public function run(array $items): array
    {
        $knownIds = [];
        foreach ($items as $item) {
            $knownIds[] = (string) $item->get('id');
        }

        $findings = [];

        foreach ($items as $item) {
            $id = (string) $item->get('id');
            $body = (string) ($item->get('body') ?? '');

            if (!preg_match_all('/\[\[([^\]]+)\]\]/', $body, $matches)) {
                continue;
            }

            foreach (array_unique($matches[1]) as $link) {
                if (!in_array($link, $knownIds, true)) {
                    $findings[] = [
                        'item_id' => $id,
                        'type' => 'broken_link',
                        'message' => "Page '{$id}' links to '[[{$link}]]' which does not exist.",
                    ];
                }
            }
        }

        return $findings;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Unit/Wiki/Check/BrokenLinkCheckTest.php
```

Expected: 4 tests, all PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Wiki/Check/BrokenLinkCheck.php tests/Unit/Wiki/Check/BrokenLinkCheckTest.php
git commit -m "feat(#23): add BrokenLinkCheck for detecting dead wiki links"
```

---

## Task 5: WikiLintReport entity (#23)

**Files:**
- Create: `src/Wiki/WikiLintReport.php`

No dedicated test file: the report entity is a simple data carrier tested through WikiLintJobTest in Task 6.

- [ ] **Step 1: Implement WikiLintReport**

Create `src/Wiki/WikiLintReport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Wiki;

use Waaseyaa\Entity\ContentEntityBase;

final class WikiLintReport extends ContentEntityBase
{
    protected string $entityTypeId = 'wiki_lint_report';

    protected array $entityKeys = [
        'id'    => 'id',
        'uuid'  => 'uuid',
        'label' => 'title',
    ];

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(array $values = [])
    {
        if (!isset($values['created_at'])) {
            $values['created_at'] = date('c');
        }

        if (!isset($values['knowledge_type'])) {
            $values['knowledge_type'] = 'lint_report';
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }

    public function getCommunityId(): string
    {
        return (string) ($this->get('community_id') ?? '');
    }

    /**
     * @return list<array{item_id: string, type: string, message: string}>
     */
    public function getFindings(): array
    {
        $raw = $this->get('findings');

        if (is_array($raw)) {
            /** @var list<array{item_id: string, type: string, message: string}> $raw */
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            try {
                /** @var list<array{item_id: string, type: string, message: string}> $decoded */
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                return $decoded;
            } catch (\JsonException) {
                return [];
            }
        }

        return [];
    }

    public function getFindingCount(): int
    {
        return count($this->getFindings());
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Wiki/WikiLintReport.php
git commit -m "feat(#23): add WikiLintReport entity"
```

---

## Task 6: WikiLintJob (#23)

**Files:**
- Create: `src/Wiki/WikiLintJob.php`
- Create: `tests/Unit/Wiki/WikiLintJobTest.php`

- [ ] **Step 1: Write the WikiLintJob test**

Create `tests/Unit/Wiki/WikiLintJobTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wiki;

use App\Entity\KnowledgeItem\KnowledgeItem;
use App\Wiki\Check\BrokenLinkCheck;
use App\Wiki\Check\LintCheckInterface;
use App\Wiki\Check\OrphanPageCheck;
use App\Wiki\WikiLintJob;
use App\Wiki\WikiLintReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityRepositoryInterface;

#[CoversClass(WikiLintJob::class)]
final class WikiLintJobTest extends TestCase
{
    #[Test]
    public function it_runs_all_checks_and_produces_report(): void
    {
        $items = [
            $this->makeItem('page-a', 'See [[page-b]] and [[missing]].'),
            $this->makeItem('page-b', 'No links.'),
            $this->makeItem('page-c', 'Orphan with [[also-missing]].'),
        ];

        $repository = $this->createMock(EntityRepositoryInterface::class);
        $repository->method('loadMultiple')->willReturn($items);

        $savedReport = null;
        $repository->method('save')->willReturnCallback(function (object $entity) use (&$savedReport): void {
            if ($entity instanceof WikiLintReport) {
                $savedReport = $entity;
            }
        });

        $job = new WikiLintJob(
            communityId: 'test-community',
            repository: $repository,
            checks: [new OrphanPageCheck(), new BrokenLinkCheck()],
        );

        $job->handle();

        self::assertNotNull($savedReport);
        self::assertInstanceOf(WikiLintReport::class, $savedReport);

        $findings = $savedReport->getFindings();
        self::assertNotEmpty($findings);

        $types = array_unique(array_column($findings, 'type'));
        sort($types);
        self::assertSame(['broken_link', 'orphan_page'], $types);
    }

    #[Test]
    public function it_produces_clean_report_when_no_issues(): void
    {
        $items = [
            $this->makeItem('page-a', 'See [[page-b]].'),
            $this->makeItem('page-b', 'See [[page-a]].'),
        ];

        $repository = $this->createMock(EntityRepositoryInterface::class);
        $repository->method('loadMultiple')->willReturn($items);

        $savedReport = null;
        $repository->method('save')->willReturnCallback(function (object $entity) use (&$savedReport): void {
            if ($entity instanceof WikiLintReport) {
                $savedReport = $entity;
            }
        });

        $job = new WikiLintJob(
            communityId: 'test-community',
            repository: $repository,
            checks: [new OrphanPageCheck(), new BrokenLinkCheck()],
        );

        $job->handle();

        self::assertNotNull($savedReport);
        self::assertSame(0, $savedReport->getFindingCount());
    }

    private function makeItem(string $id, string $body): KnowledgeItem
    {
        return new KnowledgeItem([
            'id' => $id,
            'title' => ucfirst(str_replace('-', ' ', $id)),
            'body' => $body,
            'community_id' => 'test-community',
            'knowledge_type' => 'general',
            'access_tier' => 'public',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Unit/Wiki/WikiLintJobTest.php
```

Expected: FAIL — class `WikiLintJob` not found.

- [ ] **Step 3: Implement WikiLintJob**

Create `src/Wiki/WikiLintJob.php`:

```php
<?php

declare(strict_types=1);

namespace App\Wiki;

use App\Wiki\Check\LintCheckInterface;
use Waaseyaa\Entity\EntityRepositoryInterface;

final class WikiLintJob
{
    /**
     * @param list<LintCheckInterface> $checks
     */
    public function __construct(
        private readonly string $communityId,
        private readonly EntityRepositoryInterface $repository,
        private readonly array $checks,
    ) {}

    public function handle(): void
    {
        $items = $this->repository->loadMultiple('knowledge_item', [
            'community_id' => $this->communityId,
        ]);

        $allFindings = [];

        foreach ($this->checks as $check) {
            $findings = $check->run($items);
            foreach ($findings as $finding) {
                $allFindings[] = $finding;
            }
        }

        $report = new WikiLintReport([
            'id' => 'lint-report-' . $this->communityId . '-' . time(),
            'title' => 'Wiki Lint Report — ' . date('Y-m-d H:i'),
            'community_id' => $this->communityId,
            'findings' => $allFindings,
        ]);

        $this->repository->save($report);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Unit/Wiki/WikiLintJobTest.php
```

Expected: 2 tests, all PASS.

- [ ] **Step 5: Run full suite**

```bash
./vendor/bin/phpunit --no-progress
```

Expected: ~95 tests, all passing.

- [ ] **Step 6: Run PHPStan**

```bash
./vendor/bin/phpstan analyse src/
```

Expected: No errors.

- [ ] **Step 7: Commit**

```bash
git add src/Wiki/WikiLintJob.php tests/Unit/Wiki/WikiLintJobTest.php
git commit -m "feat(#23): add WikiLintJob orchestrating checks and saving reports"
```

---

## Task 7: Register wiki entities in AppServiceProvider

**Files:**
- Modify: `src/AppServiceProvider.php`
- Modify: `tests/Unit/AppServiceProviderTest.php` (if entity registration is tested there)

- [ ] **Step 1: Read the current service provider**

Read `src/AppServiceProvider.php` to understand the registration pattern.

- [ ] **Step 2: Add WikiLintReport entity type registration**

Add the `wiki_lint_report` entity type to the service provider's entity type registrations, following the same pattern used for `knowledge_item` and `community`.

- [ ] **Step 3: Run existing service provider test**

```bash
./vendor/bin/phpunit tests/Unit/AppServiceProviderTest.php
```

Expected: PASS (may need test update if it asserts entity type count).

- [ ] **Step 4: Commit**

```bash
git add src/AppServiceProvider.php tests/Unit/AppServiceProviderTest.php
git commit -m "feat(#23): register wiki_lint_report entity type in service provider"
```

---

## Task 8: Close issues #23 and #25 on GitHub

- [ ] **Step 1: Close #25**

```bash
gh issue close 25 --repo waaseyaa/giiken --comment "WikiSchema value object added. Community::getTypedWikiSchema() provides typed access. wiki_schema field was already on Community entity. Serialization, defaults, and JSON roundtrip tested."
```

- [ ] **Step 2: Close #23**

```bash
gh issue close 23 --repo waaseyaa/giiken --comment "WikiLintJob implemented with OrphanPageCheck and BrokenLinkCheck. Produces WikiLintReport entity saved via EntityRepository. All checks unit tested."
```

Note: #23 acceptance criterion "Can be triggered manually from the Knowledge Keeper surface" is a Phase 4 UX concern (#20/#21). The backend job is ready; the UI trigger comes later.

- [ ] **Step 3: Verify milestone progress**

```bash
gh api repos/waaseyaa/giiken/milestones/2 --jq '"\(.title): \(.open_issues) open, \(.closed_issues) closed"'
```

Expected: "Phase 2: Ingestion Pipeline — 1 open, 8 closed" (only #27 remains, which spans Phase 2+3).

---

## Notes

### Issue #27 (MVP Acceptance Scenario)
This issue explicitly depends on Phase 3: Query Layer (#10 semantic search, #11 Q&A/RAG). The acceptance criteria require "Q&A produces cited answers" and "semantic search returns relevant results," which are not buildable until Phase 3. The ingestion side of #27 is validated by the existing pipeline tests. A full integration smoke test should be written when Phase 3 lands.

### Remaining Phase 2 after this plan
Only #27 remains open, correctly deferred until Phase 3 provides the query infrastructure it depends on.
