<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { KNOWLEDGE_TYPE_CONFIG } from '@/types'
import type { KnowledgeType } from '@/types'
import { computed } from 'vue'

const props = defineProps<{
  id: string
  title: string
  summary: string
  knowledgeType: KnowledgeType | null
  communitySlug: string
  accessTier?: string
  sourceOrigin?: string
  createdAt?: string
}>()

const typeConfig = props.knowledgeType ? KNOWLEDGE_TYPE_CONFIG[props.knowledgeType] : null
const typeGlyph: Record<KnowledgeType, string> = {
  cultural: 'C',
  governance: 'G',
  land: 'L',
  relationship: 'R',
  event: 'E',
}

const sourceLabel = computed(() => {
  const origin = (props.sourceOrigin ?? '').toLowerCase()
  if (origin === 'northcloud') return 'NorthCloud'
  if (origin === 'upload') return 'Upload'
  if (origin === 'manual') return 'Manual'

  return origin !== '' ? origin : 'Unknown source'
})

const formattedDate = computed(() => {
  if (!props.createdAt) return ''

  const parsed = new Date(props.createdAt)
  if (Number.isNaN(parsed.getTime())) return ''

  return new Intl.DateTimeFormat('en-CA', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  }).format(parsed)
})
</script>

<template>
  <Link :href="`/${communitySlug}/item/${id}`" class="block p-4 bg-surface-raised rounded-lg border border-border hover:shadow-md transition-shadow">
    <div class="flex items-start gap-3">
      <div
        v-if="typeConfig"
        class="w-6 h-6 rounded-full mt-0.5 shrink-0 text-[11px] font-semibold flex items-center justify-center text-on-primary"
        :class="typeConfig.dot"
      >
        {{ knowledgeType ? typeGlyph[knowledgeType] : '?' }}
      </div>
      <div class="min-w-0">
        <h3 class="font-semibold text-ink leading-tight line-clamp-2">{{ title }}</h3>
        <p class="text-[11px] text-ink-muted mt-1">
          {{ sourceLabel }}<span v-if="formattedDate"> · {{ formattedDate }}</span>
        </p>
        <p class="text-sm text-ink-muted mt-2 line-clamp-2 sm:line-clamp-3 xl:line-clamp-4">{{ summary }}</p>
        <div class="flex flex-wrap gap-1 mt-2">
          <span
            v-if="typeConfig"
            class="inline-block text-xs px-2 py-0.5 rounded-full"
            :class="typeConfig.chip"
          >
            {{ typeConfig.label }}
          </span>
          <span
            v-if="accessTier"
            class="inline-block text-xs px-2 py-0.5 rounded-full bg-surface text-ink-muted border border-border"
          >
            {{ accessTier }}
          </span>
        </div>
      </div>
    </div>
  </Link>
</template>
