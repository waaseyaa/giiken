# Indigenous Knowledge Management Platform — Design Spec

**Date:** 2026-04-04
**Status:** Approved for implementation planning
**Author:** Russell Jones / Claudia

---

## Problem Statement

Indigenous communities hold irreplaceable knowledge — oral histories, elder interviews, land records, governance documents, language recordings — scattered across hard drives, filing cabinets, email inboxes, and aging media formats. Existing knowledge management platforms (SharePoint, Google Drive, Notion, AWS offerings) require communities to surrender control of their data to third-party infrastructure. Communities who cannot trust external platforms have no sovereign alternative.

This product is that alternative: a knowledge management system that processes any-source raw material into a structured, searchable, queryable knowledge base — with the community's data staying exactly where the community decides it lives.

Inspired by Andrej Karpathy's `raw/ → wiki` LLM pattern, adapted for Indigenous data sovereignty.

---

## Architecture

Two layers, always running together. The knowledge app is a Waaseyaa application — not a parallel framework.

### Layer 1: Waaseyaa (the framework)

Waaseyaa provides the infrastructure the knowledge app builds on. No knowledge-app-specific code belongs here; instead, patterns proven by the knowledge app get extracted back into Waaseyaa over time (co-evolution approach).

Relevant Waaseyaa packages the knowledge app uses directly:

| Package | What it provides |
|---------|-----------------|
| `ai-pipeline` | `PipelineStepInterface`, `PipelineExecutor`, `PipelineDispatcher`, async queue integration |
| `ai-vector` | `VectorStoreInterface`, `SqliteEmbeddingStorage`, `OllamaEmbeddingProvider`, `OpenAiEmbeddingProvider`, `EntityEmbedder`, `SearchController` |
| `access` | `AccessPolicyInterface`, `EntityAccessHandler`, `FieldAccessPolicyInterface`, `Gate` |
| `ingestion` | Envelope validation, payload abstractions |
| `media` | Source file storage and retrieval |
| `queue` | Async job dispatch for long-running LLM tasks |
| `workflows` | Report generation pipeline |
| `auth` | Authentication |
| `entity` / `entity-storage` | Persistence for all content types |
| `mcp` | Auto-generated MCP tools from entity schemas |

**Co-evolution gap:** Waaseyaa has no explicit multi-tenancy. Community isolation (scoping all entity queries to the current community context) must be designed as a Waaseyaa-level concern — an `AccessPolicy` + query-scope pattern that benefits all future sovereign apps built on Waaseyaa.

### Layer 2: Knowledge App

What the knowledge app adds on top of Waaseyaa:

1. **Community entity type** — the tenant context; all entity queries scope to the current community
2. **KnowledgeItem entity type** — the compiled wiki entry (title, content, source refs, knowledge type, access tier, `allowed_roles[]`, `allowed_users[]`)
3. **File ingestion handlers** — extends `ingestion` with: PDF extractor, audio/video transcriber, DOCX parser, connected source adapters (email, SharePoint, Google Drive)
4. **Compilation pipeline steps** — implements `PipelineStepInterface` for: transcribe → classify → structure → link → embed
5. **Community RBAC policies** — extends `access` with community roles (see Access Control section)
6. **Q&A interface** — RAG over KnowledgeItems using `ai-vector` + `ai-agent`
7. **Report generator** — `workflows` + Twig templates → assembled documents
8. **Export tooling** — first-class data portability (see Community Sovereignty Guarantee)

---

## Data Flow

The core value of the product. All other concerns are infrastructure.

```
raw/
  └── any source: file upload, recording, connected system sync
        │
        ▼
  [Ingestion Handler]           per file type: PDF, DOCX, MP3, MP4, CSV, email...
        │                       validates, normalizes, stores original in media library
        ▼
  [Compilation Pipeline]        PipelineExecutor dispatches async via queue
        │
        ├── Step 1: Transcribe  audio/video → text
        │                       local: Whisper via Ollama
        │                       hosted: transcription API
        │
        ├── Step 2: Classify    knowledge type: cultural | governance | land | relationship | event
        │
        ├── Step 3: Structure   extract: title, date, people, places, topics,
        │                       summary, key passages
        │
        ├── Step 4: Link        connect to existing KnowledgeItems
        │                       (same people, events, topics, territory)
        │
        └── Step 5: Embed       vector embedding → EmbeddingStorage
                                local: SqliteEmbeddingStorage
                                hosted: pgvector
        │
        ▼
  wiki/   KnowledgeItem entities — versioned, access-controlled
        │
        ├── Search      full-text + semantic similarity (ai-vector SearchController)
        ├── Q&A         RAG: query → retrieve relevant items → LLM answer with citations
        └── Reports     workflow template + KnowledgeItems → assembled document
```

**Provenance is preserved at every step.** Every KnowledgeItem links to its source file in the media library. Every Q&A answer cites the KnowledgeItems it drew from. Every report lists its sources. Knowledge is always traceable back to the original recording or document.

---

## Knowledge Types

The platform is general-purpose. Communities decide what goes in. The classifier assigns one of:

- **Cultural** — oral histories, teachings, ceremonies, language recordings, elder interviews
- **Governance** — band council resolutions, meeting minutes, policies, treaties, agreements, funding documents
- **Land** — environmental monitoring, land use records, resource assessments, traditional territory knowledge
- **Relationship** — people, organizations, external contacts and their roles
- **Event** — dated occurrences, meetings, ceremonies, milestones

Knowledge type drives display, search faceting, and default access tier suggestions. It does not restrict what a community can store.

---

## Access Control

Built on Waaseyaa's `access` package. The knowledge app adds a community role layer.

### Community Roles

| Role | Who | Capabilities |
|------|-----|-------------|
| **Admin** | Band council IT, NorthOps | Full system access, user management, deployment configuration |
| **Knowledge Keeper** | Designated archivist, language coordinator | Ingest, review, approve, edit, set access tiers on any item |
| **Staff** | Band office employees | Search and retrieve all internal content, generate reports |
| **Member** | Community members | Search and Q&A on member-tier content, view public content |
| **Public** | Anyone | Only explicitly public-tier content |

### KnowledgeItem Access Tiers

| Tier | Who can access |
|------|---------------|
| `public` | Anyone, including outside the community |
| `members` | All registered community members |
| `staff` | Staff role and above |
| `restricted` | Explicitly listed roles and/or individuals |

### Restricted Access Model

The `restricted` tier supports both dimensions simultaneously:

```yaml
allowed_roles: [knowledge_keeper, elder]   # role-based
allowed_users: [user_id_123, user_id_456]  # individual-based
```

Either or both arrays can be set. An empty array means that dimension is not applied. A KnowledgeItem with `allowed_roles: [knowledge_keeper]` and no `allowed_users` is accessible to all Knowledge Keepers. One with both set is accessible to Knowledge Keepers OR the named individuals.

Implemented via `AccessPolicyInterface` evaluated by `EntityAccessHandler`. Field-level restriction (e.g., public title, restricted content) is handled by `FieldAccessPolicyInterface`.

### Multi-Tenancy

Community A never sees Community B's data. Isolation is enforced at the query scope level in Waaseyaa — not just at the UI. The co-evolution work required: a community-scoped query constraint that all entity repositories apply automatically when a community context is active.

---

## Deployment

Same codebase, three runtime configurations, one config key:

```yaml
sovereignty_profile: local   # local | self_hosted | northops
```

### SovereigntyProfile Defaults

| Setting | `local` | `self_hosted` | `northops` |
|---------|---------|---------------|------------|
| storage | filesystem | filesystem / object storage | S3-compatible |
| embeddings | SqliteEmbeddingStorage | SqliteEmbeddingStorage | pgvector |
| llm_provider | Ollama | Ollama or API | sovereign API |
| transcriber | Whisper/Ollama | Whisper/Ollama | transcription API |
| vector_store | sqlite | sqlite | pgvector |
| queue_backend | sync | database | redis |

Communities can override individual settings. The profile provides a clean, opinionated baseline.

`SovereigntyProfile` lives in Waaseyaa Layer 0 — it is a framework-level concept, not specific to this product.

### Operator Models

The platform supports all operator configurations:

- **Knowledge Keeper** — one person at the community owns and operates the system
- **Band council IT / Admin staff** — existing staff handle installation and configuration
- **NorthOps managed** — NorthOps deploys, maintains, and supports as a managed service

Larger communities may have IT staff for self-hosted; smaller communities use NorthOps hosted. The software is the same.

---

## Community Sovereignty Guarantee

This is not a marketing claim — it is a product commitment enforced by the software.

> A community can export all their data — KnowledgeItems, source media, vector embeddings, user records, and configuration — at any time, in open formats, and migrate to self-hosted or local mode without penalty or data loss.

### What this requires:

- **Export is a first-class feature** built into the admin interface from day one, not a support ticket
- **Open formats only**: KnowledgeItems as Markdown, media as original files, embeddings as portable vectors, config as YAML
- **Migration tooling ships with the product**: the same `deployer` that handles initial setup handles profile migration
- **No penalty clause** in service agreements
- **Architecture constraint**: if data cannot be cleanly exported, it should not be stored that way

The guarantee shapes design decisions throughout the build. It also makes the SaaS subscription feel like a service, not a trap.

---

## Business Model

Three revenue streams that reinforce each other:

**Grants**
NorthOps identifies and applies for (or helps communities apply for) funding from Indigenous Services Canada, heritage programs, and language revitalization funds. Grant covers the implementation engagement. This is the acquisition channel — communities get their first deployment funded.

**Professional Services**
Setup, data migration, training, and custom ingestion handlers for unusual source types. Billed per engagement. This is where NorthOps earns margin on the relationship.

**SaaS Subscription**
Annual fee per community for NorthOps-hosted deployments (covering infrastructure, model API costs, updates, and support). Local and self-hosted communities pay a reduced software maintenance fee. Sliding scale by community size.

**The flywheel:** grants fund the first deployment → professional services build the relationship → SaaS subscription is the recurring revenue → satisfied communities become references that support the next grant application.

The Community Sovereignty Guarantee strengthens all three streams: communities that trust you stay. The guarantee is the foundation of long-term revenue, not a risk to it.

---

## What is Not In Scope (v1)

- Public-facing language learning or cultural education portals (output from the knowledge base, not the base itself)
- Federation between communities (each community is a fully isolated tenant)
- Real-time collaborative editing of KnowledgeItems
- Mobile app (web interface only)
- Automated government reporting integrations (professional services engagement, not product feature)
