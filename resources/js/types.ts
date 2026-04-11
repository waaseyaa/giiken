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

export const KNOWLEDGE_TYPE_CONFIG: Record<KnowledgeType, { label: string; bg: string; text: string }> = {
  cultural: { label: 'Cultural', bg: '#f0f0ff', text: '#3d35c8' },
  governance: { label: 'Governance', bg: '#e8f5f0', text: '#1e8a6e' },
  land: { label: 'Land', bg: '#edf2e8', text: '#4a7a3a' },
  relationship: { label: 'Relationship', bg: '#fff0f5', text: '#c83568' },
  event: { label: 'Event', bg: '#fff8e8', text: '#c89a35' },
}
