import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AnswerPanel from '@/Components/AnswerPanel.vue'
import type { Citation } from '@/types'

const linkStub = { template: '<a :href="href"><slot /></a>', props: ['href'] }

function mountPanel(overrides: Partial<{
  answer: string
  citations: Citation[]
  loading: boolean
  noRelevantItems: boolean
}> = {}) {
  return mount(AnswerPanel, {
    props: {
      answer: 'Governance is described here [1] and also here [2].',
      citations: [
        { itemId: '42', title: 'Governance overview', excerpt: 'A sample governance note.', knowledgeType: 'governance' },
        { itemId: '43', title: 'Land rights', excerpt: 'A sample land reference.', knowledgeType: 'land' },
      ],
      communitySlug: 'test-community',
      loading: false,
      noRelevantItems: false,
      ...overrides,
    },
    global: { stubs: { Link: linkStub } },
  })
}

describe('AnswerPanel', () => {
  it("renders the 'from your knowledge base' label", () => {
    expect(mountPanel().text()).toContain('from your knowledge base')
  })

  it('renders [N] markers in the answer as anchored superscripts', () => {
    const wrapper = mountPanel()
    const sups = wrapper.findAll('sup')
    expect(sups).toHaveLength(2)
    expect(sups[0].text()).toBe('[1]')
    expect(sups[1].text()).toBe('[2]')
    expect(sups[0].find('a').attributes('href')).toBe('#citation-1')
    expect(sups[1].find('a').attributes('href')).toBe('#citation-2')
  })

  it('preserves the answer prose between superscripts', () => {
    const wrapper = mountPanel()
    const prose = wrapper.find('p').text()
    expect(prose).toContain('Governance is described here')
    expect(prose).toContain('and also here')
  })

  it('does NOT use v-html (answer text is rendered as plain nodes)', () => {
    // Inject markup; it must be rendered as text, not interpreted as HTML.
    const wrapper = mountPanel({ answer: '<script>alert(1)</script>' })
    expect(wrapper.html()).not.toContain('<script>alert(1)</script>')
    expect(wrapper.text()).toContain('<script>alert(1)</script>')
  })

  it('renders a citation card for each citation', () => {
    const wrapper = mountPanel()
    expect(wrapper.text()).toContain('Governance overview')
    expect(wrapper.text()).toContain('Land rights')
    // Cards have anchor ids so superscript links resolve.
    expect(wrapper.find('#citation-1').exists()).toBe(true)
    expect(wrapper.find('#citation-2').exists()).toBe(true)
  })

  it('shows a skeleton placeholder while loading and hides answer text', () => {
    const wrapper = mountPanel({ loading: true })
    expect(wrapper.find('[data-test="answer-loading"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Governance is described here')
    // Sources block is suppressed during loading.
    expect(wrapper.text()).not.toContain('Sources')
  })

  it('renders NoAnswerState when noRelevantItems is true', () => {
    const wrapper = mountPanel({ noRelevantItems: true })
    expect(wrapper.text()).toContain('No information found')
    expect(wrapper.text()).toContain("could not find information")
    expect(wrapper.find('sup').exists()).toBe(false)
  })

  it('renders NoAnswerState when both answer and citations are empty', () => {
    const wrapper = mountPanel({ answer: '', citations: [] })
    expect(wrapper.text()).toContain('No information found')
  })

  it('renders the answer normally when citations exist even if answer has no [N] markers', () => {
    const wrapper = mountPanel({ answer: 'plain prose with no markers' })
    expect(wrapper.text()).toContain('plain prose with no markers')
    expect(wrapper.findAll('sup')).toHaveLength(0)
  })
})
