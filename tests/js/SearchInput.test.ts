import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import SearchInput from '@/Components/SearchInput.vue'
import { router } from '@inertiajs/vue3'

vi.mock('@inertiajs/vue3', () => ({
  router: {
    get: vi.fn(),
  },
}))

describe('SearchInput', () => {
  beforeEach(() => {
    vi.mocked(router.get).mockReset()
  })

  it('defaults to the search route', async () => {
    const wrapper = mount(SearchInput, {
      props: { communitySlug: 'test-community' },
    })

    await wrapper.find('input').setValue('treaty history')
    await wrapper.find('form').trigger('submit.prevent')

    expect(router.get).toHaveBeenCalledWith('/test-community/search', { q: 'treaty history', page: 1 })
  })

  it('uses the ask route when mode is ask', async () => {
    const wrapper = mount(SearchInput, {
      props: { communitySlug: 'test-community', mode: 'ask' },
    })

    await wrapper.find('input').setValue('What happened here?')
    await wrapper.find('form').trigger('submit.prevent')

    expect(router.get).toHaveBeenCalledWith('/test-community/ask', { q: 'What happened here?', page: 1 })
  })
})
