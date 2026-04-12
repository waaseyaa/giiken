import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import KnowledgeCard from '@/Components/KnowledgeCard.vue'
import CitationCard from '@/Components/CitationCard.vue'
import AnswerPanel from '@/Components/AnswerPanel.vue'
import SearchInput from '@/Components/SearchInput.vue'
import TypeFilter from '@/Components/TypeFilter.vue'
import SearchHero from '@/Components/SearchHero.vue'
import BrowseStrip from '@/Components/BrowseStrip.vue'
import { KNOWLEDGE_TYPES } from '@/types'
import type { KnowledgeType, Citation, CommunitySummary } from '@/types'

const LinkStub = { template: '<a :href="href"><slot /></a>', props: ['href'] }

const singleCommunity: CommunitySummary[] = [{ id: '1', name: 'Test Nation', slug: 'test-nation', locale: 'en' }]
const multiCommunity: CommunitySummary[] = [
  { id: '1', name: 'Test Nation', slug: 'test-nation', locale: 'en' },
  { id: '2', name: 'Second Nation', slug: 'second-nation', locale: 'fr' },
]

describe('KnowledgeCard snapshots', () => {
  for (const type of KNOWLEDGE_TYPES) {
    it(`renders ${type} variant`, () => {
      const wrapper = mount(KnowledgeCard, {
        props: {
          id: '1',
          title: `${type} item`,
          summary: `A ${type} knowledge item.`,
          knowledgeType: type as KnowledgeType,
          communitySlug: 'test-community',
        },
        global: { stubs: { Link: LinkStub } },
      })
      expect(wrapper.html()).toMatchSnapshot()
    })
  }
})

describe('CitationCard snapshot', () => {
  it('renders a citation', () => {
    const citation: Citation = {
      itemId: '10',
      title: 'Governance doc',
      excerpt: 'Relevant excerpt from the document.',
      knowledgeType: 'governance',
    }
    const wrapper = mount(CitationCard, {
      props: { index: 1, citation, communitySlug: 'test-community' },
      global: { stubs: { Link: LinkStub } },
    })
    expect(wrapper.html()).toMatchSnapshot()
  })
})

describe('AnswerPanel snapshot', () => {
  it('renders an answer with citations', () => {
    const citations: Citation[] = [
      { itemId: '10', title: 'Doc A', excerpt: 'Excerpt A.', knowledgeType: 'cultural' },
      { itemId: '11', title: 'Doc B', excerpt: 'Excerpt B.', knowledgeType: 'land' },
    ]
    const wrapper = mount(AnswerPanel, {
      props: {
        answer: 'This is the answer [1] with sources [2].',
        citations,
        communitySlug: 'test-community',
      },
      global: { stubs: { Link: LinkStub } },
    })
    expect(wrapper.html()).toMatchSnapshot()
  })
})

describe('SearchInput snapshot', () => {
  it('renders default state', () => {
    const wrapper = mount(SearchInput, {
      props: { communitySlug: 'test-community' },
    })
    expect(wrapper.html()).toMatchSnapshot()
  })
})

describe('TypeFilter snapshots', () => {
  it('renders with no active filter', () => {
    const wrapper = mount(TypeFilter, {
      props: { active: null },
    })
    expect(wrapper.html()).toMatchSnapshot()
  })

  it('renders with cultural active', () => {
    const wrapper = mount(TypeFilter, {
      props: { active: 'cultural' as KnowledgeType },
    })
    expect(wrapper.html()).toMatchSnapshot()
  })
})

describe('SearchHero snapshots', () => {
  it('renders with single community (no select)', () => {
    const wrapper = mount(SearchHero, {
      props: { communities: singleCommunity },
    })
    expect(wrapper.html()).toMatchSnapshot()
  })

  it('renders with multiple communities (shows select)', () => {
    const wrapper = mount(SearchHero, {
      props: { communities: multiCommunity },
    })
    expect(wrapper.html()).toMatchSnapshot()
  })
})

describe('BrowseStrip snapshot', () => {
  it('renders with single community', () => {
    const wrapper = mount(BrowseStrip, {
      props: { communities: singleCommunity },
      global: { stubs: { Link: LinkStub } },
    })
    expect(wrapper.html()).toMatchSnapshot()
  })
})
