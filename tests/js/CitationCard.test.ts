import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CitationCard from '@/Components/CitationCard.vue'
import type { Citation } from '@/types'

const linkStub = { template: '<a :href="href"><slot /></a>', props: ['href'] }

const baseCitation: Citation = {
  itemId: '42',
  title: 'Governance overview',
  excerpt: 'A sample governance note describing how decisions get made.',
  knowledgeType: 'governance',
}

function mountCard(citation: Citation = baseCitation) {
  return mount(CitationCard, {
    props: { index: 1, citation, communitySlug: 'test-community' },
    global: { stubs: { Link: linkStub } },
  })
}

describe('CitationCard', () => {
  it('renders the index, title, and knowledge-type label', () => {
    const wrapper = mountCard()
    expect(wrapper.text()).toContain('[1]')
    expect(wrapper.text()).toContain('Governance overview')
    expect(wrapper.text()).toContain('Governance')
  })

  it('exposes an anchor id matching citation-<index> so superscript links resolve', () => {
    const wrapper = mountCard()
    expect(wrapper.find('#citation-1').exists()).toBe(true)
  })

  it('hides the excerpt by default and reveals it on click', async () => {
    const wrapper = mountCard()
    expect(wrapper.text()).not.toContain('A sample governance note')

    const toggle = wrapper.find('button')
    expect(toggle.attributes('aria-expanded')).toBe('false')

    await toggle.trigger('click')
    expect(toggle.attributes('aria-expanded')).toBe('true')
    expect(wrapper.text()).toContain('A sample governance note')
  })

  it('collapses again on a second click', async () => {
    const wrapper = mountCard()
    const toggle = wrapper.find('button')
    await toggle.trigger('click')
    await toggle.trigger('click')
    expect(toggle.attributes('aria-expanded')).toBe('false')
    expect(wrapper.text()).not.toContain('A sample governance note')
  })

  it('links to the full item detail route when expanded', async () => {
    const wrapper = mountCard()
    await wrapper.find('button').trigger('click')
    const anchors = wrapper.findAll('a')
    const itemLink = anchors.find(a => a.attributes('href') === '/test-community/item/42')
    expect(itemLink).toBeTruthy()
  })

  it('omits the knowledge-type pill when knowledgeType is null', () => {
    const wrapper = mountCard({ ...baseCitation, knowledgeType: null })
    // The pill is a span with a rounded-full class on the toggle row; assert
    // that element is absent rather than string-matching inside the title.
    expect(wrapper.find('button span.rounded-full').exists()).toBe(false)
    // Title still renders.
    expect(wrapper.text()).toContain('Governance overview')
  })
})
