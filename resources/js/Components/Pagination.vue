<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

const props = defineProps<{
  currentPage: number
  totalPages: number
  baseUrl: string
  query?: string
  extraQuery?: Record<string, string | number | undefined>
}>()

function pageUrl(page: number): string {
  const params = new URLSearchParams()
  if (props.query) params.set('q', props.query)
  if (props.extraQuery) {
    Object.entries(props.extraQuery).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params.set(key, String(value))
      }
    })
  }
  params.set('page', String(page))
  return `${props.baseUrl}?${params.toString()}`
}
</script>

<template>
  <nav v-if="totalPages > 1" class="flex gap-2 justify-center mt-6">
    <Link
      v-for="page in totalPages"
      :key="page"
      :href="pageUrl(page)"
      class="px-3 py-1.5 rounded text-sm"
      :class="page === currentPage ? 'bg-primary text-on-primary' : 'bg-primary-subtle text-primary hover:bg-primary hover:text-on-primary'"
    >
      {{ page }}
    </Link>
  </nav>
</template>
