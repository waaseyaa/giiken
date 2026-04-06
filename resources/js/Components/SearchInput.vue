<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps<{
  communitySlug: string
  initialQuery?: string
}>()

const query = ref(props.initialQuery ?? '')

function submit() {
  const q = query.value.trim()
  if (!q) return

  const isQuestion = q.includes('?') || q.split(/\s+/).length > 5
  const route = isQuestion
    ? `/${props.communitySlug}/ask`
    : `/${props.communitySlug}/search`

  router.get(route, { q, page: 1 })
}
</script>

<template>
  <form @submit.prevent="submit" class="flex gap-2 w-full max-w-2xl">
    <input
      v-model="query"
      type="text"
      placeholder="Search or ask a question..."
      class="flex-1 px-4 py-3 rounded-lg border border-border focus:outline-none focus:ring-2 focus:ring-indigo text-base"
    />
    <button type="submit" class="px-6 py-3 bg-indigo text-white rounded-lg hover:bg-indigo-mid font-medium">
      Ask →
    </button>
  </form>
</template>
