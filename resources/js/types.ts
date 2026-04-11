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
export type AccessTier = 'public' | 'members' | 'staff' | 'restricted'
export type CommunityRole = 'admin' | 'knowledge_keeper' | 'staff' | 'member' | 'public'

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
 * defined in resources/css/app.css. `chip` is the default non-active
 * pill (subtle background + saturated text); `dot` is the solid colour
 * used for the small indicator dot on cards.
 */
export interface KnowledgeTypeStyle {
  label: string
  chip: string
  dot: string
}

export const KNOWLEDGE_TYPE_CONFIG: Record<KnowledgeType, KnowledgeTypeStyle> = {
  cultural: { label: 'Cultural', chip: 'bg-cultural-subtle text-cultural', dot: 'bg-cultural' },
  governance: { label: 'Governance', chip: 'bg-governance-subtle text-governance', dot: 'bg-governance' },
  land: { label: 'Land', chip: 'bg-land-subtle text-land', dot: 'bg-land' },
  relationship: { label: 'Relationship', chip: 'bg-relationship-subtle text-relationship', dot: 'bg-relationship' },
  event: { label: 'Event', chip: 'bg-event-subtle text-event', dot: 'bg-event' },
}
