<?php

declare(strict_types=1);

namespace App\Entity\KnowledgeItem\Source;

/**
 * High-level copyright posture for a knowledge item.
 *
 * Distinct from `license` — the license is the specific legal instrument (e.g.
 * `CC-BY-4.0`), while the status communicates the relationship the platform
 * has to the underlying work. An `external_link` item is a pointer; a `licensed`
 * item is hosted content used under terms; an `owned` item is the community's
 * own knowledge under its own authority.
 */
enum CopyrightStatus: string
{
    /** Knowledge owned by the community that holds authority over it. */
    case Owned = 'owned';

    /** Hosted under a specific external license. */
    case Licensed = 'licensed';

    /** Used under fair dealing / fair use. */
    case FairDealing = 'fair_dealing';

    /** No local copy — the entity points to an external work. */
    case ExternalLink = 'external_link';

    /** Public domain work. */
    case PublicDomain = 'public_domain';
}
