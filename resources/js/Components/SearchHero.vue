<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import type { CommunitySummary } from '@/types'

const props = defineProps<{
  communities: CommunitySummary[]
}>()

const query = ref('')
const selectedSlug = computed(() => {
  if (props.communities.length === 1) return props.communities[0].slug
  return manualSlug.value
})
const manualSlug = ref(props.communities[0]?.slug ?? '')

function submit() {
  const q = query.value.trim()
  if (!q || !selectedSlug.value) return

  const isQuestion = q.includes('?') || q.split(/\s+/).length > 5
  const route = isQuestion
    ? `/${selectedSlug.value}/ask`
    : `/${selectedSlug.value}/search`

  router.get(route, { q, page: 1 })
}
</script>

<template>
  <header class="bg-gradient-to-br from-primary to-primary-hover text-on-primary py-20 px-6 text-center">
    <h1 class="text-4xl font-bold mb-3">Sovereign Indigenous Knowledge</h1>
    <p class="text-primary-subtle text-lg max-w-2xl mx-auto mb-10">
      Browse community knowledge bases. Each community governs its own content under its own protocols.
    </p>

    <form
      v-if="communities.length > 0"
      class="flex items-center justify-center gap-2 max-w-2xl mx-auto"
      @submit.prevent="submit"
    >
      <select
        v-if="communities.length > 1"
        v-model="manualSlug"
        class="px-3 py-3 rounded-lg border border-border text-ink text-base"
      >
        <option v-for="c in communities" :key="c.id" :value="c.slug">
          {{ c.name }}
        </option>
      </select>
      <input
        v-model="query"
        type="text"
        placeholder="Search or ask a question..."
        class="flex-1 px-4 py-3 rounded-lg border border-border focus:outline-none focus:ring-2 focus:ring-primary text-ink text-base"
      />
      <button
        type="submit"
        class="px-6 py-3 bg-surface text-primary rounded-lg hover:bg-surface-raised font-medium"
      >
        Ask →
      </button>
    </form>
  </header>
</template>
