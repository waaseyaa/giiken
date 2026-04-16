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
  allowedRoles?: string[]
  allowedUsers?: string[]
  sourceMediaIds?: string[]
}

export type KnowledgeType = 'cultural' | 'governance' | 'land' | 'relationship' | 'event'

export const KNOWLEDGE_TYPES = [
  'cultural', 'governance', 'land', 'relationship', 'event',
] as const satisfies readonly KnowledgeType[]

export type AccessTier = 'public' | 'members' | 'staff' | 'restricted'
export type CommunityRole = 'admin' | 'knowledge_keeper' | 'staff' | 'member' | 'public'

export interface CommunitySummary {
  id: number | string
  name: string
  slug: string
  locale: string
}

export interface Community {
  id: string
  name: string
  slug: string
  locale: string
  contactEmail: string
  sovereigntyProfile: 'local' | 'self_hosted' | 'northops'
}

export interface SearchResult {
  id: string
  title: string
  summary: string
  knowledgeType: KnowledgeType | null
  score: number
  accessTier?: AccessTier
  sourceOrigin?: string
  createdAt?: string
}

export interface SearchResultSet {
  items: SearchResult[]
  totalHits: number
  totalPages: number
}

export interface Citation {
  itemId: string
  title: string
  excerpt: string
  knowledgeType: KnowledgeType | null
}

export interface QaResponse {
  answer: string
  citedItemIds: string[]
  citations: Citation[]
  noRelevantItems: boolean
}

/**
 * Per-type badge styling. Classes reference the --color-{type}* tokens
 * defined in resources/css/app.css.
 *
 * - `chip` — default non-active pill (subtle background + saturated text).
 * - `activeChip` — inverted pill used when the type is selected (saturated
 *   background + on-primary text). Consumed by TypeFilter.
 * - `dot` — solid colour used for the small indicator dot on cards.
 */
export interface KnowledgeTypeStyle {
  label: string
  chip: string
  activeChip: string
  dot: string
}

export const KNOWLEDGE_TYPE_CONFIG: Record<KnowledgeType, KnowledgeTypeStyle> = {
  cultural: { label: 'Cultural', chip: 'bg-cultural-subtle text-cultural', activeChip: 'bg-cultural text-on-primary', dot: 'bg-cultural' },
  governance: { label: 'Governance', chip: 'bg-governance-subtle text-governance', activeChip: 'bg-governance text-on-primary', dot: 'bg-governance' },
  land: { label: 'Land', chip: 'bg-land-subtle text-land', activeChip: 'bg-land text-on-primary', dot: 'bg-land' },
  relationship: { label: 'Relationship', chip: 'bg-relationship-subtle text-relationship', activeChip: 'bg-relationship text-on-primary', dot: 'bg-relationship' },
  event: { label: 'Event', chip: 'bg-event-subtle text-event', activeChip: 'bg-event text-on-primary', dot: 'bg-event' },
}
