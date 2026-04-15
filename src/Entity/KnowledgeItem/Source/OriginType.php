<?php

declare(strict_types=1);

namespace App\Entity\KnowledgeItem\Source;

/**
 * How a knowledge item entered the system.
 *
 * The origin type is indexed on the entity for fast filtering ("show me everything
 * that came from NorthCloud", "show me manually-curated items only"). It also
 * drives linting rules: a `manual` item without an attributed creator is a lint
 * warning in a way an `northcloud` item isn't.
 */
enum OriginType: string
{
    /** Ingested from the North Cloud content pipeline. */
    case NorthCloud = 'northcloud';

    /** Uploaded via ingestion handler (file, CSV, document). */
    case Upload = 'upload';

    /** Created manually through the admin UI. */
    case Manual = 'manual';

    /** Imported from an external wiki/structured knowledge base. */
    case Wiki = 'wiki';

    /** Contributed directly by a community member. */
    case Community = 'community';
}
