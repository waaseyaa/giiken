# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Giiken is a sovereign indigenous knowledge management platform built on the **Waaseyaa** custom PHP framework. It implements community-based RBAC for multi-tenant knowledge governance.

**PHP:** 8.4+ | **License:** GPL-2.0-or-later | **Namespace:** `Giiken\` (PSR-4)

## Commands

```bash
# Dependencies
composer install

# Run all tests
./vendor/bin/phpunit

# Run a single test file
./vendor/bin/phpunit tests/Unit/Access/KnowledgeItemAccessPolicyTest.php

# Run a specific test suite
./vendor/bin/phpunit --testsuite Unit

# Static analysis
./vendor/bin/phpstan analyse src/
```

## Architecture

### Framework

Waaseyaa is a modular PHP framework split into 30+ packages (`waaseyaa/*`). Key packages used here:

- `waaseyaa/entity` — `ContentEntityBase`, `EntityRepositoryInterface`, `EntityTypeManager`
- `waaseyaa/access` — `AccessPolicyInterface`, `PolicyAttribute`
- `waaseyaa/foundation` — Service provider bootstrap, `WaaseyaaRouter`

### Entity Pattern

All domain objects extend `ContentEntityBase`. Properties are accessed via `$this->get('key')` — define typed getter methods on top of that. Repositories wrap `EntityRepositoryInterface` with typed query methods and set `updated_at` automatically on save.

### RBAC / Multi-Tenancy

Community membership is encoded as account roles using the pattern `giiken.community.{communityId}.{roleSlug}`. The `CommunityRole` enum defines five roles with a numeric rank hierarchy: Admin (5) > KnowledgeKeeper (4) > Staff (3) > Member (2) > Public (1).

`KnowledgeItemAccessPolicy` (annotated with `#[PolicyAttribute('knowledge_item')]`) evaluates access by parsing these account roles and comparing against the item's `AccessTier` (Public / Members / Staff / Restricted). Restricted items also support explicit `allowed_roles` and `allowed_users` lists.

### Service Provider

`GiikenServiceProvider` registers entity types with `EntityTypeManager` and defines routes via `WaaseyaaRouter`. This is the entry point for adding new entity types to the system.

## Testing Conventions

- PHPUnit 10.5+ with `#[Test]`, `#[CoversClass]`, and `#[DataProvider]` attributes
- Test fixtures built in `setUp()` using private helper methods for creating entities and mock accounts
- Access policy tests cover every combination of role × access tier
- Config: `phpunit.xml.dist` (bootstrap: `vendor/autoload.php`, suites: Unit, Integration)
