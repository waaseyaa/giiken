---
work_package_id: WP03
title: Pipeline + ingestion handlers
dependencies:
- WP01
requirement_refs:
- FR-003
planning_base_branch: main
merge_target_branch: main
phase: Phase 2 — Domain
assignee: ''
agent: ''
authoritative_surface: src/Ingestion/IngestionHandlerRegistry.php
owned_files:
- src/Ingestion/IngestionHandlerRegistry.php
- src/Ingestion/IngestionException.php
- src/Ingestion/Handler/CsvIngestionHandler.php
- src/Ingestion/Handler/HtmlIngestionHandler.php
- src/Ingestion/Handler/DocumentIngestionHandler.php
- src/Ingestion/Handler/MarkdownIngestionHandler.php
- src/Ingestion/Handler/MediaIngestionHandler.php
- src/Pipeline/CompilationPipeline.php
- src/Pipeline/PipelineException.php
- src/Pipeline/CompilationPayload.php
- src/Pipeline/SovereigntyConfig.php
- src/Pipeline/Step/TranscribeStep.php
- src/Pipeline/Step/ClassifyStep.php
- src/Pipeline/Step/StructureStep.php
- src/Pipeline/Step/LinkStep.php
- src/Pipeline/Step/EmbedStep.php
- src/Pipeline/Provider/LlmProviderInterface.php
- src/Pipeline/Provider/EmbeddingProviderInterface.php
- src/Pipeline/Provider/AnthropicLlmProvider.php
- src/Pipeline/Provider/NullLlmProvider.php
- src/Pipeline/Provider/NullEmbeddingProvider.php
execution_mode: code_change
---

# WP03 — Pipeline + ingestion handlers

**Mission:** `eliminate-symfony-ingestion-cli-path-01KR2JYJ`  
**Lane:** `lane-domain`

## Goal

Ensure **`IngestionHandlerRegistry`**, ingest-roster **handlers**, **`CompilationPipeline`**, **steps**, and **providers** throw and propagate **Giiken/Waaseyaa-shaped** errors only — no Symfony exception types or resolver/array-callable dispatch in this path.

## Steps

1. Audit each file under [tasks.md](../tasks.md) WP03 **write_scope**.
2. **`CompilationPipeline`**: ensure step failure paths always surface **`PipelineException`** (or documented subclass); inner causes may remain **`RuntimeException`** only as **`$previous`**, not as sole thrown type to callers.
3. Handlers: **`IngestionException`** for operator-relevant failures; no **`UploadedFile`** dependencies in these five handlers.
4. If **`PipelineContext::pipelineId`** or step ids change, update contract doc.

## Done when

- [ ] No `use Symfony\...` under WP03 production scope except any entry explicitly added to allowlist in contract doc (expect **none**).
- [ ] PHPUnit coverage for pipeline/handler failure types still passes or is extended.
