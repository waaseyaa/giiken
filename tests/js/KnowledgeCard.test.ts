import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import KnowledgeCard from '@/Components/KnowledgeCard.vue'

describe('KnowledgeCard', () => {
  const baseProps = {
    id: '42',
    title: 'Governance overview',
    summary: 'Sample governance note.',
    knowledgeType: 'governance' as const,
    communitySlug: 'test-community',
  }

  function mountCard(props = baseProps) {
    return mount(KnowledgeCard, {
      props,
      global: {
        // @inertiajs/vue3's Link renders as <a>; stub it as a plain anchor so
        // tests don't require a router/Inertia runtime.
        stubs: {
          Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
        },
      },
    })
  }

  it('renders the title and summary', () => {
    const wrapper = mountCard()
    expect(wrapper.text()).toContain('Governance overview')
    expect(wrapper.text()).toContain('Sample governance note.')
  })

  it('links into the community item detail route', () => {
    const wrapper = mountCard()
    const anchor = wrapper.find('a')
    expect(anchor.exists()).toBe(true)
    expect(anchor.attributes('href')).toBe('/test-community/item/42')
  })

  it('renders the knowledge type label when a type is provided', () => {
    const wrapper = mountCard()
    expect(wrapper.text()).toContain('Governance')
  })

  it('omits the type label when knowledgeType is null', () => {
    const wrapper = mountCard({ ...baseProps, knowledgeType: null as unknown as typeof baseProps.knowledgeType })
    // Title + summary still render.
    expect(wrapper.text()).toContain('Governance overview')
    // But no pill.
    expect(wrapper.find('span.inline-block').exists()).toBe(false)
  })
})
